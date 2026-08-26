# Specification Quality Checklist: P1 — Production Profiling & Question Mirror

**Purpose**: Validate specification completeness and quality before planning
**Created**: 2026-08-25
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

**One spec, ten phases.** P1 is delivered as a single Spec Kit feature covering the whole project
(P1 plan §7.1, constitution "How Work Gets Done"). It is not split into increments. The plan's ten
phases are the implementation order **inside** this spec; phases 3, 4 and 5 do not depend on phase 2
and may be built alongside it, while phase 6 does, because it derives `answer_key_state`.

**Content-quality note, same as 001–004.** This spec names commands, columns, table names, and
thresholds. That is deliberate: the deliverables here **are** two commands, fourteen tables and one
console, and the mirror schema shape is one of the three things the constitution calls expensive to
reverse once data exists (Principle I). A column that must not exist is a requirement, not an
implementation detail.

**Zero clarification markers, by construction.** The P1 plan v2.0 specifies the schema column by
column and settles its three deviations explicitly (§3.1, §3.2). Where the plan left something open,
it was resolved as a stated assumption rather than a question, per Principle I's narrow gate — the
Telegram fields riding on `source_courses`, the snapshot row that import runs link to, the two-pass
derivation of `requires_media_review` and `questions_count`, and the 10,000-row starting chunk. All
four are reversible by an edit.

**Clarified 2026-08-25** (`/speckit-clarify`, five questions, all answered). Each one closed a gap
that would have been discovered during implementation, and each is recorded in the spec's
`## Clarifications` section and folded into the requirement it affects:

| Gap | Resolution | Requirements touched |
|---|---|---|
| `payload_hash` was defined only for questions, but FR-010 put it on all fourteen tables and FR-023 made it the idempotency mechanism | One rule: every table hashes its own copied columns; `source_questions` alone uses §16's definition verbatim | FR-010, FR-018 |
| A no-op re-import would have logged zero errors, making the console report a clean bank | `import_errors` is an append-only per-run log; the quality cards read the mirror's columns instead | FR-027, FR-049, FR-051 |
| "The multi-key decision blocks the bank import" would have idled ~14.9 M rows on a meeting | It blocks `answer_key_state`, not the copy: the column stays pending and an idempotent backfill sets it | FR-016, FR-034, FR-061, SC-020 |
| Queue execution left no completion signal for the two headline acceptance tests | Synchronous by default with a real exit code; `--queue` for the behavioural run; one shared implementation | FR-022, FR-029, SC-019 |
| Filament direction is panel-level, so "Arabic RTL console" decided a whole phase's shape | One panel, Arabic and RTL globally; P0's health screen keeps its English operator output inside it | FR-047 |

**What is genuinely blocked, and on whom:**

| Item | Blocks | Owner |
|---|---|---|
| `STUDENT_REF_PEPPER` confirmed stored off Git **and off this machine** | The behavioural import — once ~1.1 M `student_ref` values exist, changing the pepper orphans every row and there is no backup | Operator, before FR-037 |
| The meaning of multi-key (queries 3 + 4) | `answer_key_state`, and therefore `payload_hash` — the bank import | Domain expert, from the profiling output |
| The enrolment table (queries 15 + 16) | P5 and P6 build on the answer; pinned in the docs | Operator, from the profiling output |
| `correct_count = 0` above 2% (query 3) | Would stop P2's dedup track and reopen this feature's remaining scope **before** it is built | Program decision, from the profiling output |

The first is a prerequisite. The other three are outputs of user story US1, which is exactly why US1
is P1-priority and can be delivered alone.

**Gates written, and gates deliberately not written.** Written, because each protects a real
engineering property: no write reaches the source · no PII column exists in the Lab · the ETL is
idempotent · the ETL is resumable · profiling and the mirror agree on the broken-question rate · no
`profile_tables` row is stored. Not written, per the constitution's gate policy: any threshold on the
snapshot's age, any memory number, any mandatory report authoring, documentation review, or handover
sign-off.
