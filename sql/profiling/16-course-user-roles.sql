-- §6.2 query 16 — من هم مستخدمو course_user؟ مدربون أم طلاب؟
--
-- Tables read : course_user, user_roles, roles
-- Allowlist   : PROFILE-ONLY — all three are on profile_tables
--               (apps/lab/config/lab.php, added 2026-08-23, P0 §3.2):
--               READ AS COUNTS ONLY, never copied into the Lab.
--               Before the split this query was blocked by a rule written about
--               copying; with the split in place NO query in this pack is blocked.
--
-- Written 2026-08-23 (P0 004, FR-019). NOT executed in P0 (FR-021) — first run
-- belongs to P1 المرحلة 1. Query is verbatim from core plan §6.2.

-- 16) من هم مستخدمو course_user؟ مدربون أم طلاب؟
SELECT r.name AS role, COUNT(DISTINCT cu.user_id) AS users_in_course_user
FROM course_user cu
JOIN user_roles ur ON ur.user_id = cu.user_id
JOIN roles r       ON r.id = ur.role_id
GROUP BY r.name ORDER BY users_in_course_user DESC;
