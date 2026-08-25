# tests/test_embedding_contract.py — the embedding contract, asserted.
#
# The contract is `eg300m-qat-q4_0/sim-v1/768/l2norm` (contracts/ai-service.md,
# §12.2). These tests exist so a drift in any component fails loudly here
# rather than silently invalidating stored vectors later.
#
# Most tests call the real runtime — the contract is a property of the running
# system, and a mocked model proves nothing about it. They skip cleanly when
# the runtime is absent. The zero-norm case is mocked, because the real model
# must never produce one (N1).

import math

import httpx
import pytest

from app.config import load_settings
from app.embedding import EmbeddingClient, ZeroNormVectorError

settings = load_settings()


def runtime_available() -> bool:
    try:
        r = httpx.get(f"http://{settings.ollama_host}/api/version", timeout=2)
        return r.status_code == 200
    except httpx.HTTPError:
        return False


requires_runtime = pytest.mark.skipif(
    not runtime_available(), reason="model runtime not reachable"
)


@pytest.fixture
def client():
    return EmbeddingClient(settings)


@requires_runtime
async def test_dimension_is_768(client):
    async with httpx.AsyncClient() as http:
        result = await client.embed(http, "ما هو الرقم الهيدروجيني للماء النقي؟")
    assert len(result.vector) == 768


@requires_runtime
async def test_l2_norm_is_one(client):
    async with httpx.AsyncClient() as http:
        result = await client.embed(http, "اختبار")
    norm = math.sqrt(sum(v * v for v in result.vector))
    assert norm == pytest.approx(1.0, abs=1e-5)  # SC-016


@requires_runtime
async def test_prefix_is_applied_server_side(client):
    """The same text with and without the prefix must produce DIFFERENT
    vectors — proving the service applies the prefix (one owner, FR-004)."""
    text = "اختبار"
    prefixed = settings.prefix_template.replace("{text}", text)
    async with httpx.AsyncClient() as http:
        via_service = await client.embed(http, text)
        # Bypass the service's prefixing by calling the runtime directly.
        r = await http.post(
            f"http://{settings.ollama_host}/api/embed",
            json={"model": settings.embedding_model, "input": prefixed},
        )
        direct = r.json()["embeddings"][0]
    assert len(direct) == len(via_service.vector)
    same = all(a == pytest.approx(b, abs=1e-6) for a, b in zip(direct, via_service.vector))
    # The service's output equals the prefixed runtime output (it owns the
    # prefix), and therefore differs from what raw text would have produced.
    assert same


@requires_runtime
async def test_prefix_changes_the_vector(client):
    text = "اختبار"
    async with httpx.AsyncClient() as http:
        r = await http.post(
            f"http://{settings.ollama_host}/api/embed",
            json={"model": settings.embedding_model, "input": text},
        )
        raw = r.json()["embeddings"][0]
        via_service = await client.embed(http, text)
    assert any(
        a != pytest.approx(b, abs=1e-6) for a, b in zip(raw, via_service.vector)
    ), "prefixed and raw text must not produce the same vector"


@requires_runtime
async def test_overlength_input_reports_truncated(client):
    async with httpx.AsyncClient() as http:
        result = await client.embed(http, "جملة عربية طويلة للاختبار. " * 400)
    assert result.truncated is True  # N2
    assert result.prompt_eval_count >= result.context_length
    assert result.context_length > 0  # read from /api/show, never hard-coded


async def test_zero_norm_vector_raises(client):
    """A zero-norm runtime response is an error, never a NaN (N1)."""

    def handler(request: httpx.Request) -> httpx.Response:
        if request.url.path == "/api/embed":
            return httpx.Response(
                200, json={"embeddings": [[0.0] * 768], "prompt_eval_count": 3}
            )
        if request.url.path == "/api/show":
            return httpx.Response(
                200, json={"model_info": {"gemma3.context_length": 2048}}
            )
        return httpx.Response(404)

    transport = httpx.MockTransport(handler)
    async with httpx.AsyncClient(transport=transport) as http:
        with pytest.raises(ZeroNormVectorError):
            await client.embed(http, "anything")


async def test_context_length_is_cached(client):
    calls = 0

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal calls
        if request.url.path == "/api/show":
            calls += 1
            return httpx.Response(
                200, json={"model_info": {"gemma3.context_length": 2048}}
            )
        if request.url.path == "/api/embed":
            return httpx.Response(
                200, json={"embeddings": [[0.5] * 768], "prompt_eval_count": 3}
            )
        return httpx.Response(404)

    transport = httpx.MockTransport(handler)
    async with httpx.AsyncClient(transport=transport) as http:
        await client.embed(http, "one")
        await client.embed(http, "two")
    assert calls == 1
