CREATE TABLE IF NOT EXISTS `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`username`, `password_hash`, `display_name`, `role`)
VALUES
  ('Stacy', '$2y$10$OhRutu5Ksbxu.UV4k8/USuvE3C4haQOhI/5nK1RJXrinI.9SRlosG', 'Stacy', 'admin'),
  ('簡晨芸', '$2y$10$4FLQOZ2ZuyoCW08YRE4f5uxn6J1VL0k2j4ZMpVz84N9cIBGwVN8KK', '簡晨芸', 'admin')
ON DUPLICATE KEY UPDATE
  `password_hash` = VALUES(`password_hash`),
  `display_name` = VALUES(`display_name`),
  `role` = VALUES(`role`);
