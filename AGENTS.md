<!-- SPECKIT START -->
Active feature: **001-lab-foundation-bootstrap** — P0 AI Lab Foundation, المراحل 0–2
(safety preflight · repository boundary · PostgreSQL 17 + pgvector data layer).

Read these before writing code for it:

- Plan: `specs/001-lab-foundation-bootstrap/plan.md`
- Spec: `specs/001-lab-foundation-bootstrap/spec.md` (30 FRs, 12 SCs, Acceptance Gate vs P0 §13)
- Research: `specs/001-lab-foundation-bootstrap/research.md` (environment measured 2026-08-20)
- Contracts: `specs/001-lab-foundation-bootstrap/contracts/` (three verification scripts)
- Quickstart: `specs/001-lab-foundation-bootstrap/quickstart.md`

Environment facts measured 2026-08-20 — do not assume otherwise:
`/bin/bash` is 3.2 (no bash 4+ syntax) · Compose v5.1.2 · host `psql` is 14 vs server 17, so run
SQL in-container · port 5432 is held by `postgresql@14` (untouchable), 5433 free · OrbStack
installed, daemon stopped · iCloud Desktop & Documents sync is ON.

⛔ **FileVault is Off.** The increment cannot be accepted until encryption is on or converting and
the recovery key is attested in `docs/runbooks/safety.md` (P0 §8 Item A, non-waivable).
<!-- SPECKIT END -->

## Project governance

Before writing any spec, plan, or code in this repository, read
`.specify/memory/constitution.md` (v1.0.0). It is binding and defines seven principles
(core idea, plan traceability, project-by-project delivery, code quality, basic testing only,
UX consistency, performance budgets) plus non-waivable data-protection constraints:
production is read-only, the `lab_ro` grant covers 11 tables only, and no PII may enter the
Lab database.

Governing plan: `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0).
Production reality: `docs/schema/injazedu-db-schema.md`.
