# Data Model — Service, Health Matrix & Guardrails

What this increment creates or configures. Every measured figure here traces to
[notes.md](./notes.md) or to `002/notes.md`; nothing is assumed.

This increment adds **one table** to the Lab database and **three shapes** that two components each
must agree on. Everything else it creates is behaviour, not data.

---

## 1. The Health Result

The value object the command renders as a table and the panel renders as rows. One per check, ten per
run. Held in memory only — never persisted (FR-019, operator decision 2026-08-22).

| Field | Type | Note |
|---|---|---|
| `number` | 1–10 | fixed; the order is load-order-significant (N5) |
| `name` | string | English, operator-facing (Constitution VI) |
| `target` | string | what it reached for — `postgres:5433`, `ai-service:8001`, `ollama:11434`, `mysql:3306`, `queue`, `injazedu.questions` |
| `expectation` | `must_succeed` \| **`must_be_refused`** | printed on the line itself (FR-013) |
| `outcome` | `pass` \| `fail` \| `skipped` | `skipped` exits non-zero too (FR-014) |
| `detail` | string | on failure, names the target **and** the reason (FR-015) |

`expectation` is a field rather than a comment because checks 9 and 10 read backwards otherwise —
"the write failed → PASS" invites a future reader to "fix" a working check into a broken one.

### The ten checks

| # | Name | Target | Expectation | Proves |
|---|---|---|---|---|
| 1 | Lab database | `postgres:5433` | must succeed | the default connection is live |
| 2 | AI service | `ai-service:8001` | must succeed | liveness only — no dependency of the service |
| 3 | Queue | `queue` | must succeed | a job **executed** by a worker that has since exited (FR-016) |
| 4 | Service → Lab database | `ai-service:8001/health/db` | must succeed | the service's own reach |
| 5 | Service → chat model | `gemma4:e2b-it-qat` | must succeed | **runs before 6** (N5) |
| 6 | Service → embedding model | `embeddinggemma:300m-qat-q4_0` | must succeed | with the mandatory prefix |
| 7 | pgvector round-trip | `postgres:5433` | must succeed | 768 floats out and back, **exactly equal** (N3) |
| 8 | InjazEdu source | `injazedu.questions` | must succeed | count **with** `snapshot_taken_at` (FR-018) |
| 9 | Source write attempt | `injazedu` | **must be refused** | ADR-021 guards 1 and 2 still hold |
| 10 | Forbidden table | `injazedu.users` | **must be refused** | ADR-021 guard 3 still holds, naming the table |

Check 5 before check 6 is not cosmetic: on this 16 GB machine loading the 276 MiB embedding runner
first causes the scheduler to evict it when the 3,393 MiB chat runner loads (N5, `002` N5). The
matrix must **not** assert both are simultaneously resident — that was `002`'s measurement and it is
order-dependent.

---

## 2. The Vector Probe Row

The only table this increment adds. Follows `lab_job_probes` exactly: a fixed id, so re-running the
check never accumulates rows (`002` notes N4).

```php
Schema::create('lab_vector_probes', function (Blueprint $table) {
    $table->unsignedInteger('id')->primary();   // always 1
    $table->vector('embedding', 768);           // native in Laravel 13 (N4)
    $table->timestamp('written_at')->nullable();
});
```

| Field | Value |
|---|---|
| `id` | always `1` — idempotent by construction |
| `embedding` | 768 float32, **deterministically generated**, never a model output (N3) |
| `written_at` | when check 7 last ran |

**The vector is generated, not embedded.** Check 7 exists to prove pgvector stores and returns a
768-dimension vector; a model call inside it would make a runtime failure look like a database
failure. Check 6 already covers the model. The value read back is compared for **exact equality** —
pgvector round-trips float32 textually intact (N3), so a threshold would be weaker than the truth.

**No column here can hold personal data** (FR-025), which is why `NoPiiInLabSchemaTest` passes over it
unchanged.

---

## 3. The Embedding Contract

One opaque string, fixed here, carried by every vector from P2 onward. §12.2 is explicit: changing any
component **silently invalidates every stored vector**.

```text
EMBEDDING_CONFIG_VERSION = eg300m-qat-q4_0/sim-v1/768/l2norm
                           └─ model ──┘ └prefix┘ └d┘ └ norm ┘
```

| Component | Value | Fixed by |
|---|---|---|
| model tag | `embeddinggemma:300m-qat-q4_0` → `eg300m-qat-q4_0` | §12.1, verified in the registry |
| prefix template | `task: sentence similarity \| query: {text}` → `sim-v1` | §12.2 — **mandatory**, symmetric, applied by the **service** |
| dimension | `768` | §12.2; Matryoshka 512 is a P2 decision needing a measurement first (FR-006) |
| normalization | `l2norm` | operator decision 2026-08-22 — performed by the service (N1) |

**The prefix has exactly one owner.** If a caller pre-applies it and the service applies it again, the
vectors are wrong and nothing errors. The service applies it; callers send raw text.

**The normalization claim is made true, not observed.** The runtime already returns unit-length
vectors (N1: norm `0.9999997`), so normalizing again changes no value today. It is done anyway because
the string claims `l2norm` about *our* output — an undocumented runtime behaviour that a version bump
could change is not something to build a contract on. SC-016 asserts the norm on what we return.

**Zero-norm is an error, not something to normalize** (N1) — dividing by it yields `NaN` and every
later comparison fails silently.

---

## 4. The Truncation Signal

EmbeddingGemma's window is **2048 tokens**. The runtime truncates silently and returns a well-formed
vector of the first 2048 tokens, with no error and no flag (N2).

| Field | Source | Rule |
|---|---|---|
| `prompt_eval_count` | the runtime's response | tokens actually embedded |
| `context_length` | `/api/show` → `gemma3.context_length` | read, **never hard-coded** |
| `truncated` | derived | `prompt_eval_count >= context_length` |

`>=` rather than `>`: a text landing exactly on the boundary is reported as possibly truncated —
conservative in the safe direction. Reported in **both** the response and the log line (FR-007), which
is what makes §12.2's requirement to record truncation cases available to P2's 29,142-question batch
from its first row.

---

## 5. Environment Surface — Three Files Now

`002` established two files, each read by its own tool. The service adds a third. The rule is
unchanged: **each tool reads its own file**.

| File | Read by | Tracked | Adds this increment |
|---|---|---|---|
| `/.env` | Docker Compose | no | — |
| `apps/lab/.env` | Laravel | no | `STUDENT_REF_PEPPER`, `EMBEDDING_CONFIG_VERSION`, `AI_SERVICE_URL` |
| `apps/ai-service/.env` | the service | no | all of its keys — Lab DB, runtime URL, `EMBEDDING_CONFIG_VERSION` |

Each has a committed `.env.example` listing every key with **no values**.

**`EMBEDDING_CONFIG_VERSION` appears in two files.** It must match, for the same reason `DB_PASSWORD`
and `LAB_DB_PASSWORD` must match — and `verify-ai-service.sh` asserts it, the way `verify-lab-app.sh`
already asserts the password pair.

**`apps/ai-service/.env` holds no MySQL key of any kind** (FR-003, ADR-013). Every read of the source
goes through Laravel's guarded connection or does not happen. The verification script asserts the
absence by name, not by inspection.

### `STUDENT_REF_PEPPER`

| Property | Value |
|---|---|
| Generated | once, here, locally (§8 item F) |
| Lives in | `apps/lab/.env` only — untracked |
| Committed template | listed, **no value** |
| Consumed by | **nothing in this increment** (FR-023) |
| Backed up | off-machine, by the operator |

Regenerating it after P1 has stored `student_ref` values breaks the link between old and new rows
irreversibly. It is created now so that P1 starts with it rather than pausing for a human step — the
container, never its contents.
