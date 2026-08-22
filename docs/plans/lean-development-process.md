# Lean the development process for injazedu-ai-lab

## Context

This repo is ~95% governance prose and ~5% executing code. Against **one Postgres container**, three shell scripts, and a 15-line `init.sql`, it carries a 356-line constitution, an 8-gate constitution table stamped into every plan, a 1,994-line spec directory for an increment that is **0% implemented**, and a documentation chain (`requirement → research → ADR → contract → task → success criterion → §13 checkbox`) where each artefact exists mainly because another artefact demands it.

The audit found three things that shape the whole change:

1. **`002-snapshot-access-and-runtime` is pure prose.** `apps/lab/` holds a `.gitkeep`. `sql/` is empty. `docs/ADR/ADR-016/017/020/022` — declared "in force" by the spec — were never written. All 48 tasks are `[ ]`, and the entire directory is untracked in git. Nothing has to be undone; only documents rewritten.
2. **`001` is real and its three scripts are good** — inverted assertions, fail-closed on unparseable input, reading limits off the running container rather than the compose file, and deliberately no `--force`. They stay.
3. **The §14.2 allowlist is written in the core plan as an *ETL* rule** — what may be copied *into* the Lab — not fundamentally as a grant. Removing `lab_ro` removes the mechanism, not the guarantee. That is why the guarantee can survive intact at the application layer.

Every decision below is one you approved in this session.

### Approved architecture

```text
Native MySQL 9.1 on 127.0.0.1:3306          Docker Postgres 17 + pgvector
database: injazedu                          127.0.0.1:5433
user: root / no password                    database: injazedu_lab
                    │                                    ▲
                    │  READ / COPY ONLY                  │  READ / WRITE
                    └──────────► Laravel 13 ─────────────┘
                                 apps/lab
```

### Approved decisions

| # | Decision |
|---|---|
| 1 | `apps/lab/.env` is standard Laravel. `DB_*` = Lab Postgres (default connection); `INJAZEDU_DB_*` = MySQL source. Root `.env` keeps only what docker compose reads. |
| 2 | Read-only toward MySQL is enforced by connection config (`'write' => ['host' => []]`) **plus** a query listener that throws on any non-`SELECT`, proven by a script. |
| 3 | The 11-table allowlist survives as `config/lab.php` + a reader that throws on anything else + a test that no Lab migration creates a PII column. |
| 4 | Ollama runs as the official macOS app and registered login item with defaults. Measure memory first; pin limits **only** if the 13 GB budget is actually at risk. No project-owned launch agent. |
| 5 | `docs/plans/` Arabic documents: patch the contradictions only. Strategy, profiling pack, budgets, P1–P5 untouched. |
| 6 | `docs/ADR/`: keep all three files. Rewrite ADR-021 only. ADR-016/017/020/022 are never written. |
| 7 | `PRODUCTION_WRITE_ENABLED` is dropped — it guards a write path to `injazedu.co` that does not exist. |
| 8 | `specs/001-*` stays as a historical record. One "superseded" line at the top, nothing else. |

---

## 1. Constitution — `.specify/memory/constitution.md`

Rewrite to **v2.0.0**, ~130 lines (from 356). Keep every rule that protects data or code quality; delete every rule whose product is paperwork.

**New Principle I — No Unapproved Assumptions** (this is the priority rule):

> Do not decide architecture, infrastructure, security, database, dependency, or workflow questions that the repository or the operator has not already settled. When something is ambiguous, contradictory, missing, or carries a real trade-off — stop and ask. Identify the problem, lay out the options, recommend one, then wait for approval.
>
> This does not apply to ordinary implementation that follows from an approved architecture. Use normal engineering judgement there. The gate is for **meaningful decisions**, not every line of code.
>
> Never grant yourself extra constraints, permissions, infrastructure, services, accounts, or scope.

**Principles kept, condensed:**

| Principle | What survives |
|---|---|
| II — What the Lab is | Prepare / recommend / human approves. AI may suggest, classify, rank, flag, draft, explain. AI never publishes, deletes a source question, changes a correct answer, or messages students unreviewed. |
| III — Data boundaries | InjazEdu MySQL is a **read-only source**. Lab Postgres is the Lab's own read/write storage. No PII enters the Lab (`student_ref = HMAC-SHA256(pepper, user_id)`). Snapshot on FileVault storage, outside the repo, outside cloud sync. Secrets in `.env` only, never committed. |
| IV — Deterministic core, AI at the edge | Laravel owns every Lab migration. Metrics computed in SQL/Python, never by an LLM. AI output schema-validated. Prompts versioned. `embedding_config_version` on every vector. Deterministic option order and `payload_hash`. Idempotent jobs. Anomalies recorded, never swallowed. Arabic `raw`/`clean`/`search` layering. |
| V — Targeted testing | Unit tests for the deterministic core; a health check; the guardrail tests (the app cannot write to InjazEdu MySQL, no PII column in the Lab schema); golden eval sets after any model/prompt/embedding change. Nothing broader. |
| VI — One coherent surface | Arabic-first RTL for reviewer/student screens. Every number carries `n` and `snapshot_taken_at`. Suppression at n<10 / n<30 / n≥30. AI output visibly labelled. Human override recorded. |
| VII — Measured budget | 16 GB is a hard constraint. Cheap layers before the LLM (hash → pg_trgm → pgvector → LLM). Model choices backed by a measurement. Batches resumable. |

**Deleted outright:**

- The 34-line HTML Sync Impact Report block.
- "Any deviation MUST be a numbered ADR **before** the code is written" → replaced by: *an ADR only when a decision is architectural, durable, and expensive to reverse. Not for which PHP binary, `.env` location, Docker settings, or dependency config.*
- "Work not described in a plan and not covered by an ADR MUST NOT be built."
- Principle III's project-by-project ceremony: mandatory written handoffs, "partial completion MUST be reported as partial", binding out-of-scope lists, Go/No-Go gates as formal halts.
- The Governance section's 4-step amendment procedure, mandatory same-commit propagation to three templates, and the MAJOR/MINOR/PATCH versioning policy → one line: *edit this file and update the version line.*
- The Compliance Review checklist.
- The entire `lab_ro` / eleven-grants / permission-layer paragraph in Data Protection, and `PRODUCTION_WRITE_ENABLED`.

**Replaced:** the "Development Workflow & Quality Gates" section becomes ~10 lines — spec → plan → tasks → implement; `research.md`, `contracts/`, and `checklists/` are produced **only when they earn their place**; done means it runs, its tests pass, and you said plainly what you skipped.

## 2. Spec Kit templates

- **`.specify/templates/plan-template.md`** — replace the 8-gate Constitution Check table (lines 30–46, which is what generates all the gate prose downstream) with a 6-line checklist: read-only toward MySQL · no PII into the Lab · Laravel owns migrations · tests are the targeted kind · fits the memory budget · nothing decided that needed approval. Soften the Documentation tree so `research.md` / `contracts/` / `checklists/` are optional.
- **`.specify/templates/tasks-template.md`** — delete the "Constitution Alignment" section (lines 243–259, including the `lab_ro` line at 258–259). Add two lines: tasks are implementation, testing, infrastructure, and safety work; a documentation task needs a reason beyond "the process asks for one".

## 3. `002-snapshot-access-and-runtime` — the main rewrite

Directory name and branch stay (renaming breaks `.specify/feature.json` and git). Retitle inside to **"Source Access & Lab Runtime (P0 — المراحل 3–5)"**.

**Delete:** `contracts/` (3 files, 267 lines — each duplicates in prose what the script asserts in code; the assertion list moves into the script header, where 001's scripts already keep it) and `checklists/requirements.md`.

**`spec.md`** — 355 → ~120 lines.

- Remove all of FR-001…FR-011 (the `lab_ro` identity, grants, deny-by-default, admin-credential-absence, `CURRENT_USER()` host matching), FR-030/FR-033/FR-034 (ADR mandates), FR-037 (§13 tally), FR-039 (`PRODUCTION_WRITE_ENABLED`).
- Remove the **Acceptance Gate — Mapping to P0 §13** table and its closed/advanced/vacuous/untouched tally.
- Remove the Clarifications section — fold the resolved answers into the requirements.
- Keep, rewritten: the source connection is read-only by architecture; the app-level guard; the allowlist; the model runtime; the Laravel shell (migrations, queue, panel, log channel); the PHP 8.4 pinning that must not disturb the 31 other local projects.
- Keep the edge cases that name a real failure mode: a queue that "works" without a worker; a panel page faking a green status; dependency installation running under the wrong PHP; the framework's write-block possibly falling back to the read host rather than refusing.
- Net: ~18 FRs, ~10 SCs.

**`plan.md`** — 201 → ~70 lines. Delete the 8-gate table, the 🔴 Operator Prerequisite section, Complexity Tracking, and the Go/No-Go table (keep the two rows that are real engineering forks: PHP 8.4 cannot run Laravel 13 → fall back to Laravel 12/PHP 8.2; both models resident exceed 13 GB → reduce context or separate embedding batches). Keep Technical Context, the file tree, and the group ordering.

**`tasks.md`** — 48 → ~26 tasks. Delete T001–T006 (operator gate + four ADRs), T011–T012 (grant SQL + `SNAPSHOT_DB_*`), T010/T017 (kill switch), T035–T036 (plist + setup script), T045/T047/T048 (plan amendment, §13 tally, inspection). Renumber.

**`research.md`** — 451 → ~90 lines, renamed to `notes.md`. Keep the five findings that still change what gets built:

| Keep | Why it survives |
|---|---|
| R7 | `gemma4:e2b-it-qat` is **4,135 MB**, not ~3 GB, and ships a 941 MB vision projector nothing uses. This is the number المرحلة 10 turns on. |
| R8 | Laravel 13.26.1 needs `php ^8.3`; the machine links 8.2.27 and 31 projects depend on it. Composer resolves against the running interpreter — use the 8.4 binary for resolve **and** run. |
| R11 | The framework's empty write-host list may silently fall back to the read host instead of refusing. Must be proven, which is exactly why the query listener exists. |
| R12 | A queued job must leave a **row** behind, asserted after the worker has exited. A log line is not evidence. |
| R14 | `.gitignore`'s blanket `*.sql` would swallow المرحلة 11's 18 profiling queries. Still needs narrowing. |

Dropped: R1–R4 (`CURRENT_USER()` vs `USER()`, `caching_sha2_password` for `lab_ro`, `GRANT` semantics, deny-by-default) — all moot. R5/R6 (Homebrew plist rewriting and Homebrew-only defaults) — moot after the operator selected the official macOS app on 2026-08-22. R10, R13 — moot.

**`data-model.md`** — 248 → ~90 lines. §1 becomes the *source connection* rather than a database identity. §2 becomes the app-level allowlist. §3 becomes two env files. §4 (the plist) deleted. §5–§8 kept.

**`quickstart.md`** — keep and simplify. Delete "🔴 Step 0 — the one step that is not automated". It is the genuinely useful artefact here: the runnable path from clean checkout to green.

## 4. Application shape (what 002 will build)

```text
apps/lab/
├── .env                      ← Laravel's own; git-ignored (already covered by .gitignore:11)
├── .env.example              ← committed (already covered by !.env.example)
├── config/database.php       ← 'pgsql' default · 'injazedu' read-only source
├── config/lab.php            ← source_tables allowlist + snapshot_taken_at
├── app/Providers/AppServiceProvider.php   ← the read-only query listener
├── app/Support/SourceReader.php           ← throws on any table outside the allowlist
├── app/Jobs/LabQueueProbe.php             ← upserts a fixed id (idempotent)
├── app/Filament/Pages/LabHealth.php       ← stated placeholder, no fabricated status
└── tests/Feature/
    ├── ReadOnlyGuardTest.php              ← INSERT through 'injazedu' throws
    ├── SourceTableAllowlistTest.php       ← unknown table throws
    └── NoPiiInLabSchemaTest.php           ← no Lab column named email/phone/id_number/national_id
```

`config/database.php` — the `injazedu` connection:

```php
'injazedu' => [
    'driver'   => 'mysql',
    'host'     => env('INJAZEDU_DB_HOST', '127.0.0.1'),
    'port'     => env('INJAZEDU_DB_PORT', '3306'),
    'database' => env('INJAZEDU_DB_DATABASE', 'injazedu'),
    'username' => env('INJAZEDU_DB_USERNAME', 'root'),
    'password' => env('INJAZEDU_DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    // Layer 1: no write target. Layer 2 is the listener in AppServiceProvider.
    'read'     => ['host' => [env('INJAZEDU_DB_HOST', '127.0.0.1')]],
    'write'    => ['host' => []],
],
```

**Split of responsibility for proof:** shell scripts verify *infrastructure* (is MySQL reachable, is Ollama loopback-only, is the repo boundary intact); Pest tests verify *application behaviour* (the guard throws, the allowlist throws, no PII column exists). Each tool checks what it is good at, instead of shell scripts reaching into PHP.

## 5. Scripts

**Keep unchanged** — all three earn their place:

| Script | Why it stays |
|---|---|
| `scripts/preflight-check.sh` (197) | FileVault, recovery-key custody, cloud-sync exposure, disk, container engine. Treats an unparseable `fdesetup` result as Off. No `--force`. |
| `scripts/verify-repo-boundary.sh` (111) | 13 `git check-ignore` assertions incl. the inverted `.env.example` case. This is what keeps credentials and dumps out of git. |
| `scripts/verify-data-layer.sh` (187) | Reads the memory ceiling off the *running container*; proves the LAN address is refused; guards that `postgresql@14` on 5432 is still alive. |

**Amend** `verify-repo-boundary.sh`: add one assertion that `apps/lab/.env` is ignored while `apps/lab/.env.example` is not (both already work under the current rules — the assertion locks them in), plus one that a dump path is still ignored after the `*.sql` narrowing.

**New — three, each smaller than its deleted 90-line contract:**

- `scripts/verify-injazedu-access.sh` — connects as root, asserts all 11 allowlisted tables are readable and `SELECT COUNT(*) FROM questions` = **29142**. Runs without the app. Contains **no inverted DB-level check** — root can write, and asserting otherwise would be a lie.
- `scripts/verify-model-runtime.sh` — Ollama alive, both tags present, listening socket is loopback-only, one non-loopback connection attempt refused. `--with-memory` loads both models and reports resident memory against the 13 GB ceiling.
- `scripts/verify-lab-app.sh` — the app runs 8.4 **and** the machine still links 8.2; migrations applied; the probe job's row exists with `worker_pid` ≠ dispatcher pid after the worker exits; root `.env`'s `LAB_DB_PASSWORD` matches `apps/lab/.env`'s `DB_PASSWORD` (the one real cost of two env files); panel requires auth. Then delegates to `php artisan test`.

## 6. `.gitignore` and env files

- Narrow `*.sql` (line 16) to dumps by extension and location; un-ignore `sql/` and `infrastructure/`; drop the now-redundant `!infrastructure/postgres/init.sql`.
- Root `.env` / `.env.example` shrink to what docker compose reads: `LAB_DB_PASSWORD`. Delete the "Keys deliberately ABSENT" block — including `SNAPSHOT_DB_ROOT_* → never. No root credentials, ever.`, which the approved architecture now contradicts.
- New `apps/lab/.env.example` carries `APP_*`, the `DB_*` pgsql group, `INJAZEDU_DB_*` (username `root`, password empty), `QUEUE_CONNECTION=database`, `LOG_*`, `SNAPSHOT_TAKEN_AT`, `OLLAMA_HOST`.

## 7. `docs/`

- **`docs/ADR/ADR-021.md`** — rewrite. It currently asserts *"The Lab connects **only** as `lab_ro`"* and *"ADR-020's eleven-table grant stays the enforcement boundary"* (lines 43–46), both now false. New body: root/no-password is the approved connection; the risk accepted is the pre-existing machine state; what enforces read-only now is the connection config, the query listener, and the allowlist; keep its four re-evaluation triggers, which are still correct. Number unchanged.
- **`docs/ADR/ADR-018.md`, `ADR-019.md`** — unchanged, per your decision.
- **`docs/runbooks/snapshot.md`** — line 19 (*"the first permitted access is المرحلة 3 (read-only, via the ADR-020 grant)"*) → read-only via the app's `injazedu` connection. Everything else (provenance, containment rule, refresh policy still open) stays.
- **`docs/runbooks/safety.md`** — unchanged. `preflight-check.sh` condition 2 reads it, so it is mechanical, not paperwork.
- **`docs/plans/project/1/p0-ai-lab-foundation.md`** — patch four places: المرحلة 3 (lines 396–509: replace the `CREATE USER`/eleven-`GRANT` block and the two-identity table with the approved architecture); §8 Item C (line 773: delete the operator gate; Item D and Item K resolve to the approved answers); §13 checkboxes 869/870/873/877; §11 Go/No-Go line 837 (*"`GRANT` cannot be executed → P0 not accepted"*).
- **`docs/plans/core/…final….md`** — §14.2 only: it stays the ETL allowlist, with a note that it is enforced in the application layer.
- **`specs/001-lab-foundation-bootstrap/spec.md`** — one line at the top: superseded governance model, artefacts still in force.

## 8. `CLAUDE.md` / `AGENTS.md`

Byte-identical files (`.specify/init-options.json` sets `context_file: AGENTS.md`); keep them identical. Rewrite both to ~45 lines from 61. Delete the 🔴 operator gate block, the `lab_ro` / `CURRENT_USER()` / `caching_sha2_password` facts, the pre-fixed ADR-016/017/020/022 list, and `PRODUCTION_WRITE_ENABLED`. Keep the measured environment facts that are still true and still save time: PHP 8.2 is linked and 31 projects depend on it (use the 8.4 binary explicitly, never `brew link`); Postgres 17 on 5433 while 5432 is untouchable `postgresql@14`; host `psql` is 14 vs server 17 so run SQL in-container; `/bin/bash` is 3.2; the bank is 29,142 questions; MySQL 9.1 on 3306 as root/no-password.

---

## Verification

```bash
# 1. Nothing stale survives
grep -rn "lab_ro\|PRODUCTION_WRITE_ENABLED\|ADR-020\|ADR-022\|Operator Gate" \
  --exclude-dir=.git --exclude-dir=node_modules . \
  | grep -v "specs/001-lab-foundation-bootstrap"     # 001 is the historical record
# expect: no hits outside 001

# 2. The three existing guards still pass, unchanged
scripts/preflight-check.sh && scripts/verify-repo-boundary.sh && scripts/verify-data-layer.sh

# 3. The .gitignore narrowing works both ways
git check-ignore -q sql/profiling/q01.sql   ; echo "profiling ignored? $? (want 1)"
git check-ignore -q data/snapshots/dump.sql ; echo "dump ignored?      $? (want 0)"
git check-ignore -q apps/lab/.env           ; echo "app env ignored?   $? (want 0)"
git check-ignore -q apps/lab/.env.example   ; echo "app tmpl ignored?  $? (want 1)"

# 4. Sanity-check the one unproven assumption before 002 starts:
#    can PHP 8.4 authenticate as root with an empty password over TCP?
/opt/homebrew/opt/php@8.4/bin/php -r '
  $p = new PDO("mysql:host=127.0.0.1;port=3306;dbname=injazedu", "root", "");
  echo $p->query("SELECT COUNT(*) FROM questions")->fetchColumn(), PHP_EOL;'
# expect: 29142
```

Then read `specs/002-snapshot-access-and-runtime/spec.md` end to end and confirm every requirement is something that gets **built, tested, or protected** — no requirement whose only output is another document.

## Not in scope

Building `002` itself. This change rewrites the process and the specification; the Laravel app, the model runtime, and the scripts are the next session's work, executed against the leaned spec.

## Deferred to you after the rewrite

- **Snapshot refresh policy** (§8 Item E) is still undecided and now blocks nothing mechanical — but the snapshot is dated `2026-08-07`, so the gap grows silently.
- **`docs/runbooks/safety.md` records the FileVault recovery key as living on Google Drive**, while `preflight-check.sh` condition 3 exists specifically to keep the repo off cloud sync. Not a contradiction (the key is not the repo), but worth a deliberate look.

## Safety trade-offs, stated plainly

Removing `lab_ro` means MySQL no longer enforces anything. The app connects as `root`, which can write to and read every table in `injazedu`, including `users` (57,482 rows) and `personal_access_tokens` (~24,408). You accepted this explicitly.

What replaces it is weaker in kind but real in practice: the connection has no write target, a query listener throws on any non-`SELECT`, the reader refuses tables outside the allowlist, and a test asserts no PII column reaches the Lab schema. Three application layers instead of one database layer. **A `DB::statement()` written to bypass the listener would succeed** — the guard stops accidents, not intent. That is the correct trade for a single-operator local machine, and it is what the rewritten ADR-021 will say in those words.
