-- ============================================
-- Update Titan Email Provider credentials
-- Email: system@flasherpsystem.com
-- SMTP:  smtp.titan.email:465 (SSL)
-- ============================================

UPDATE `erp_email_providers`
SET provider_name = 'Titan Email',
    email = 'system@flasherpsystem.com',
    smtp_username = 'system@flasherpsystem.com',
    smtp_password = '!)O?lZB*OP4p(Fl',
    smtp_host = 'smtp.titan.email',
    smtp_port = 465,
    email_encryption = 'SSL'
WHERE id = 1;
