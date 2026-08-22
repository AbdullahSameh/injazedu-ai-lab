# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]
**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: [e.g., Python 3.11, Swift 5.9, Rust 1.75 or NEEDS CLARIFICATION]  
**Primary Dependencies**: [e.g., FastAPI, UIKit, LLVM or NEEDS CLARIFICATION]  
**Storage**: [if applicable, e.g., PostgreSQL, CoreData, files or N/A]  
**Testing**: [e.g., pytest, XCTest, cargo test or NEEDS CLARIFICATION]  
**Target Platform**: [e.g., Linux server, iOS 15+, WASM or NEEDS CLARIFICATION]
**Project Type**: [e.g., library/cli/web-service/mobile-app/compiler/desktop-app or NEEDS CLARIFICATION]  
**Performance Goals**: [domain-specific, e.g., 1000 req/s, 10k lines/sec, 60 fps or NEEDS CLARIFICATION]  
**Constraints**: [domain-specific, e.g., <200ms p95, <100MB memory, offline-capable or NEEDS CLARIFICATION]  
**Scale/Scope**: [domain-specific, e.g., 10k users, 1M LOC, 50 screens or NEEDS CLARIFICATION]

## Checks Before Building

*Confirm each before Phase 0, and again after design. One line of evidence each; N/A is a valid
answer. Source: `.specify/memory/constitution.md`.*

- [ ] **Nothing decided that needed approval** — no architecture, infrastructure, security,
      database, dependency, or workflow decision was made that the repo or the operator had not
      already settled (Principle I). Open questions are listed, not resolved unilaterally.
- [ ] **Read-only toward InjazEdu MySQL** — this increment reads and copies; it never writes.
- [ ] **No PII into the Lab** — nothing outside the copy allowlist; `user_id` never stored.
- [ ] **Laravel owns migrations**; metrics computed deterministically; AI output schema-validated.
- [ ] **Tests are the targeted kind** — deterministic units, health check, guardrails, evals.
- [ ] **Fits the ~11–13 GB budget**; cheap layers before the LLM.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── tasks.md             # Phase 2 output (/speckit-tasks — NOT created by /speckit-plan)
│
│  Optional — write these ONLY when they change what gets built:
├── research.md          # measured findings that alter the design
├── data-model.md        # entities worth pinning down before code
├── quickstart.md        # the runnable path from clean checkout to green
└── contracts/           # interface contracts a second party depends on
```

Do not create an artefact because this tree lists it. An empty or restating document is worse than
no document.

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```text
# [REMOVE IF UNUSED] Option 1: Single project (DEFAULT)
src/
├── models/
├── services/
├── cli/
└── lib/

tests/
├── contract/
├── integration/
└── unit/

# [REMOVE IF UNUSED] Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── models/
│   ├── services/
│   └── api/
└── tests/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   └── services/
└── tests/

# [REMOVE IF UNUSED] Option 3: Mobile + API (when "iOS/Android" detected)
api/
└── [same as backend above]

ios/ or android/
└── [platform-specific structure: feature modules, UI flows, platform tests]
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Open Questions

> **Fill ONLY if something needs the operator's decision before this can be built.**
> Per Principle I: state the problem, the options, the trade-off, and your recommendation — then stop.

| Question | Options | Recommendation |
|---|---|---|
| | | |
