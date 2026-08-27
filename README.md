# InjazEdu AI Assessment Lab

A local-only laboratory for the InjazEdu assessment program: a Laravel 13 + Filament 5 application
(`apps/lab`) on its own PostgreSQL 17 + pgvector container, a stateless FastAPI embedding service
(`apps/ai-service`), a native read-only MySQL snapshot of the InjazEdu source (`injazedu`,
29,142 questions), and Ollama running two local models. The Lab never writes to the source, never
stores personal data, and never connects to any remote environment.

This README takes a **clean clone of this repository** to ten green health checks. Every step you
need is on this page.

## Prerequisites (installed once, on the host)

| Tool | Version used | Check |
|---|---|---|
| Docker + Compose | OrbStack | `docker compose version` |
| Ollama (the macOS app) | 0.32.15, already running | `curl -s http://127.0.0.1:11434/api/version` |
| PHP | **8.4.2** at `/opt/homebrew/opt/php@8.4/bin/php` — never `brew link`ed | see check below |
| Composer | 2.8.4 | `composer --version` |
| uv | 0.10.x | `uv --version` |
| MySQL client | 9.1.0 (Homebrew) — only for the §6 pack in P1 | `mysql --version` |

```sh
/opt/homebrew/opt/php@8.4/bin/php -v          # PHP 8.4.2
```

Both models must exist in Ollama's registry (pull once):

```sh
ollama pull gemma4:e2b-it-qat
ollama pull embeddinggemma:300m-qat-q4_0
```

Start the Ollama app before starting the stack — the stack script checks it and starts nothing if it
is absent; it never starts Ollama itself.

## Step 1 — Environment files

Three `.env` files, one per consumer. Copy each template and fill in the values below.

```sh
cp .env.example .env
cp apps/lab/.env.example apps/lab/.env
cp apps/ai-service/.env.example apps/ai-service/.env
```

Generate one database password and use the same value in both places that need it:

```sh
openssl rand -hex 16    # this is your LAB_DB_PASSWORD / DB_PASSWORD value
```

**`.env` (root)** — Docker Compose reads only this:

```text
LAB_DB_PASSWORD=<the generated password>
```

**`apps/lab/.env`** — every application key:

```text
APP_NAME=InjazEduLab
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=injazedu_lab
DB_USERNAME=lab
DB_PASSWORD=<the same generated password>
INJAZEDU_DB_HOST=127.0.0.1
INJAZEDU_DB_PORT=3306
INJAZEDU_DB_DATABASE=injazedu
INJAZEDU_DB_USERNAME=root
INJAZEDU_DB_PASSWORD=
QUEUE_CONNECTION=database
LOG_CHANNEL=stack
LOG_LEVEL=debug
SNAPSHOT_TAKEN_AT=2026-08-07
OLLAMA_HOST=127.0.0.1:11434
AI_SERVICE_URL=http://127.0.0.1:8001
EMBEDDING_CONFIG_VERSION=eg300m-qat-q4_0/sim-v1/768/l2norm
STUDENT_REF_PEPPER=<openssl rand -hex 32>
```

`INJAZEDU_DB_PASSWORD` stays empty — the source's password-less `root` over loopback is the approved
architecture (`docs/ADR/ADR-021.md`). Read-only is enforced by the application, not by MySQL.
`EMBEDDING_CONFIG_VERSION` is the embedding contract: changing it silently invalidates every stored
vector. `APP_KEY` is left empty here — step 3 generates it.

**`apps/ai-service/.env`**:

```text
LAB_DB_HOST=127.0.0.1
LAB_DB_PORT=5433
LAB_DB_NAME=injazedu_lab
LAB_DB_USER=lab
LAB_DB_PASSWORD=<the same generated password>
OLLAMA_HOST=127.0.0.1:11434
EMBEDDING_CONFIG_VERSION=eg300m-qat-q4_0/sim-v1/768/l2norm
SERVICE_HOST=127.0.0.1
SERVICE_PORT=8001
```

## Step 2 — Dependencies

```sh
cd apps/lab && /opt/homebrew/opt/php@8.4/bin/php /opt/homebrew/bin/composer install && cd ../..
cd apps/ai-service && uv sync && cd ../..
```

## Step 3 — Database container

`migrate` connects to Postgres, it cannot start it — bring the container up first, and `--wait`
returns once its healthcheck passes:

```sh
docker compose up -d --wait postgres
```

## Step 4 — Application key and database schema

The queue worker (step 5) reads the `cache` and `jobs` tables at boot, so the schema must exist
before the full stack starts. When you ever inspect the database directly, run SQL inside the
container: the host's `psql`/`pg_dump` is 14.18 and aborts against this 17.11 server.

```sh
cd apps/lab
/opt/homebrew/opt/php@8.4/bin/php artisan key:generate
/opt/homebrew/opt/php@8.4/bin/php artisan migrate
cd ../..
```

## Step 5 — Start the stack

Brings up the ai-service and the queue worker; the container from step 3 is recognised as already
healthy (the command is idempotent). From tomorrow onward this single command is your whole morning
routine — container included. It also reports the model runtime (checked, never started):

```sh
scripts/lab-stack.sh up
```

## Step 6 — Verify: ten green checks

Last command of every setup, and the verdict for everything above (~7 s cold, both models load on
checks 5–6):

```sh
cd apps/lab && /opt/homebrew/opt/php@8.4/bin/php artisan lab:health
```

Ten `PASS` rows with exit code `0` means the Lab is ready. Checks 9 and 10 pass *by refusing*: the
source refuses writes, and the `users` table is refused by name — that refusal is the guardrail
working, not a failure.

## P1 — Profile the bank, mirror it, see it

Two commands take the ten green checks above to a populated console:

```sh
cd apps/lab
/opt/homebrew/opt/php@8.4/bin/php artisan lab:profile
/opt/homebrew/opt/php@8.4/bin/php artisan lab:import
```

`lab:profile` runs the eighteen `sql/profiling/` queries once through the guarded `injazedu`
connection, persists the results (`source_snapshots.profiling_results`), and regenerates
`docs/reports/p1-profiling.md` — never hand-edit that file, it is output, not a second source of
truth. `lab:import` (`--kind=all` by default) then mirrors the bank and the behavioural tables from
that snapshot into the Lab's own tables; re-running it writes nothing on a second pass (FR-022,
FR-029).

One screen: create a panel user if you don't have one yet, serve the app, then open the console.

```sh
/opt/homebrew/opt/php@8.4/bin/php artisan make:filament-user
/opt/homebrew/opt/php@8.4/bin/php artisan serve
```

`http://localhost:8000/admin` → **Inventory**. Every card links to the filtered question list behind
its count, and the header above every screen carries `snapshot_taken_at`, the mirrored question
count, and the date of the last import run — no number appears without that frame (FR-048, FR-050).
The panel defaults to Arabic RTL; switch to English from the user menu top-right.

## Day-to-day

```sh
scripts/lab-stack.sh up      # start a session (container, service, worker)
scripts/lab-stack.sh status  # what is running right now
scripts/lab-stack.sh down    # end a session (leaves the model runtime alone)
```

Every `up` line should read `[ OK ]`; exit code `1` means at least one component did not come up —
fix what the `[FAIL]` lines name and re-run. It is idempotent: running it twice leaves exactly one
worker and one service.

## Where to look next

- `sql/profiling/` — the eighteen §6 profiling queries P1 runs first (written, deliberately never
  executed during setup).
- `docs/runbooks/setup.md` — the measured pitfalls behind steps 2–6 (port 5433 vs 5432, host psql
  14.18 vs server 17.11, PHP by absolute path, bash 3.2, model load order).
- `docs/runbooks/memory-check.md` — manual memory triage when the machine feels slow (no thresholds).
- `docs/runbooks/snapshot.md` — where the source snapshot lives and what is still owed about it.
- `docs/runbooks/safety.md` — FileVault recovery-key custody record.
- `specs/` — the specification-driven history of how this was built.
