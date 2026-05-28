CREATE TABLE IF NOT EXISTS `{{prefix}}sso_users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` varchar(190) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `given_name` varchar(190) NOT NULL,
  `family_name` varchar(190) NOT NULL,
  `display_name` varchar(190) NOT NULL,
  `mail` varchar(190) NOT NULL,
  `imap_user` varchar(190) NOT NULL,
  `quota` varchar(50) NOT NULL DEFAULT '512 MB',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
