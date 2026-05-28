CREATE TABLE IF NOT EXISTS `{{prefix}}sso_user_groups` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `group_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `fk_sso_user_groups_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
