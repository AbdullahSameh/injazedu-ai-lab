# Setup Runbook — the pitfalls, each with its measured value

Every entry here was hit and measured on this machine. The value is the point: it is what a future
reader cannot guess and should not have to re-derive. Measured 2026-08-21…2026-08-23.

## 1. Port 5432 is taken — the Lab lives on 5433

`5432` is Homebrew `postgresql@14`, which other local projects use. **Untouchable.** The Lab's
PostgreSQL 17 + pgvector container publishes `127.0.0.1:5433` → container `5432`
(`docs/ADR/ADR-018.md`). If a connection to "the Lab database" fails, check the port first:

```sh
lsof -nP -iTCP:5432 -sTCP:LISTEN   # postgresql@14 — not ours, leave it alone
lsof -nP -iTCP:5433 -sTCP:LISTEN   # injazedu_lab_postgres — ours
```

## 2. Host psql/pg_dump is 14.18 — all SQL runs in-container

The host's `psql` is **14.18**; the server in the container is **17.11**. The client aborts at
connect time — "aborting because of version mismatch" — before any statement runs. Never install or
link a newer host client for this; run SQL inside the container instead:

```sh
docker exec injazedu_lab_postgres psql -U lab -d injazedu_lab -c '\dt'
```

This survives المرحلة 9's cancellation as a pitfall because any manual inspection of the Lab
database hits it.

## 3. PHP is invoked by absolute path — never `brew link`

Laravel 13 needs PHP `^8.3`, but the machine's *linked* PHP is **8.2.27** and **31 local projects
depend on it** (`docs/ADR/ADR-017.md`). This project uses **8.4.2**, unlinked, by absolute path:

```sh
/opt/homebrew/opt/php@8.4/bin/php artisan lab:health
/opt/homebrew/opt/php@8.4/bin/php /opt/homebrew/bin/composer install   # in apps/lab
```

Running plain `php` here resolves to 8.2 and fails on Laravel 13's platform requirement. Running
`brew link php@8.4` fixes this project by breaking thirty-one others — do not.

## 4. `/bin/bash` is 3.2 — no bash-4 syntax anywhere

`/bin/bash` is **3.2.57**. Every script in `scripts/` targets it: no associative arrays, no
`mapfile`, no `${var,,}`, no `&>>`. Verified working in 3.2: `set -o pipefail` and
`${PIPESTATUS[n]}` — use them when a pipeline must catch a failing left-hand side; the naive
`a | b > file` form hides it.

## 5. The chat model loads BEFORE the embedding model

On this 16 GB machine, loading `embeddinggemma:300m-qat-q4_0` first and then requesting
`gemma4:e2b-it-qat` can evict the embedding runner — the chat runner squeezes it out. Load order:

```sh
curl -s http://127.0.0.1:11434/api/generate -d '{"model":"gemma4:e2b-it-qat","keep_alive":-1}' > /dev/null
curl -s http://127.0.0.1:11434/api/embed    -d '{"model":"embeddinggemma:300m-qat-q4_0","input":[],"keep_alive":-1}' > /dev/null
curl -s http://127.0.0.1:11434/api/ps       # both runners resident
```

`scripts/verify-model-runtime.sh --with-memory` does exactly this and reports both residents.
When memory feels tight, see `docs/runbooks/memory-check.md` — there is no gate to consult.

## 6. Tests need `injazedu_lab_test` — a real database, not sqlite

`apps/lab/tests/` runs against a dedicated, disposable `injazedu_lab_test` Postgres database, never
the real `injazedu_lab` (ADR-023, after a destructive command wiped real imported data). It is a
second database in the same container, so it does not exist yet on a fresh clone or after the
volume is recreated — create it once:

```sh
docker exec injazedu_lab_postgres psql -U lab -d injazedu_lab -c "CREATE DATABASE injazedu_lab_test OWNER lab"
docker exec injazedu_lab_postgres psql -U lab -d injazedu_lab_test -c "CREATE EXTENSION IF NOT EXISTS vector; CREATE EXTENSION IF NOT EXISTS pg_trgm;"
```

`apps/lab/.env.testing` (gitignored, same shape as `.env`) must exist and point every `DB_*` key at
it — `composer test` fails immediately without it. `RefreshDatabase` (`tests/TestCase.php`) migrates
it automatically; no manual `migrate` step is needed after this.
