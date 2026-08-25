# Phase 1 — Data Model

**Feature**: Lab Foundation Bootstrap (P0 — المراحل 0–2)
**Date**: 2026-08-20

This increment creates almost no persistent data. What it does create is **configuration, attested
records, and one marker row** — so this document models those, plus the environment key surface that
later phases fill in.

> **Boundary note.** No entity here derives from `docs/schema/injazedu-db-schema.md`. Nothing reads
> the production snapshot (FR-025), so no production schema fact is used, assumed, or needed.

---

## 1. Machine Safety State *(ephemeral — computed per run, never persisted)*

Produced by `preflight-check.sh`. Evaluated as a whole; any blocking condition failing fails the run.

| Field | Type | Source | Blocking? | Pass condition |
|---|---|---|---|---|
| `encryption_state` | enum `On` / `InProgress` / `Off` / `Unknown` | `fdesetup status` | **Yes** | `On` or `InProgress` |
| `recovery_key_location` | string | `docs/runbooks/safety.md` | **Yes** | present, non-placeholder |
| `recovery_key_attested_on` | date | `docs/runbooks/safety.md` | **Yes** | valid ISO date |
| `repo_real_path` | path | `realpath` of repo root | — | resolved |
| `sync_root_match` | string / null | sync-root scan (research R1) | **Yes** | `null` |
| `free_disk_gb` | integer | `df` on `/` | **Yes** | ≥ 20 |
| `container_engine_state` | enum `Responding` / `InstalledNotRunning` / `NotInstalled` | `docker context ls`, socket probe | **Yes** | `Responding` |

**Validation rules**

- `Unknown` encryption state is treated as `Off`. An unreadable answer is never a pass.
- `sync_root_match` tests the **resolved real path** against: `~/Library/Mobile Documents/`,
  `~/Library/CloudStorage/`, `~/OneDrive*`, `~/Dropbox*`, and — when iCloud Desktop & Documents sync
  is active — `~/Desktop` and `~/Documents` (research R1). The matched root is reported, not just a
  boolean.
- `InstalledNotRunning` and `NotInstalled` are distinct outputs with distinct remediation (FR-005).
- Every field is reported individually; there is no single aggregate verdict line (FR-008).

**State transitions**: none. Each run recomputes from scratch — a cached "we checked last week" is
precisely the failure mode the check exists to prevent.

---

## 2. Recovery-Key Custody Record *(persistent, committed)*

Lives in `docs/runbooks/safety.md`. Read as a blocking input by entity 1.

| Field | Type | Constraint |
|---|---|---|
| `recovery_key_location` | free text, one line | Names **where**, never the key. Must not match the placeholder token. |
| `attested_on` | ISO date | Set by the operator when they verify custody. |
| `attested_by` | string | Who confirmed it. |

**Validation rules**

- The file ships with an explicit placeholder (e.g. `<UNSET — preflight will fail until edited>`);
  `preflight-check.sh` fails while that token is present (FR-006a).
- The record MUST NOT contain key material or anything from which it could be derived (FR-006).
  Its own instructions state this at the point of editing (spec edge case).
- Committed **deliberately** — it must survive the loss of the machine it describes, which is the
  only circumstance the recovery key exists for.

---

## 3. Snapshot Provenance Record *(persistent, committed)*

Lives in `docs/runbooks/snapshot.md`. Written here, cited by every later report (Constitution VI:
"numbers never travel alone").

| Field | Type | Value at creation |
|---|---|---|
| `snapshot_taken_at` | date | `2026-08-07` |
| `physical_location` | path | The native database service's data directory — outside this repository |
| `containment_rule` | text | Never copied into the repository; `data/snapshots/` stays empty |
| `refresh_policy` | enum / text | **Undecided** — §8 Item E, owed before P1 |
| `measured_row_counts` | reference | Points at P0 §2; not duplicated here, to avoid two sources drifting |

---

## 4. Repository Boundary *(persistent, committed)*

`.gitignore`. Modelled as rule categories so FR-015's check can assert one representative path per
category rather than testing an arbitrary file list.

| Category | Representative test path | Expected |
|---|---|---|
| Environment files | `apps/lab/.env` | ignored |
| Environment template | `.env.example` | **not ignored** (inverted case) |
| Plain dumps | `backup.sql` *(probed at repo root — a probe under `data/snapshots/` would also match the FR-012 containment rule and mask removal of `*.sql`)* | ignored |
| Compressed dumps | `backup.sql.gz`, `lab.dump` | ignored |
| PHP dependencies | `apps/lab/vendor/x` | ignored |
| JS dependencies | `apps/lab/node_modules/x` | ignored |
| Python environment | `apps/ai-service/.venv/x` | ignored |
| Generated storage | `storage/documents/x` | ignored |
| Application logs | `apps/lab/storage/logs/x` | ignored |
| OS noise | `.DS_Store` | ignored |

**Validation rules**

- Assertion is `git check-ignore -v <path>` exit status: `0` ignored, `1` not ignored (research R7).
- The template row is an **inverted assertion** — a `.gitignore` that swallows `.env.example` is a
  defect, and only the inverted case catches it.
- Checks operate on paths, not files; nothing is created or deleted in the working tree.

---

## 5. Environment Key Surface *(persistent, committed as `.env.example`)*

Only keys this increment actually needs. Later phases add their own; `.env.example` must list every
key that exists at any point (Constitution IV).

| Key | Set here? | Purpose |
|---|---|---|
| `LAB_DB_PASSWORD` | yes — empty in the template | Lab database password; startup fails if unset (FR-022) |
| `LAB_DB_HOST` / `LAB_DB_PORT` | yes | `127.0.0.1` / `5433` |
| `LAB_DB_DATABASE` / `LAB_DB_USERNAME` | yes | `injazedu_lab` / `lab` |
| `SNAPSHOT_TAKEN_AT` | yes — value `2026-08-07` | Stamped on every later report |

**Explicitly absent, and their absence is an acceptance criterion**: no `SNAPSHOT_DB_*` keys (المرحلة 3),
no `SNAPSHOT_DB_ROOT_*` key ever, no `STUDENT_REF_PEPPER` (المرحلة 8), no `PRODUCTION_WRITE_ENABLED`
(المرحلة 8), no `EMBEDDING_CONFIG_VERSION` (المرحلة 6).

---

## 6. Lab Database *(persistent — the only real datastore)*

| Property | Value | Requirement |
|---|---|---|
| Image | `pgvector/pgvector:0.8.6-pg17` | ADR-018 context |
| Database / user | `injazedu_lab` / `lab` | FR-022 |
| Bind | `127.0.0.1:5433` → container `5432` | FR-017, FR-018 |
| Memory ceiling | 1536 MB, **read back from the running container** | FR-021, research R4 |
| Tuning | `shared_buffers=512MB`, `work_mem=32MB`, `maintenance_work_mem=256MB`, `max_connections=50` | FR-021 |
| Volume | named `lab_pgdata` | FR-020 |
| Init | `infrastructure/postgres/init.sql`, mounted read-only | runs **once**, at volume creation (research R6) |

### 6a. Capabilities

| Capability | Extension | Needed by |
|---|---|---|
| Vector similarity | `vector` | P2 duplicate detection |
| Trigram text matching | `pg_trgm` | P2 cascade, layer 2 |

Verified by `SELECT extname, extversion FROM pg_extension WHERE extname IN ('vector','pg_trgm')`
expecting exactly **2** rows, executed **inside the container** (research R3) — a catalog query, never
a `\dx` meta-command, and never by reading `init.sql`.

### 6b. Persistence Marker *(the only row this increment writes)*

Proves FR-020 / SC-007. Deliberately trivial and PII-free.

| Column | Type | Note |
|---|---|---|
| `id` | integer, PK | |
| `written_at` | timestamptz | set on insert |
| `note` | text | fixed literal, e.g. `persistence probe` |

**Lifecycle**: created on first verification run if absent → one row inserted → service restarted →
row re-read and compared. The table is idempotent (re-running inserts no duplicate; it upserts a
fixed id) and is left in place as evidence. It contains **no PII** and no production-derived data,
so it does not breach the "no PII in the Lab database" rule.

---

## Entity relationships

```text
Machine Safety State ──reads──> Recovery-Key Custody Record   (blocking input)
                     ──reads──> filesystem, fdesetup, container socket

Snapshot Provenance Record ──describes──> Production Snapshot (never read)

Repository Boundary ──governs──> everything committed, incl. the two runbooks above

Environment Key Surface ──supplies──> Lab Database credentials
Lab Database ──hosts──> Capabilities (vector, pg_trgm)
             ──hosts──> Persistence Marker (1 row)
```

The production snapshot appears once, as the object the provenance record *describes*. No arrow
points into it — that is the whole design.
