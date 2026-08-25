-- §6.2 query 15 — *** حسم غموض التسجيل *** course_user أم course_order؟
--
-- Tables read : course_user, course_order, orders
-- Allowlist   : PROFILE-ONLY — all three are on profile_tables
--               (apps/lab/config/lab.php, added 2026-08-23, P0 §3.2):
--               READ AS COUNTS ONLY, never copied into the Lab.
--               Before the split this query was blocked by a rule written about
--               copying; with the split in place NO query in this pack is blocked.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.2.

-- 15) *** حسم غموض التسجيل *** course_user أم course_order؟
SELECT 'course_user' AS src, COUNT(*) AS rows_,
       COUNT(DISTINCT user_id) AS users_, COUNT(DISTINCT course_id) AS courses_
FROM course_user
UNION ALL
SELECT 'course_order', COUNT(*),
       COUNT(DISTINCT o.user_id), COUNT(DISTINCT co.course_id)
FROM course_order co JOIN orders o ON o.id = co.order_id;
