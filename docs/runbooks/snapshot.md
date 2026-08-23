# Snapshot Provenance Runbook

**Implements**: FR-007 · **Entity**: data-model §3 · **Cited by**: every later report
(Constitution VI — numbers never travel alone)

## Record

| Field | Value |
|---|---|
| `snapshot_taken_at` | 2026-08-07 |
| `physical_location` | `/opt/homebrew/var/mysql` — native MySQL data directory (`mysqld`, Homebrew `mysql`, 127.0.0.1:3306). **Outside this repository.** |
| `containment_rule` | The snapshot is never copied into this repository. `data/snapshots/` stays empty forever (`.gitkeep` plus ignore rule only). |
| `refresh_policy` | **OWED BEFORE P1 — not decided** (P0 §8 item E). The open question: whether to refresh this 2026-08-07 copy before P1 starts, and at what cadence afterwards. The date prints in every report and the gap widens with time. It is deliberately left open here — but it is a named debt with an owner and a deadline (before P1's first import), never silence. |
| `measured_row_counts` | See P0 §2 (57,482 users · ~70K orders · ~24,408 API tokens). Not duplicated here, to avoid two sources drifting. |

## Rules

- Nothing in this repository writes to or mounts the snapshot. The first permitted access is
  المرحلة 3: read-only through the application's `injazedu` connection, guarded by an empty write
  host list, a query listener that throws on non-reads, and two application-level allowlists —
  eleven tables that may be copied into the Lab and six more readable as counts only (P0 §3.2,
  2026-08-23; `docs/ADR/ADR-021.md`).
- If the snapshot is ever refreshed, update `snapshot_taken_at` here **and** in
  `apps/lab/.env` / `apps/lab/.env.example` (`SNAPSHOT_TAKEN_AT`) in the same commit. The refresh
  policy itself (§8 item E) must be decided **before P1 starts** and recorded in its place above —
  a refresh without the decision recorded would trade one silence for another. Nothing else needs
  re-issuing — the connection uses the host's existing account, so a refresh cannot leave a stale
  or over-privileged identity behind.
- This file is committed deliberately: it must survive the loss of the machine it describes.
