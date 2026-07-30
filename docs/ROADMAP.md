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
   `Scrapkit/ci-pipeline` non sa avviare un servizio PostgreSQL.

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

Chiuso lo step 15: `TicketTransitions` porta la tabella del §4 e
`InvalidTicketTransition` rifiuta tutto il resto. Vivono in `app/Tickets/`,
che è la casa del dominio del ticket che non è né un model né un caso d'uso: da
qui passano anche gli eventi dello step 16.

La tabella è un `match` sull'enum e non un array indicizzato per stringa: uno
stato aggiunto senza decidere dove va rompe subito, invece di rispondere in
silenzio "da nessuna parte". `chiuso` e `annullato` compaiono con la lista
vuota perché terminale è una decisione, non una dimenticanza.

**Uno stato non transiziona verso sé stesso.** Non è nella tabella del §4 e
resta fuori: lasciarlo passare emetterebbe un evento e muoverebbe le metriche
per un ticket che non si è mosso.

La classe valida e risponde, non tocca il ticket e non salva niente: scrivere
lo stato nuovo, i timestamp delle metriche e l'audit trail è delle Action degli
step 18–20, come vuole il §5 ("i timestamp li scrive l'Action che causa il
fatto").

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

## Fase 3 — Ingresso

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
