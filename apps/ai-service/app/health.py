# app/health.py — three independent probes (FR-002).
#
# Each probe reports its own state and no probe's failure masks another:
# /health/full composes their results, it does not collapse them.
#
# The runtime probe calls CHAT FIRST, then embedding. Reversing the order
# evicts the embedding runner on this 16 GB machine (notes N5). This is a
# memory constraint, not a style choice.
#
# The chat probe is a minimal generation — num_predict: 1, and num_ctx: 4096
# passed PER CALL, never globally, because the cost is KV-cache memory
# (FR-008). It asserts a response came back, never its content.

import time

import asyncpg
import httpx

from .config import Settings


async def probe_db(settings: Settings) -> dict:
    """SELECT 1 plus the server version — and nothing else (read-only, FR-003)."""
    try:
        conn = await asyncpg.connect(
            host=settings.lab_db_host,
            port=settings.lab_db_port,
            database=settings.lab_db_name,
            user=settings.lab_db_user,
            password=settings.lab_db_password,
            timeout=5,
        )
    except (asyncpg.PostgresError, OSError, TimeoutError) as exc:
        return {
            "status": "error",
            "error": "db_unreachable",
            "detail": str(exc),
        }
    try:
        await conn.fetchval("SELECT 1")
        server_version = await conn.fetchval("SHOW server_version")
    finally:
        await conn.close()
    return {
        "status": "ok",
        "database": settings.lab_db_name,
        "host": f"{settings.lab_db_host}:{settings.lab_db_port}",
        "server_version": server_version,
    }


async def probe_ollama(settings: Settings, client: httpx.AsyncClient) -> dict:
    """Chat first, then embedding — the load order that keeps both resident."""
    base = f"http://{settings.ollama_host}"
    models: dict[str, dict] = {}

    async def probe_chat() -> dict:
        start = time.perf_counter()
        response = await client.post(
            f"{base}/api/generate",
            json={
                "model": settings.chat_model,
                "prompt": "",
                "stream": False,
                "options": {"num_predict": 1, "num_ctx": 4096},
            },
            timeout=30,
        )
        response.raise_for_status()
        response.json()  # a response came back; its content is never asserted
        return {"status": "ok", "latency_ms": round((time.perf_counter() - start) * 1000)}

    async def probe_embed() -> dict:
        start = time.perf_counter()
        prompt = settings.prefix_template.replace("{text}", "health probe")
        response = await client.post(
            f"{base}/api/embed",
            json={"model": settings.embedding_model, "input": prompt},
            timeout=30,
        )
        response.raise_for_status()
        payload = response.json()
        if not payload.get("embeddings"):
            raise ValueError("no embedding returned")
        return {"status": "ok", "latency_ms": round((time.perf_counter() - start) * 1000)}

    # Fixed order: chat, THEN embedding (N5).
    for name, probe in (
        (settings.chat_model, probe_chat),
        (settings.embedding_model, probe_embed),
    ):
        try:
            models[name] = await probe()
        except (httpx.HTTPError, httpx.InvalidURL, ValueError, KeyError) as exc:
            models[name] = {
                "status": "error",
                "error": "model_unavailable",
            "detail": str(exc) or repr(exc),
            }

    ok = all(m["status"] == "ok" for m in models.values())
    result: dict = {"status": "ok" if ok else "degraded", "host": settings.ollama_host}
    result["models"] = models
    return result
