# app/config.py — the service's settings, loaded once at startup.
#
# Every key is required and none has a default. A missing key must fail loudly
# at startup rather than default silently — a silent default on
# EMBEDDING_CONFIG_VERSION is exactly how the embedding contract drifts
# (FR-005, §12.2).
#
# This file must never gain an InjazEdu MySQL key. The service holds no source
# credential (ADR-013, FR-003); every source read goes through Laravel's
# guarded connection.

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8")

    # Lab database (PostgreSQL) — used read-only: SELECT 1 + server version.
    lab_db_host: str
    lab_db_port: int
    lab_db_name: str
    lab_db_user: str
    lab_db_password: str

    # Model runtime (Ollama), e.g. 127.0.0.1:11434
    ollama_host: str

    # The embedding contract string. No default, by design.
    embedding_config_version: str

    # Bind address — loopback only (FR-001). Enforced, not trusted.
    service_host: str
    service_port: int

    # Fixed by the contract (contracts/ai-service.md); not configurable.
    embedding_dimension: int = 768
    chat_model: str = "gemma4:e2b-it-qat"
    embedding_model: str = "embeddinggemma:300m-qat-q4_0"
    prefix_template: str = "task: sentence similarity | query: {text}"


def load_settings() -> Settings:
    return Settings()
