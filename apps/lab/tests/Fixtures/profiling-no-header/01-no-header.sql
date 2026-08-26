-- Fixture — no "Tables read :" header line, on purpose.
--
-- Used to prove QueryFile::fromFile() treats a missing header as a hard
-- failure, never a default-to-empty (FR-002, notes.md N5).

SELECT 1;
