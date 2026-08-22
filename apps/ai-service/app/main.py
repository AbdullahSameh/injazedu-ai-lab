# app/main.py — the five endpoints of contracts/ai-service.md.
#
#   GET  /health         liveness, touches nothing             200 always
#   GET  /health/db      Lab database reachable (read-only)    200 | 503
#   GET  /health/ollama  both models answer, reported singly   200 | 503
#   GET  /health/full    the three sections, none masked       200 | 503
#   POST /embed          the embedding contract, stateless     200 | 422 | 502 | 503
#
# The service is stateless (ADR-013): no write, no session, no stored vector,
# no MySQL credential of any kind (FR-003).

from contextlib import asynccontextmanager

import httpx
from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from .config import load_settings
from .embedding import EmbeddingClient, OllamaUnreachableError, ZeroNormVectorError
from .health import probe_db, probe_ollama
from .logging import RequestLoggingMiddleware

SERVICE_NAME = "injazedu-lab-ai-service"
SERVICE_VERSION = "0.1.0"


def error_response(status: int, code: str, detail: str) -> JSONResponse:
    return JSONResponse(status_code=status, content={"error": code, "detail": detail})


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = load_settings()
    app.state.settings = settings
    app.state.embedding = EmbeddingClient(settings)
    app.state.http = httpx.AsyncClient()
    yield
    await app.state.http.aclose()


app = FastAPI(title=SERVICE_NAME, version=SERVICE_VERSION, lifespan=lifespan)
app.add_middleware(RequestLoggingMiddleware)


class EmbedRequest(BaseModel):
    text: str = Field(min_length=1)


@app.get("/health")
async def health() -> dict:
    return {"status": "ok", "service": SERVICE_NAME, "version": SERVICE_VERSION}


@app.get("/health/db")
async def health_db(request: Request):
    result = await probe_db(request.app.state.settings)
    if result["status"] != "ok":
        return error_response(503, "db_unreachable", result["detail"])
    return result


@app.get("/health/ollama")
async def health_ollama(request: Request):
    result = await probe_ollama(request.app.state.settings, request.app.state.http)
    if result["status"] != "ok":
        return JSONResponse(status_code=503, content=result)
    return result


@app.get("/health/full")
async def health_full(request: Request):
    settings = request.app.state.settings
    sections = {
        "service": {"status": "ok"},
        "db": await probe_db(settings),
        "ollama": await probe_ollama(settings, request.app.state.http),
    }
    ok = all(s["status"] == "ok" for s in sections.values())
    return JSONResponse(
        status_code=200 if ok else 503,
        content={"status": "ok" if ok else "degraded", "sections": sections},
    )


@app.post("/embed")
async def embed(request: Request, body: EmbedRequest):
    request.state.model = request.app.state.settings.embedding_model
    try:
        result = await request.app.state.embedding.embed(request.app.state.http, body.text)
    except ZeroNormVectorError as exc:
        return error_response(502, "zero_norm_vector", str(exc))
    except OllamaUnreachableError as exc:
        return error_response(503, "ollama_unreachable", str(exc))

    request.state.truncated = result.truncated
    return {
        "vector": result.vector,
        "dimension": request.app.state.settings.embedding_dimension,
        "embedding_config_version": request.app.state.settings.embedding_config_version,
        "truncated": result.truncated,
        "prompt_eval_count": result.prompt_eval_count,
        "context_length": result.context_length,
    }


@app.exception_handler(RequestValidationError)
async def invalid_input(request: Request, exc: RequestValidationError):
    return error_response(422, "invalid_input", "text is missing or empty")


@app.exception_handler(Exception)
async def unhandled(request: Request, exc: Exception):
    return error_response(500, "internal_error", str(exc))
