# Specification Quality Checklist: Handover & P1 Readiness (P0 — المرحلة 10 مختصرة + المرحلة 11)

**Purpose**: Validate specification completeness and quality before planning
**Created**: 2026-08-23 · **Re-validated**: 2026-08-23 after the operator's re-scope
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

**This spec was re-scoped on 2026-08-23.** The first draft covered المراحل 9–11: a nightly backup, a
restore drill, a memory measurement script with a dual go/no-go gate, and the handover. The operator
cancelled the first two outright. What replaced them is smaller and points forward.

Decisions taken under **Constitution Principle I**, all on 2026-08-23:

| Decision | Effect on this spec |
|---|---|
| Backup and restore removed from the program entirely | Twelve requirements and one user story deleted. The Lab database is 8.4 MB and reproducible from `migrate` + `lab:health`; the snapshot is disposable. Reviewer decisions are the one irreproducible artefact and do not exist until P2 — a go-live concern, not a local one. |
| No memory gate, manual steps only | Six requirements became one (FR-014). The measured stack is 5,132 MiB with ~90% of it the two models, and the old whole-machine ceiling moved with browser tabs — no P0 remedy could have fixed a breach of it. |
| The allowlist splits into copy and profile | Six new requirements (FR-001…FR-006) and a new P1-priority user story. Unblocks §6 queries 15, 16 and 18, which resolve how enrolment is recorded — work P1 cannot start without. |
| Document edits reach the constitution and core plan | Constitution → v2.1.0; core plan §§6, 12.3, 14.2, 14.6, 15; P0 plan throughout; ADR-021 revised, ADR-018 and ADR-019 re-verified. |

One item remains open **by design** and owed before P1, not before this increment: the snapshot
refresh policy (§8 item E), recorded rather than decided (FR-015).

Content-quality note: this spec names ports, paths, and tool versions. That is deliberate and matches
001–003 — the "system" here **is** local infrastructure, so a measured port conflict or a
client/server version gap is a requirement, not an implementation detail.
