# app/logging.py — exactly one structured JSON line per request (FR-009).
#
# The line carries request_id, endpoint, method, model, latency_ms, status,
# and (on /embed) truncated. The same request_id goes back as the
# X-Request-Id response header. This is what makes latency measurement and the
# budget check a matter of reading a log rather than re-measuring by hand.

import json
import logging
import sys
import time
import uuid

from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import Response

logger = logging.getLogger("ai-service")
if not logger.handlers:
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(logging.Formatter("%(message)s"))
    logger.addHandler(handler)
    logger.setLevel(logging.INFO)
    logger.propagate = False


class RequestLoggingMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next) -> Response:
        request_id = str(uuid.uuid4())
        request.state.request_id = request_id
        start = time.perf_counter()
        response = await call_next(request)
        latency_ms = round((time.perf_counter() - start) * 1000)

        line = {
            "request_id": request_id,
            "endpoint": request.url.path,
            "method": request.method,
            "model": getattr(request.state, "model", None),
            "latency_ms": latency_ms,
            "status": response.status_code,
        }
        if request.url.path == "/embed":
            line["truncated"] = getattr(request.state, "truncated", None)

        logger.info(json.dumps(line, ensure_ascii=False))
        response.headers["X-Request-Id"] = request_id
        return response
