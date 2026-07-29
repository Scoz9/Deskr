# Deskr

Applicativo di ticketing per team di helpdesk: raccoglie le richieste da più
canali, le trasforma in ticket assegnabili, tiene traccia di ogni risposta e
nota interna e misura il tempo di prima risposta e di risoluzione.

- **Dominio, decisioni architetturali e cosa è fuori scope:** [docs/PROJECT.md](docs/PROJECT.md)
- **Step di lavoro, in ordine:** [docs/ROADMAP.md](docs/ROADMAP.md)
- **Regole per chi sviluppa (umani e agenti):** [CLAUDE.md](CLAUDE.md)

## Stack

Laravel 13 su PHP 8.5, PostgreSQL 17 con `pgvector`, Inertia + React 19 +
TypeScript, Tailwind 4, `spatie/laravel-permission` con la gerarchia di
`scrapkit/laravel-permission-hierarchy`. Tutto gira in Docker con Laravel Sail.

## Avvio

Serve solo Docker (e Composer per il primo `install`; in alternativa usa
l'immagine `laravelsail/php85-composer`).

```bash
cp .env.example .env
composer install
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

L'applicazione risponde su <http://localhost> (`APP_PORT` per cambiarlo), la
posta in uscita finisce su Mailpit, <http://localhost:8025>.

### Utenti di sviluppo

`db:seed` crea un utente per ruolo. In ambiente locale la password è
`password`; fuori dal locale il seeder ne genera una casuale.

| Email | Ruolo | Rank |
| --- | --- | --- |
| `super-admin@example.test` | `superAdmin` | 0 |
| `admin@example.test` | `admin` | 1 |
| `agent@example.test` | `agent` | 2 |
| `requester@example.test` | `requester` | 3 |

I ruoli sono gestiti da spatie: non esiste una colonna `role` su `users`.
L'enum `App\Enums\UserRole` ne è l'elenco type-safe, letto da seeder e factory.

## Qualità

La suite che gira in CI si lancia con un comando solo:

```bash
vendor/bin/sail composer ci:check
```

Esegue, in ordine: ESLint, Prettier, `tsc --noEmit`, Vitest, Pint, PHPStan
(livello 7) e Pest. I pezzi singoli:

```bash
vendor/bin/sail artisan test --compact          # Pest
vendor/bin/sail artisan test --compact --filter=nomeTest
vendor/bin/sail npm run test                    # Vitest
vendor/bin/sail bin pint                        # formattazione PHP
vendor/bin/sail composer types:check            # PHPStan
```

## Contribuire

`main` è protetto: si lavora su un branch per step di roadmap
(`step-NN-descrizione-breve`), la CI deve essere verde e la PR si chiude in
squash merge. Il resto delle regole — conventional commits, dimensione delle
PR, TDD sulla logica di dominio — è in `CLAUDE.md` e in
`vendor/scrapkit/engineering-kit/`.
