# Snapshot Provenance Runbook

**Implements**: FR-007 · **Entity**: data-model §3 · **Cited by**: every later report
(Constitution VI — numbers never travel alone)

## Record

| Field | Value |
|---|---|
| `snapshot_taken_at` | 2026-08-07 |
| `physical_location` | `/opt/homebrew/var/mysql` — native MySQL data directory (`mysqld`, Homebrew `mysql`, 127.0.0.1:3306). **Outside this repository.** |
| `containment_rule` | The snapshot is never copied into this repository. `data/snapshots/` stays empty forever (`.gitkeep` plus ignore rule only). |
| `refresh_policy` | **UNDECIDED — P0 §8 Item E, owed before P1** |
| `measured_row_counts` | See P0 §2 (57,482 users · ~70K orders · ~24,408 API tokens). Not duplicated here, to avoid two sources drifting. |

## Rules

- Nothing in this repository reads from, writes to, or mounts the snapshot. The first permitted
  access is المرحلة 3 (read-only, via the ADR-020 grant).
- If the snapshot is ever refreshed, update `snapshot_taken_at` here **and** in `.env.example`
  (`SNAPSHOT_TAKEN_AT`) in the same commit, and record the new `refresh_policy` before P1.
- This file is committed deliberately: it must survive the loss of the machine it describes.
