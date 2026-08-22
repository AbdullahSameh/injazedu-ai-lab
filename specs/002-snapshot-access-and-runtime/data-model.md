# Data Model — Source Access & Lab Runtime

What this increment creates or configures. No production schema fact appears here that is not traced
to `docs/schema/injazedu-db-schema.md` or to live metadata read on 2026-08-21.

---

## 1. The Source Connection

Not a database identity — this increment creates no database account. It is a **Laravel connection**
named `injazedu`, guarded in the application.

| Field | Value | Note |
|---|---|---|
| driver | `mysql` | MySQL 9.1.0, native Homebrew service |
| host / port | `127.0.0.1` / `3306` | loopback only |
| database | `injazedu` | the local copy, taken 2026-08-07 |
| username | `root` | approved; see `docs/ADR/ADR-021.md` |
| password | *(empty)* | approved; MySQL enforces nothing |
| `read` | `['host' => ['127.0.0.1']]` | |
| `write` | `['host' => []]` | **guard 1** — no write target |

**Guard 2** — a query listener on this connection throws `ReadOnlyViolation` on any statement whose
first keyword is not `SELECT`, `SHOW`, `DESCRIBE`, or `EXPLAIN`.

**Guard 3** — `SourceReader` throws on any table outside `config('lab.source_tables')`.

Each guard must block with the other two removed (SC-003). They stop accidents, not intent: a
deliberate `DB::statement()` written to bypass the listener would succeed. That is the accepted trade.

---

## 2. The Copy Allowlist

`config/lab.php → source_tables`. Eleven tables, all confirmed present as InnoDB base tables on
2026-08-21:

```text
categories · courses · chapters · lectures · quizzes · sections
questions · options · quiz_files · results · question_result
```

The database holds **50 tables**. The other 39 are unreachable through `SourceReader`, which is the
only sanctioned path. Seventeen of them are named forbidden in core plan §14.2 (`users`, `orders`,
`course_order`, `book_order`, `coupons`, `certificates`, `complaints`, `complaint_responses`,
`social_providers`, `personal_access_tokens`, `paymob_logs`, `zoom_users`, `audits`, `telescope_*`,
`google_oauth_tokens`, `failed_jobs`, `settings`); the remaining 22 are simply not on the allowlist.

**`results` and `question_result` carry `user_id`.** They are readable by design and it must never be
stored — P1's ETL converts it to `student_ref` on the way in. This increment stores nothing at all,
so the constraint is inherited rather than discharged here. Recording it now is what makes it
inheritable.

---

## 3. Environment Surface — Two Files, One Shared Value

Each tool reads its own file. That is the convention, not a compromise.

**`/.env`** — read by Docker Compose only. Untracked.

| Key | Value |
|---|---|
| `LAB_DB_PASSWORD` | generated locally; no default, so `docker compose up` fails loudly if unset |

**`/apps/lab/.env`** — read by Laravel. Untracked. `/apps/lab/.env.example` is committed with the
same keys and no values.

| Group | Keys |
|---|---|
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` |
| Lab database (default) | `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5433`, `DB_DATABASE=injazedu_lab`, `DB_USERNAME=lab`, `DB_PASSWORD` |
| InjazEdu source | `INJAZEDU_DB_HOST=127.0.0.1`, `INJAZEDU_DB_PORT=3306`, `INJAZEDU_DB_DATABASE=injazedu`, `INJAZEDU_DB_USERNAME=root`, `INJAZEDU_DB_PASSWORD=` *(empty by design)* |
| Queue and logs | `QUEUE_CONNECTION=database`, `LOG_CHANNEL`, `LOG_LEVEL` |
| Provenance | `SNAPSHOT_TAKEN_AT=2026-08-07` |
| Model runtime | `OLLAMA_HOST=127.0.0.1:11434` — consumers arrive in المرحلة 6 |

**The one shared value**: `LAB_DB_PASSWORD` in the root file and `DB_PASSWORD` in the app file are
the same Postgres password. `verify-lab-app.sh` asserts they match — that assertion is the entire
cost of using two conventional files instead of one unconventional one.

Keys that belong to later phases and must not appear early: `STUDENT_REF_PEPPER` (المرحلة 8),
`EMBEDDING_CONFIG_VERSION` (المرحلة 6).

---

## 4. Model Inventory

| Tag | Artefact | Role |
|---|---|---|
| `embeddinggemma:300m-qat-q4_0` | 227.5 MB | embeddings — المرحلة 6 onward |
| `gemma4:e2b-it-qat` | **4,135.5 MB** | chat — includes a 941 MB vision projector nothing in P0–P9 uses |

Tags are exact and are part of a contract: changing one later invalidates every stored vector. A tag
that cannot be retrieved stops the increment rather than being substituted.

Ollama runs as the official macOS app and registered login item with its defaults. Limits are pinned
only if the measured resident figure breaches the 13 GB ceiling.

---

## 5. Lab Application Schema

**Framework defaults** — `users`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
`sessions`. Note that Laravel's `failed_jobs` shares a name with a forbidden InjazEdu table; they are
in different databases and unrelated.

**`lab_job_probes`** — the only table this increment designs:

| Column | Type | Note |
|---|---|---|
| `id` | integer, PK | always `1` — a fixed id makes re-running idempotent |
| `dispatched_at` | timestamp | set by the dispatcher |
| `ran_at` | timestamp | set by the worker |
| `worker_pid` | integer | must differ from the dispatcher's pid |

No column here or anywhere in the Lab schema may hold personal data (FR-024).

---

## 6. Lab Log Channel

`config/logging.php → channels.lab` — daily files, 14-day retention, separate from the framework
default. Reserved fields, all empty until المرحلة 6: `model_name`, `prompt_version`, `latency_ms`,
`request_id`. Created before the first AI call exists, so that call has nowhere else to go.

---

## 7. Panel Placeholder

One page, `LabHealth`, behind authentication. Zero resources, zero functional screens, zero
fabricated status indicators, and no locale lock-in — P1's first reviewer screen must be able to
bring Arabic + RTL with it rather than unpick a decision made here.
