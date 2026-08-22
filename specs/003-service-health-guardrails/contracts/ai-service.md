# Contract — `apps/ai-service`

**Base**: `http://127.0.0.1:8001` — loopback only, refused from every other address (FR-001).
**Consumers**: `apps/lab` health checks 2, 4, 5, and 6. From P2 onward, the embedding batch.
**Stateless** (ADR-013): no migration, no write, no session, no stored vector.

This file exists because Laravel's checks are written against endpoints Python implements. Both sides
must agree before either is written.

---

## Conventions

- All responses are JSON. All errors carry `{"error": "<machine_code>", "detail": "<english text>"}`.
- Every request produces **exactly one** structured log line (§Logging below).
- Every response carries `X-Request-Id`, matching the log line's `request_id`.
- No endpoint reads or writes the InjazEdu source. The service holds no MySQL credential (FR-003).
- Operator-facing text is English (Constitution VI).

---

## `GET /health`

Liveness. Touches neither the database nor the model runtime — that independence is what makes check 2
distinguishable from checks 4–6.

```json
{ "status": "ok", "service": "injazedu-lab-ai-service", "version": "0.1.0" }
```

`200` always, if the process is up. There is no failure mode that returns a body.

---

## `GET /health/db`

The Lab database is reachable. **Read-only** — a `SELECT 1` and the server version, nothing more.

```json
{ "status": "ok", "database": "injazedu_lab", "host": "127.0.0.1:5433", "server_version": "17.x" }
```

| Status | Condition | Body |
|---|---|---|
| `200` | reachable | above |
| `503` | unreachable | `{"error": "db_unreachable", "detail": "…"}` |

---

## `GET /health/ollama`

Both models are present and answer. Reports each **separately** so one failing does not mask the
other.

```json
{
  "status": "ok",
  "host": "127.0.0.1:11434",
  "models": {
    "gemma4:e2b-it-qat":            { "status": "ok", "latency_ms": 47 },
    "embeddinggemma:300m-qat-q4_0": { "status": "ok", "latency_ms": 31 }
  }
}
```

The chat probe is a **minimal generation** — `num_predict: 1`, `num_ctx: 4096` — asserting that a
response came back, never its content. There is no prompt here: a prompt written in P0 would be an
unversioned prompt (Constitution IV) in a phase with no prompt registry.

`num_ctx` is passed **per call**, never set globally, because the cost is KV-cache memory (FR-008).

Measured latency (N5): chat cold **~4,819 ms**, warm **47–218 ms**; embed cold **~1,300 ms**.

| Status | Condition |
|---|---|
| `200` | both `ok` |
| `503` | the runtime is unreachable, or either model fails — `status` is `degraded`, and the `models` map still names which |

**Probe order is fixed: chat first, then embedding.** Reversing it evicts the embedding runner on this
16 GB machine (N5). This is a memory constraint, not a style choice.

---

## `GET /health/full`

Composes the three above. **Each section reports independently** — a failure in one must not collapse
the response into a single error (FR-002), or the operator learns less than from three separate calls.

```json
{
  "status": "degraded",
  "sections": {
    "service": { "status": "ok" },
    "db":      { "status": "ok", "…": "…" },
    "ollama":  { "status": "error", "error": "model_unavailable", "detail": "…" }
  }
}
```

`status` is `ok` only when every section is `ok`; otherwise `degraded`. HTTP `200` when `ok`, `503`
otherwise.

---

## `POST /embed`

**Verification only.** Returns a vector and persists nothing (FR-004, FR-006).

**Request**

```json
{ "text": "ما هو الرقم الهيدروجيني للماء النقي؟" }
```

`text` is **raw**. The service applies the mandatory prefix. A caller that pre-applies it produces
wrong vectors with no error — the prefix has exactly one owner.

**Response** `200`

```json
{
  "vector": [-0.032863766, 0.05793848, "…766 more"],
  "dimension": 768,
  "embedding_config_version": "eg300m-qat-q4_0/sim-v1/768/l2norm",
  "truncated": false,
  "prompt_eval_count": 21,
  "context_length": 2048
}
```

| Field | Guarantee |
|---|---|
| `vector` | exactly 768 floats, **L2 norm 1** within floating-point tolerance |
| `dimension` | always `768` — Matryoshka 512 is a P2 decision requiring a measurement first (FR-006) |
| `embedding_config_version` | the contract string; identical to the value in both `.env` files |
| `truncated` | `prompt_eval_count >= context_length` (N2) |
| `context_length` | read from `/api/show` → `gemma3.context_length`, **never hard-coded** |

**The prefix** is `task: sentence similarity | query: {text}` — symmetric, applied to both sides of
every later comparison (§12.2).

**Normalization** is performed by the service. The runtime already returns unit-length vectors
(N1: norm `0.9999997`), so this changes no value today; it is done so the contract's `l2norm` claim is
true of *our* output rather than of a runtime behaviour a version bump could change.

**Truncation is silent at the runtime** (N2): over-length input returns a well-formed vector of the
first 2048 tokens with no error and no flag. `truncated: true` is the only signal the caller gets, and
it appears in the log line as well.

| Status | Condition | Body |
|---|---|---|
| `200` | embedded | above |
| `422` | `text` missing or empty | `{"error": "invalid_input", …}` |
| `502` | the runtime returned a zero-norm vector | `{"error": "zero_norm_vector", …}` — not normalizable; `NaN` here would poison every later comparison silently (N1) |
| `503` | the runtime is unreachable | `{"error": "ollama_unreachable", …}` |

---

## Logging

One structured JSON line per request, on stdout (FR-009):

```json
{"request_id":"…","endpoint":"/embed","method":"POST","model":"embeddinggemma:300m-qat-q4_0",
 "latency_ms":31,"status":200,"truncated":false}
```

| Field | Always present |
|---|---|
| `request_id` | yes — matches `X-Request-Id` |
| `endpoint`, `method`, `status`, `latency_ms` | yes |
| `model` | when a model was called; `null` otherwise |
| `truncated` | on `/embed` only |

This is what makes §12.4's latency measurement and المرحلة 10's budget check a matter of reading a
log rather than re-measuring by hand.

---

## What this service must never do

```text
Write to any database — no migration, no INSERT, no UPDATE      (ADR-013, FR-003)
Hold an InjazEdu MySQL credential of any kind                   (FR-003)
Store a vector                                                  (FR-004)
Define or version a prompt                                      (Constitution IV — P2 owns prompts)
Set a global context length                                     (FR-008)
Compute a similarity, a duplicate score, or any metric          (FR-011)
Bind to anything but loopback                                   (FR-001)
```

SC-010 is the compensating control for the first: the Lab database's row count is unchanged across a
full health run.
