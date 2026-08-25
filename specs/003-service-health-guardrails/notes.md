# Measured Notes — Service, Health Matrix & Guardrails

Everything below was measured on this machine on **2026-08-22**, before any of this increment's code
was written. Only findings that change what gets built are here.

Zero rows were read from any `injazedu` table while measuring this, and nothing was written anywhere.
The embedding calls below produced vectors that were inspected and discarded — none was stored.

This file is this increment's Phase 0 output. It is called `notes.md` rather than `research.md` to
match `002`; the content is the same thing.

---

## N1 — The runtime already returns unit-length vectors ✅

A single embedding call through `/api/embed` with the mandatory similarity prefix:

```text
dimension        768
L2 norm          0.9999997576825181
prompt_eval_count 21
response keys    model · embeddings · total_duration · load_duration · prompt_eval_count
```

The vector arrives **already L2-normalized**. The operator's decision (spec FR-005) that the service
normalizes explicitly therefore costs one pass over 768 floats and changes no value in practice.

**Consequence — keep the explicit normalization anyway, and this is why**: the contract string claims
`l2norm`, and that claim must be true of *our* output, not of a runtime behaviour we did not choose
and cannot pin. Ollama's normalization is undocumented in the response, could change with a version
bump, and differs across embedding models. Normalizing in the service makes the claim structurally
true. SC-016 asserts it on the returned vector, so the assertion passes today either way — which is
exactly the property that makes it a useful regression test tomorrow.

**Guard against the degenerate case**: a zero vector cannot be normalized. If the runtime ever returns
one, dividing by a zero norm yields `NaN` and every later comparison silently fails. Treat a
zero-norm result as an error, not as something to normalize.

---

## N2 — Truncation is silent; `prompt_eval_count` is the only signal

EmbeddingGemma's context is **2048 tokens** (`gemma3.context_length` from `/api/show`; the model
reports `gemma3.*` keys). Sending ~400 repetitions of an Arabic sentence:

```text
prompt_eval_count  2048      ← capped exactly at the window
dimension          768        (unchanged)
norm               1.0000     (unchanged)
error              none       ← no error, no flag, no warning
```

The runtime truncates and returns a perfectly well-formed vector of the **first 2048 tokens**. Nothing
in the response says so. A caller that does not compare `prompt_eval_count` against the window cannot
tell a complete embedding from a truncated one.

**Consequence**: FR-007's truncation report is built from `prompt_eval_count >= context_length`, with
the window read from `/api/show` rather than hard-coded. The comparison is `>=`, not `>`, so a text
that lands exactly on the boundary is reported as *possibly* truncated — conservative in the safe
direction. The response field and log field must both say which it was.

This matters beyond P0: P2 embeds 29,142 questions, and §12.2 requires truncation cases to be
recorded. Building the signal now means the batch has it from the first row.

---

## N3 — pgvector round-trips exactly, so check 7 can assert equality

```sql
INSERT INTO rt VALUES (1, '[-0.032863766,0.05793848,0.1]');
SELECT v::text = '[-0.032863766,0.05793848,0.1]';   -- t
```

`vector` stores float32 and renders the shortest representation that round-trips. A value written from
a float32 source comes back textually identical.

**Consequence**: check 7 asserts **exact equality**, not a distance threshold. And the vector it writes
must be **deterministically generated** (a fixed formula over the index), never a model output — check
7 exists to prove the extension stores and returns a 768-dimension vector, and a model call inside it
would make a runtime failure look like a database failure. Check 6 already covers the model.

---

## N4 — Laravel 13 has a native vector column; the test suite never sees it

`Blueprint::vector($column, $dimensions)` and `PostgresGrammar::typeVector()` both exist in the
installed framework (`vendor/laravel/framework`). The probe migration is
`$table->vector('embedding', 768)` — no raw SQL, no `DB::statement`.

`phpunit.xml` points the default connection at sqlite `:memory:`, which has no vector type. This is
**not** a problem, because the suite does not migrate: `NoPiiInLabSchemaTest` already reads the live
`information_schema` from the real Postgres connection and the other guardrail tests touch MySQL. The
established pattern — override `database.connections.pgsql.database`, `DB::purge('pgsql')`, then query
live — is what any new schema assertion follows.

**Consequence**: the vector column exists only where it is applied by `php artisan migrate` against
Postgres. No test needs a vector-capable sqlite, and none should try to create one.

---

## N5 — The plan's check order is the order that keeps both models resident

Measured latency for a minimal generative probe (`num_predict: 1`, `num_ctx: 4096`):

| Call | Latency | Load component |
|---|---:|---:|
| Chat, cold | **4,819 ms** | 4,575 ms |
| Chat, warm | **218 ms** | 3 ms |
| Chat, warm again | **47 ms** | 1 ms |
| Embed, cold | ~1,300 ms | 886 ms |

Residency after calling chat **then** embed:

```text
gemma4:e2b-it-qat             3,393.0 MiB
embeddinggemma:300m-qat-q4_0    276.3 MiB      ← both resident
```

This confirms `002` notes N5 from the other direction: the larger runner must load first. The health
matrix's numbering — check 5 chat, then check 6 embed — is already that order. **Do not reorder them
for tidiness**; on this 16 GB machine, embed-then-chat evicts the embedding runner.

**Consequence for the on-demand panel run (FR-019)**: worst case is a cold stack — roughly 5 s for the
chat check and 1 s for the embedding check, so the full matrix is a few seconds, not a minute. That is
viable inside a Livewire action, but the action must show a pending state and the operator-facing
output must not imply the page is hung. A warm run is well under a second for both model checks.

**Consequence for the health command**: it MUST NOT assert that both models are simultaneously
resident. That was `002`'s measurement (T029/T030) and it is order-dependent; a health check that
re-measures it would fail intermittently for a reason that has nothing to do with health.

---

## N6 — Guzzle is already installed

`guzzlehttp/guzzle 8.0.2` is in the lock file, so Laravel's HTTP client can call the service with no
new dependency. Checks 2, 4, 5, and 6 all go through it; only check 2 talks to the service directly
about itself, while 4, 5, and 6 ask the service about its own dependencies.

---

## N7 — The authenticated cold panel run completes in 12.87 seconds

Measured during Phase 5 acceptance on **2026-08-23**. Both model runners were unloaded first
(`ollama ps` was empty), then the authenticated Filament page was opened. It showed no result before
the operator action, and one click rendered all ten checks with ten passes.

```text
Browser-observed click → ten rendered rows     12,870 ms
Chat probe reported by check 5                  6,378 ms
Embedding probe reported by check 6             1,645 ms
Livewire action through the test harness         5,828.2 ms  (separate cold run)
```

The real browser figure is slower than N5's rough `~5 s + ~1 s` estimate, but the authenticated web
action completed normally in the current local runtime. Separately, the combined cold model probe
stayed below the service request's configured 10-second upstream timeout. The fallback in `plan.md`
is therefore not triggered by the observed behaviour: the page continues to run all ten checks and
does not make checks 5 and 6 CLI-only.

---

## N8 — Phase 5 row-count acceptance controls stayed unchanged

Measured on **2026-08-23** immediately before and after one full `lab:health` run, which returned ten
passes including the two expected refusals.

```text
InjazEdu allowlisted tables: 11 before / 11 after
SHA256 of sorted table-name + exact-count rows:
217ac39211d4b54f80f44c890592faaa5597e244d6d7787ce4b4d5b03ece0ec3 (both)

Lab included tables: 8 before / 8 after
SHA256 of sorted table-name + exact-count rows:
4baf79ada9e8f40cd8fdaff3968399f2eedaeb6fa63a6bdcda71cb18eb1ef01d (both)
```

The source set was `categories`, `courses`, `chapters`, `lectures`, `quizzes`, `sections`,
`questions`, `options`, `quiz_files`, `results`, and `question_result`. The Lab set was `cache`,
`cache_locks`, `lab_job_probes`, `lab_persistence_marker`, `migrations`, `password_reset_tokens`,
`sessions`, and `users`. As T030 requires, the Lab comparison excluded `lab_vector_probes` and the
queue tables `jobs`, `job_batches`, and `failed_jobs`, which Laravel owns.

Unchanged counts are the specified SC-004/SC-010 compensating control; by themselves they prove
unchanged cardinality, not the absence of an update or a delete/reinsert pair. The stronger boundary
comes from the same run's source-write refusal plus inspection and tests showing that the stateless
service exposes no persistence path and its database probe executes only `SELECT 1` and reads the
server version.
