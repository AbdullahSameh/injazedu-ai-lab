# P0 — AI Lab Foundation
## Implementation Plan — First Project

**Project:** P0 — AI Lab Foundation
**Order:** First in the program — every following project depends on it
**Version:** 1.0
**Date:** 2026-08-18
**Governing reference:** §15 of `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0)
**Status:** Ready for implementation · **Revised 2026-08-23** (Phase 9 cancelled · Phase 10 reduced to
manual steps · the aggregate-read list added — see §3.2)
**Effort estimate:** 4–5 focused working days for a single developer

---

# 1. Context and Goal

## 1.1 Why This Project Comes First

The final plan (v2.0) describes ten projects, and every one of them — without exception — needs a working local environment before it
can start. P0 is that environment. P0 produces no educational or analytical value of its own, and that is **deliberate**: its value
is that it makes P1…P9 possible, and that it fixes the security constraints **before** anything touches real data.

## 1.2 The Governing Principle

```text
The smallest possible number of services.
Every service that enters the foundation must be justified today, not "because we will need it later."
```

This is not a stylistic preference: the machine has 16 GB, and the memory budget in §12.3 of the final plan is genuinely
tight. Every extra service is subtracted from the generative model.

## 1.3 Definition of Done

> The whole stack starts with a single command, and a single `lab:health` command proves that **ten** connections work — including
> that the Production copy **refuses** writes and **cannot even see** the PII tables.

## 1.4 What This Project Unblocks

| Next project | What it needs from P0 |
|--------------|------------------------|
| P1 — Profiling & Question Mirror | A read-only MySQL connection + PostgreSQL + Filament + a queue |
| P2 — Duplicate Intelligence | pgvector + `pg_trgm` + Ollama (embed + chat) + the embedding contract |
| P3 — Item Statistics | PostgreSQL only (no AI) |
| P4 / P5 / P9 | FastAPI + Ollama + the prompt registry |

---

# 2. Measured Environment Facts

**Every line in this table was measured on the machine on 2026-08-18; none of it is assumed.**
Several of its lines **contradict** what §10 and §15 assumed, which is why it comes before the implementation plan rather than after it.

| Item | Measured value | Effect on the plan |
|------|----------------|--------------------|
| Hardware | Apple M1 Pro — 16 GB — 139 GB free | Matches the §3.3 assumption |
| PHP | **8.2.27** is the linked one; `php@8.3` and `php@8.4` are installed and unlinked | Laravel 13 requires `^8.3` → see ADR-017 |
| Composer | 2.8.4 | Ready |
| Node / npm | v23.5.0 / 11.3.0 | Ready for Filament assets |
| Python / uv | 3.13.7 / 0.10.12 | Ready for FastAPI |
| OrbStack | Installed, **and the daemon is stopped** | Start it — see ADR-019 |
| Ollama | **Not installed** — no `~/.ollama` | Install it and pull both models |
| MySQL | Homebrew **9.1.0** running on `127.0.0.1:3306`, datadir `/opt/homebrew/var/mysql/` | The copy's host |
| **The Production copy** | The `injazedu` database **exists and is already loaded** — 2,189 MB | §3.5 confirmed — no need to build an exporter |
| The copy's contents | questions **29,142** · options 124,549 · quizzes 3,362 · results 1,136,204 · question_result **13,776,378** · users 57,482 · courses 231 | The bank is **29,142**, not ~25,000 — see §14 |
| The copy's age | The latest `results.created_at` = **2026-08-07** (11 days) | This is the value of `snapshot_taken_at` |
| PostgreSQL | **`postgresql@14` is already running on port 5432** | **A port conflict** → ADR-018 |
| **The MySQL account** | **`root@localhost` with no password at all** (`authentication_string` is empty), and it is **the only real account** | Not used in the Lab — see Phase 3; and item L is **optional** (ADR-021) |
| MySQL binding | `bind_address = 127.0.0.1`, `skip_networking = 0` | Not exposed to the network — this limits the impact, it does not remove it |
| FileVault | **Disabled** | **Violates §14.1** — see §2.1 below |
| Ports 5000 / 7000 | Reserved by ControlCenter (AirPlay) | FastAPI on **8001** |
| Git | `injazedu-ai-lab` is **not a repository** yet | `git init` in Phase 1 |
| OneDrive | The process is running | Verify that `~/Projects` is not synced (§14.1) |
| psql client | **14.18** while the target server is 17 | All SQL runs **inside the container**; the host client refuses to connect to a 17 server |

## 2.1 What Is Actually Exposed in the Copy

§14.1 enumerates tables that are sensitive in theory. Measurement determines which of them are actually populated in this copy:

| Table | Rows (approximate) | Nature |
|-------|--------------------|--------|
| `orders` | ~70,453 | Payment data |
| `users` | 57,482 (exact count) | Emails, phone numbers |
| `personal_access_tokens` | ~24,408 | **API access tokens** |
| `social_providers` | ~17,369 | OAuth tokens |
| `complaints` | 68 | Complaints |
| `certificates` | **0** | **Empty — no national IDs in this copy** |

**Two important corrections:**

1. `certificates` is **empty**, so there is no `id_number` (national ID) in the current copy.
   But **the rule remains in force**: any newer copy (item E) may contain it, and policy must not be built
   on the contents of one particular copy.
2. The severest risk here is not the one §14.1 anticipated, but **`personal_access_tokens` (~24,408)**
   and `social_providers` (~17,369). If any of those tokens are valid against Production, then the unencrypted
   disk carries not merely personal data but **live credentials**. This raises the priority of
   item A, and it deserves a question to the team: are the access tokens in this copy still valid?

## 2.2 Externally Verified (2026-08-18)

```text
gemma4:e2b-it-qat                  present in the Ollama registry  ✅
embeddinggemma:300m-qat-q4_0       present in the Ollama registry  ✅
pgvector/pgvector:0.8.6-pg17       present, and it is the latest   ✅
laravel/framework v13.26.0         requires PHP ^8.3
filament/filament v5.7.6           requires PHP ^8.2
```

---

# 3. Approved Deviations from §15

Only two deviations warrant an independent ADR in `docs/ADR/`. The rest are ordinary local decisions documented in place
(a comment in the config file or a line in the spec) and need no ADR — see
`docs/plans/lean-development-process.md`.

| Identifier | Deviation | Reason |
|------------|-----------|--------|
| **ADR-018** | PostgreSQL 17 + pgvector on port **5433** | 5432 is occupied by `postgresql@14`, which serves other projects on the machine |
| **ADR-019** | **OrbStack** instead of Docker Desktop | Already installed, and §10 names it explicitly as the lighter alternative |
| **ADR-021** | The InjazEdu copy is read with the `root` account with no password, and read-only is enforced **in the application**, not in the database | The central architectural decision of this project — see below |

Decisions with no ADR (and that is deliberate): keeping the copy in the native MySQL 9.1 instead of Docker · choosing
Laravel 13 + Filament 5 on PHP 8.4 · the location of the `.env` files · Docker configuration details.

## 3.1 ADR-021 — Read-only as an application guarantee

§14.2 defines the tables the ETL is allowed to read, and forbids `users`, `orders`, `certificates`,
`complaints`, and others. The question: what stops the Lab from writing to the copy or from going outside the list?

**A previous design (withdrawn):** a dedicated MySQL user `lab_ro` holding `SELECT` on eleven tables, created
interactively with root privilege. That design was withdrawn: it requires an operator gate, a committed grants file, re-issuing the grants
on every refresh of the copy, and a verification script built on enumerating privileges — a large machine for a local single-developer project.

**The adopted decision (2026-08-21):** the connection uses the `root` account with no password, and read-only is enforced
by three layers in the application, each of which must block on its own:

```text
1) The injazedu connection with no write host   'write' => ['host' => []]
2) A query listener that throws on any statement that is not SELECT/SHOW/DESCRIBE/EXPLAIN
3) SourceReader refuses any table outside the eleven
```

```text
categories · courses · chapters · lectures · quizzes · sections
questions · options · quiz_files · results · question_result
```

**The residual risk, stated plainly:** MySQL enforces nothing here. The account can write and can read every
forbidden table. The three layers prevent **mistakes**, not **intent**. The decision is accepted and recorded in full in
`docs/ADR/ADR-021.md` along with its re-evaluation triggers.

**Its effect on §16:** "no PII in the Lab database" remains binding and is proven by three tests
(`ReadOnlyGuardTest`, `SourceTableAllowlistTest`, `NoPiiInLabSchemaTest`). Removing any layer fails a test.


## 3.2 Two lists, not one (2026-08-23)

The third layer in ADR-021 was a single list of eleven tables governing **reading and copying alike**. Measurement
showed that this blocks legitimate work: three of the §6 queries — **15, 16 and 18** — read
`course_user`, `course_order`, `orders`, `user_roles`, `roles`, and `book_course`, and they are the ones that settle
two questions without which the program cannot advance (which table records enrollment, and who the users in `course_user` are).

**Operator decision, 2026-08-23:** the list is split into two lists, because **reading and storing are different acts**:

```text
source_tables   (11)  what may be copied into the Lab database        — unchanged
profile_tables  (6)   what may be read as counts only, never copied   — new
                      course_user · course_order · orders
                      user_roles · roles · book_course
Forbidden (15)        everything else — refused by name, users among them
```

`SourceReader` refuses any table outside the two lists. The write prohibition (layers 1 and 2) is **unchanged**, and checks
9 and 10 in `lab:health` stay exactly as they are — check 10 targets `users`, which is forbidden in both directions.

The guarantee that remains is the guarantee that matters: **no PII column in the Lab database**, proven by
`NoPiiInLabSchemaTest` against the schema itself — not against whatever some passing query happened to select.

---

# 4. Scope

## 4.1 In scope

```text
Laravel 13 + Filament 5            (native, PHP 8.4)
FastAPI                            (native, uvicorn, uv)
PostgreSQL 17 + pgvector 0.8.6     (OrbStack, port 5433, memory-capped)
A read-only injazedu connection    (enforced in the application — ADR-021)
Ollama + two models                (native, Metal)
Queue worker                       (database driver — ADR-011)
Logging + ten health checks
The repository structure (§11) + .env.example + README + runbooks
Read-only toward InjazEdu: three application layers + two allowlists (11 for copying · 6 for aggregate reads)
The §6 query pack written into sql/profiling/ — not executed
```

## 4.2 Out of scope — explicitly

```text
Redis                          ← ADR-011
n8n                            ← ADR-012 (enters at P6)
Any ETL or data import         ← P1
Running the profiling queries  ← P1 Phase 1
Any backup or restore          ← cancelled from the whole program (operator decision 2026-08-23, §14.6)
Any memory gate or criterion   ← cancelled (2026-08-23) — manual steps only
Any connection to injazedu.co  ← forbidden across the whole program (§3.1)
Any real AI logic              ← P2 onward
Any functional Filament screen ← P1 (the inventory console)
Telegram / public pages        ← P6 / P7
```

**A rule:** if you find yourself writing business logic in P0, you are in the wrong project.

## 4.3 Mandatory development rules

These are the §15 rules verbatim, with the phase in which each is executed — so that no intention is left without an owner:

```text
[ ] All secrets in .env, and .env is never pushed to Git.      → Phase 1 + 11
[ ] No Production credentials locally.                          → Phase 3 (the copy is local only,
                                                                   and no account from the server is used)
[ ] Ollama on 11434 locally only, never exposed.                → Phase 4 (OLLAMA_HOST)
[ ] PostgreSQL and MySQL are not exposed.                       → Phase 2 (bound to 127.0.0.1),
                                                                   and MySQL is already bound to 127.0.0.1
[ ] Read-only toward InjazEdu enforced in three layers.         → Phase 3 (ADR-021)
```

---

# 5. The Target Local Architecture

This is the §10 picture redrawn with the real ports, and with MySQL taken out of Docker:

```text
Mac — M1 Pro, 16 GB
┌──────────────────────────────────────────────────────────────────┐
│  Laravel 13 + Filament 5        (native, PHP 8.4)                │
│  ├── owns every Lab migration             (ADR-013)              │
│  ├── Queue worker — database driver       (ADR-011)              │
│  ├── php artisan lab:health                                      │
│  └── injazedu connection: no write host + listener + allowlist   │
│              │                     │                              │
│              │ HTTP/JSON           │ PDO (SELECT only)            │
│              ▼                     ▼                              │
│  FastAPI  127.0.0.1:8001     MySQL 9.1  127.0.0.1:3306           │
│  (native, stateless)          (Homebrew — the Production copy)    │
│  ├── /health                  ├── the injazedu database (2.2 GB)  │
│  ├── /health/db               ├── read-only via the application:  │
│  ├── /health/ollama           │   11 tables only  (ADR-021)       │
│  └── /embed  (test only)      └── no writes, no PII               │
│         │            │                                            │
│         ▼            ▼                                            │
│  Ollama 11434    PostgreSQL 17 + pgvector                         │
│  (native, Metal)  127.0.0.1:5433  (OrbStack, mem_limit 1.5 GB)    │
│  ├── gemma4:e2b-it-qat          ├── extension: vector             │
│  └── embeddinggemma:300m-qat-q4_0 └── extension: pg_trgm          │
│                                                                   │
│  [postgresql@14 on 5432 — other projects, untouched]              │
│  [Redis — not present, ADR-011]   [n8n — deferred, ADR-012]        │
└──────────────────────────────────────────────────────────────────┘
```

---

# 6. Repository Structure

From §11, distinguishing what is created in P0 from what is reserved for later projects:

```text
injazedu-ai-lab/
├── apps/
│   ├── lab/                    ✅ P0 — Laravel 13 + Filament 5
│   └── ai-service/             ✅ P0 — FastAPI
├── infrastructure/
│   ├── docker/                 ✅ P0
│   ├── postgres/init.sql       ✅ P0 — CREATE EXTENSION vector, pg_trgm
│   └── n8n/                    ⏸  P6
├── data/
│   ├── snapshots/              ✅ P0 — an empty folder + .gitignore (it never holds anything)
│   ├── fixtures/               ✅ P0 — synthetic data for testing
│   └── exports/                ⏸  P9
├── storage/
│   ├── documents/              ⏸  P8
│   └── extracted/              ⏸  P8
├── sql/
│   └── profiling/              ✅ P0 — the §6 pack written, not executed
├── evals/                      ⏸  P2 onward (the folders only)
├── prompts/                    ⏸  P2 onward (the folder only)
├── docs/
│   ├── plans/                  ✅ exists
│   ├── schema/                 ✅ exists
│   ├── architecture/           ✅ P0
│   ├── ADR/                    ✅ P0 — ADR-018, ADR-019, ADR-021
│   └── runbooks/               ✅ P0
├── scripts/
│   └── lab-stack.sh            ✅ P0 — up | down | status
├── docker-compose.yml          ✅ P0
├── .env.example                ✅ P0
├── .gitignore                  ✅ P0
└── README.md                   ✅ P0
```

**A §14.1 note:** `data/snapshots/` stays **permanently empty** in this design, because the copy lives
in `/opt/homebrew/var/mysql/` outside the repository. The folder exists because §11 mentions it, and to forestall any temptation
to drop a dump inside the repository later.

---

# 7. Implementation Plan — 12 Phases

Every phase has: a goal, steps, files, and one checkable acceptance criterion.

---

## Phase 0 — Preparation and Safety

**Goal:** close the existing security gaps **before** any technical work.

**Steps:**
1. Enable FileVault (a human step — item A in §8). The remaining phases do not resume before encryption has begun.
2. Verify that `~/Projects` is not inside a synced folder (OneDrive is running on the machine).
3. Verify disk space (139 GB free — sufficient).
4. Record `snapshot_taken_at = 2026-08-07` in `docs/runbooks/snapshot.md`.
5. Start OrbStack and verify `docker ps`.

**Acceptance criterion:** `fdesetup status` returns "On" or "Encryption in progress", and `docker ps` works.

---

## Phase 1 — The Repository and Git

**Goal:** a clean repository with clear boundaries on what is committed and what is not.

**Steps:**
1. `git init` at the project root.
2. Create the §6 structure above (empty folders with `.gitkeep`).
3. Write `.gitignore`:

```gitignore
# secrets
**/.env
.env.*
!.env.example

# data copies — no real data ever enters the repository
/data/snapshots/*
!/data/snapshots/.gitkeep
*.sql
*.sql.gz
*.dump

# dependencies
/apps/lab/vendor/
/apps/lab/node_modules/
/apps/ai-service/.venv/
__pycache__/

# storage
/storage/documents/*
/storage/extracted/*
/apps/lab/storage/logs/*

.DS_Store
```

4. An initial commit.

**Acceptance criterion:** `git status` is clean, and `git check-ignore -v data/snapshots/test.sql` confirms the exclusion.

---

## Phase 2 — The Data Layer (PostgreSQL 17 + pgvector)

**Goal:** the Lab database running on 5433 with a memory cap, and both required extensions.

**Files:** `docker-compose.yml`, `infrastructure/postgres/init.sql`

```yaml
services:
  postgres:
    image: pgvector/pgvector:0.8.6-pg17
    container_name: injazedu_lab_pg
    restart: unless-stopped
    environment:
      POSTGRES_DB: injazedu_lab
      POSTGRES_USER: lab
      POSTGRES_PASSWORD: ${LAB_DB_PASSWORD}
    ports:
      - "127.0.0.1:5433:5432"          # ADR-018 — 5432 is taken
    mem_limit: 1536m                    # §12.3
    command:
      - postgres
      - -c
      - shared_buffers=512MB
      - -c
      - work_mem=32MB
      - -c
      - maintenance_work_mem=256MB
      - -c
      - max_connections=50
    volumes:
      - lab_pgdata:/var/lib/postgresql/data
      - ./infrastructure/postgres/init.sql:/docker-entrypoint-initdb.d/10-init.sql:ro

volumes:
  lab_pgdata:
```

```sql
-- infrastructure/postgres/init.sql
CREATE EXTENSION IF NOT EXISTS vector;    -- P2
CREATE EXTENSION IF NOT EXISTS pg_trgm;   -- P2 §17 layer 2
```

**Why `pg_trgm` now:** §17 needs it in the cascade (layer 2). Creating it now is free, and it avoids
an extra migration in the middle of P2.

**A binding note:** `127.0.0.1:5433` — not exposed to the network (§15 development rules).

**Acceptance criterion:**
```sql
SELECT extname, extversion FROM pg_extension WHERE extname IN ('vector','pg_trgm');
-- returns two rows
```
and restarting the container does not lose data (a named volume).

---

## Phase 3 — Read-Only Access to the InjazEdu Copy (ADR-021)

**Goal:** a connection to the copy that reads only the allowed tables and writes nothing — enforced in the application.

**No operator step here.** No `CREATE USER`, no `GRANT`, no generated password. The account already exists.

### a) Credentials

In `apps/lab/.env` (excluded from Git):

```dotenv
# The local InjazEdu copy — a read-only source (ADR-021)
INJAZEDU_DB_HOST=127.0.0.1
INJAZEDU_DB_PORT=3306
INJAZEDU_DB_DATABASE=injazedu
INJAZEDU_DB_USERNAME=root
INJAZEDU_DB_PASSWORD=
SNAPSHOT_TAKEN_AT=2026-08-07
```

`apps/lab/.env.example` (which does enter Git) — the same keys with no values.
The root `.env` holds only what Docker Compose reads (`LAB_DB_PASSWORD`).
`apps/ai-service/.env` **contains no MySQL details at all** — every read passes through Laravel (ADR-013).

### b) The three layers

`config/database.php` — the second connection, with no write host:

```php
'injazedu' => [
    'driver'   => 'mysql',
    'host'     => env('INJAZEDU_DB_HOST', '127.0.0.1'),
    'port'     => env('INJAZEDU_DB_PORT', '3306'),
    'database' => env('INJAZEDU_DB_DATABASE', 'injazedu'),
    'username' => env('INJAZEDU_DB_USERNAME', 'root'),
    'password' => env('INJAZEDU_DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'read'     => ['host' => [env('INJAZEDU_DB_HOST', '127.0.0.1')]],
    'write'    => ['host' => []],          // layer 1
],
```

`AppServiceProvider` — layer 2: a listener that throws `ReadOnlyViolation` on any statement that is not a read.
`config/lab.php` + `SourceReader` — layer 3: the list of eleven tables, and refusal by name of anything else.

**Why `results` is allowed despite containing `user_id`:** §14.2 permits it after anonymization.
The `user_id` is **read** and never **stored**: the P1 ETL immediately converts it into
`student_ref = HMAC-SHA256(pepper, user_id)`.

### Acceptance criterion

```text
[✓] scripts/verify-injazedu-access.sh  → 11 tables readable, questions = 29142
[✗] INSERT/UPDATE/DELETE over the injazedu connection  → must throw (ReadOnlyGuardTest)
[✗] SourceReader on a table outside the list           → must throw, naming the table
[✓] Each layer blocks on its own when the other two are disabled
```

---

## Phase 4 — Ollama and the Models

**Goal:** two models loaded within the memory budget, with the §12.3 settings.

**Steps:**
```bash
curl -fsSL https://ollama.com/install.sh | sh

ollama pull embeddinggemma:300m-qat-q4_0     # ~239 MB
ollama pull gemma4:e2b-it-qat                # the working model
```

Environment variables (§12.3):
```bash
OLLAMA_MAX_LOADED_MODELS=2      # chat + embed together only
OLLAMA_NUM_PARALLEL=1
OLLAMA_KEEP_ALIVE=5m
OLLAMA_HOST=127.0.0.1:11434     # not exposed — §15
```

The official macOS app registers itself as a login item. If measurement proves the limits are needed, they are set with
`launchctl setenv` and the app is restarted, and the values are read from the live process rather than from a file.

`num_ctx=4096` is passed with every call from FastAPI, not as a global setting — because the reason is the KV cache memory,
not stinginess (§12.3).

**Acceptance criterion:**
```bash
ollama list                          # shows both models
curl 127.0.0.1:11434/api/tags        # responds
```
and both models are loaded together without exceeding the §12.3 budget (measured in Phase 10).

---

## Phase 5 — The Laravel Application

**Goal:** `apps/lab` runs, owns the schema (ADR-013), and its queue is on the database (ADR-011).

**Steps:**
```bash
PHP84=/opt/homebrew/opt/php@8.4/bin/php     # without brew link — item D in §8

$PHP84 $(which composer) create-project laravel/laravel apps/lab "^13.0"
cd apps/lab
$PHP84 $(which composer) require filament/filament:"^5.0"
$PHP84 artisan filament:install --panels
```

The settings:
```text
DB_CONNECTION=pgsql          → 127.0.0.1:5433 / injazedu_lab
mysql_snapshot               → Phase 3
QUEUE_CONNECTION=database    → ADR-011
```

The `jobs` / `job_batches` / `failed_jobs` tables come with Laravel's default migrations —
there is no need to create them by hand.

The Filament panel in P0 is **a skeleton only**: a login + one page displaying the `lab:health` result.
No resources and no functional screens — those start in P1.

**Logging (§15):** an independent `lab` channel in `config/logging.php` (daily, 14-day retention)
separates the Lab's logs from Laravel's general log. Every call to FastAPI is logged with `model_name`,
`prompt_version`, and `latency` — the fields §7 of v1.0 requires for every AI task. There is no real AI
call in P0, but the channel and the fields are ready before the first call in P2.

**Acceptance criterion:** `php artisan migrate` succeeds against PostgreSQL, the Filament panel opens,
and `php artisan queue:work` actually executes a test job.

---

## Phase 6 — The FastAPI Service

**Goal:** a **stateless** service (ADR-013) that abstracts access to Ollama.

**Steps:**
```bash
cd apps/ai-service
uv init && uv add fastapi uvicorn httpx pydantic asyncpg
uv run uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

The endpoints in P0 (no business logic):

| Endpoint | Function |
|----------|----------|
| `GET /health` | Alive + version |
| `GET /health/db` | PostgreSQL connection (read only — ADR-013) |
| `GET /health/ollama` | Both models respond |
| `GET /health/full` | All three together |
| `POST /embed` | **Test only** — returns the vector and does not write it |

**The embedding contract (§12.2) — fixed from now on:**

```python
# A symmetric call for similarity and duplication — the prefix is mandatory
PREFIX_SIMILARITY = "task: sentence similarity | query: {text}"
```

and every `/embed` response carries:
```json
{
  "vector": [...],
  "dimension": 768,
  "embedding_config_version": "eg300m-qat-q4_0/sim-v1/768/l2norm"
}
```

**Why now and not in P2:** §12.2 says that changing the prefix **silently invalidates every stored vector**.
Fixing the contract before the first vector is stored is far cheaper than discovering the mistake after 58 thousand vectors.

**Logging:** a structured JSON log for every request (`request_id`, `endpoint`, `model`, `latency_ms`,
`status`) — which makes the §12.4 benchmark later a matter of reading a log rather than re-measuring by hand.

**Acceptance criterion:** `GET /health/full` returns `200` with every section `ok`, and FastAPI **writes**
no row in PostgreSQL (verified by code review).

---

## Phase 7 — The Health Check Matrix

**Goal:** one command that proves everything works and that the security boundaries hold.

`php artisan lab:health` runs **ten** checks — eight from §15 and two new ones:

```text
 1  Laravel  → PostgreSQL (5433)
 2  Laravel  → FastAPI (8001)
 3  Laravel  → Queue          (a job is created and actually executed, not merely a connection)
 4  FastAPI  → PostgreSQL
 5  FastAPI  → Ollama chat    (gemma4:e2b-it-qat)
 6  FastAPI  → Ollama embed   (embeddinggemma:300m-qat-q4_0 + the prefix)
 7  pgvector → one vector(768) saved and retrieved
 8  Laravel  → MySQL snapshot (the allowed tables)
 9  New: Laravel → an attempted write to MySQL  ← must fail
10  New: Laravel → SourceReader('users')        ← must throw (proving ADR-021)
```

Checks 9 and 10 invert the usual logic: **success is failure**. That is deliberate — without them,
"Production read-only" stays a claim in a document instead of a tested property.

The command returns a coloured table and an exit code ≠ 0 on any failure, so it can be invoked automatically later.

**Acceptance criterion:** the command passes all ten, and stopping any service makes it fail with the right message.

---

## Phase 8 — The Guardrails

**Goal:** make the §3.1 and §14 constraints automatically enforceable rather than dependent on attention.

**Steps:**
1. The three layers on the `injazedu` connection are in place, and each blocks on its own (ADR-021).
   They are read from `config/lab.php`, and any code that tries to write to Production checks them first.
   No such code exists in P0 — the point is that the switch exists **before** the need does.
2. A test: writing to `mysql_snapshot` fails.
3. A test: every table forbidden in §14.2 is refused by name through `SourceReader` — an explicit list checked one by one:
   `users, orders, course_order, book_order, coupons, certificates, complaints,`
   `complaint_responses, social_providers, personal_access_tokens, paymob_logs,`
   `zoom_users, audits, telescope_entries, google_oauth_tokens, failed_jobs, settings`
4. `EMBEDDING_CONFIG_VERSION` in `.env` (Phase 6).
5. `SNAPSHOT_TAKEN_AT=2026-08-07` in `.env` — every later report displays it (§16 risks).
6. `STUDENT_REF_PEPPER` in `.env`, generated now, never committed to Git (§14.2 — item F in §8).

**Acceptance criterion:** the test suite passes, and removing any `GRANT` from Phase 3 makes a test fail.

---

## Phase 9 — ~~Backup and Restore Drill~~ · **Cancelled 2026-08-23**

**Operator decision, 2026-08-23: this phase is cancelled entirely, and there is no backup requirement in the program.**

The reason, by measurement rather than opinion: the Lab database at the end of P0 is **8.4 MB** — one operator account in
Filament, a queue probe row, and a vector probe row. All of it comes back with `php artisan migrate` and `lab:health`.
And the Production copy is **disposable**: it is restored by taking a fresh copy, not by restoring one. A nightly backup
on the same machine protects against disk loss alone, which is the least likely outcome here and the most expensive in time.

**What was not cancelled:** the human review decisions (P2 onward) have no other source. Protecting them is a matter of
**moving to real operation**, not a local matter — §14.6 of the core plan has been updated accordingly.

The reference number if it is ever needed: `pg_dump` must be run **inside the container**; the host client at 14.18
refuses to connect to a 17.11 server when connecting directly.

**The phase number is preserved** and nothing after it was renumbered, so that the references in
`specs/001…003` and in `CLAUDE.md` do not break.

---

## Phase 10 — Memory: manual steps, no script and no gate · **Reduced 2026-08-23**

**Goal:** that the operator knows what to do when the machine feels slow — not that a number be passed.

**Operator decision, 2026-08-23:** no measurement script, no go/no-go gate, and no acceptance criterion on a memory number.
macOS manages memory; and real performance work belongs to the **pipeline**, not to database tuning.

**The actual measurement on the machine (2026-08-23)** — and it is why the gate fell:

| Component | §12.3 estimate | Measured |
|-----------|----------------|----------|
| The Ollama tree with both models | ~3.3 GB | **4,646.9 MiB** |
| PostgreSQL (OrbStack host RSS) | ~1.5 GB | **394.7 MiB** |
| Native MySQL 9.1 | ~1–1.5 GB | **18.6 MiB** (`innodb_buffer_pool_size` = 128 MiB) |
| Laravel + worker | ~0.4 GB | **13.3 MiB** |
| FastAPI | ~0.3 GB | **58.8 MiB** |
| **The whole stack** | ~6.8–8.5 GB | **5,132 MiB** |

**~90% of the number is the two models.** Everything else is not worth tuning. Which is why a gate that fails
the project over a number the project owns no more than 10% of is meaningless.

**The only deliverable:** `docs/runbooks/memory-check.md` — three manual commands, what each number means,
and what to do about it. No script, no scheduling, no acceptance criterion.

---

## Phase 11 — Documentation

**Goal:** that someone else (or you in two months) can run this from scratch.

**Deliverables:**
1. `README.md` — installation from scratch with copy-pasteable commands.
2. `docs/runbooks/setup.md` — the details and the traps (the port conflict, the pg_dump issue, PHP 8.4).
3. `docs/runbooks/snapshot.md` — where the copy came from, its date, the refresh policy.
4. `docs/runbooks/memory-check.md` — the Phase 10 deliverable (manual steps, no script).
5. `docs/ADR/ADR-018, ADR-019, ADR-021.md` — the three deviations (§3), reviewed against reality.
6. A complete `.env.example` with every key and not one secret value.
7. **`sql/profiling/` — the complete §6 pack** (18 queries) in numbered, runnable `.sql` files.
8. **One command that starts the stack** — `scripts/lab-stack.sh` (the definition of done in §1.3).

**Regarding item 7:** the queries are **written and not executed** in P0. And since 2026-08-23, all eighteen
are **runnable in P1** without exception: queries 15, 16 and 18 were blocked by the copy list,
and the new aggregate-read list (§3.2) opens them. Running them and interpreting their results is
Phase 1 of P1, and it is the first substantive decision in the program (§6.3). Writing them now means P1 begins
by running rather than by writing, and that the review of the queries themselves happens before the pressure of results arrives.

**Acceptance criterion:** following the README literally in a clean folder arrives at a green `lab:health`.

---

# 8. Steps on Your Side (Human)

These are items the developer cannot carry out alone — either because they are a decision, a privilege, or human coordination.
**Item A is a blocker: no work on data resumes before it.**

| # | Item | Why | When |
|---|------|-----|------|
| **A** | 🔴 **Enable FileVault** and store the recovery key off the machine | 57,482 users + ~70K orders + **~24,408 API access tokens** on an unencrypted disk (§2.1). §14.1 requires this explicitly | **Now — before anything else** |
| **B** | Verify that `~/Projects` is not synced with OneDrive / iCloud / Dropbox | §14.1; OneDrive is running on the machine | Phase 0 |
| **L** | ⚪ **Set a password on the MySQL `root` account** — **optional and not required (ADR-021)** | The account has **no password**, and it is the account the Lab connects with. Binding to `127.0.0.1` prevents access from the network, but it **does not prevent any local process**. **Operator decision 2026-08-21: it stays without a password** — 31 `.env` files under `~/Projects` depend on it, and the machine has a single operator. The residual risk is **explicitly accepted** and documented in ADR-021 with its re-evaluation triggers | **Blocks nothing.** An optional hardening item |
| **D** | ✅ **Settled**: PHP 8.4 is invoked explicitly for this project, with no `brew link` | `php@7.4`/`8.1`/`8.2`/`8.3` are installed and 8.2 is currently the linked one, and your other projects depend on it. Use `/opt/homebrew/opt/php@8.4/bin/php` explicitly for this project | Phase 5 |
| **E** | **The copy refresh policy** — the current copy is 2026-08-07 (11 days old). Do we refresh it before P1? And at what cadence? | The date is printed in every report, and the gap grows over time | Before P1 |
| **F** | Generate `STUDENT_REF_PEPPER` and store it outside Git | §14.2 — the basis of anonymization in P1 | Phase 8 |
| **G** | 🟡 **Launch the taxonomy authoring request now** and nominate the subject-matter experts | §20 calls this "the most important scheduling note in the document": the request starts at P2 time, not at P5, otherwise the program stalls waiting on a human decision that could have matured in parallel (2–4 weeks of elapsed time) | **Now** |
| **H** | Book review slots with the moderators and the trainers | §13.3 estimates 30–60 hours of human review, and says: "scheduled slots, not review on demand" | Before P2 |
| **I** | A decision on repository hosting (a private remote, or local only) | A working preference, not a protection requirement. The "encrypted disk for the weekly copy" strand was **cancelled 2026-08-23** along with Phase 9 | Blocks nothing |
| **J** | Open the file on the **legal provenance of the questions** with management | §14.5 — a bank of 29 thousand questions in the domain of official examinations may contain protected material. A management decision, not an engineering one, but starting it early avoids a surprise at P9 | Early |
| **K** | ✅ **Settled**: Ollama through the official macOS app and as a login item with its default settings. **No runtime limits are pinned** — the 13 GB ceiling was cancelled on 2026-08-23, and measurement put the stack at 5,132 MiB | Done | Phase 4 |

---

# 9. Deliverables

```text
docker-compose.yml                      infrastructure/postgres/init.sql
apps/lab/                (Laravel 13 + Filament 5, migrations, lab:health)
apps/ai-service/         (FastAPI: health ×4 + embed)
scripts/lab-stack.sh     (up | down | status — one command that starts the stack)
sql/profiling/*.sql      (18 queries — written, not executed)
.env.example             .gitignore              README.md
docs/ADR/ADR-018, ADR-019, ADR-021.md
docs/runbooks/{setup,snapshot,memory-check}.md
A read-only injazedu connection + two allowlists: 11 for copying and 6 for aggregate reads (in the application)
A test suite: 10 health checks + the guardrail tests
```

---

# 10. Effort Estimate

| Phase | Days |
|-------|------|
| 0 — Preparation and safety | 0.5 |
| 1 — The repository and Git | 0.25 |
| 2 — PostgreSQL + pgvector | 0.5 |
| 3 — Read-only MySQL access | 0.25 |
| 4 — Ollama and the models | 0.5 |
| 5 — The Laravel application | 1.0 |
| 6 — The FastAPI service | 0.75 |
| 7 — The health matrix | 0.5 |
| 8 — The guardrails | 0.5 |
| 9 — ~~Backup and restore~~ | **0** (cancelled) |
| 10 — Memory (manual steps) | 0.1 |
| 11 — Documentation + the start command | 0.75 |
| **Total** | **~4.6 days** |

**The 2026-08-23 revision** removed ~0.65 days: all of Phase 9, and most of Phase 10. What remains is slightly above
the §15 estimate because building the read-only layers and writing the §6 pack were not in §15, and both take work out of P1
that would otherwise have been pushed into it or forgotten.

---

# 11. Accept/Reject Thresholds (Go / No-Go)

From §15, with additions produced by measurement:

| Situation | Decision |
|-----------|----------|
| ~~The idle stack exceeds 13 GB~~ | **Cancelled 2026-08-23.** No memory gate. Measurement put the stack at 5,132 MiB with ~90% of it the two models; moving PostgreSQL to Homebrew saves ~400 MiB and fixes nothing. The manual steps are in `docs/runbooks/memory-check.md` |
| Ollama does not stay stable with both models | Lower `num_ctx`, or separate the embedding batches from the chat — **a runtime diagnosis**, not a gate |
| Linking PHP 8.4 breaks other projects | **Reverse ADR-017**: fall back to Laravel 12 + Filament 5 on PHP 8.2. The cost: a major upgrade later |
| The native MySQL competes for memory | Tune `innodb_buffer_pool_size` and run it only while working |
| One of the three read-only layers does not block on its own | **Not accepted** until it is fixed, or until it is recorded explicitly which layer carries the guarantee alone (ADR-021) |
| A table outside the two lists is read successfully | **Not accepted** — the two lists are enforced by name |

---

# 12. Risks and Mitigation

| Risk | Mitigation |
|------|------------|
| Working on real data before FileVault is enabled | Item A is an explicit blocker in §8, and an acceptance criterion in Phase 0 |
| **Using `root` in the Lab because it is "easier and already there"** | Phase 3 (paragraph a) explains exactly what breaks; and checks 9 and 10 fail immediately if root is used — so the mistake is caught in the first minute rather than after months |
| `root` with no password being read by any local process | **An explicitly accepted risk — ADR-021**. The compensating controls: FileVault + binding to `127.0.0.1` + the credentials living in `apps/lab/.env` alone + the three layers that stop the Lab's code from writing or from going outside the list. The layers prevent mistakes, not intent. It is re-evaluated on any of the ADR-021 triggers (a second user, remote access, or 3306 leaving the local loopback) |
| The temptation to add services "because they will be needed later" | ADR-011 and ADR-012 are written for exactly this; and §4.2 forbids it explicitly |
| Port conflicts with other projects on the machine | ADR-018 (5433) + binding to `127.0.0.1` only + documenting it in a runbook |
| A PHP upgrade breaking other projects | Not using a global `brew link` (item D) |
| Changing the embedding prefix later silently invalidating the vectors | `embedding_config_version` is fixed from Phase 6, before the first vector is stored (§12.2) |
| The copy going stale unnoticed | `SNAPSHOT_TAKEN_AT` in `.env`, displayed in every report (§16) |
| P0 swelling and swallowing P1's work | §4.2's out-of-scope list is explicit; the §6 pack is written and not executed |
| Local infrastructure built for a production environment that does not exist | The 2026-08-23 revision: backups and the memory gate were cancelled. The machine is a development environment; what belongs to real operation is built when we move to it |
| A read of a table outside the copy list being stored by accident | The two lists are separate in `config/lab.php`, and `NoPiiInLabSchemaTest` fails if a PII column appears in the Lab database |

---

# 13. Acceptance Criteria

```text
[ ] FileVault is enabled, and the recovery key is stored off the machine.
[ ] The stack starts predictably with a single command.
[ ] Laravel creates a job, and the worker actually executes it.
[ ] FastAPI calls Ollama and returns valid JSON.
[ ] A 768-dimension embedding — with the correct prefix — is saved to pgvector and retrieved.
[ ] Every vector carries an embedding_config_version.
[ ] Restarting does not lose PostgreSQL data.
[ ] The MySQL copy is read and refuses writes — proven by ReadOnlyGuardTest, with each layer blocking on its own.
[ ] A table outside the two lists is refused by name, and an aggregate-read table is read but never copied — proven by
    SourceTableAllowlistTest (ADR-021, §14.2 updated 2026-08-23).
[ ] No column in the Lab database holds personal data — proven by NoPiiInLabSchemaTest.
[ ] php artisan lab:health passes all ten checks, and fails with exit code ≠ 0 when any service is stopped.
[ ] The MySQL credentials exist in apps/lab/.env alone, and are not committed to Git.
[ ] apps/ai-service contains no MySQL credentials (ADR-013).
[ ] `docs/runbooks/memory-check.md` is written — manual steps, no script and no gate.
[ ] ADR-018, ADR-019 and ADR-021 are written in docs/ADR/ and match reality.
[ ] The §6 query pack is written into sql/profiling/ — and not executed.
[ ] Following the README in a clean folder reaches a green lab:health.
[ ] Not a single line was written to injazedu.co or to the local copy.
```

---

# 14. Handover to P1

## 14.1 What P1 inherits ready

```text
A working mysql_snapshot connection, with two allowlists: 11 tables for copying and 6 for aggregate reads, and no PII
PostgreSQL 17 + pgvector + pg_trgm ready for P1's tables
Filament running — the inventory console is built directly on it
A database queue ready for the ETL batches
The §6 pack written and entirely runnable (15, 16 and 18 now open) — P1 starts by running, not by writing
STUDENT_REF_PEPPER ready for student_ref
SNAPSHOT_TAKEN_AT fixed: 2026-08-07
```

## 14.2 Numbers P1 must recompute

The §13 budgets were written against an estimate of **~25,000 questions**. Measurement says **29,142** — that is **+16.6%**.
This changes no architectural decision, but it changes every number:

| Item | §13 estimate | Adjusted for 29,142 | Verdict |
|------|--------------|---------------------|---------|
| Embedding calls | 50,000 | **~58,300** | No effect — still 10–30 minutes (§13.1) |
| Undirected top-K=20 pairs | ~250,000 | **~291,000** | No direct effect — shortlisting shrinks them |
| Uncertain-band pairs | 20,000–60,000 | **~23,000–70,000** | Calibrated by the thresholds to reach the 5,000-pair ceiling (§13.2) |
| pgvector storage | ~154 MB | **~180 MB** | Small — no index to begin with (§13.4) |

**The pleasant surprise:** `question_result` holds **13,776,378** rows and `results` holds **1,136,204**.
That is far larger than v2.0 assumed. Query 14 in §6.2 (how many questions have `n ≥ 30`) will determine
the coverage precisely, but the raw volume suggests that **P3 will cover a large part of the bank** — which raises its value
above what §18 estimated, and makes defining the difficulty bands by p-value in §20 genuinely applicable.

---

# 15. Open Items

| # | Item | Impact |
|---|------|--------|
| 1 | **The final plan ends at §20 (P5)** with a `<!-- SENTINEL:PART4B -->` marker. Projects P6–P9 and Phase D, and §21 (the metrics) and §34 (the ordering), are **referenced and not yet written** | It does not block P0 or P1. It must be completed before P6, and preferably before P4, because §21 is the reference for the acceptance gates |
| 2 | §17 and §19 refer to "the target in §21" for their acceptance gates | Settled when Part 4B is written |
| 3 | The meaning of `MULTI_KEY` (§5.1) is unsettled | Query 4 settles it in P1 |
| 4 | The enrollment ambiguity: `course_user` versus `course_order` (§5) | Queries 15 and 16 settle it in P1 |

---

**End of the P0 plan.**
Next project: **P1 — Production Profiling & Question Mirror** (§16).
It does not start before every acceptance criterion in §13 above is satisfied.
