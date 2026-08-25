# Snapshot Provenance Runbook

**Implements**: FR-007 · **Entity**: data-model §3 · **Cited by**: every later report
(Constitution VI — numbers never travel alone)

## Record

| Field | Value |
|---|---|
| `snapshot_taken_at` | 2026-08-07 |
| `physical_location` | `/opt/homebrew/var/mysql` — native MySQL data directory (`mysqld`, Homebrew `mysql`, 127.0.0.1:3306). **Outside this repository.** |
| `containment_rule` | The snapshot is never copied into this repository. `data/snapshots/` stays empty forever (`.gitkeep` plus ignore rule only). |
| `refresh_policy` | **Decided 2026-08-25: no refresh.** This 2026-08-07 copy is the source for the entire local AI Lab program — P1 and every project after it. There is no cadence, no scheduled refresh, and **no gate anywhere blocks on the copy's age**. The date prints in every report and on every screen as *context*, so each number is read in its own frame. Closes P0 §8 item E. |
| `measured_row_counts` | See P0 §2 (57,482 users · ~70K orders · ~24,408 API tokens). Not duplicated here, to avoid two sources drifting. |

## Rules

- Nothing in this repository writes to or mounts the snapshot. The first permitted access is
  المرحلة 3: read-only through the application's `injazedu` connection, guarded by an empty write
  host list, a query listener that throws on non-reads, and two application-level allowlists —
  eleven tables that may be copied into the Lab and six more readable as counts only (P0 §3.2,
  2026-08-23; `docs/ADR/ADR-021.md`).
- **The copy is not refreshed during this program.** If a future project ever changes that
  decision, update `snapshot_taken_at` here **and** in `apps/lab/.env` /
  `apps/lab/.env.example` (`SNAPSHOT_TAKEN_AT`) in the same commit, and re-run `lab:profile` so the
  stored measurement matches the data. Nothing else needs re-issuing — the connection uses the
  host's existing account, so a refresh cannot leave a stale or over-privileged identity behind.
- **The operator may inspect, query, transform, or modify this local copy freely.** The read-only
  rule is a property of the **Lab application** — the three layers on the `injazedu` connection —
  not a restriction on the person at the keyboard.
- This file is committed deliberately: it must survive the loss of the machine it describes.
