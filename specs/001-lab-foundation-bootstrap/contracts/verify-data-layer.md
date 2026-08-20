# Contract — `scripts/verify-data-layer.sh`

**Implements**: FR-023 (proving FR-016…FR-022) · **Gates**: SC-005…SC-010 · **Phase**: المرحلة 2

## Invocation

```text
scripts/verify-data-layer.sh [--with-restart]
```

Default run performs the non-destructive checks. `--with-restart` additionally stops and starts the
service to prove persistence (SC-007) — separated because it is the only assertion with a side
effect and the only one that takes tens of seconds.

## Exit codes

| Code | Meaning |
|---|---|
| `0` | All assertions pass. |
| `1` | At least one assertion failed. |
| `2` | Could not run (container engine not responding, `LAB_DB_PASSWORD` unset). |

## Output contract

```text
[ OK ] readiness         ready in 6s (threshold 60s)
[ OK ] capabilities      vector 0.8.6, pg_trgm 1.6  (2 of 2)
[ OK ] bind address      127.0.0.1:5433
[ OK ] loopback only     connection from 192.168.x.x refused
[ OK ] port 5432 intact  postgresql@14 still listening (pid 1984)
[ OK ] memory ceiling    limit 1536 MiB, in use 214 MiB
[ OK ] persistence       marker row survived restart (written 2026-08-20T14:02:11Z)

DATA LAYER VERIFIED — 7 assertions, 0 failures
```

## Assertions

| # | Assertion | Method | Requirement |
|---|---|---|---|
| 1 | Readiness | `pg_isready` in-container, **then** a trivial `SELECT 1` (research R5) | FR-016, SC-005 |
| 2 | Capabilities | `SELECT extname, extversion FROM pg_extension WHERE extname IN ('vector','pg_trgm')` in-container; expect exactly 2 rows (research R3, R6) | FR-019, SC-006 |
| 3 | Bind address | inspect published port; assert `127.0.0.1:5433` | FR-017 |
| 4 | Loopback only | attempt a connection to the host's LAN address on 5433; must be **refused** | FR-018, SC-010 |
| 5 | Neighbour intact | `postgresql@14` still listening on 5432 | FR-017, SC-008 |
| 6 | Memory ceiling | read the limit **back from the running container**, not from the compose file (research R4) | FR-021, SC-009 |
| 7 | Persistence | upsert marker row → restart → re-read and compare | FR-020, SC-007 |

## Behavioural guarantees

- **All SQL runs inside the container.** The host client is psql 14 against a server 17; executing
  in-container makes client and server the same build by construction (research R3).
- **Capabilities are read from the live database**, never from `init.sql` — that file runs only at
  volume creation, so a file-based check can report success on a database that lacks the extension
  (research R6).
- **The memory limit is measured, not assumed.** `mem_limit` is a legacy compose key on Compose
  v5.1.2 and is exactly the sort of setting that gets silently dropped; a silently ignored ceiling
  would produce a green SC-009 while Principle VII's budget is breached (research R4).
- **Assertion 4 is inverted** — success means the connection is *refused*.
- **Idempotent.** The marker row upserts on a fixed id; re-running never accumulates rows.
- **Touches no production data.** The only database contacted is the Lab database.

## Test cases

| Given | Expect |
|---|---|
| Healthy stack | exit `0`, 7/7 |
| Cold start from stopped | ready < 60 s — **SC-005** |
| `LAB_DB_PASSWORD` unset | exit `2`, message names the missing key; no fallback to a default or empty password — **FR-022** |
| `pg_trgm` missing from a pre-existing volume | exit `1`, assertion 2 reports `1 of 2` — the research R6 trap, caught |
| `--with-restart` | marker row identical before and after — **SC-007** |
| Machine restarted, then run | marker row still present — the second half of SC-007 |
| `mem_limit` silently ignored | exit `1`, assertion 6 reports no limit rather than passing |
| Attempt from a non-loopback address | refused — **SC-010** |
| Throughout | `postgresql@14` uninterrupted — **SC-008** |
