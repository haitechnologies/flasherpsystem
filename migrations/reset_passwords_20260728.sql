-- Password reset for all users except Imran Nazir (id=1)
-- Run on live database
START TRANSACTION;

UPDATE `erp_users` SET `password` = '$2y$10$2ddqrO4fjjyDnFVTMVBOoOXEfYvyUtmFTOshE/Y1FZBpiNUXpT0hu' WHERE `id` = 9;
UPDATE `erp_users` SET `password` = '$2y$10$TV5alJ5Dllz8EFcdUMbnsuerzEAVUECfHpPYjWWyGyKjrLeQJb9py' WHERE `id` = 3;
UPDATE `erp_users` SET `password` = '$2y$10$2KurQMumOiXtUk9Fis2tBu14l1zNZktEEmw8dJcNUG/..YRzS83SW' WHERE `id` = 4;
UPDATE `erp_users` SET `password` = '$2y$10$XKinR4d6YUg8VWM1uPWiW.smPSpwu1fMTyvI9mOrsUMd7r2/6Bc9a' WHERE `id` = 6;
UPDATE `erp_users` SET `password` = '$2y$10$zmFPvYyN4N0SpXDlUA2XK.ttuLxmJwn5GhYyRosB.n7ZGNFIrL6NW' WHERE `id` = 7;
UPDATE `erp_users` SET `password` = '$2y$10$NZUHt9JtLsSaQ9Oq/ls7Ve.Mufe2bnl2scuPOeEmxSfNPiz5Twvya' WHERE `id` = 11;
UPDATE `erp_users` SET `password` = '$2y$10$szvmADw0dsgf/j3jwOJ79.doGyITyWAWUXFlsywW8rcVvIxab5nmK' WHERE `id` = 2;

COMMIT;
