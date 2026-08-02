# Deskr — roadmap

Regola valida per tutti gli step: **fatto** significa test verdi, qualità
pulita, PR mergiata. Ogni step deve lasciare l'applicazione in uno stato
funzionante. Un solo step per sessione.

Il contesto (dominio, decisioni prese, workflow) è in [PROJECT.md](PROJECT.md).

## Fase 0 — Fondamenta

Chiusa nella sessione di bootstrap. Il progetto non è nato da un `laravel new`
ma dallo **starter kit Scrapkit**, che arrivava già con Inertia, React,
TypeScript, Fortify, i ruoli spatie e i workflow CI: gli step 1–3 erano quindi
in gran parte soddisfatti alla partenza, e il lavoro reale è stato riconciliare
lo starter kit con le decisioni del §2–§6.

1. `scrapkit/engineering-kit` e `CLAUDE.md` che rimanda a `docs/PROJECT.md`.
   **Prima di tutto il resto:** le regole hanno precedenza su ogni convenzione,
   quindi devono esistere prima che si scriva configurazione.
2. Sail e **PostgreSQL con `pgvector` nell'immagine** al posto di MariaDB.
   L'estensione costa nulla ora ed evita di rifare l'immagine in Fase 6.
3. Inertia + React + TypeScript strict, build, layout di base.
4. Toolchain qualità completa in GitHub Actions: test backend, test frontend,
   linter e formatter di entrambi i lati, analisi statica PHP, `tsc --noEmit`.
   I workflow Pest e Browser sono locali al repository e non riusabili finché
   `Scrapkit/ci-pipeline` non sa avviare un servizio PostgreSQL. Dallo step 23
   lo è anche Vitest, per un motivo diverso: le pagine importano le route
   tipizzate che Wayfinder genera, che sono un artefatto di build ignorato da
   git, e il workflow riusabile esegue i test con il solo Node — senza PHP e
   artisan quei file non esistono.

## Fase 1 — Modello dati

Fase chiusa con gli step 5–14: enum del dominio, `Organization`, `User` legato
all'organizzazione con i ruoli allineati a Deskr, `Team` con la pivot
`team_user`, `Category` con il team di destinazione, `Ticket` con tutti gli
attributi del §4, `TicketMessage` con il thread, `Attachment` appeso al
messaggio, `TicketEvent` con l'attore polimorfico e il seeder demo. Ogni step è
un commit di squash su `main`, con il perché nel corpo del messaggio.

La `reference` è generata da una sequenza PostgreSQL dedicata, legata con
`OWNED BY` alla colonna che alimenta: così non deriva dall'id auto-incrementale
e non sopravvive al `migrate:fresh` dei test.

`external_message_id` è `unique`: il threading dell'email inbound arriva allo
step 29, ma la colonna nasce con il vincolo che impedisce al webhook consegnato
due volte di appendere due volte lo stesso messaggio.

Gli allegati vivono su un disco `attachments` dedicato: privato, senza `url` e
con `serve` spento, e con la radice presa dall'ambiente perché in produzione
deve puntare a storage persistente (§8). Il disco è anche una colonna della
riga, così il giorno che cambia quello di default i file già scritti continuano
a risolversi da dove sono. Whitelist MIME, dimensione massima e route firmata
sono lo step 24, quando c'è un form che carica davvero.

`TicketEvent` porta l'attore polimorfico **e** `actor_kind`: sistema e AI non
hanno una riga a cui puntare, quindi il tipo va scritto comunque, altrimenti
resta un attore nullo con la ragione persa. La riga si scrive una volta sola e
non ha `updated_at`. `type` è una stringa e `payload` un json: il vocabolario
degli eventi lo decide lo step 16 insieme agli eventi di dominio.

`DemoSeeder` sta fuori da `DatabaseSeeder` — trecento ticket inventati non sono
qualcosa con cui un'installazione pulita deve svegliarsi — e si lancia con
`artisan db:seed --class=DemoSeeder`. Le date sono sparse su 90 giorni perché
trecento righe con lo stesso istante nasconderebbero proprio i problemi di
paginazione che questo volume deve far emergere in Fase 4. **Lascia
`first_response_at` e `resolved_at` a null**: li scrive il backfill dello step
43, che senza dati da riempire non proverebbe niente — i thread però contengono
già la risposta dell'operatore da cui il backfill li ricaverà.

5. Enum `TicketStatus` (inclusi `annullato`, escluso `riaperto`),
   `TicketPriority`, `TicketChannel` e `UserRole`. Test.
   `UserRole` è l'**elenco** dei ruoli spatie seedati (`superAdmin`, `admin`,
   `agent`, `requester`), non una colonna su `users`: vedi la decisione sui
   ruoli nel §3.
6. `Organization`: migration, model, factory.
7. `User`: appartenenza a `Organization`, seeder dei ruoli spatie, factory
   con stati `admin()`, `agent()`, `requester()` che assegnano il ruolo via
   `assignRole()`. Il model `User` esiste già: qui si aggiunge
   `organization_id` e si estendono factory e seeder dei permessi. I due ruoli
   dello starter kit sono rinominati con una migration di dati
   (`amministratore` → `admin`, `operatore` → `agent`) invece di essere
   affiancati dai nomi di Deskr: vedi la decisione sui ruoli nel §3.
8. `Team` e pivot `team_user`.
9. `Category`, con `team_id` per l'instradamento.
10. `Ticket`: migration con **tutti gli attributi del §4**, generazione della
    `reference`, model, relazioni, factory con stati per ogni status.
11. `TicketMessage` con `is_internal`, `external_message_id`, relazione
    all'autore, factory.
12. `Attachment` su `TicketMessage`, disco privato configurato.
13. `TicketEvent` con **attore polimorfico** (utente, sistema, AI).
14. Seeder demo: 3 organizzazioni, 2 team, 6 operatori, 300 ticket su tutti gli
    stati e i canali, con thread di messaggi. Volume sufficiente a far emergere
    N+1 e problemi di paginazione già in Fase 4, e a fare da base per il
    recupero per similarità in Fase 6.

## Fase 2 — Ciclo di vita

Chiusi gli step 15–21: `TicketTransitions` porta la tabella del §4,
`InvalidTicketTransition` rifiuta tutto il resto, ogni arco ammesso emette il
proprio evento di dominio, che `RecordTicketEvent` scrive nell'audit trail,
`CreateTicket` apre il ticket a partire dal DTO `NewTicket`, `AssignTicket` lo
mette nelle mani di qualcuno, `ReplyToTicket` ci fa conversare sopra e
`TransitionTicket` lo muove lungo il ciclo di vita con le metriche che ogni
passaggio vale. Transizioni ed
eventi stanno in `app/Tickets/`, la casa del dominio del ticket che non è né un
model né un caso d'uso, con gli eventi in `app/Tickets/Events/`; le Action, che
casi d'uso sono, stanno in `app/Actions/Tickets/` accanto a quelle di Fortify.

Archi ed eventi sono **una tabella sola**, un `match` sull'enum: uno stato
aggiunto senza decidere dove va rompe subito invece di rispondere in silenzio
"da nessuna parte", e un arco non può restare ammesso senza niente che lo
annunci. `chiuso` e `annullato` compaiono con la lista vuota perché terminale è
una decisione, non una dimenticanza.

**Uno stato non transiziona verso sé stesso.** Non è nella tabella del §4 e
resta fuori: lasciarlo passare emetterebbe un evento e muoverebbe le metriche
per un ticket che non si è mosso.

Tredici archi e **nove** eventi: quello che una transizione significa è la
coppia che attraversa, non lo stato in cui arriva. Tre archi finiscono in
`annullato` e due ciascuno in `risolto` e `in_attesa` senza voler dire niente di
diverso una volta lì; i tre che arrivano in `in lavorazione`, invece, sono tre
fatti distinti — presa in carico, risposta del richiedente, riapertura — e hanno
tre classi. Un evento unico costringerebbe ogni listener a ricostruire dalla
coppia cosa è successo.

`TicketEventType` è il vocabolario scritto su `ticket_events.type`: la colonna
resta una stringa (crescerà a ogni step che aggiunge un fatto da registrare), è
l'attributo del model a essere castato.

`RecordTicketEvent` ascolta l'**interfaccia** `TicketDomainEvent`, non i nove
eventi uno per uno: un evento aggiunto dopo — la riassegnazione dello step 18,
che non è una transizione — finisce nel trail per il fatto di implementarla. È
l'unico listener non in coda del progetto, mentre le notifiche lo sono sempre
(§5): una notifica in ritardo è una notifica, un trail in ritardo è un ticket con
un buco nella storia, e uno che fallisce in silenzio è un ticket con la storia
sbagliata per sempre. Gira dentro la transazione della transizione: o atterrano
entrambi o il ticket non si è mosso.

`apply()` valida, salva lo stato ed emette. Restano fuori i timestamp delle
metriche e `reopen_count`: li scrivono le Action degli step 18–20, come vuole il
§5 ("i timestamp li scrive l'Action che causa il fatto"). Il salvataggio si porta
dietro anche le altre modifiche pendenti sul model, così l'Action che valorizza
`resolved_at` prima di chiamare `apply()` fa atterrare timestamp e stato insieme.

`CreateTicket` accetta il DTO e nient'altro: è il DTO a rendere i canali
intercambiabili (§3), mentre parametri sciolti lascerebbero al canale aggiunto
dopo la libertà di passarne uno in più, e all'ingresso di significare una cosa
diversa a seconda di chi chiama. Il DTO porta i model e non gli id, perché
l'instradamento ha bisogno del team che la categoria indica e con gli id
l'ingresso rileggerebbe dal database righe che il chiamante ha già in mano.

**La categoria è opzionale e il team si scrive sulla riga.** Un'email in arrivo
non è classificata: rifiutarla vorrebbe dire perdere la richiesta, quindi il
ticket atterra senza categoria e senza team, nel pool dove la console della Fase
4 va a guardare. Il team si legge una volta sola, all'ingresso: reinstradare una
categoria mesi dopo non deve riscrivere dove sono finiti i ticket già lavorati.

Ticket e primo messaggio in **una transazione sola**. La descrizione non è un
campo del ticket ma il primo `TicketMessage` (§3), e un ticket senza quel
messaggio ha perso la richiesta per cui è stato aperto: mezzo ingresso è peggio
di nessun ingresso, perché obbliga a chiedere al richiedente di riscrivere
qualcosa che è già in elenco.

La creazione **non emette un evento di dominio**: il trail del §4 registra
transizioni e assegnazioni, e che il ticket sia nato lo dice la riga stessa con
il suo `created_at`. La priorità invece sta nel DTO con default `normale`, perché
l'ingresso pubblico non la espone (§3) mentre l'operatore che apre il ticket al
telefono allo step 39 la sceglie.

`AssignTicket` è **un caso d'uso su due fatti**. Fuori dal pool è la transizione
`nuovo` → `assegnato` e passa da `TicketTransitions`, così l'arco e il suo evento
restano dove stanno tutti gli altri; l'assegnatario è già pendente sul model
quando la transizione salva, quindi il ticket atterra assegnato a qualcuno in una
scrittura sola e non è mai, per un istante, assegnato a nessuno. Da ogni altro
stato è un passaggio di mano e basta: l'assegnatario è un attributo e non uno
stato (§3), e riportare a `assegnato` un ticket in lavorazione falserebbe le
metriche di un ticket che non si è mosso.

`TicketReassigned` è il primo evento di dominio che **non è una transizione**, ed
è il caso previsto dallo step 16: finisce nel trail per il fatto di implementare
`TicketDomainEvent`, senza che nessuno lo colleghi. Porta vecchio e nuovo
assegnatario, e il vecchio può essere nullo — un ticket annullato senza che
nessuno l'avesse preso è un fatto da rileggere com'era.

**Dare il ticket a chi ce l'ha già non emette niente**, come uno stato che non
transiziona verso sé stesso: niente si è mosso, quindi il trail tace. Restano
fuori il togliere l'assegnatario (la rimessa nel pool non è in roadmap), il
vincolo che il destinatario sia un `agent` e il rifiuto sui ticket terminali:
sono autorizzazione, e l'autorizzazione è lo step 21.

`ReplyToTicket` scrive risposta e nota interna con **un DTO solo**: sono lo
stesso fatto con un flag sopra (§4), e una forma a parte per la nota sarebbe un
secondo modo di scrivere nello stesso thread da tenere allineato al primo il
giorno che la nota prende un allegato.

**Rispondere non è una transizione.** L'Action non passa da `TicketTransitions`
e non lascia niente nel trail: il trail del §4 registra transizioni e
assegnazioni, e che un messaggio sia stato scritto lo dice il messaggio. La
risposta che riporta `in attesa` a `in lavorazione` è il portale dello step 27,
gli altri passaggi sono lo step 20.

`first_response_at` si scrive a **tre condizioni**, e ognuna è la metrica che si
rifiuta di dire una cosa non successa: la nota interna è scritta per il team e
il richiedente non la legge mai; il richiedente che aggiunge alla propria
richiesta non è il team che risponde; e prima vuol dire prima, quindi la seconda
risposta lascia il timestamp dove l'ha messo la prima. "Operatore" è il ruolo
spatie (`admin` o `agent`), che per il §5 è l'unica autorità in materia, non
"autore diverso dal richiedente". Il timestamp è quello del messaggio e non un
secondo `now()`: la metrica misura la risposta che sta nel thread, all'istante
che quella risposta porta.

`TransitionTicket` prende **uno stato di destinazione, non un verbo**. Risolvi,
chiudi, riapri, metti in attesa e annulla sono cinque voci di menu, ma ciò che
le distingue è già scritto nella tabella del §4: un metodo per verbo sarebbe la
stessa tabella scritta due volte, e la seconda copia è quella che al prossimo
arco nessuno aggiorna. Per la stessa ragione l'Action accetta qualunque
passaggio ammesso e non solo i cinque — così il portale dello step 27 e le
chiusure automatiche dello step 42 passano di qui invece di riscrivere altrove i
timestamp.

Le metriche si leggono dalla **coppia** `(from, to)`, esattamente come gli
eventi. Entrambi gli archi che atterrano in `risolto` scrivono `resolved_at`:
quello che si misura è il ticket risolto, non lo stato da cui è stato risolto.
La riapertura invece è solo l'arco che **esce** da `risolto`: il richiedente che
risponde a un ticket in attesa riprende qualcosa che non era mai stato risolto,
e contarlo gonfierebbe il tasso di riapertura dello step 46 con ticket che non
sono mai tornati indietro. `in attesa` e `annullato` non hanno colonna e non
scrivono niente.

**La riapertura azzera `resolved_at`.** Un ticket riaperto non è risolto: il
timestamp lasciato lì racconterebbe alla dashboard dello step 46 una risoluzione
non più vera, e la risoluzione successiva lo riscrive comunque.

L'ammissibilità si chiede **prima** di toccare qualsiasi cosa, così un passaggio
rifiutato lascia il model pulito quanto la riga — un `resolved_at` pendente su
un ticket che non si è mosso lo scriverebbe il primo che salva. I timestamp
invece si valorizzano prima di `apply()`, perché è la transizione a salvare:
stato e metrica atterrano nella stessa scrittura.

15. Classe delle transizioni con la tabella del §4. Test esaustivo: ogni
    transizione valida passa, ogni invalida solleva eccezione.
16. Eventi di dominio a ogni transizione + `TicketEvent` che li registra.
17. Action `CreateTicket`, **che accetta un DTO** e instrada al team a partire
    dalla categoria. Il DTO è ciò che rende i canali intercambiabili;
    l'instradamento è il pilastro #3 e sta qui, non in fondo.
18. Action `AssignTicket`: transiziona a `assegnato` **solo da `nuovo`**,
    altrimenti cambia solo l'assegnatario.
19. Action `ReplyToTicket`, pubblica o interna. **Scrive `first_response_at`** se
    è il primo messaggio pubblico di un operatore.
20. Action `TransitionTicket` per risolvi / chiudi / riapri / metti in attesa /
    annulla. **Scrive `resolved_at` e `closed_at`**, incrementa `reopen_count`.
21. Policies su `Ticket` e `TicketMessage` + test di autorizzazione per i tre
    ruoli, incluso il test esplicito che un richiedente non accede ai ticket di
    un altro richiedente.

Lo step 21 era rimasto scoperto quando il resto della Fase 2 è stato chiuso —
le sessioni successive erano già passate alla Fase 3 senza accorgersene, e la
Fase 2 andava dichiarata chiusa solo fino al 20. Recuperato qui, prima di
aprire la Fase 4: la console che sta per arrivare avrà bisogno di una policy
su `Ticket` da subito, e non del debito di scriverla mentre già la si usa.

`TicketPolicy` e `TicketMessagePolicy` estendono `BasePolicy` con il solo
`view()` da sovrascrivere: `viewAny`/`create`/`update`/`delete` bastano così
come sono, sulla sola permission convenzionale — `agent` la ha su tutto
tranne ruoli e permessi (§5), `requester` non ne ha **nessuna** (era già
deciso da `RoleSeeder`: "il portale è difeso da policy sui propri ticket, non
da abilità sulla console"). **Proprio perché il richiedente non ha permessi,
`view()` non può fermarsi al controllo della permission**: senza
un'eccezione, negherebbe l'accesso anche al proprio ticket. La riga in più è
l'unica differenza fra "chiunque abbia il permesso" e "il proprietario",
esattamente il filtro che tiene separati due clienti.

`TicketMessagePolicy::view()` aggiunge una condizione sopra la stessa regola:
una nota interna non esce mai, nemmeno sul proprio ticket (§3) — chi ha la
permission la vede sempre, il proprietario del ticket solo se il messaggio
non è interno.

Nessuna delle due Policy è ancora agganciata a un controller: `SupportTicketController`
e `PortalController` restano con i controlli inline già scritti e già testati
(la firma di un link non ha un `Authorizable` da controllare, quindi non
possono comunque passare da una Policy per intero). Le consumerà la prima
volta un controller della console che le vorrà — e da lì in poi basterà non
disattivare `$authorizesResources`, come fanno già `RoleController` e
`UserController`.

## Fase 3 — Ingresso

Chiusi gli step 22–30: dal form pubblico al webhook email con threading,
allegati e protezione dai loop, i due canali dell'ingresso pubblico
convergono entrambi su `CreateTicket` (§3) senza sapersi l'uno dell'altro.

`/assistenza` è la prima pagina che risponde a chi non ha
un account, come vuole il §3 — un richiedente non si registra mai — e da lì una
richiesta diventa un ticket. Il percorso è
in italiano perché lo legge chi ha bisogno di aiuto e non chi mantiene
l'applicazione; il nome della route e il codice restano in inglese come il resto.

`SupportRequestController` **rinuncia esplicitamente all'autorizzazione di
risorsa** dello starter kit: qui non c'è né una policy da consultare né un utente
su cui consultarla. A difendere la porta ci sono il rate limit sulla route e
l'esca nel form, non un permesso.

Il **rate limit è per IP** e basta: finché la pagina si limita a rendersi non c'è
altro su cui contare. Il limite per indirizzo email e il tetto di ticket aperti
per indirizzo, che il §5 chiede insieme a questo, arrivano con l'invio dello step
23 — prima non ci sarebbe un'email da contare.

**L'esca è nel markup e fuori dalla pagina**: `aria-hidden`, fuori dall'ordine di
tabulazione e con `autocomplete` spento, così non la incontra nessuno per cui non
è pensata. Il client non la giudica mai — un campo che nessuno vede non può
essere il motivo per cui a una persona si dice che il form è sbagliato: cosa
significhi trovarla piena lo decide il server allo step 23.

La validazione sta in `lib/support-request.ts`, separata dalla pagina perché è
una regola e non una resa: si legge e si prova da sola, riporta **tutti** i campi
sbagliati insieme — così si corregge il form in un giro invece di scoprire il
problema successivo a ogni tentativo — ed è una cortesia, non una difesa. La
difesa è la validazione server-side dello step 23.

La categoria è l'unica cosa che il form non può inventarsi, perché è ciò che
instrada al team: la manda il server, e **niente altro della categoria arriva a
una pagina pubblica** — il team dietro è come è organizzato l'helpdesk dentro, e
chi chiede aiuto non ha motivo di leggerlo. La priorità non c'è affatto (§3).

Il `select` è nativo e non la primitiva Radix: il portale pubblico è una
superficie leggera vista da fuori (§5), e questo è il controllo che ogni browser
sa già rendere e ogni tecnologia assistiva sa già leggere.

Con lo step 23 il form invia davvero, e il controller è **l'adapter web del
canale**: prende quello che è stato scritto, ne fa il DTO `NewTicket` con
`channel = web` e lo passa a `CreateTicket`. Cosa sia un ticket e dove venga
instradato non si decide qui — è già deciso allo step 17.

**L'indirizzo è l'identità.** Se esiste, il ticket nasce sull'account che c'è
già; se non esiste, l'account nasce ora con il ruolo `requester` e una password
casuale che non va da nessuna parte: la registrazione è spenta e il portale dello
step 26 si raggiunge per magic link. Il nome di un account esistente **non si
riscrive** con quello appena digitato: scrivere all'helpdesk apre un ticket, non
rinomina qualcun altro.

Le due difese che il §5 chiede accanto all'esca arrivano qui perché **solo ora
c'è un indirizzo su cui contare**: un rate limit per indirizzo (l'unica cosa che
resta uguale quando chi invia cambia rete) e un tetto di ticket aperti per
indirizzo. Il tetto si dice a voce alta, a differenza dell'esca: chi ha già dieci
richieste aperte non è uno script, e lasciarlo indovinare perché non è successo
niente è il modo sicuro per farlo riscrivere. **L'esca invece risponde esattamente
come il caso buono** — uno script che distingue il rifiuto dal successo ha
imparato ad aggirarla — e non scrive niente.

La `reference` torna in sessione ed è mostrata una volta sola: è la ricevuta
della richiesta appena inviata, e finché non esistono la conferma via email dello
step 25 e il portale dello step 26 è l'unica cosa che il richiedente si porta via.

Il flusso ha anche il suo **browser test**: è il primo dei tre end-to-end del §5,
e nessuno dei livelli sotto — validazione client, visita Inertia, regole server,
Action — può dire se il giro completo funziona davvero da un browser.

Con lo step 24 il form prende allegati. I limiti — whitelist MIME, peso massimo,
numero massimo — stanno su `Attachment` e vengono **mandati alla pagina come
prop**: scritti una seconda volta in TypeScript, sarebbe la copia a invecchiare
il giorno che la lista cambia.

La whitelist è controllata con `mimetypes` e non con `mimes`: la prima chiede
cosa il file **è**, la seconda crede all'estensione che il mittente ha digitato.
Ed è una whitelist e non una blacklist, perché l'elenco di ciò che può fare male
non è mai finito.

**Il nome del file arriva come dato, mai come percorso.** I byte finiscono sul
disco privato sotto un nome generato dall'applicazione; quello scelto da chi
invia viaggia sulla riga e torna solo come nome del download. Un nome di file che
decide dove il file atterra è un nome di file che può atterrare ovunque.

Le righe `Attachment` nascono **dentro la transazione** di `CreateTicket`, appese
al primo messaggio come qualsiasi altro allegato (§4): la descrizione è un
messaggio, quindi i file che arrivano con lei non sono un caso speciale. Il DTO
porta file già scritti su disco, non upload HTTP — l'ingresso non ha motivo di
sapere cos'è un `UploadedFile`, e un allegato email dello step 30 non lo è.

Il download passa **solo dalla route firmata**: il disco è privato e non serve
niente da sé, quindi la firma è ciò che dice che il link l'ha dato
l'applicazione. Una riga il cui file è sparito risponde **404** e non un download
vuoto che sembra il file vero. La policy che il §5 vuole accanto alla firma
arriverà quando il portale (step 26) e la console (step 34) cominceranno a
linkare gli allegati: oggi non c'è ancora un utente da autorizzare.

Lo step 25 chiude il giro con la ricevuta. `TicketReceived` è **in coda** come
ogni notifica del progetto (§5): un ingresso che aspetta il server di posta è un
ingresso che fallisce quando fallisce il server di posta, e un ticket che esiste
vale più di una conferma arrivata nello stesso secondo. Il contenuto passa dal
notification kit come `UserInvitation`, quindi si edita senza toccare il codice.

**La `reference` sta nell'oggetto**, che è dove una casella di posta la mostra:
è quella che il richiedente cita al telefono, ed è la chiave su cui l'email
inbound dello step 29 farà threading.

Il link firmato apre **una pagina del solo ticket**, in sola lettura. Non è il
portale — elenco, ambito ai soli ticket del destinatario e pagina di rinnovo del
link scaduto restano lo step 26 — ma è ciò che rende la mail utile invece di un
foglietto con un codice sopra. Dura **7 giorni** come vuole il §5, ed è
riusabile fino ad allora.

**La firma è la chiave, non la sessione**: un richiedente non si registra e non
fa login (§3), quindi chi è autenticato non dice niente su chi può leggere. La
firma copre l'id, così un link non si modifica nel ticket di qualcun altro.

La pagina mostra **meno** di quanto mostrerà la console: stato e conversazione,
e niente assegnatario, team o categoria — come l'helpdesk è organizzato dentro
non è quello che serve a chi aspetta una risposta. Le **note interne non escono
mai**, nemmeno sul proprio ticket: sono scritte per il team, e un thread che ne
lascia passare una l'ha pubblicata.

Lo step 26 mette insieme le richieste in un portale. **Il magic link apre una
sessione**, e da lì elenco e dettaglio sono pagine normali con l'ambito
sull'utente, invece di una firma trascinata dentro ogni indirizzo. È la scelta
che rende possibile lo step 27: un POST vuole identità e CSRF, non una firma
nella query string.

Il dettaglio dello step 25 ora ha **due vie e nessuna terza**: la firma del link
che l'email porta, o la sessione di chi ha aperto quella richiesta. Il controllo
sta nel controller perché le due si leggono insieme — un richiedente non fa login
con una password (§3), quindi nessuna delle due da sola è "la" credenziale. Essere
autenticati come qualcun altro qui non vale niente: è **l'unico filtro fra due
clienti**, e sotto non c'è scoping globale a raccogliere quello che passa.

**La richiesta del link risponde sempre allo stesso modo**, che l'indirizzo esista
o no: un form che distingue i due casi è un modo per sapere chi è cliente
dell'helpdesk, ed è aperto a internet. Il rate limit è doppio — per IP come
l'ingresso, e per indirizzo — perché senza, questo form riempie la casella di
qualcun altro di link validi (§5).

**Un link scaduto non è un muro.** Chi ha cliccato è qualcuno da cui l'helpdesk
vuole sentire, quindi atterra sulla pagina che ne consegna uno nuovo invece che su
un 403 muto: per questo la firma si verifica nel controller e non nel middleware.

C'è anche il **secondo dei tre browser test** del §5: che il link apra davvero
qualcosa non è una domanda a cui un livello sotto il browser possa rispondere.

Lo step 29 aggiunge il threading, la protezione dai loop e la policy sul
mittente sconosciuto sopra il webhook dello step 28. `ReceiveInboundEmail` è
la nuova Action che decide: crea un ticket, aggancia un messaggio a uno
esistente, o non fa niente — `PostmarkInboundController` resta solo
l'adapter che traduce il payload di Postmark nel DTO `InboundEmail`, come
`SupportRequestController` fa per il form web.

**La `reference` nell'oggetto è la chiave del threading**, esattamente come
promesso dallo step 25: un `preg_match` sul prefisso `DSK-` nel `Subject`
basta a ritrovare il ticket. L'header `In-Reply-To` (e, a scendere,
`References`) è la seconda via, per il client che nella risposta ha tolto la
reference dall'oggetto: entrambi cercano tra gli `external_message_id` già
scritti sui messaggi.

**Il mittente deve essere il richiedente del ticket trovato, o il threading
non vale.** Una reference nell'oggetto è un valore che chiunque può scrivere,
non una prova di chi sta scrivendo: senza questo controllo, un estraneo che
cita la reference giusta scriverebbe nel thread di qualcun altro. Quando non
coincide, la mail non sparisce — apre un ticket nuovo, come farebbe
comunque un primo contatto.

`ReplyFromRequester` (rinominata da `ReplyFromPortal` dello step 27, insieme
al DTO `PortalReply` → `RequesterReply`) è dove il threading finisce: la
stessa Action decide se riprendere, riaprire o accodare, sia che la risposta
arrivi dal portale sia che arrivi da un'email agganciata. Duplicarla per il
canale email avrebbe tenuto due copie della stessa macchina a stati in
passo.

**La protezione dai loop viene prima di tutto**, nell'ordine in cui costa
meno verificarla: l'header `Auto-Submitted` (RFC 3834) scarta un
autorisponditore senza toccare il database, l'idempotenza su
`external_message_id` riconosce un webhook consegnato due volte prima di
consumare il tetto per mittente, e il tetto — cinque messaggi al minuto — è
quello che due autorisponditori che si rispondono a vicenda superano in
pochi secondi, cosa che una persona non fa mai. Un messaggio scartato
risponde comunque `204`: Postmark non deve ritentare qualcosa che è stato
scartato apposta.

`NewTicket` e `NewReply` portano ora `externalMessageId`: la colonna
`external_message_id` di `TicketMessage` esiste dalla prima migration
apposta per questo, ed era rimasta sempre `null` fino a questo step.

Lo step 30 chiude la Fase 3 con allegati e pulizia del corpo del messaggio.

**La rimozione di firma e testo citato non è un parser scritto in casa**:
`StrippedTextReply`, il campo che Postmark stesso manda con la risposta già
ripulita da citazioni e firma, sostituisce `TextBody` quando c'è. Scriverne
uno proprio avrebbe voluto dire mantenere per sempre un'euristica che il
provider risolve già meglio di noi; quando Postmark non trova niente da
togliere (un primo messaggio, non una risposta), `StrippedTextReply` non
c'è e si torna a `TextBody`.

**Gli allegati inbound riusano `Attachment`/`NewAttachment`** dello step 24,
non una seconda whitelist: stesso elenco di MIME type ammessi, stesso tetto
di peso, stesso numero massimo per messaggio. Il `Content` di ogni allegato
arriva già in base64 nel payload; una volta decodificato, il MIME è
**sniffato dai byte** e non letto dal `ContentType` che l'email dichiara —
la stessa regola `mimetypes` e non `mimes` del form web, perché un'email è
input non fidato quanto un upload.

**Un allegato rifiutato non fa perdere il messaggio.** A differenza del form
web, dove un file fuori whitelist blocca l'intero invio con un errore che
la persona può correggere, qui non c'è nessuno dall'altra parte a leggere un
422: l'allegato che non supera whitelist o peso viene semplicemente escluso,
il ticket nasce comunque. Lo stesso vale oltre il tetto per messaggio — i
primi arrivano, gli altri restano fuori — invece di rifiutare tutta l'email.

**Un `ContentID` valorizzato non è un allegato**: è un'immagine incorporata
nel corpo, tipicamente il logo della firma di chi scrive. Senza questo
filtro, ripulire la firma dal testo (sopra) sarebbe stato solo mezzo lavoro
— l'immagine sarebbe comunque arrivata come allegato a ogni email.

`NewReply` porta ora `attachments` come `NewTicket`: prima di questo step
solo il primo messaggio di un ticket poteva avere file, perché era l'unico
caso che il form web copriva. Una risposta agganciata a un ticket esistente
(step 29) può ora arrivare con i propri.

22. Form pubblico in React con validazione, **honeypot e rate limit**. Solo
    campi, nessun invio.
23. Form → `CreateTicket` via DTO: riconoscimento del richiedente dall'email,
    creazione se non esiste, descrizione come primo `TicketMessage`,
    `channel = web`.
24. Allegati dal form: whitelist MIME, dimensione massima, disco privato, route
    firmata per il download.
25. Notifica email di conferma, in coda, con `reference` nell'oggetto e link
    firmato.
26. Portale "i miei ticket": accesso via magic link, elenco e dettaglio in sola
    lettura, ambito ai soli ticket del destinatario.
27. Risposta dal portale: da `in attesa` a `in lavorazione`, da `risolto` a
    `in lavorazione`, da `chiuso` nuovo ticket con `parent_ticket_id`.
28. Email inbound: endpoint webhook, parsing del messaggio, adapter →
    `CreateTicket` con `channel = email`.
29. Email inbound: threading su ticket esistente (`reference` nell'oggetto e
    header `In-Reply-To`), **protezione dai loop**, policy sul mittente
    sconosciuto.
30. Email inbound: estrazione allegati, rimozione di firma e testo citato.

## Fase 4 — Console operatore

Aperto lo step 31: la console riusa il layout autenticato dello starter kit
(la stessa sidebar di dashboard, utenti e ruoli) e aggiunge `TicketController`
— il primo controller dell'applicazione a **consumare davvero** le policy
recuperate allo step 21: `$authorizesResources` resta `true` di default, e
`can:viewAny,Ticket` fa il suo lavoro senza che il controller debba saperlo.

**La lista è paginata sul server, non sul client.** `UserController` manda
tutti gli utenti in un colpo solo e lascia che sia `material-react-table` a
sfogliarli — corretto per una manciata di account, sbagliato per un backlog
che i 300 ticket del seeder esistono apposta a far crescere. `Ticket::paginate()`
manda una pagina alla volta, e la tabella è in `manualPagination`: cambiare
pagina è una visita Inertia parziale (`only: ['tickets']`), non un giro sui
dati che il server ha già mandato tutti.

**Lo stato di paginazione della tabella si legge dalle prop, non si
specchia in uno stato locale.** Tenerlo sincronizzato a mano con due
`useEffect` che si controllano a vicenda è la fonte più comune di loop e
disallineamenti in questo pattern; leggerlo da `tickets.meta.currentPage` a
ogni render lo rende impossibile da disallineare, e fa funzionare avanti e
indietro del browser gratis.

`Ticket::query()->orderByDesc('created_at')->orderByDesc('id')` e non il solo
`latest()`: senza un tiebreaker esplicito, due ticket con lo stesso secondo
di `created_at` — frequente nel seeder demo, possibile anche in produzione —
lascerebbero l'ordine ai capricci del motore, con righe che una pagina
ripete e la successiva salta.

Niente filtri, niente ricerca, niente dettaglio cliccabile: sono gli step
32-34. La query non applica nessuno scoping per team, com'è deciso da §3 —
un `agent` vede tutto il backlog, il team è un filtro che arriverà allo step
32, non un confine che serve già oggi.

Lo step 32 aggiunge i cinque filtri sopra la lista: stato, priorità, canale,
team, assegnatario. Vivono nella query string e non in uno stato del
componente, per lo stesso motivo della paginazione dello step 31 — un
filtro letto dalle prop del server invece che specchiato altrove non può
disallinearsi, e un link con `?status=risolto` è già l'intera vista da
condividere o salvare nei preferiti.

**Ogni filtro è un `when()` in coda alla stessa query**, non un ramo
separato: si combinano tutti insieme, come un operatore si aspetta da un
elenco di filtri e non da un elenco di viste alternative. Validati contro
gli enum del dominio (`Rule::enum`) prima di toccare il database — un valore
sconosciuto è un 422, non una query silenziosamente vuota o un errore SQL.

**"Non assegnato" è un valore a sé** nel filtro assegnatario, non un id da
cercare: il pool dei ticket che nessuno ha ancora preso in carico non è una
persona, e senza questo valore sarebbe irraggiungibile dal filtro.

`TicketController` cerca gli assegnabili con `whereHas('roles', ...)` e non
con lo scope `role()` di spatie: quello risolve il nome del ruolo e lancia
un'eccezione se non è ancora seedato, fatale per una richiesta che vuole
solo sapere chi lo tiene oggi.

Il centro di comando degli spostamenti è la pagina, non più la tabella: la
navigazione della paginazione (step 31) si sposta da `TicketsTable` a
`tickets/index.tsx`, che ora è l'unico punto che costruisce la query string
— una tabella che decide da sola la propria pagina non saprebbe portare con
sé i filtri che un componente diverso possiede.

**La barra dei filtri usa i `Select` di shadcn/ui, non quelli di MUI**:
sta fuori dalla griglia dati, ed è lì — non altrove — che §5 confina MUI e
`material-react-table`.

Lo step 33 aggiunge la ricerca full-text: oggetto, messaggi del thread,
nome del richiedente, nome dell'organizzazione. **Sul dizionario nativo di
Postgres in italiano**, non su un motore di ricerca a parte — è esattamente
la ragione per cui il §3 ha scelto Postgres invece di MySQL fin dal primo
step, e questo è il punto in cui quella scelta inizia a ripagare.

**Quattro indici GIN, uno per colonna, mai una colonna calcolata**:
`tickets.subject`, `ticket_messages.body`, `users.name`,
`organizations.name`, ciascuno con `$table->fullText(...)->language('italian')`.
Una colonna `tsvector` unica che raccolga tutt'e quattro le fonti avrebbe
richiesto un trigger — o una sincronizzazione manuale nelle Action che ogni
nuovo messaggio avrebbe dovuto ricordarsi di rifare — per un dominio dove
`ticket_messages` sta in una tabella diversa da `tickets` e cresce con ogni
risposta. Quattro query a runtime restano invece quattro `whereFullText()`
indipendenti, ciascuna che gira già sul proprio indice.

**Un solo gruppo `where`, quattro `orWhereHas` dentro**: la ricerca è "un
match su una qualunque delle quattro fonti", non quattro filtri che si
sommano — e deve restare un blocco solo perché altrimenti i suoi `or`
uscirebbero a sommarsi anche a stato, team e agli altri filtri già
applicati, invece di restringerli.

Cerca per nome del richiedente, non per email: è quello che il roadmap
chiede alla lettera ("richiedente"), e un'email è un campo da confronto
esatto più che da full-text — resta un'estensione naturale se servirà, non
un buco di oggi.

Lo step 34 apre il dettaglio: la riga cliccabile nella lista porta a
`tickets/{ticket}`, dove `TicketController::show` carica l'intero thread
tramite la relazione `Ticket::messages()` già ordinata (§4 dello step 31 —
un thread ha un solo ordine di lettura, e non è compito del controller
rifarlo). Non serve un filtro sulle note interne: la console è il posto
dell'operatore, e la stessa policy `view` recuperata allo step 21 già
decide chi arriva alla pagina.

**Le note interne si distinguono visivamente, non si nascondono**: uno
sfondo e un badge dedicati sulla stessa lista che mostra le risposte
pubbliche, non due liste separate — è la lettura naturale di "thread con
note interne visivamente distinte", ed è anche quello che tiene il
componente uno solo invece di due varianti quasi identiche.

Il link agli allegati usa `URL::signedRoute`, la stessa route già firmata
che serve il portale (§3, `AttachmentController`): il disco è privato e la
firma sull'URL resta l'unica cosa che dice che è stata l'applicazione a
distribuirlo, che a chiederlo sia un operatore o un richiedente.

Lo step 35 mette le mani sul ticket dal dettaglio, ma non scrive nessuna
logica di dominio nuova: "assegna a me" è `AssignTicket` degli step 15-17,
"cambia stato" e "annulla" sono `TransitionTicket` sulla stessa tabella dei
passaggi di `TicketTransitions` (§4) — annullare non è un caso a parte, è
solo il passaggio verso `annullato` che l'interfaccia isola nel proprio
bottone invece di lasciarlo dentro il menu degli stati, perché non è quello
su cui un operatore deve poter scorrere per sbaglio. Il controller valida il
passaggio scelto contro `TicketTransitions::allows()` prima di toccare il
dominio: una richiesta con uno stato che la lista di partenza non ammette
torna un 422, non l'eccezione `InvalidTicketTransition` che l'azione
lancerebbe.

**Le tre azioni condividono un solo permesso, `ticket:update`.** Il
`PermissionSeeder` genera cinque abilità per modello, non una per bottone —
inventarne una per "assegna", una per "cambia stato" e una per "cambia
priorità" avrebbe distinto ruoli che la roadmap non chiede di distinguere:
chi può toccare un ticket dal dettaglio lo può toccare in ogni modo che
questo step offre.

**Cambiare priorità non passa da `TicketTransitions`.** Non è un passaggio
di stato e non emette un evento: la lista dell'audit trail sul modello
`Ticket` — "ogni transizione e ogni assegnazione" — non la include, ed è
la prima volta che qualcosa oltre l'intake tocca `priority`, quindi resta
un aggiornamento diretto del campo dietro alla sua validazione.

Lo step 36 chiude il dettaglio con la composizione: un solo form per la
risposta pubblica e la nota interna, un checkbox a distinguerle — la stessa
lettura di `NewReply` (§4), "un fatto con un flag sopra", non due form da
tenere allineati il giorno che uno dei due cresce un campo che serve anche
all'altro. Dietro passa `ReplyToTicket`, lo stesso Action che già scrive il
thread per il portale e per l'email in ingresso: comporre dalla console non
aggiunge logica di dominio, aggiunge un terzo chiamante allo stesso Action.

**`TicketMessageController` è un controller a sé, non un altro metodo su
`TicketController`.** Le tre azioni dello step 35 condividono `ticket:update`
perché sono tutte una forma di modificare il ticket che già esiste; scrivere
un messaggio è creare una `TicketMessage`, un'abilità di classe
(`ticketMessage:create`) che non ha un'istanza contro cui essere verificata
finché il messaggio non esiste — la policy dello step 21 lo prevedeva già,
solo senza una rotta che la raggiungesse.

**Gli allegati riusano la stessa validazione dell'intake pubblico**, non
una copia: `Attachment::MAX_PER_MESSAGE`, `MAX_KILOBYTES`,
`ALLOWED_MIME_TYPES` erano già la fonte unica delle regole lato client e
lato server dello step 24, e la logica che scrive i file scelti su disco e
li descrive per l'Action — identica byte per byte tra l'intake e la
console — si sposta in un trait (`StoresAttachmentUploads`) invece di
duplicarsi una seconda volta.

Lo step 37 ha due trigger diversi perché sono due fatti diversi, e ognuno
prende la strada che il codice già gli offriva:

- **La risoluzione passa da un listener sull'evento di dominio.**
  `App\Tickets\Events\TicketResolved` esiste dallo step 20 per il trail
  d'audit, e il suo stesso docblock anticipava questo step ("The requester
  is told at step 37"). `SendTicketResolvedNotification` è un secondo
  ascoltatore sullo stesso evento — non tocca `RecordTicketEvent`, non sa
  che esiste — e funziona per qualunque passaggio arrivi a `risolto`, dalla
  console di oggi alla chiusura automatica dello step 42, senza che il
  chiamante debba ricordarsi di notificare.
- **La risposta pubblica passa da una chiamata diretta nel controller.**
  Scrivere un messaggio non è un `TicketDomainEvent` — la lista dell'audit
  trail copre solo transizioni e assegnazioni (§4) — quindi non c'è un
  evento da ascoltare, ed è lo stesso motivo per cui non ce n'è mai stato
  bisogno prima. `TicketMessageController::store` notifica solo se
  `is_internal` è falso, esattamente come il thread del portale non mostra
  mai una nota interna (§3).
- **Il link firmato si sposta in un trait**, `LinksToTicket`: la stessa
  procedura che la conferma dello step 25 già scriveva, e le due notifiche
  di questo step avrebbero dovuto copiare identica altrove.

31. Layout autenticato e lista ticket paginata.
32. Filtri: stato, priorità, assegnatario, team, canale.
33. Ricerca full-text su oggetto, messaggi, richiedente e organizzazione.
34. Dettaglio ticket: thread con note interne visivamente distinte.
35. Azioni dal dettaglio: assegna a me, cambia stato, cambia priorità, annulla.
36. Composizione della risposta e della nota interna, con allegati.
37. **Notifica al richiedente alla risposta pubblica dell'operatore e alla
    risoluzione.** Senza, il portale è un posto dove nessuno torna e il ciclo
    `in attesa → risposta` non parte mai.
38. Notifica all'operatore all'assegnazione.
39. Creazione ticket da parte dell'operatore per conto del richiedente,
    `channel = telefono`. Riusa `CreateTicket`: copre telefono e sportello quasi
    a costo zero.
40. Amministrazione: CRUD `Organization`, e il CRUD `User` dello starter kit
    esteso con `Organization` e con i tre ruoli di Deskr.
41. Amministrazione: CRUD `Team` e `Category`.

## Fase 5 — Misura

42. Job schedulato delle chiusure automatiche: `in attesa` → `risolto` dopo 7
    giorni con preavviso, `risolto` → `chiuso` dopo 7 giorni.
43. Migration di backfill di `first_response_at` e `resolved_at` sui dati del
    seeder. Una tantum: da qui in poi li scrivono le Action.
44. Policy SLA per priorità, con calcolo della scadenza su orario lavorativo
    (calendario, festività e fuso orario in configurazione).
45. Job schedulato di rilevamento breach + notifica.
46. Dashboard metriche autenticata: tempo medio di prima risposta, tempo medio
    di risoluzione, backlog per stato e per team, tasso di riapertura,
    distribuzione per canale.
47. Wallboard kiosk: token revocabile, aggregati senza dati personali,
    aggiornamento automatico, tipografia leggibile a distanza.

## Fase 6 — Triage AI

Nessuno step di questa fase può alterare il comportamento del sistema quando il
provider è irraggiungibile.

48. Infrastruttura: client, job in coda, tetto di spesa, logging delle chiamate,
    fallback deterministico, entità `AiSuggestion`.
49. Suggerimento di categoria e priorità, su campi propri, con confidenza.
    Nessuna azione automatica.
50. Riassunto del thread, utile soprattutto ai passaggi di consegne.
51. Embedding dei ticket con `pgvector` e recupero per similarità: duplicati e
    ticket correlati.
52. Bozze di risposta **fondate sui ticket risolti recuperati allo step 51**, mai
    inviate automaticamente, marcate come generate. Ultimo step per costruzione:
    senza il recupero, una bozza è un'invenzione plausibile.
53. Metrica di qualità del triage: suggerimenti accettati, corretti, ignorati,
    per tipo.

## Tag di fine fase

`v0.1` dopo lo step 14 · `v0.2` dopo il 21 · `v0.3` dopo il 30 ·
`v0.4` dopo il 41 · `v0.5` dopo il 47 · `v0.6` dopo il 53.
