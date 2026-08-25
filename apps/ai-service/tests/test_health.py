import json

import httpx

from app.config import load_settings
from app.health import probe_ollama


async def test_runtime_health_embeds_with_the_mandatory_service_prefix():
    settings = load_settings()
    embedded_input = None

    def handler(request: httpx.Request) -> httpx.Response:
        nonlocal embedded_input

        if request.url.path == "/api/generate":
            return httpx.Response(200, json={"response": ""})
        if request.url.path == "/api/embed":
            embedded_input = json.loads(request.content)["input"]
            return httpx.Response(200, json={"embeddings": [[0.5] * 768]})

        return httpx.Response(404)

    async with httpx.AsyncClient(transport=httpx.MockTransport(handler)) as client:
        result = await probe_ollama(settings, client)

    assert result["status"] == "ok"
    assert embedded_input == settings.prefix_template.replace("{text}", "health probe")
