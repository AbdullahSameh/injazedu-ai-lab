-- infrastructure/postgres/init.sql — Lab database initialization.
--
-- WARNING: this file runs ONLY when the data volume is first created
-- (docker-entrypoint-initdb.d semantics). It does NOT re-run on container
-- restart, and editing it has no effect on an existing volume.
-- Therefore verification must always query the live database
-- (SELECT ... FROM pg_extension), never this file — a file-based check can
-- report success on a database that lacks the extension (research R6).
--
-- Capabilities required by the Lab (data-model §6a):
--   vector  — vector similarity, needed by P2 duplicate detection
--   pg_trgm — trigram text matching, needed by P2 cascade layer 2

CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
