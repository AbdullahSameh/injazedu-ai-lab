# Memory Check Runbook — manual steps, no gate

When the machine feels slow, run these and read them. There is **no gate, no threshold, and no
acceptance criterion** on any memory number in this program (constitution v2.1.0, 2026-08-23): this
file tells you what each number means and what to do about it. It deliberately contains no script
and no schedule.

## The three steps

### 1. Whole machine (this is the number that moves with browser tabs)

```sh
top -l 1 -s 0 | grep PhysMem
```

`used` is active + wired + compressed — the figure comparable to the machine's total. It moves by
gigabytes as browser tabs open; it says almost nothing about the Lab stack specifically.

### 2. Where the stack's memory actually is

```sh
curl -s http://127.0.0.1:11434/api/ps | jq '.models[] | {model: .name, mib: (.size/1048576|floor)}'
```

Each resident model reports its allocation. On this stack this is where nearly all of it sits.

### 3. Everything else, per process

```sh
ps -axo rss=,command= | grep -E 'mysqld|com.docker|OrbStack|uvicorn|artisan' | grep -v grep | awk '{printf "%6.1f MiB  ", $1/1024; $1=""; print}'
```

RSS per process, kib → MiB.

## What the numbers mean — measured 2026-08-23

With both models resident and `lab:health` green:

```text
ollama tree (llama-server ×2 + app + serve)   4,646.9 MiB   ← ~90% of the whole stack
OrbStack VM, host RSS                          394.7 MiB   (guest view inside: postgres 77.6 MiB)
mysqld                                          18.6 MiB   (innodb_buffer_pool_size = 128 MiB)
ai-service (uvicorn + workers)                  58.8 MiB
laravel (artisan serve)                         13.3 MiB
─────────────────────────────────────────────────────────
STACK TOTAL                                   5,132.3 MiB
```

The conclusion that survives into every later phase: **the two models are ~90% of the stack**;
every database and service component is an order of magnitude below its §12.3 estimate. If memory
or speed ever becomes a real problem, the lever is **the pipeline** — batch sizes, filter cascades,
how many model calls happen — not tuning a database process that costs 20 MiB. If the machine feels
slow with both models resident, that is expected: unload one (`ollama stop gemma4:e2b-it-qat`) or
close tabs. Nothing to fix, nothing failing.

## Two traps

1. **`ps` RSS undercounts idle processes on macOS** — `mysqld`, for example, reads **6.3 MiB at
   rest**, far below what it touches while working. Never compare a `ps` number against a
   whole-machine figure: step 1 measures the machine (active + wired + compressed), steps 2–3
   measure processes. Always label which kind of number you are reading.
2. **`docker stats` and the OrbStack VM's host RSS are two views of the same memory — never sum
   them.** They were observed moving in opposite directions within minutes. For the container,
   either the guest view (`docker stats --no-stream`) or the host view (step 3's OrbStack line),
   never both added together.
