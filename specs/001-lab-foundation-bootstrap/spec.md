# Feature Specification: Lab Foundation Bootstrap (P0 — المراحل 0–2)

**Feature Branch**: `001-lab-foundation-bootstrap`
**Created**: 2026-08-20
**Status**: Draft
**Input**: User description: "read docs/plans/project/1/p0-ai-lab-foundation.md carefully, then start specify first three phases (المرحلة)"

**Implements**: `docs/plans/project/1/p0-ai-lab-foundation.md` (v1.0) — **المرحلة 0** (التمهيد والسلامة), **المرحلة 1** (المستودع وGit), **المرحلة 2** (طبقة البيانات) — which in turn implement §15 of `docs/plans/core/final_injazedu_ai_assessment_engagement_lab_full_plan.md` (v2.0).

**Governing deviations already in force**: ADR-016 (snapshot stays in the native MySQL host), ADR-018 (Lab database on port 5433), ADR-019 (OrbStack as the container engine), ADR-020 (allowlist enforced by grants). ADR-017 (PHP 8.4 / Laravel 13) is inherited context but is exercised in later phases.

---

## Scope of This Increment

This specification covers **only the first three phases** of the P0 plan. Read together, they answer one question: *is this machine, this repository, and this database safe and ready to hold work that touches a production snapshot?*

**In scope**

| Plan phase | Outcome this spec must deliver |
|---|---|
| المرحلة 0 — التمهيد والسلامة | The machine is provably safe to hold the 2.2 GB production snapshot: disk encryption active, repository path not cloud-synced, sufficient free space, snapshot provenance recorded, container engine running. |
| المرحلة 1 — المستودع وGit | A clean repository whose committed boundary is enforced by ignore rules, containing the full P0 directory skeleton, with no real data and no secrets tracked. |
| المرحلة 2 — طبقة البيانات | The Lab database runs on a non-conflicting port under a memory ceiling, provides vector-similarity and trigram-search capability, and survives a restart without losing data. |

**Explicitly out of scope** (later P0 phases or later projects — writing any of this here is a defect per Constitution Principle III):

```text
Read-only snapshot access / the lab_ro grant   ← المرحلة 3
Setting a password on the MySQL root account   ← Item L — optional, ADR-021
Ollama and the two models                      ← المرحلة 4
The Laravel application and Filament panel     ← المرحلة 5
The FastAPI service and the embedding contract ← المرحلة 6
php artisan lab:health and its ten checks      ← المرحلة 7
Guardrail tests / PRODUCTION_WRITE_ENABLED     ← المرحلة 8
Backup script and restore drill                ← المرحلة 9
Memory measurement and the go/no-go decision   ← المرحلة 10
README, runbooks, ADR files, sql/profiling     ← المرحلة 11
Any ETL, data import, or business logic        ← P1 and beyond
```

Where a later phase depends on something created here (an empty directory, an ignore rule, an environment key placeholder), this spec creates the **container only**, never its contents.

---

## Clarifications

### Session 2026-08-20

- Q: Does "first three phases" mean المرحلة 0–2, or should المرحلة 3 (the `lab_ro` grant) be folded in? → A: Option A — keep as specified: المرحلة 0, 1, 2. المرحلة 3 is the next increment.
- Q: What form do the FR-001 / FR-015 / FR-023 verifications take, given the application layer does not exist until المرحلة 5? → A: Option A — standalone executable scripts under `scripts/`, permanent and independent of the later `lab:health` command.
- Q: How is FR-006's "recovery key stored off-machine" attested, given a script cannot verify an off-machine fact? → A: Option C — a committed runbook field holding the key's location and attestation date (never the key); the preflight script fails while that value is still a placeholder.
- Q: What language does operator-facing verification output use, given Constitution VI mandates Arabic only for reviewer- and student-facing UI? → A: Option A — English for all operator/CLI tooling; Arabic stays scoped to reviewer and student surfaces.
- Q: What is the acceptance gate for an increment covering only 3 of P0's 12 phases, given Constitution III requires every plan checkbox? → A: Option C — SC-001…SC-012 gate the increment, plus a committed mapping against the P0 §13 acceptance list showing what is closed, advanced, and untouched.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Make the machine safe to hold the snapshot (Priority: P1)

The lab operator has a full production copy already sitting on this machine — 57,482 user records, roughly 70,000 payment orders, and about 24,408 API access tokens that may still be valid against the live platform. Before doing any further technical work, they need the machine itself to stop being the weak link: the disk encrypted, the working directory outside any cloud-sync folder, enough free space to work in, and the provenance of the snapshot written down so no later report can quietly present stale numbers as current.

**Why this priority**: This is the program's only hard blocker. The governing plan marks it as a barrier — no other phase may resume until encryption has at least begun — and the constitution places data protection above every other principle. It also delivers standalone value: even if nothing else in this feature is built, the existing exposure is closed.

**Independent Test**: Can be fully tested by running the preflight verification on a machine with the snapshot present and confirming it reports every safety condition as satisfied — and by confirming it refuses to pass when encryption is off or the working directory sits inside a synced folder.

**Acceptance Scenarios**:

1. **Given** disk encryption is disabled, **When** the operator runs the preflight verification, **Then** it reports a blocking failure naming encryption, exits non-zero, and states that no subsequent phase may proceed.
2. **Given** disk encryption is enabled or actively in progress, **And** the working directory is not inside a cloud-synced folder, **And** free disk space is at or above the working threshold, **And** the container engine responds, **When** the operator runs the preflight verification, **Then** every condition reports as satisfied and the command exits zero.
3. **Given** the working directory is discovered inside a cloud-synced folder, **When** the preflight verification runs, **Then** it reports a blocking failure naming the specific sync provider and path, and does not silently pass.
4. **Given** preflight has passed, **When** the operator inspects the snapshot provenance record, **Then** it states the snapshot's capture date (2026-08-07), where the snapshot physically lives, that it is outside the repository, and that its refresh policy is still an open decision owned by the team.
5. **Given** the container engine is installed but its daemon is stopped, **When** the preflight verification runs, **Then** it reports that condition distinctly from "not installed" and tells the operator how to proceed.

---

### User Story 2 - Establish a repository that cannot leak (Priority: P2)

The operator needs a working repository whose shape matches the agreed project structure and whose committed boundary is enforced mechanically rather than by memory. Real data, database dumps, environment files, dependency trees, and machine-local noise must be impossible to commit by accident; the directory skeleton that later phases will fill must exist now, so no one improvises a location later.

**Why this priority**: Every later phase writes files into this repository. If the ignore boundary is not in place first, the first accidental commit of a dump or an environment file is a permanent disclosure in history. It is second only to disk encryption because it protects the same data by a different mechanism.

**Independent Test**: Can be fully tested by attempting to stage representative forbidden artefacts — a SQL dump, a compressed dump, an environment file, a dependency directory — and confirming each is refused by the ignore rules, while the example environment template is still committable.

**Acceptance Scenarios**:

1. **Given** the repository exists, **When** the operator inspects its top level, **Then** every directory named in the P0 structure is present, and directories reserved for later projects are present but empty with a placeholder file.
2. **Given** the ignore rules are in place, **When** the operator tests a database dump path, a compressed dump path, an environment file, and a dependency directory against them, **Then** every one is reported as ignored, with the rule that ignored it identifiable.
3. **Given** the ignore rules are in place, **When** the operator tests the example environment template, **Then** it is reported as *not* ignored and can be committed.
4. **Given** machine-local noise files were previously committed, **When** the operator inspects the tracked file list, **Then** those files are no longer tracked and are covered by ignore rules going forward.
5. **Given** the snapshot directory exists in the repository, **When** its contents are inspected at any point during this feature, **Then** it contains nothing but its placeholder file — the snapshot is never copied into the repository.
6. **Given** all repository work is complete, **When** the operator checks repository status, **Then** it is clean, with no untracked or modified files left behind.

---

### User Story 3 - Stand up the Lab's own database (Priority: P3)

The operator needs the Lab's own database available on this machine — separate from, and not disturbing, the unrelated database service already using the conventional port. It must start with one command, stay inside its declared memory ceiling, be reachable only from this machine, offer the vector-similarity and fuzzy-text-matching capabilities that later projects depend on, and keep its data across restarts.

**Why this priority**: It is the first piece of the stack that later phases build on, but it is worthless — and dangerous — if the machine and repository are not yet safe. It is genuinely independent: it can be started, verified, restarted, and verified again without any other phase existing.

**Independent Test**: Can be fully tested by starting the data layer from a clean state, confirming both capabilities are present, writing a marker row, restarting the service, and confirming the marker row survived — all without the application, service, or model layers existing.

**Acceptance Scenarios**:

1. **Given** the container engine is running and the required password is configured, **When** the operator issues the single start command, **Then** the Lab database becomes ready to accept connections and reports healthy.
2. **Given** the Lab database is running, **When** the operator queries its installed capabilities, **Then** both vector-similarity support and trigram text-matching support are reported as present.
3. **Given** the unrelated database service is already running on the conventional port, **When** the Lab database starts, **Then** it binds to its own assigned port and the pre-existing service continues running undisturbed.
4. **Given** the Lab database is running, **When** a connection is attempted from any address other than this machine's loopback interface, **Then** it is refused.
5. **Given** data has been written to the Lab database, **When** the service is stopped and started again, **Then** all previously written data is still present.
6. **Given** the required password is absent from the environment, **When** the operator issues the start command, **Then** startup fails with a message naming the missing key rather than starting with a default or empty password.
7. **Given** the Lab database is under load, **When** its memory use is observed, **Then** it stays within its declared ceiling.

---

### Edge Cases

- **Encryption started but not finished.** Encryption reported as "in progress" satisfies the barrier; the operator may proceed, but the provenance record must note that encryption was still converting at the time.
- **Recovery key never stored off-machine.** Encryption alone is not sufficient — an unrecoverable encrypted disk destroys the review decisions the constitution calls the program's most valuable data. Preflight must fail while the custody record is an unedited placeholder, so "encrypted but unrecoverable" cannot pass as safe.
- **Recovery key pasted into the custody record.** The record names a location, never a secret. A value that looks like key material rather than a location is a defect, and the record's own instructions must say so at the point of editing.
- **Working directory is inside a synced folder.** Either remediation is acceptable — relocating the repository, or excluding the path from sync — but the condition must be re-verified after remediation, not assumed fixed.
- **Free space falls below threshold mid-encryption.** Encryption needs headroom. Preflight must check free space *before* declaring the machine ready, and re-check if it is re-run after remediation.
- **Assigned Lab port is also occupied.** The conflict must surface as a named error identifying the occupying process, not as a silent failure or a fallback to another port.
- **Initialization only applies to a brand-new data volume.** Capability setup runs once, when the data volume is first created. If capabilities are later added to the setup file, an existing volume will not pick them up. Verification must therefore test the *live* database's capabilities, never the setup file's contents, and the discrepancy must be documented as a known trap.
- **Container engine unavailable at boot.** Restart persistence must hold across a full machine restart, not only across a service restart.
- **Repository already initialized.** The repository already exists with history and tracked files. Repository work must be idempotent and additive: it must not re-initialize, must not rewrite history, and must correct already-tracked noise files going forward rather than retroactively.
- **A forbidden artefact was already committed before ignore rules existed.** Ignore rules do not remove history. Any such discovery must be reported explicitly as a finding, and its remediation treated as a decision for the operator, not silently absorbed.
- **Snapshot copied into the repository by a later contributor.** The snapshot directory must remain empty by design and be covered by ignore rules, so a copy would be un-committable even if made.

---

## Requirements *(mandatory)*

### Functional Requirements

**Safety preflight (المرحلة 0)**

- **FR-001**: The system MUST provide a single repeatable **executable verification script** (under `scripts/`, runnable without any application layer) that reports the machine's readiness to hold the production snapshot, covering disk encryption state, cloud-sync exposure of the working directory, free disk space, and container engine availability.
- **FR-002**: The verification MUST treat disk encryption as a blocking condition: encryption reported as enabled, or as actively converting, passes; any other state fails and the verification MUST state that no subsequent phase may proceed.
- **FR-003**: The verification MUST determine whether the repository's path lies inside a folder managed by a cloud-sync provider and MUST fail, naming the provider and path, when it does.
- **FR-004**: The verification MUST report free disk space and MUST fail when it is below the declared working threshold.
- **FR-005**: The verification MUST confirm the container engine is installed *and* responding, reporting "not installed" and "installed but not responding" as distinguishable outcomes.
- **FR-006**: A committed runbook document MUST hold a recovery-key custody record stating **where** the disk-encryption recovery key is stored off this machine and the **date** that was attested. It MUST NOT contain the key, or any value from which the key could be derived. The record is committed deliberately: it must survive the loss of the machine it describes, which is the only circumstance the recovery key exists for.
- **FR-006a**: The verification MUST read that record and MUST fail, as a blocking condition, while the location value is absent or still an unedited placeholder — so an unattested machine cannot pass preflight.
- **FR-007**: The system MUST record the snapshot's provenance in a durable, committed document stating its capture date (2026-08-07), its physical location outside the repository, the fact that it is never copied into the repository, and that its refresh cadence is an open team decision.
- **FR-008**: The verification MUST exit with a non-zero status when any condition fails, and MUST report each condition individually rather than as a single aggregate pass/fail.

**Repository boundary (المرحلة 1)**

- **FR-009**: The repository MUST contain every directory named in the P0 project structure, with directories reserved for later projects present but empty apart from a placeholder file.
- **FR-010**: The repository MUST refuse, by ignore rules, to track: environment files, database dumps in plain or compressed form, dependency directories, generated storage contents, application logs, and operating-system noise files.
- **FR-011**: The repository MUST permit tracking of the example environment template, which lists every required key with no values.
- **FR-012**: The snapshot directory inside the repository MUST remain empty by design, retaining only its placeholder file, and MUST be covered by ignore rules so that adding real data to it cannot be committed.
- **FR-013**: Any operating-system noise file already tracked in the repository MUST be untracked, and MUST be covered by ignore rules going forward.
- **FR-014**: Repository setup MUST be idempotent and additive — safe to run against the existing repository without re-initializing it or rewriting its history.
- **FR-015**: The system MUST provide an **executable check script** that verifies the ignore boundary by testing representative forbidden and permitted paths, reporting the outcome for each.

**Lab data layer (المرحلة 2)**

- **FR-016**: The Lab database MUST start from a single declarative command and reach a ready state without further manual steps.
- **FR-017**: The Lab database MUST listen on its own assigned port (5433, per ADR-018) and MUST NOT disturb the unrelated database service already occupying the conventional port on this machine.
- **FR-018**: The Lab database MUST be reachable only from this machine's loopback interface and MUST NOT be exposed to any network interface.
- **FR-019**: The Lab database MUST provide vector-similarity capability and trigram text-matching capability, both verifiable by querying the running database itself rather than by reading its setup file.
- **FR-020**: The Lab database MUST retain all written data across a service restart and across a full machine restart, via storage that is independent of the container's lifetime.
- **FR-021**: The Lab database MUST operate within a declared memory ceiling (1536 MB, per §12.3) and MUST have its working-memory settings pinned explicitly rather than left at defaults.
- **FR-022**: The Lab database's credentials MUST be supplied from the environment file and MUST NOT appear anywhere in tracked files; startup MUST fail with a message naming the missing key rather than falling back to a default or empty password.
- **FR-023**: The system MUST provide an **executable verification script** that reports Lab database readiness, both capabilities, the bound address and port, and data survival across a restart.

**Traceability and cross-cutting**

- **FR-024**: Each deliverable in this increment MUST be traceable to the plan phase it implements, and any deviation from the plan MUST be recorded as a numbered ADR before the corresponding work is done.
- **FR-025**: The increment MUST NOT create, connect to, or read from the production snapshot, nor create any database account against it — that access is defined in a later phase.
- **FR-026**: The increment MUST NOT contain business logic, data transformation, or application/service/model code of any kind.
- **FR-027**: The three verification scripts MUST be permanent and MUST remain runnable after the later consolidated health command exists. They cover a set of concerns disjoint from it — disk encryption, cloud-sync exposure, free space, recovery-key custody, and the repository ignore boundary are never checked by connection-level health checks — so they MUST NOT be written as scaffolding to be discarded.
- **FR-029**: This increment is accepted when SC-001…SC-012 all pass. It MUST additionally carry a committed mapping against the P0 §13 acceptance list (see *Acceptance Gate* below), recording for every one of its 19 checkboxes whether this increment closes it, partially advances it, leaves it untouched, or renders it only vacuously true. Any claim of P0 completion based on this increment alone MUST be reported as partial.
- **FR-028**: All operator-facing output produced by this increment — verification results, failure messages, and the runbook documents created here — MUST be in English. Arabic remains reserved for reviewer- and student-facing surfaces under Constitution VI; this increment creates no such surface. This sets the precedent for the later consolidated health command's output.

### Key Entities

- **Machine safety state**: The set of conditions that make this machine fit to hold the snapshot — encryption state, recovery-key custody, sync exposure of the working path, free space, container engine availability. Evaluated as a whole; a single failure blocks the increment.
- **Production snapshot**: A read-only copy of the platform database, already present on this machine, outside the repository. Characterised by its capture date, its size, its physical location, and the sensitive tables it contains. Referenced but never accessed in this increment.
- **Snapshot provenance record**: The committed document naming the snapshot's capture date, location, containment rule, and open refresh decision. It is the source that later reports cite when they stamp their numbers.
- **Recovery-key custody record**: A committed field naming where the disk-encryption recovery key is held off this machine, and when that was attested. Holds a location and a date only — never key material. Read by the preflight verification as a blocking condition.
- **Repository boundary**: The rule set determining what may enter version history. Relates the project structure (what exists) to the ignore rules (what may be committed).
- **Lab database**: The Lab's own store, distinct from the production snapshot and from the unrelated pre-existing database service. Characterised by its assigned port, memory ceiling, loopback-only binding, persistent storage, and its two required capabilities.
- **Lab database capabilities**: Vector-similarity support and trigram text-matching support — installed at first initialization, required by later projects (P2's duplicate-detection cascade), and verifiable only against the running database.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The safety verification reports every machine-readiness condition individually and completes in under 30 seconds.
- **SC-002**: With disk encryption disabled, the safety verification fails and blocks — verified by observing a non-zero exit and a message naming encryption as the blocker.
- **SC-003**: 100% of the forbidden artefact categories (environment files, plain dumps, compressed dumps, dependency directories, generated storage, logs, OS noise) are refused by the repository boundary, and the example environment template is accepted — proven by a check that tests each category individually.
- **SC-004**: Zero real data files and zero secret values exist anywhere in the repository's tracked content at the end of this increment.
- **SC-005**: The Lab database goes from stopped to ready with a single command in under 60 seconds on a warm data volume.
- **SC-006**: Both required database capabilities are present when queried against the running Lab database — exactly two, no fewer.
- **SC-007**: Data written to the Lab database survives a service restart and a full machine restart with zero rows lost, verified by writing a marker row and re-reading it after each restart.
- **SC-008**: The pre-existing database service on the conventional port continues running and serving its other consumers throughout, with zero interruptions attributable to this increment.
- **SC-009**: The Lab database's observed memory use stays at or below its declared ceiling during idle and during a write.
- **SC-010**: The Lab database refuses every connection attempt originating from a non-loopback address — verified by at least one such attempt.
- **SC-011**: An operator following the phase instructions on this machine completes all three phases without needing an undocumented decision — every choice they face is either specified here or explicitly listed as a human decision item.
- **SC-012**: Zero rows are read from, and zero statements are written to, the production snapshot during this increment.

---

### Acceptance Gate — Mapping to P0 §13

This increment is accepted when **SC-001…SC-012 all pass**. The table below maps that against the
P0 plan's §13 acceptance list so the eventual P0 sign-off inherits a running tally rather than a
re-derivation. Status meanings:

- **Closed** — this increment fully satisfies the checkbox; it needs no later re-proof.
- **Advanced** — partially satisfied; the remainder belongs to a later phase.
- **Vacuous** — technically true right now only because the thing it constrains does not yet
  exist. Recorded, but **not** counted as proof; it becomes testable in a later phase.
- **Untouched** — owned entirely by a later phase.

| # | §13 checkbox (abridged) | Status | Owned by |
|---|---|---|---|
| 1 | Disk encryption enabled, recovery key held off-machine | **Closed** | المرحلة 0 |
| 2 | Stack starts predictably with one command | **Advanced** — data layer only | المرحلة 2 → 5, 6 |
| 3 | Application creates a job and the worker runs it | Untouched | المرحلة 5 |
| 4 | Service calls the model runtime and returns valid JSON | Untouched | المرحلة 6 |
| 5 | 768-dim embedding with correct prefix stored and retrieved | Untouched | المرحلة 6, 7 |
| 6 | Every vector carries `embedding_config_version` | Untouched | المرحلة 6 |
| 7 | Restart does not lose Lab database data | **Closed** | المرحلة 2 |
| 8 | Snapshot reads succeed, writes fail (test expects failure) | Untouched | المرحلة 3, 7 |
| 9 | PII tables invisible to `lab_ro` across the full forbidden list | Untouched | المرحلة 3, 8 |
| 10 | Consolidated health command passes ten checks, non-zero exit on failure | Untouched | المرحلة 7 |
| 11 | `PRODUCTION_WRITE_ENABLED=false` present in env and template | Untouched | المرحلة 8 |
| 12 | Lab connects to the snapshot only as `lab_ro`; no admin account in any config | **Vacuous** — no snapshot credential exists yet | المرحلة 3 |
| 13 | The service holds no snapshot credentials | **Vacuous** — the service does not exist yet | المرحلة 6 |
| 14 | A backup was taken **and actually restored** at least once | Untouched | المرحلة 9 |
| 15 | Memory budget measured, recorded, decision written | **Advanced** — the data layer's ceiling is declared and verified (SC-009); the full-stack measurement is later | المرحلة 2 → 10 |
| 16 | ADR-016…ADR-020 written | **Advanced** — ADR-018 and ADR-019 are in force and relied on here; the files are authored later | المرحلة 11 |
| 17 | §6 profiling pack written, not executed | Untouched | المرحلة 11 |
| 18 | README executed on a clean directory reaches a green health check | Untouched | المرحلة 11 |
| 19 | Not one line written to production or to the local snapshot | **Closed** for this increment (SC-012); remains a standing constraint | all phases |

**Tally: 3 closed · 3 advanced · 2 vacuous · 11 untouched.** Reporting this increment as
"P0 complete" would be a constitutional violation, not a rounding error.

**Observed results (verified 2026-08-21, this machine):**

- Checkbox 1 — `fdesetup status` reports `FileVault is On.`; recovery key attested off-machine in
  `docs/runbooks/safety.md` (attested 2026-08-20). `scripts/preflight-check.sh` exits `0` with all
  five conditions individually `[ OK ]` (SC-001).
- Checkbox 2 — `docker compose up -d postgres` brings the data layer ready in 0 s on a warm volume
  (threshold 60 s, SC-005); both capabilities present, exactly 2 rows (pg_trgm 1.6, vector 0.8.6,
  SC-006). Application/worker layers remain with المرحلة 5 and 6.
- Checkbox 7 — marker row `persistence probe` (written 2026-08-20 23:23:51 UTC) survived a service
  restart via `scripts/verify-data-layer.sh --with-restart`. **Second half (full machine restart,
  SC-007) pending operator reboot — see tasks.md T035.**
- Checkbox 15 — memory ceiling read back from the running container: limit 1536 MiB, in use
  46.14 MiB (SC-009). Full-stack measurement remains with المرحلة 10.
- Checkbox 16 — `docs/ADR/ADR-018.md` (port 5433) and `docs/ADR/ADR-019.md` (OrbStack) authored and
  in force; ADR-016, ADR-017, ADR-020 remain with their owning phases.
- Checkbox 19 — zero rows read from, zero statements written to the production snapshot (SC-012);
  loopback-only binding verified by a refused non-loopback connection attempt (SC-010);
  `postgresql@14` on 5432 undisturbed throughout (SC-008).
- SC-003/SC-004 — `scripts/verify-repo-boundary.sh` passes all 13 assertions including the inverted
  `.env.example` case; inspection confirms zero real data files and zero secret values in tracked
  content.
- **Increment status: partial P0 — 3 closed · 3 advanced · 2 vacuous · 11 untouched** (FR-029).

## Assumptions

**Scope reading**

- **Confirmed 2026-08-20**: this increment is **المرحلة 0, 1, and 2** of the P0 plan. The boundary is deliberate — these three phases touch no production data and create no credential, so the safety foundation is accepted and proven *before* any account exists against the snapshot. المرحلة 3 (read-only snapshot access and the `lab_ro` grant) is the next increment, and it carries **no unmet human prerequisite**: Item L (setting a password on the native database's administrative account) was resolved as **optional** by ADR-021 on 2026-08-21 — the account stays password-less and the residual risk is accepted there.

**Environment (measured 2026-08-18, per §2 of the P0 plan)**

- The target machine is the single 16 GB Apple M1 Pro described in the plan, with ~139 GB free; the free-space working threshold is taken as **20 GB**, comfortably above encryption headroom and below what is measured available.
- The production snapshot (~2.2 GB, captured 2026-08-07) is already loaded in the machine's native database service and is not re-created, re-imported, or moved by this increment.
- An unrelated database service already occupies the conventional port 5432 and serves other projects on this machine; it is treated as untouchable.
- A cloud-sync client is running on the machine, which is why sync exposure is checked rather than assumed absent.
- The container engine is installed but its daemon may be stopped; starting it is part of this increment, installing it is not.

**Repository**

- The repository already exists with commit history and tracked files, including at least one OS noise file. Repository work is therefore corrective and additive, never a fresh initialization.
- Correcting an already-tracked noise file means untracking it going forward; rewriting existing history is out of scope and would be a separate, explicitly requested decision.
- Existing `docs/`, `.specify/`, and agent-configuration directories are retained as-is; the P0 structure is added around them.

**Lab database**

- Version and image selection, the 5433 port, and the 1536 MB ceiling are inherited constraints from ADR-018 and §12.3 of the governing plan, not choices made by this specification.
- The two required capabilities are installed at first volume initialization only; verification consequently targets the running database.
- The Lab database password is generated locally by the operator and lives only in the untracked environment file.

**Boundaries**

- Human decision items from §8 of the P0 plan that fall inside these phases — enabling encryption and storing its recovery key (Item A), confirming the working directory is unsynced (Item B) — are prerequisites the operator performs; this increment verifies and records them, it does not perform them.
- Item L (a password on the native database's administrative account) was **resolved as optional** by ADR-021 — the account stays password-less, the compensating controls are unchanged, and المرحلة 3 is not blocked by it. This increment records that decision; it changes nothing it built.
- Human decision items scheduled for later phases — the language-runtime linking decision (Item D), snapshot refresh cadence (Item E) — remain open and are explicitly not resolved here.
- Nothing in this increment reads the production snapshot. The first read is defined in المرحلة 3.

## Dependencies

- **Blocking human prerequisite**: disk encryption must be enabled (or converting) with its recovery key stored off-machine before any other work in this increment proceeds — §8 Item A of the P0 plan, and the non-waivable data-protection section of the constitution.
- **Machine prerequisite**: a working container engine on the local machine.
- **Governance prerequisite**: ADR-018 and ADR-019 must be in force before the data layer is stood up; the ADR files themselves are authored in المرحلة 11, but the decisions they record are binding from now.

## Handoff to the Next Increment (المرحلة 3)

On completion, the next increment inherits:

```text
A machine verified safe to hold the snapshot, with provenance recorded
A repository whose committed boundary is mechanically enforced
An empty, ignore-protected snapshot directory that stays empty
A running Lab database on 5433 with both required capabilities, surviving restarts
An environment template whose keys later phases fill in
```

Still open when this increment ends, by design: no account exists against the production snapshot, no allowlist grant has been issued, the administrative account of the native database service has no password — deliberately, and permanently unless a re-evaluation trigger fires (Item L is optional under ADR-021, so it does not block المرحلة 3) — and the snapshot refresh cadence is undecided (Item E, due before P1).
