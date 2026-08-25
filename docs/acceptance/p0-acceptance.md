# P0 Acceptance Record — §13's eighteen criteria

**Recorded**: 2026-08-23 · **Instrument**: the commands cited per line, re-run in the verification
window recorded at the bottom of this file · **Rule**: every line is either **met, with its
evidence** (a command, its output, a date), or **not met, with the reason**. No third state.

Source list: `docs/plans/project/0/p0-ai-lab-foundation.md` §13.

| # | Criterion | Status | Evidence |
|---|---|---|---|
| 1 | FileVault on; recovery key stored off-device | **Met** | `fdesetup status` → "FileVault is On" (2026-08-23). Custody attested in `docs/runbooks/safety.md`, `attested_on: 2026-08-20`. |
| 2 | The stack starts with one command | **Met** | `scripts/lab-stack.sh up` → four `[ OK ]` lines, exit 0 — run repeatedly on 2026-08-23, including after full teardown and inside the clean-folder rehearsal. |
| 3 | Laravel dispatches a job; the worker executes it | **Met** | `php artisan lab:health` check 3: "LabQueueProbe executed by worker pid 34319" (2026-08-23). |
| 4 | FastAPI calls Ollama and returns valid JSON | **Met** | Checks 5–6 PASS (chat answered in 11 ms, embed in 77 ms); `scripts/verify-ai-service.sh` assertion set green (2026-08-23). |
| 5 | A 768-dim embedding — with the correct prefix — is stored in pgvector and retrieved | **Met** (composite) | Storage/retrieval: check 7 stores and reads back 768 floats exactly. Prefix correctness: `apps/ai-service/tests/test_embedding_contract.py::test_prefix_is_applied_server_side` and `::test_prefix_changes_the_vector` prove the service owns and applies `task: sentence similarity \| query: {text}`. Both green 2026-08-23 (46/46). |
| 6 | Every vector carries `embedding_config_version` | **Not met — by scope, owed to P2** | The only vector table is the probe (`lab_vector_probes`: id, embedding, written_at), which deliberately uses a generated vector and no version column (002). The contract travels end-to-end at runtime — service response field (`app/main.py`), identical value in both `.env` files, asserted by `verify-ai-service.sh` assertion 5 — but persisted per-row versions arrive with P2's real vector tables (§12.2). Recorded here rather than silently passed over. |
| 7 | Restart loses no PostgreSQL data | **Met** | 2026-08-23 rehearsal cycle: container stopped, removed, and recreated from compose while volume `lab_pgdata` persisted. Fingerprint before/after identical: `lab_vector_probes` embedding md5 `30d9e3442db03eee31a9b986e65783d4`, persistence-marker row unchanged, 12 tables. |
| 8 | MySQL copy is read-only; each of three layers blocks alone | **Met** | `ReadOnlyGuardTest` green (within 46/46, 2026-08-23): empty write-host list, non-read listener throw, allowlist refusal — each refuses with the other two removed. Live check 9: INSERT refused by `ReadOnlyViolation`. |
| 9 | A table outside both lists is refused by name; a profile table is read but never copied | **Met** | `SourceTableAllowlistTest` + `ForbiddenTableRefusalTest` green (2026-08-23): six profile tables readable and **not** copyable, eleven source tables both, fifteen names refused one assertion each. Live check 10: `users` refused naming itself. |
| 10 | No Lab column holds personal data | **Met** | `NoPiiInLabSchemaTest` green against the live schema (2026-08-23) — 12 tables, none carrying PII-class columns. |
| 11 | `lab:health` passes ten checks and fails ≠ 0 when a service is down | **Met** | 2026-08-23, both directions measured: stack up → 10 PASS, exit 0; stack down → 7 FAIL rows, **exit 1**. |
| 12 | MySQL credentials live only in `apps/lab/.env`, never committed | **Met** | `git grep 'INJAZEDU_DB_PASSWORD=.' -- '*.env*'` → nothing committed; `.env` files gitignored (boundary assertion 1); templates carry keys with no values (assertions 20–22, 2026-08-23). |
| 13 | `apps/ai-service` holds no MySQL credential | **Met** | `verify-ai-service.sh` "no MySQL keys" assertion green; direct count of `INJAZEDU_DB\|MYSQL` lines in the service env file = 0 (2026-08-23). |
| 14 | `memory-check.md` written — manual steps, no script, no gate | **Met** | `docs/runbooks/memory-check.md` (2026-08-23): three manual steps, two traps, explicit no-gate statement, zero thresholds. The surviving script gate was retired the same day (`verify-model-runtime.sh --with-memory` reports and warns, never blocks). |
| 15 | ADR-018, ADR-019, ADR-021 match reality | **Met** | Confirmed 2026-08-23 against the running system: `postgresql@14` on 5432 / Lab container on 5433 coexist (ADR-018); OrbStack runs every container incl. the rehearsal clone (ADR-019); the running config carries exactly the eleven-plus-six lists, guard message quotes them, check 10 refuses `users` (ADR-021 revised). Each ADR also carries its own 2026-08-23 re-verification note. |
| 16 | §6 pack written in `sql/profiling/`, not executed | **Met** | Eighteen numbered `.sql` files dated 2026-08-23, bodies diffed verbatim against §6.1–§6.2. Never executed: every statement issued against `injazedu` in this window is enumerated below (health checks 8–10, guard tests, access verification) — none is a §6 query. |
| 17 | Following the README on a clean folder reaches green `lab:health` | **Met** | Clean-folder rehearsal 2026-08-23 (FR-012 protocol): isolated container project/volume, README verbatim, **ten PASS, exit 0**, teardown including the rehearsal volume, live stack restored with original data intact. Two missing steps were exposed, added to the README, and the rehearsal was re-run to green. |
| 18 | Not one line written to `injazedu.co` or the local copy | **Met** | No remote reference exists outside prose (`git grep injazedu\.co` → docs only). Local writes: all MySQL `Com_insert/update/delete/create_table/drop_table/alter_table/load` counters **unchanged across the entire verification window** (before = after, 2026-08-23) while the full battery ran. |

## Verification window (T019)

All evidence above was produced or re-confirmed in one window on **2026-08-23**, in this order:

```text
mysql SHOW GLOBAL STATUS (write counters)        → recorded as "before"
scripts/preflight-check.sh                       → PASSED, exit 0
scripts/verify-data-layer.sh                     → 6 assertions, 0 failures
scripts/verify-injazedu-access.sh                → 3 assertions, 11 tables, 0 failures
scripts/verify-ai-service.sh                     → 5 assertions, 0 failures
scripts/verify-lab-app.sh                        → 46 tests, 46 passed
scripts/verify-model-runtime.sh                  → 6 assertions, 0 failures
scripts/verify-repo-boundary.sh                  → 22 assertions, 0 failures
lab:health with the stack down                   → exit 1, 7 FAIL rows   (criterion 11, failure half)
scripts/lab-stack.sh up                          → STACK UP, exit 0
php artisan lab:health                           → 10 PASS, exit 0
php artisan test                                 → 46 tests, 46 passed, 88 assertions
fdesetup status                                  → FileVault is On
mysql SHOW GLOBAL STATUS (write counters)        → identical to "before": zero rows written
```

Seventeen of eighteen criteria are met with evidence; criterion 6 is explicitly not met, with its
reason and the phase that owns it. That is the honest state of P0: complete for everything it
promised, with one deliberate deferral recorded instead of hidden.
