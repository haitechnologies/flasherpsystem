-- ============================================================================
-- Migration: CS Agent → Departments Remap (2026-08-02)
-- Run: mysql --force -u user -p db < this_file.sql
-- Remaps cs_agent FK from erp_cs_agents to erp_departments
-- ============================================================================
-- Adjust prefix if needed: replace 'erp_' with your actual prefix below.

UPDATE erp_jobs j
JOIN erp_cs_agents ca ON j.cs_agent = ca.id
JOIN erp_departments d ON d.department = ca.name
SET j.cs_agent = d.id;

UPDATE erp_customers c
JOIN erp_cs_agents ca ON c.cs_agent = ca.id
JOIN erp_departments d ON d.department = ca.name
SET c.cs_agent = d.id;

UPDATE erp_vendors v
JOIN erp_cs_agents ca ON v.cs_agent = ca.id
JOIN erp_departments d ON d.department = ca.name
SET v.cs_agent = d.id;

-- Verify (optional): should return 0 rows - meaning no unmapped cs_agent values left
-- SELECT j.id, j.cs_agent FROM erp_jobs j
-- LEFT JOIN erp_departments d ON j.cs_agent = d.id
-- WHERE j.cs_agent > 0 AND j.cs_agent NOT IN (1,2,3) AND d.id IS NULL;

-- SELECT c.id, c.cs_agent FROM erp_customers c
-- LEFT JOIN erp_departments d ON c.cs_agent = d.id
-- WHERE c.cs_agent > 0 AND c.cs_agent NOT IN (1,2,3) AND d.id IS NULL;

-- SELECT v.id, v.cs_agent FROM erp_vendors v
-- LEFT JOIN erp_departments d ON v.cs_agent = d.id
-- WHERE v.cs_agent > 0 AND v.cs_agent NOT IN (1,2,3) AND d.id IS NULL;
