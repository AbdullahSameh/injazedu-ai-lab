-- §6.2 query 14 — كم سؤالًا لديه عدد إجابات يكفي للإحصاء؟
--
-- Tables read : question_result
-- Allowlist   : profile-only — question_result is on profile_tables
--               (apps/lab/config/lab.php): readable as aggregates, NEVER
--               copyable. It left source_tables on 2026-08-26 (ADR-022) when
--               the raw answer mirror was replaced by derived statistics.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.2.

-- 14) كم سؤالًا لديه عدد إجابات يكفي للإحصاء؟
SELECT bucket, COUNT(*) AS questions FROM (
  SELECT question_id, CASE
    WHEN COUNT(*) >= 100 THEN 'a_100_plus'
    WHEN COUNT(*) >=  30 THEN 'b_30_to_99'
    WHEN COUNT(*) >=  10 THEN 'c_10_to_29'
    ELSE 'd_under_10' END AS bucket
  FROM question_result GROUP BY question_id
) t GROUP BY bucket ORDER BY bucket;
