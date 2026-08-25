# app/embedding.py — the embedding contract, applied server-side (FR-004).
#
# The contract is `eg300m-qat-q4_0/sim-v1/768/l2norm` (§12.2):
#   model tag    embeddinggemma:300m-qat-q4_0
#   prefix       task: sentence similarity | query: {text}   — applied HERE,
#                the one owner. Callers send raw text; a caller that pre-applies
#                it produces wrong vectors with no error.
#   dimension    768
#   normalization L2 — performed HERE so the contract's `l2norm` claim is true
#                of our output, not of a runtime behaviour a version bump could
#                change (notes N1).
#
# Two failure rules, both measured:
#   N1  a zero-norm vector is an ERROR, never something to normalize — dividing
#       by zero yields NaN and poisons every later comparison silently.
#   N2  truncation is SILENT at the runtime. prompt_eval_count >= context_length
#       is the only signal. The window is read from /api/show and cached, never
#       hard-coded.

import math

import httpx

from .config import Settings


class OllamaUnreachableError(Exception):
    """The model runtime did not answer (503)."""


class ZeroNormVectorError(Exception):
    """The runtime returned a vector that cannot be normalized (502)."""


class EmbedResult:
    def __init__(
        self,
        vector: list[float],
        truncated: bool,
        prompt_eval_count: int,
        context_length: int,
    ) -> None:
        self.vector = vector
        self.truncated = truncated
        self.prompt_eval_count = prompt_eval_count
        self.context_length = context_length


class EmbeddingClient:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._base_url = f"http://{settings.ollama_host}"
        self._context_length: int | None = None

    async def context_length(self, client: httpx.AsyncClient) -> int:
        """Read the model's window from /api/show, once, and cache it (N2).

        The model reports its metadata under `gemma3.*` keys.
        """
        if self._context_length is not None:
            return self._context_length
        try:
            response = await client.post(
                f"{self._base_url}/api/show",
                json={"model": self._settings.embedding_model},
            )
            response.raise_for_status()
        except (httpx.HTTPError, httpx.InvalidURL) as exc:
            raise OllamaUnreachableError(str(exc)) from exc
        model_info = response.json().get("model_info", {})
        length = model_info.get("gemma3.context_length")
        if not isinstance(length, int) or length <= 0:
            raise OllamaUnreachableError(
                f"/api/show returned no usable context_length for "
                f"{self._settings.embedding_model}: {model_info!r}"
            )
        self._context_length = length
        return length

    async def embed(self, client: httpx.AsyncClient, text: str) -> EmbedResult:
        # The prefix has exactly one owner — this service (FR-004).
        prompt = self._settings.prefix_template.replace("{text}", text)
        try:
            response = await client.post(
                f"{self._base_url}/api/embed",
                json={"model": self._settings.embedding_model, "input": prompt},
            )
            response.raise_for_status()
        except (httpx.HTTPError, httpx.InvalidURL) as exc:
            raise OllamaUnreachableError(str(exc)) from exc

        payload = response.json()
        embeddings = payload.get("embeddings") or []
        if not embeddings or not embeddings[0]:
            raise OllamaUnreachableError("/api/embed returned no embedding")
        vector = [float(v) for v in embeddings[0]]

        norm = math.sqrt(sum(v * v for v in vector))
        if norm == 0.0:
            # Never normalize a zero vector into NaN (N1).
            raise ZeroNormVectorError(
                f"{self._settings.embedding_model} returned a zero-norm vector"
            )
        vector = [v / norm for v in vector]

        prompt_eval_count = int(payload.get("prompt_eval_count", 0))
        window = await self.context_length(client)
        truncated = prompt_eval_count >= window  # >=, conservative (N2)

        return EmbedResult(
            vector=vector,
            truncated=truncated,
            prompt_eval_count=prompt_eval_count,
            context_length=window,
        )
