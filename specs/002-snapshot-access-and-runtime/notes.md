# Measured Notes — Source Access & Lab Runtime

Everything below was measured on this machine on **2026-08-21**. Only findings that change what gets
built are here; the rest of the original Phase 0 research described a `lab_ro` grant architecture
that no longer exists.

Zero rows were read from any `injazedu` table in the course of measuring this, and nothing was written.

---

## N1 — PHP authenticates as `root` with an empty password ✅

**Status**: **resolved 2026-08-21**. It works. No remediation needed.

MySQL 9.1 removed `mysql_native_password`; `root@localhost` uses `caching_sha2_password` with an
empty `authentication_string`. An empty password short-circuits the plugin's RSA exchange, so a plain
loopback connection succeeds without TLS. Verified from PHP 8.4.2:

```
CURRENT_USER = root@localhost
plugin       = caching_sha2_password
questions    = 29142
```

Note that `CURRENT_USER()` reports `@localhost` rather than `@127.0.0.1` — name resolution is on and
`root`'s only account row is `@localhost`. Nothing depends on that distinction any more (it mattered
only to the withdrawn grant design), but it is worth knowing when reading connection diagnostics.

**If a future change breaks this** — a MySQL upgrade, a password set on `root` — stop and ask. Do
not enable TLS on loopback, create another database user, or change the authentication plugin: each
is an architecture decision the operator has not made (Constitution I).

---

## N2 — The linked PHP cannot run this framework at all

`/opt/homebrew/bin/php` is **8.2.27** and 31 local projects under `~/Projects` depend on it.
Laravel 13.26.1 requires `php ^8.3`, so the linked binary cannot run the application — this is not a
preference, it is a hard incompatibility.

**Consequence**: use `/opt/homebrew/opt/php@8.4/bin/php` explicitly for **resolution as well as
execution**. Composer resolves against the running interpreter, so a `composer` run under the linked
8.2 either fails or locks a dependency tree 8.4 must then run.

**Never `brew link php@8.4`.** The blast radius is 31 unrelated projects.

PHP 8.4.2 already ships `pdo_pgsql`, `pdo_mysql`, and `mysqlnd` — no extension install is needed.

---

## N3 — The framework's write-block must be proven, not assumed

**Status**: **resolved 2026-08-21** (T013). Laravel 13 **refuses** — no fallback. With the
listener disabled, a write through the `injazedu` connection threw
`QueryException: Database hosts array is empty` before any PDO was created. With the write-host
guard pointed at the real server instead, the listener threw `ReadOnlyViolation`. Each layer
blocks alone (SC-003).

Laravel's read/write connection split with `'write' => ['host' => []]` is a deliberate abuse of a
replication feature. Whether an empty write-host list **refuses** or silently **falls back to the
read host** is version-dependent and has not been verified here.

**Consequence**: this is exactly why the query listener exists as a second, independent layer. SC-003
requires each layer to block alone. If the empty write-host list turns out to fall back, the listener
carries the guarantee by itself and `verify-lab-app.sh` records which mechanism is in force — it does
not quietly pass.

---

## N4 — A queued job must leave a row, not a log line

"The queue connection is reachable" is not evidence that a worker ran anything. The probe job upserts
a fixed id and records `ran_at` and the executing `worker_pid`.

**Consequence**: the assertion is made **after the worker process has exited**, and requires
`worker_pid` ≠ the dispatching process's pid. A fixed id keeps re-running idempotent, so the check
never accumulates rows.

---

## N5 — The chat model is ~1 GB heavier than the plan budgeted

| Tag | Artefact | Note |
|---|---|---|
| `embeddinggemma:300m-qat-q4_0` | **227.5 MB** | as expected |
| `gemma4:e2b-it-qat` | **4,135.5 MB** | against a `~3 GB` line in P0 §12.3 — includes a **941 MB vision projector** that nothing in P0–P9 uses |

**Consequence**: against a 13 GB idle ceiling this is the single largest unknown in the budget.
FR-011 makes measuring resident memory with both models loaded an acceptance step, and an overrun a
go/no-go trigger rather than a footnote. **Measure before pinning limits** — Ollama runs as the
official macOS app with defaults until the number says otherwise.

**Operator decision 2026-08-22**: use Ollama's official macOS installer rather than the Homebrew
formula. Version 0.32.15 is installed at `/Applications/Ollama.app`, the CLI symlink is
`/usr/local/bin/ollama`, and the app registers as a macOS login item. This changes dependency
installation and service ownership, not the loopback, model-tag, or memory contracts. If limits
become necessary, set them for the app through `launchctl`, restart the app, and read them back from
the running process rather than trusting configuration text.

**Measured 2026-08-22 03:21–03:24 EEST (T029/T030)**: after unloading both models, the verifier loaded the
larger chat model first and the embedding model second, using empty requests that returned neither
generated text nor embedding vectors. Both then appeared together in `/api/ps`:

| Resident model | `/api/ps` allocation |
|---|---:|
| `embeddinggemma:300m-qat-q4_0` | **276.3 MiB** |
| `gemma4:e2b-it-qat` | **3,393.0 MiB** |
| **Combined** | **3,669.2 MiB** |

The combined allocation is **290.2 MiB above** the 3,379 MiB §12.3 estimate, but the complete
Ollama process tree measured **4,581 MiB RSS** initially and **4,673 MiB RSS** on the final clean
verification run; the higher figure is the conservative result. Both resident figures are below the
**13,312 MiB (13 GiB)** Phase 4 ceiling, so this is a pass rather than a go/no-go trigger. The
running server process was then read directly: `OLLAMA_MAX_LOADED_MODELS`, `OLLAMA_NUM_PARALLEL`,
`OLLAMA_KEEP_ALIVE`, and `OLLAMA_CONTEXT_LENGTH` were all absent, so no limits were pinned. `top`
reported 15,360 MiB system memory in use as non-blocking context; compressed/cache memory makes
that unsuitable for this component-level gate, and Phase 10 owns the full-stack measurement.

Load order matters on this 16 GB unified-memory Mac. Loading the smaller embedding runner first
caused Ollama's scheduler to evict it when the larger runner was loaded (`system_limited=true` in
the server log). Loading the larger runner first, followed by the 276.3 MiB runner, kept both
resident without changing any runtime setting.

---

## N6 — `.gitignore`'s blanket `*.sql` will swallow committed SQL

Line 16 is `*.sql` with a single negation for `infrastructure/postgres/init.sql`. المرحلة 11 commits
**18 profiling queries** to `sql/profiling/`; under the current rule they are written, `git add`
reports nothing, the working tree looks clean, and they never enter history.

**Consequence**: narrow the rule to dumps — by extension and by location (`data/snapshots/**`) — and
un-ignore the repository's committed SQL directories. Adding one more per-file negation fixes today
and leaves the trap set for eighteen more.
