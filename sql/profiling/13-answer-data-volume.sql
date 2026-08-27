-- §6.2 query 13 — حجم بيانات الإجابات المتاحة للإحصاء
--
-- Tables read : question_result
-- Allowlist   : profile-only — question_result is on profile_tables
--               (apps/lab/config/lab.php): readable as aggregates, NEVER
--               copyable. It left source_tables on 2026-08-26 (ADR-022) when
--               the raw answer mirror was replaced by derived statistics.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.2.

-- 13) حجم بيانات الإجابات المتاحة للإحصاء
SELECT COUNT(*) AS answers,
       COUNT(DISTINCT result_id)   AS results,
       COUNT(DISTINCT question_id) AS questions_with_data
FROM question_result;
