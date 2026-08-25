# Phase 0 — Research

**Feature**: Lab Foundation Bootstrap (P0 — المراحل 0–2)
**Branch**: `001-lab-foundation-bootstrap`
**Date**: 2026-08-20
**Method**: All findings below were **measured on the target machine on 2026-08-20**, not carried
forward from the P0 plan's 2026-08-18 survey. Where the two disagree, the measurement wins
(Constitution Principle II).

---

## Environment re-measurement (2026-08-20)

| Fact | P0 plan said (2026-08-18) | Measured now | Delta |
|---|---|---|---|
| FileVault | Disabled — Item A blocker | **`FileVault is Off.`** | Unchanged — **still blocking** |
| Free disk | 139 GB | **135 GB** on `/` | −4 GB, still far above threshold |
| Container engine | OrbStack installed, daemon stopped | Installed (`/Applications/OrbStack.app`, `orb`, `orbctl`); **daemon still stopped** — socket absent | Unchanged |
| Port 5432 | `postgresql@14` running | Confirmed: PID 1984, `/opt/homebrew/opt/postgresql@14/bin/postgres` | Unchanged — ADR-018 stands |
| Port 5433 | assumed free | **Confirmed FREE** | Newly verified |
| Port 3306 | MySQL on 127.0.0.1 | Confirmed: `mysqld` PID 2547, `127.0.0.1:3306` | Unchanged |
| Repo path | `~/Projects` not synced | `/Users/abdullah/Projects/injazedu-ai-lab`, real path, no sync xattrs, no flags | Confirmed safe |
| Sync clients | OneDrive running | OneDrive **and** iCloud Drive both running | **iCloud was not in the plan's survey** |

---

## R1 — Cloud-sync detection is harder than "look for a OneDrive folder"

**Decision**: FR-003's check must test the repository's **real path** against a set of known sync
roots that includes iCloud's *relocated* Desktop and Documents, not just literal `~/OneDrive`.

**Rationale**: The measurement turned up something the P0 plan's survey missed. `~/OneDrive` does
not exist and `~/Library/CloudStorage/` is empty — so a naive check finds nothing and reports
"safe". But **iCloud "Desktop & Documents Folders" sync is switched on**, confirmed by the presence
of `~/Library/Mobile Documents/com~apple~CloudDocs/Desktop`. When that setting is on, macOS
*relocates* `~/Desktop` and `~/Documents` into the iCloud container. The repository is safe only
because it sits in `~/Projects`, which is outside both.

This is a live near-miss, not a hypothetical: had the repository been created in `~/Documents` —
an entirely ordinary choice — the 2.2 GB production snapshot's sibling working files would be
uploading to iCloud right now, and the naive check would have passed.

**Alternatives considered**:
- *Check for `~/OneDrive` only* — rejected; would have returned a false "safe" on this exact machine.
- *Check `xattr` sync markers on the repo* — rejected as the sole method; markers appear on synced
  *content*, so an empty or newly created directory can carry none while still sitting inside a
  sync root. Useful as a secondary signal, not a primary one.
- *Ask the operator* — rejected; FR-003 is meant to catch the case where the operator does not know.

**Implementation consequence**: resolve the repo to its real path, then test whether it is a
descendant of any of: `~/Library/Mobile Documents/`, `~/Library/CloudStorage/`, `~/OneDrive*`,
`~/Dropbox*`, and — when D&D sync is on — `~/Desktop` and `~/Documents`. Report the matched root.

---

## R2 — Target bash 3.2, not bash 5

**Decision**: All scripts target **bash 3.2** and are invoked with an explicit `#!/bin/bash`.

**Rationale**: Measured `/bin/bash` is **3.2.57**, and there is no Homebrew bash on this machine
(`which -a bash` returns only `/bin/bash`). Apple has shipped 3.2 since 2007 for licensing reasons.
Writing bash 4+ syntax would fail at runtime on the one machine this feature targets.

Concretely forbidden: associative arrays (`declare -A`), `mapfile`/`readarray`, `${var,,}` case
conversion, `&>>`, and `${!prefix@}` expansions. Parallel indexed arrays and `tr` cover every case
these scripts need.

**Alternatives considered**:
- *Require Homebrew bash 5* — rejected. Constitution Principle IV: services and dependencies are
  added only when justified **today**. A new dependency to gain syntax sugar is not justified, and
  it would add an install step to a preflight script whose entire job is to run before anything
  else is installed.
- *Write POSIX `sh`* — rejected as unnecessary; bash 3.2 is guaranteed present and `[[ ]]` plus
  `local` materially improve readability of the check logic.

---

## R3 — Run database verification inside the container, never with the host client

**Decision**: FR-019 and FR-023 verification queries run via `docker compose exec -T postgres psql`,
using the container's own client.

**Rationale**: The host client is **psql 14.18** while the target server is **17**. The P0 plan
already caught the fatal half of this — `pg_dump` 14 refuses to talk to a server 17 — and routed
المرحلة 9 backups through the container. The same reasoning applies earlier than the plan noticed:
`psql` 14 will *connect* to a server 17 and run plain SQL, but it emits a major-version warning and
its meta-commands (`\d`, `\dx`) query system catalogs whose shape changed. A verification script
that shells out to `\dx` to prove the extensions exist is exactly the kind of check that fails for
a reason unrelated to what it is testing.

Executing inside the container makes client and server the same build by construction, and removes
the host's psql from the dependency set entirely.

**Alternatives considered**:
- *Install `postgresql@17` client via Homebrew* — rejected for now. The plan itself warns about link
  conflicts with the existing `postgresql@14` that serves other projects, and المرحلة 2 has no need
  for a host client at all. Revisit at المرحلة 9 if the container route proves awkward for backups.
- *Use plain `SELECT` through psql 14 and avoid meta-commands* — workable but fragile; it relies on
  a discipline the next contributor cannot see. The container route makes the constraint structural.

**Implementation consequence**: FR-019's proof is `SELECT extname, extversion FROM pg_extension
WHERE extname IN ('vector','pg_trgm')` expecting exactly 2 rows — a catalog query, not a
meta-command — executed in-container.

---

## R4 — `mem_limit` must be verified, not assumed

**Decision**: The data-layer verification asserts the **observed** container memory limit, rather
than trusting that the compose key took effect.

**Rationale**: Measured Compose version is **v5.1.2**. `mem_limit` is a legacy top-level service key
carried over from the v2 compose file format; the modern Compose Specification expresses the same
thing as `deploy.resources.limits.memory`. Docker Compose still honours `mem_limit` for
non-swarm runs, but it is precisely the class of key that gets silently ignored across a version
bump — and a silently ignored memory ceiling produces a green SC-009 while the constitution's
Principle VII budget is being exceeded.

Since SC-009 is the only thing standing between this increment and an unbudgeted stack, the check
reads the limit back from the running container rather than from the file that requested it.

**Alternatives considered**:
- *Trust the compose file* — rejected; this is the "silent failure" category Principle IV exists to
  make loud.
- *Switch to `deploy.resources.limits.memory`* — deliberately **not** done as a blind swap. The P0
  plan specifies `mem_limit`, so changing it is a plan deviation requiring an ADR. The verification
  will establish empirically whether `mem_limit` is honoured on this Compose version; if it is not,
  that measurement becomes the justification for the deviation, which is the correct order.

---

## R5 — Readiness means `pg_isready` plus a real query, not "container started"

**Decision**: FR-016's "ready" is defined as: container reports healthy **and** a trivial SQL query
returns successfully.

**Rationale**: The official Postgres image starts, runs `/docker-entrypoint-initdb.d/` scripts, then
*restarts* the server. A container that is "running" and even briefly accepting connections may still
be mid-initialization. Treating "container up" as ready produces an intermittently failing SC-005
and — worse — a first-run race where the extension check runs before `init.sql` has finished.

**Alternatives considered**:
- *Fixed `sleep`* — rejected; either too slow or flaky, and unmeasurable against SC-005's 60 s bound.
- *Container `healthcheck` only* — good, and it will be used, but a healthcheck proves the server
  answers; it does not prove initialization completed. Both, in order.

---

## R6 — Initialization scripts run **once**, so verify the live database

**Decision**: Restated here because it drives task ordering: `init.sql` executes only when the data
volume is first created. Extension verification therefore always targets the running database.

**Rationale**: This is the trap the spec's edge-case list already names, and it is the single most
likely way this increment produces a false green months from now: someone adds a third extension to
`init.sql`, the existing volume ignores it, and a file-reading check reports success. Documented in
the runbook, and encoded in FR-019's wording ("verifiable by querying the running database itself").

---

## R7 — Ignore-boundary proof uses `git check-ignore -v`

**Decision**: FR-015's check runs `git check-ignore -v <path>` per representative artefact and
asserts on exit status, capturing the matching rule.

**Rationale**: `check-ignore` is the only method that reports *which* rule matched, which turns a
failure from "something is wrong with .gitignore" into "line 12 of .gitignore matched, but line 7
was supposed to". Its exit codes are unambiguous — `0` ignored, `1` not ignored — which is exactly
the assertion shape needed, including for the inverted case where `.env.example` **must** return 1.

`check-ignore` operates on paths, not on files that exist, so the check needs no temporary files
and leaves no residue in the working tree.

**Alternatives considered**:
- *Create real files, `git add`, inspect status, delete* — rejected; it mutates the index and risks
  leaving a real forbidden file behind if the script dies mid-run. For a check whose purpose is
  preventing data from entering the repo, that failure mode is unacceptable.

---

## R8 — History is clean; FR-013 is the only corrective work

**Finding**: A scan of every path ever added across all refs returns no `.env`, no `.sql`/`.gz`/
`.dump`, and no `vendor/`, `node_modules/`, or `.venv/`. The sole tracked artefact needing
correction is **`.DS_Store`**.

**Consequence**: FR-013 is a one-line `git rm --cached`, not a history rewrite. The spec's
"forbidden artefact already committed" edge case is precautionary and needs no task. Confirming this
before planning avoided provisioning work for a problem that does not exist.

---

## R9 — ADR-018 and ADR-019 must be authored **in this increment**

**Decision**: Author `docs/ADR/ADR-018.md` (Lab database on port 5433) and `docs/ADR/ADR-019.md`
(OrbStack as container engine) as part of المرحلة 2 — not deferred to المرحلة 11 as the P0 plan
schedules.

**Rationale**: This is a direct conflict between the P0 plan and the constitution, and the
constitution resolves it. Principle II: *"Any deviation from a governing plan section MUST be
recorded as a numbered ADR in `docs/ADR/` **before the code is written**."* المرحلة 2 is the code
that acts on both decisions — it binds to 5433 *because of* ADR-018 and runs under OrbStack
*because of* ADR-019. Writing the compose file first and the ADRs nine phases later inverts the
required order.

The governance section is explicit that a plan/constitution conflict "MUST be resolved explicitly —
either the plan is amended or an ADR records the exception — never resolved silently in code."

**Scope discipline**: only the two ADRs this increment actually acts on. ADR-016 (snapshot stays in
the native MySQL host) and ADR-020 (grant-enforced allowlist) belong to المرحلة 3; ADR-017 (PHP 8.4 /
Laravel 13) belongs to المرحلة 5. Authoring those here would be exactly the scope creep Principle III
forbids.

**Alternatives considered**:
- *Follow the plan and defer all five to المرحلة 11* — rejected; violates Principle II on the two
  ADRs being acted upon now.
- *Author all five now* — rejected; violates Principle III, and three of them document decisions
  this increment does not make.

---

## Resolved unknowns

Every `NEEDS CLARIFICATION` from Technical Context is resolved above: sync detection (R1), shell
target (R2), database client version (R3), memory-limit enforcement (R4), readiness definition (R5),
initialization semantics (R6), ignore-check mechanics (R7), history state (R8), ADR timing (R9).

**None remain open.**
