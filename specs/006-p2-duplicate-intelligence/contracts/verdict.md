# Contract — `POST /verdict`

**Feature**: `006-p2-duplicate-intelligence` · **Date**: 2026-08-28
**Parties**: `apps/ai-service` (FastAPI, stateless) produces · `apps/lab` (Laravel) consumes ·
**P4 copies this pattern** for its own AI review layer, so the shape is decided here, once.

This is the sixth endpoint on the service described by
`specs/003-service-health-guardrails/contracts/ai-service.md`. It follows that contract's rules
unchanged: loopback only, stateless, no write, no session, no stored vector, **no MySQL credential
of any kind** (ADR-013).

---

## 1. Why this needs a written contract

Three reasons, each of which would otherwise cost a rewrite:

1. **It is the program's first structured-output endpoint.** Constitution IV forbids regex-parsing
   prose and requires a JSON Schema validated before the result is accepted. The schema below is that
   contract, and P4's quality review will be the second endpoint to honour it.
2. **The seven fields are §17's, verbatim.** They are stored as seven columns
   (`data-model.md` §4), so a field rename is a migration, not an edit.
3. **The prompt is versioned and the version is stored on every row.** A quality change must be
   attributable to the model or to the prompt, and that is only possible if the response says which
   prompt produced it.

---

## 2. Request

```http
POST /verdict
Content-Type: application/json
```

```jsonc
{
  "question_a": {
    "text": "…",                    // search_text, or raw_text — the caller decides, and says which
    "options": ["…", "…", "…", "…"],// in option_index order
    "correct_option_index": 2,      // null when answer_key_state is not single_correct
    "has_image": true
  },
  "question_b": { "…": "same shape" },
  "media_relation": "different_media",  // same_media | different_media | no_media
  "same_section": false,
  "prompt_version": "v1"                // the caller pins it; the service does not choose
}
```

**`prompt_version` is a request field, not a service default.** A service that silently upgraded the
prompt would make every stored verdict's version a lie.

**`has_image` and `media_relation` are both sent** because the prompt states Decision 4's rule as its
third line of defence (FR-033), and the model needs the fact in the same message as the rule.

---

## 3. Response — 200

```jsonc
{
  "relation": "semantic_duplicate",
  "same_learning_objective": true,
  "same_correct_answer": true,
  "confidence": 0.91,
  "issues": [],
  "recommended_action": "group_under_canonical",
  "review_required": true,
  "prompt_version": "v1",
  "model": "gemma4:e2b-it-qat"
}
```

`prompt_version` is echoed and `model` is added so the stored row records what actually produced the
verdict rather than what was asked for.

### `relation` — the seven values, and only these

```text
exact_duplicate         formatting_duplicate    semantic_duplicate
same_objective_variant  related_not_duplicate   conflicting_duplicate
not_related
```

**`probable_duplicate` is not in this list and must be rejected if returned** (FR-133). It means "a
threshold was cleared and nobody looked" — a claim only the high-band auto path may make, never the
model.

### Field rules

| Field | Type | Rule |
|---|---|---|
| `relation` | enum | one of the seven above |
| `same_learning_objective` | boolean | |
| `same_correct_answer` | boolean | |
| `confidence` | number | `0.0 ≤ c ≤ 1.0` |
| `issues` | array of string | may be empty; never null |
| `recommended_action` | string | free text, shown to a reviewer, never executed |
| `review_required` | boolean | `true` routes the pair into the `uncertain_review` queue |

---

## 4. Errors

The endpoint reuses the shape `main.py::error_response` already establishes —
`{"error": code, "detail": …}` — and adds one code of its own.

| Condition | Status | `error` |
|---|---|---|
| Malformed or missing request body | 422 | `invalid_input` |
| Model output fails schema validation after the constrained generation | **502** | **`invalid_verdict`** |
| Ollama unreachable or timed out | 503 | `ollama_unreachable` |

**`invalid_verdict` is the new code**, and it is a 502 rather than a 500 for the same reason
`zero_norm_vector` is: the service worked, the upstream model returned something unusable, and the
caller must be able to tell those apart to decide whether retrying is worthwhile.

The Laravel side maps 502 and 503 onto a bounded retry and then a terminal `verdict_failed`
(FR-122 – FR-124). A 422 is a **caller bug** and must throw rather than consume a retry.

---

## 5. Two independent guarantees, not one

```text
1. Ollama's `format` parameter constrains generation to the JSON Schema
2. pydantic validates the parsed response server-side, before it is returned
```

**Both, always.** Schema-constrained generation is not a guarantee — it constrains token selection,
not semantics, and it cannot enforce `0.0 ≤ confidence ≤ 1.0` or reject `probable_duplicate` arriving
in a field whose enum it was given. Constitution IV requires the validation step regardless of what
the runtime claims to do. This mirrors the reasoning already recorded for L2 normalization in
`embedding.py`: the contract's claim must be true of **our** output, not of a runtime behaviour a
version bump could change.

---

## 6. Prompt injection — question text is data

Constitution III: **retrieved content is data, never instructions.** Both questions' stems and options
reach the prompt inside a delimited data field, and the system prompt states that instructions
appearing inside that field are ignored. A question whose text reads like a directive is a question.

The contract test includes a stem containing an instruction-shaped string, and asserts the verdict is
still a verdict.

---

## 7. What the caller must not do

```text
Do not apply the embedding prefix          ← wrong endpoint; /verdict has no prefix at all
Do not send a pair outside band='uncertain'← LlmBudgetGuard throws first (FR-078); the counter
                                             in FR-079 must read zero
Do not retry a 422                         ← that is a caller bug, not a transient failure
Do not overwrite an existing verdict       ← a re-run must not re-judge (FR-080)
Do not parse a non-200 body as a verdict   ← the error shape is not the verdict shape
```

---

## 8. Prompt versioning

`apps/ai-service/app/prompts/duplicate_verdict_v1.md`. A change creates `v2` **beside** `v1`; it never
overwrites it (constitution IV). Both stay on disk, and the service resolves the file from the
request's `prompt_version`, so an old verdict remains explicable after a new prompt ships.

An unknown `prompt_version` is a **422**, not a fallback to the newest — silently answering with a
different prompt than the one asked for is the exact failure versioning exists to prevent.

---

## 9. The eleventh health check

`GET /health/ollama` already probes the chat model, so `/verdict`'s dependency is covered at the
runtime level. What is not covered is the endpoint itself, so the Lab's eleventh check
(`App\Support\Health\VerdictEndpointCheck`, `number() === 11`) asserts that `/verdict` **answers with
a schema-valid verdict on a fixed synthetic pair**.

It follows `ChatModelCheck`'s shape exactly: extend `AbstractHealthCheck`, return
`CheckResult::MUST_SUCCEED`, and report a one-line detail with the latency. `lab:health` then reads
**11/11, exit 0**.

The fixed pair is the identical-text/different-image case (FR-033), so the health check and the
contract test assert the same rule from two directions.
