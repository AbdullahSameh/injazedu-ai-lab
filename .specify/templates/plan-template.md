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

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

*Source: `.specify/memory/constitution.md` v1.0.0. Mark each gate PASS / FAIL / N-A with
one line of evidence. Any FAIL must be removed or justified in Complexity Tracking.*

| # | Gate | Principle | Status |
|---|------|-----------|--------|
| 1 | Feature names the process it improves and which layer (code / SQL / embeddings / LLM / human) does each part; no AI action outside suggest-classify-rank-flag-draft-explain; no source question deleted | I | |
| 2 | Spec cites the governing plan section; every schema fact traced to `docs/schema/injazedu-db-schema.md` or a profiling result; any deviation has a numbered ADR in `docs/ADR/` | II | |
| 3 | Belongs to exactly one active project (P0…P9); declared dependencies accepted; nothing from another project's scope; Go/No-Go limits stated | III | |
| 4 | Laravel owns migrations, FastAPI stateless; metrics computed deterministically (LLM explains, never computes); AI output schema-validated; prompts versioned; `embedding_config_version` set; jobs idempotent; anomalies recorded not swallowed | IV | |
| 5 | Tests limited to: deterministic unit tests, `lab:health` integration checks (incl. the two inverted checks), guardrail/PII tests, golden eval sets. No coverage targets or e2e suites | V | |
| 6 | Arabic RTL; every metric shows `n` and `snapshot_taken_at`; suppression thresholds (n<10 / n<30 / n≥30) applied; AI output labelled as recommendation; human override recorded; queues ordered by priority | VI | |
| 7 | Fits the ~11–13 GB memory budget; LLM calls confined to the uncertainty band with a counter and a cap; cheap layers run first; model/dimension choices backed by a recorded benchmark; batches resumable | VII | |
| 8 | **Non-waivable:** Production read-only; `lab_ro` grants limited to the 11 allowed tables; no PII in the Lab DB (`student_ref` HMAC only); `PRODUCTION_WRITE_ENABLED=false`; snapshot handling and backup rules honoured | Data Protection | |

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

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

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
