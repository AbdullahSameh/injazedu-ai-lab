# Measured Notes — Handover & P1 Readiness

Measured on this machine on **2026-08-23**, before any of this increment's code was written. Only
findings that change what gets built are here.

Zero rows were read from any `injazedu` table beyond what `lab:health` already reads, and nothing was
written to it.

> **Revised 2026-08-23.** An earlier draft of these notes carried ten findings, most of them about a
> nightly backup and a restore drill. المرحلة 9 was cancelled the same day; those findings are gone
> rather than archived, except the two that survive as pitfalls in `setup.md`. The measurement that
> **caused** the cancellation (N5) is kept, because a decision without its evidence is an opinion.

---

## N1 — The baseline is green, and it is the acceptance instrument

```text
php artisan lab:health   →  10/10 PASS, exit 0, 7.058 s cold (both models unloaded beforehand)
                            check 5 chat 4,858 ms · check 6 embed 1,095 ms
                            check 8 injazedu.questions count=29142, snapshot_taken_at=2026-08-07
                            checks 9 and 10 passed by being refused
```

**Consequence**: cheap enough to run after every step of this increment — after the allowlist split,
after the README rehearsal, and after the live stack is restored (SC-010). Nothing in this increment
needs its own verification harness; it needs to not break this one.

---

## N2 — Three §6 queries were blocked by a rule about copying, not reading ✅

Reading §6.1 and §6.2 against `config('lab.source_tables')`:

| Query | Tables read | Status before |
|---|---|---|
| 1–14, 17 | all inside the eleven | runnable |
| **15** | `course_user`, `course_order`, `orders` | **blocked** — two are in the forbidden seventeen |
| **16** | `course_user`, `user_roles`, `roles` | **blocked** |
| **18** | `book_course` | **blocked** |

Queries 15 and 16 are the ones §15 of the P0 plan names as resolving open item 4 — whether enrolment
is recorded by `course_user` or `course_order`, and whether `course_user` holds students or trainers.
The program cannot size P3 or P6 without them. Query 15 needs `orders` only to reach `user_id`
through `course_order`, and reads it as `COUNT(DISTINCT o.user_id)`.

**Consequence**: the operator split the list on 2026-08-23 (§3.2). `source_tables` keeps its name and
its eleven entries and now governs **copying only**; `profile_tables` adds six read-only names. The
forbidden set drops from seventeen to fifteen — `orders` and `course_order` move to profile-only,
`users` stays refused, so `lab:health` check 10 is unaffected.

The property that makes this safe rather than merely wider is that **read and copy are separately
enforced** (FR-002, FR-004). A single union check used for both would quietly undo it.

---

## N3 — Why المرحلة 9 was cancelled, in numbers

```text
Lab database          8,398 kB · 12 tables
Contents              1 Filament operator row · 1 queue probe · 1 vector probe
                      + cache, sessions, migrations, and the queue tables
Reproducible by       php artisan migrate  +  php artisan lab:health
```

There is nothing here that a nightly `pg_dump` protects. The snapshot it derives from is itself
disposable — recovered by taking a new copy, not by restoring one.

**Consequence**: المرحلة 9 removed from the P0 plan, §14.6 rewritten in the core plan, and the backup
line removed from Constitution III (v2.1.0). The one irreproducible artefact — reviewer decisions —
does not exist until P2 and is a go-live concern, not a local one.

---

## N4 — The memory gate could not have measured what it claimed

Both models resident, `lab:health` green:

```text
ollama tree (llama-server ×2 + app + serve)   4,646.9 MiB
OrbStack VM, host RSS                           394.7 MiB   (guest view: postgres 77.6 MiB)
mysqld                                           18.6 MiB
ai-service (uvicorn + workers)                   58.8 MiB
laravel (artisan serve)                          13.3 MiB
─────────────────────────────────────────────────────────
STACK TOTAL                                   5,132.3 MiB

WHOLE MACHINE (active + wired + compressed)  12,862 MiB     old ceiling 13,312 MiB
```

Against §12.3's estimates, every non-model component is an order of magnitude smaller: PostgreSQL
~1.5 GB → **394.7 MiB**; MySQL ~1–1.5 GB → **18.6 MiB** (`innodb_buffer_pool_size` is **128 MiB**,
not gigabytes); Laravel ~0.4 GB → **13.3 MiB**; FastAPI ~0.3 GB → **58.8 MiB**.

**~90% of the stack is the two models.** The whole-machine figure meanwhile sits at 96.6% of the old
ceiling and moves by a gigabyte as browser tabs open — so a gate on it would have failed P0 for a
reason no P0 remedy could fix. §11's fallback (move PostgreSQL to Homebrew) recovers ~400 MiB.

**Consequence**: the gate is gone (constitution v2.1.0, P0 §11). What survives is a runbook of manual
steps, and one design conclusion worth carrying into P2: **performance work belongs in the pipeline** —
batch sizes, filter cascades, how many model calls happen — not in tuning databases that cost 20 MB.

Two traps for whoever writes that runbook:

- `ps` RSS on macOS **undercounts** idle processes (`mysqld` reads 6.3 MiB at rest); the figure
  comparable to §12.3's *total* is `active + wired + compressed` from `vm_stat`. The two metrics are
  never interchangeable and must always be labelled.
- `docker stats` (guest) and the OrbStack VM's host RSS are two views of the same memory and must
  **never be summed** — they were observed moving in opposite directions within minutes.

---

## N5 — Two pitfalls that outlive the phase that found them

Both belong in `setup.md`, not in a script:

```text
pg_dump / psql   host client 14.18 aborts at connect against the 17.11 server:
                 "aborting because of version mismatch". All SQL runs in-container.

bash             /bin/bash is 3.2.57. `set -o pipefail` and ${PIPESTATUS[n]} both work
                 (verified), so pipelines in scripts can detect a failing left-hand side —
                 which the naive `a | b > file` form cannot.
```

**Consequence**: FR-013 lists them as measured pitfalls. `lab-stack.sh` uses `pipefail`; nothing else
in this increment runs a pipeline that matters.
