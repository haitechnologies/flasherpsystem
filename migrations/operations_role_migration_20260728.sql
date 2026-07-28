-- ============================================================================
-- Migration: Operations Role Scoping (role_id=4)
-- Date: 2026-07-28
-- Description: Remove all non-CRM module permissions for the Operations role.
--              Keep only leads, lead_quotations, projects, jobs, job_statuses.
-- ============================================================================

DELETE p FROM erp_permissions p
JOIN erp_modules m ON m.id = p.module_id
WHERE p.role_id = 4
  AND m.slug NOT IN (
    'leads', 'lead_quotations', 'projects', 'jobs', 'job_statuses'
  );
