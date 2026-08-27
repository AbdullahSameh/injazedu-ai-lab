# Commands Runbook — `lab:health`, `lab:profile`, `lab:import`

Three commands, and that is the whole console surface of the Lab. This is the friendly version:
what each one is for, when you would reach for it, and what its output is telling you.

Everything here was run on this machine. Where a number appears, it is measured, not estimated.

---

## Before anything: two things that will bite you

**1. Always call PHP by its absolute path.**

```sh
/opt/homebrew/opt/php@8.4/bin/php artisan lab:health
```

The machine's *linked* `php` is 8.2.27 and 31 other local projects depend on it. Laravel 13 needs
`^8.3`. Plain `php artisan …` fails on the platform requirement every time. Never `brew link` to fix
this — see `setup.md` §3.

A shell alias makes this painless for a work session:

```sh
alias art='/opt/homebrew/opt/php@8.4/bin/php artisan'
# then: art lab:health · art lab:profile --dry-run · art lab:import --kind=bank
```

The rest of this document writes `php artisan …` for readability. Read it as the absolute path.

**2. Run every command from `apps/lab/`.**

```sh
cd /Users/abdullah/Projects/injazedu-ai-lab/apps/lab
```

**3. Start the stack first.** `lab:health` and `lab:import` both need PostgreSQL up; `lab:health`
also needs the AI service, the queue worker and Ollama.

```sh
../../scripts/lab-stack.sh up      # up | down | status
```

Not a login item, by decision. Start it when you sit down, `down` when you finish. Ollama is left
alone either way — the script never stops it.

---

## The three at a glance

| Command | Reads | Writes | Takes | Reach for it when |
|---|---|---|---|---|
| `lab:health` | everything | **nothing** | ~7s cold | Anything feels wrong. Start here, always. |
| `lab:profile` | MySQL source | `source_snapshots` + a report file | ~12s | You want to *measure* the source without copying it. |
| `lab:import` | MySQL source | the fifteen mirror tables | 0.1s–100s | You want the source *copied* into the Lab. |

The one rule that connects them: **`lab:health` is the verdict tool.** Every other command, and the
stack script, ends by pointing back at it. If `lab:health` is 10/10 the Lab is fine, whatever else
you think you saw.

---

## `lab:health` — is the Lab actually working?

```sh
php artisan lab:health
```

No flags. Runs ten checks and prints a table. **Persists nothing** — run it as often as you like.

### What the ten checks are

| # | Check | Expectation | It is really asking |
|---|---|---|---|
| 1 | Lab database | must succeed | Is PostgreSQL 17 answering on 5433? |
| 2 | AI service | must succeed | Is the FastAPI service up on 8001? |
| 3 | Queue | must succeed | Did a **worker process** actually execute a job? |
| 4 | Service → Lab database | must succeed | Can the service reach Postgres, not just us? |
| 5 | Service → chat model | must succeed | Is `gemma4:e2b-it-qat` loaded and answering? |
| 6 | Service → embedding model | must succeed | Is `embeddinggemma:300m-qat-q4_0` answering? |
| 7 | pgvector round-trip | must succeed | Do 768 floats come back **exactly** as stored? |
| 8 | InjazEdu source | must succeed | Can we read MySQL? (prints the question count) |
| 9 | Source write attempt | **must be refused** | Does a real INSERT get blocked? |
| 10 | Forbidden table | **must be refused** | Is `users` refused by name? |

Checks 9 and 10 are the interesting ones. They **pass by failing**: the command issues a genuine
write against MySQL and a genuine read of a forbidden table, and passes only because the guards
threw. A "PASS" there means the read-only boundary is real, not documented.

### Reading the result

- Exit code **0** = all ten passed. **1** = at least one did not.
- The `Detail` column is the useful part. It carries the actual error, not a category.

```text
| 8 | InjazEdu source | injazedu.questions | must_succeed | PASS | injazedu.questions count=29142, snapshot_taken_at=2026-08-07 |
```

Every number the Lab reports travels with `snapshot_taken_at` beside it. The snapshot is fixed at
**2026-08-07** and is never refreshed — the date is context, never a freshness threshold.

### When checks 2, 4, 5 and 6 all fail together

They are all the same fact: **the AI service is down.** Checks 5 and 6 reach Ollama *through* the
service, so a dead service takes four checks with it. Fix one thing:

```sh
../../scripts/lab-stack.sh up
```

### Use it as a bookend

Run it before you start work and after any change that touches the database, the guards or the
service. 10/10 with exit 0 is the baseline this project does not break.

---

## `lab:profile` — measure the source without copying it

```sh
php artisan lab:profile
```

Runs the eighteen §6 profiling queries against MySQL through the guarded connection, stores the
results as **data** in `source_snapshots.profiling_results`, and generates
`docs/reports/p1-profiling.md` from that stored data.

This is how the Lab learns things like "the bank is 29,142 questions" or "31 active questions have
no correct answer" — by measuring, not by copying.

### Flags

| Flag | What it does |
|---|---|
| `--dry-run` | Lists the eighteen files and the tables each declares. **Executes nothing, writes nothing.** |
| `--query=N` | Runs exactly one file, by its §6 number. ⚠️ See the warning below. |

### `--dry-run` is the safe way to look

```sh
php artisan lab:profile --dry-run
```

```text
| #  | File                                         | Tables read                       | Allowlist    |
| 1  | 01-bank-size.sql                             | questions                         | copy         |
| 13 | 13-answer-data-volume.sql                    | question_result                   | profile-only |
| 15 | 15-enrolment-source-course-user-vs-order.sql | course_user, course_order, orders | profile-only |
```

The `Allowlist` column is worth understanding. **`copy`** means the table may also be copied into
the Lab. **`profile-only`** means it may be read as aggregates and its rows may *never* be stored —
`question_result`'s 13.8M answer events live there and stay in MySQL forever.

Reading and storing are separate permissions on purpose. `--dry-run` is the only place you see both
at once.

### ⚠️ Two things `lab:profile` does that surprise people

**It creates a new `source_snapshots` row every single run.** It never updates the previous one.
Run it five times and you have five snapshot rows. That is by design — a profiling run is an
observation with a timestamp — but `lab:import` links to the *latest* one, so casual re-running
moves what your import runs point at.

**`--query=N` overwrites the whole report with a single query.** It creates a snapshot holding only
query N, then regenerates `docs/reports/p1-profiling.md` from it — so an eighteen-section report
becomes a one-section report. If you only want to look at one query, use `--dry-run` to find it and
read the SQL in `sql/profiling/` directly, or accept that you will need a full `lab:profile` run
afterwards to restore the report.

### Reading the result

```text
Snapshot recorded: source_snapshots.id=1
```

Everything else is in two places: the row it just wrote, and `docs/reports/p1-profiling.md`. The
report is **generated from the stored row**, never written independently — so the file is a view,
and the row is the truth.

---

## `lab:import` — copy the source into the Lab

```sh
php artisan lab:import                    # everything, in order
php artisan lab:import --kind=bank        # just the question bank
```

Copies MySQL into the Lab's fifteen mirror tables. **Idempotent and resumable** — re-running is
always safe, and a run that finds nothing changed writes nothing at all.

### The four kinds

| `--kind` | What it does | Rows | Measured |
|---|---|---|---|
| `bank` | The nine content tables: categories → courses → chapters → lectures → quizzes → sections → questions → options → quiz_files. Runs the thirteen validators. | 174,119 | **~9–10s** re-run |
| `behaviour` | `source_results` (1.1M attempts), then the attempt-index pass, then the two statistics tables | 1,443,586 | **~100s** re-run |
| `backfill` | Re-derives three columns that could not be computed at copy time | 61,600 | **~0.1s** |
| `all` *(default)* | `bank`, then `behaviour`, then `backfill` — as **three separate** `import_runs` rows | | |

The order inside `bank` is not a preference — it is key-dependency order and cannot be rearranged.
`backfill` runs last in `all` because each of its three passes reads a table the bank pass has to
have finished writing.

**Why `backfill` exists at all:** three columns describe a row using a table that does not exist yet
when that row is written. `source_sections.questions_count` needs questions; questions are imported
after sections. Same story for `source_questions.requires_media_review` (needs media) and
`answer_key_state` (needs a recorded human decision). Each is one SQL statement over the whole
mirror, touching exactly one column.

### Flags

| Flag | What it does |
|---|---|
| `--kind=` | `bank` · `behaviour` · `backfill` · `all` (default) |
| `--resume` | Continue the last run of this kind from its recorded cursor instead of starting over |
| `--queue` | Dispatch the same jobs to the database queue instead of running them inline |
| `--chunk=N` | ⚠️ **Currently has no effect** — see below |

### Reading the summary table

```text
| Run # | Kind | Read   | Inserted | Updated | Unchanged | Errors | Elapsed (s) | Status    |
| 83    | bank | 174119 | 0        | 0       | 174119    | 29341  | 9.666       | completed |
```

- **Inserted / Updated / Unchanged** — a row is `unchanged` when its content hash matches what is
  already stored, and then **no write happens at all**. A healthy re-import is all-unchanged.
- **Errors is not failures.** It counts *anomalies found in the source data* — questions with no
  correct answer, duplicate option texts, and so on. 29,341 on a bank pass is normal and expected;
  the bank's defects do not heal between runs. A run with `status = completed` and 29,341 errors
  succeeded completely.
- **Status** — `completed` or `failed`. A resumed run finishes as `completed` like any other; the
  counters, not the status, are the record of what resuming did.

Exit code 0 means completion. That is the run's only completion signal — no separate report is
written.

### What the errors actually are

```sh
php artisan help lab:import          # prints all thirteen codes with descriptions
```

On the fixed snapshot a bank pass finds:

| Code | Severity | Count | Active only |
|---|---|---|---|
| `OPTION_ORDER_TIE` | warning | 29,075 | 28,705 |
| `DUPLICATE_OPTION_TEXT` | warning | 127 | 123 |
| `ZERO_CORRECT` | **error** | 56 | **31** |
| `MISSING_OPTIONS` | error | 49 | 27 |
| `MULTI_CORRECT` | warning | 34 | 34 |

`OPTION_ORDER_TIE` firing on nearly the whole bank is not a bug — `options.order` defaults to 0 in
the source and so repeats constantly. Group by code when you read this table; a flat list is useless.

**`ZERO_CORRECT` is the one to care about.** 31 active questions have no correct answer, which means
31 questions a student cannot get right today. That count is checked against the profiling run's
query 3 by a test, and a disagreement blocks acceptance.

To read them:

```sh
docker exec injazedu_lab_postgres psql -U lab -d injazedu_lab -c \
  "SELECT code, severity, count(*) FROM import_errors
   WHERE import_run_id = (SELECT max(id) FROM import_runs WHERE kind='bank')
   GROUP BY 1,2 ORDER BY 3 DESC;"
```

`import_errors` is **append-only per run**. Nothing is ever deleted or rewritten, so a run that
writes nothing logs nothing — which is exactly why a no-op re-import does not make the bank look
clean.

### `--resume` — after a crash

If a run dies part-way, its cursor records the last confirmed position per table. Resuming picks up
from there:

```sh
php artisan lab:import --kind=bank --resume
```

It never duplicates a row and never drops one. Rows already written are re-read harmlessly — they
land as `unchanged` — which is what makes the crash-in-the-middle case safe rather than merely
unlikely.

**When you do *not* need it:** the import is idempotent, so a plain re-run also works and is only
slower. `--resume` is the optimisation, not the correctness mechanism.

### `--queue` — for the long behavioural run

```sh
php artisan lab:import --kind=behaviour --queue
```

Dispatches the identical job classes to the database queue instead of running them inline. Same
jobs, same cursor, same upsert — only the dispatcher differs.

**The catch:** the command returns as soon as dispatch finishes, not when the work finishes. Its
summary table will show zeros because the jobs have not run yet. A worker must be running
(`lab-stack.sh up` starts one), and you check progress by re-reading the run row:

```sh
docker exec injazedu_lab_postgres psql -U lab -d injazedu_lab -c \
  "SELECT id, kind, status, rows_read, error_count, ran_via FROM import_runs ORDER BY id DESC LIMIT 3;"
```

For the ~100s behavioural run, inline is usually simpler. `--queue` earns its keep when you want to
walk away.

### ⚠️ `--chunk` currently does nothing

The flag is accepted and echoed back in the startup line, but the value never reaches a job. Every
job batches at the hardcoded `BatchUpsert::BATCH_SIZE` (1,000 rows). Passing `--chunk=5000` changes
the printed line and nothing else. Do not use it to tune anything until it is wired.

---

## Recipes

**"I just sat down."**

```sh
cd apps/lab && ../../scripts/lab-stack.sh up && php artisan lab:health
```

**"I want the Lab fully populated from scratch."**

```sh
php artisan lab:profile      # measure first — lab:import needs a snapshot row
php artisan lab:import       # bank, behaviour, backfill
php artisan lab:health       # confirm nothing broke
```

The first load into an empty mirror is much slower than the re-run times quoted above — every row
is an insert rather than a hash comparison. `import_runs.elapsed_seconds` records what it actually
took.

**"Did anything change since last time?"**

```sh
php artisan lab:import --kind=all
```

All-unchanged, zero inserted, zero updated means the mirror already matches the source. Since the
snapshot is frozen, that is the expected answer forever.

**"Something is broken and I do not know what."**

```sh
php artisan lab:health
```

Read the `Detail` column of the first FAIL. Do not guess from the check name.

**"I changed a validator / a derivation and want to see the effect."**

```sh
php artisan lab:import --kind=bank    # re-runs the thirteen checks over the whole bank
```

Note that derived columns outside the content hash are **not** refreshed by a re-import — an
all-unchanged run skips the write entirely. Use `--kind=backfill` for the three backfilled columns.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `Composer detected issues in your platform` | Plain `php` is 8.2 | Use the absolute 8.4 path |
| Checks 2, 4, 5, 6 all FAIL | AI service down | `../../scripts/lab-stack.sh up` |
| Check 3 FAIL | No queue worker | same |
| Check 1 FAIL | Postgres not up, or you hit 5432 | The Lab is on **5433**; 5432 is another project's `postgresql@14` |
| `No source_snapshots row exists` | `lab:import` before `lab:profile` | Run `lab:profile` once |
| `aborting because of version mismatch` from `psql` | Host client is 14, server is 17 | Run SQL in-container: `docker exec injazedu_lab_postgres psql -U lab -d injazedu_lab -c '…'` |
| `lab:import` reports thousands of errors | Not a failure | That is anomalies *found*. Check `status` and the exit code instead |
| Run shows `ran_via = queue` and all zeros | Jobs dispatched, not yet run | A worker must be up; re-read the run row after a moment |

---

## What none of these commands will ever do

Worth knowing, because it saves you from looking for switches that do not exist:

- **Write to MySQL.** Three independent layers block it, and `lab:health` check 9 proves it on every
  run. There is no `--force`.
- **Copy a `profile-only` table.** `question_result` is read as aggregates and its rows never leave
  MySQL.
- **Store a `user_id`.** It is hashed into `student_ref` in the same statement that reads it.
- **Repair anything.** Validators name problems and the row is copied exactly as it was. Cleaning is
  a later project's job.
- **Refresh the snapshot.** It is fixed at 2026-08-07 for the whole program.
