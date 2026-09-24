-- siac_ia.cache definição

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.cache_locks definição

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.failed_jobs definição

CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.job_batches definição

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.jobs definição

CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.migrations definição

CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.password_reset_tokens definição

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.sessions definição

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.team_invitations definição

CREATE TABLE `team_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `team_id` bigint unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `invited_by` bigint unsigned NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_invitations_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- siac_ia.chart_series definição

CREATE TABLE `chart_series` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `chart_template_id` bigint unsigned NOT NULL,
  `monitored_system_id` bigint unsigned NOT NULL,
  `parameter_id` bigint unsigned NOT NULL,
  `axis` tinyint unsigned NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `options` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chart_series_team_id_foreign` (`team_id`),
  KEY `chart_series_template_team_fk` (`chart_template_id`,`team_id`),
  KEY `chart_series_parameter_scope_fk` (`parameter_id`,`team_id`,`monitored_system_id`),
  KEY `chart_series_order_idx` (`chart_template_id`,`sort_order`),
  CONSTRAINT `chart_series_parameter_scope_fk` FOREIGN KEY (`parameter_id`, `team_id`, `monitored_system_id`) REFERENCES `parameters` (`id`, `team_id`, `monitored_system_id`) ON DELETE CASCADE,
  CONSTRAINT `chart_series_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chart_series_template_team_fk` FOREIGN KEY (`chart_template_id`, `team_id`) REFERENCES `chart_templates` (`id`, `team_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.chart_templates definição

CREATE TABLE `chart_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT '0',
  `options` json DEFAULT NULL,
  `markers` json DEFAULT NULL,
  `y1_min` double DEFAULT NULL,
  `y1_max` double DEFAULT NULL,
  `y2_min` double DEFAULT NULL,
  `y2_max` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chart_templates_id_team_unique` (`id`,`team_id`),
  KEY `chart_templates_user_id_foreign` (`user_id`),
  KEY `chart_templates_team_id_is_favorite_index` (`team_id`,`is_favorite`),
  CONSTRAINT `chart_templates_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chart_templates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.monitored_systems definição

CREATE TABLE `monitored_systems` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `systems_id_team_unique` (`id`,`team_id`),
  KEY `monitored_systems_team_id_sort_order_index` (`team_id`,`sort_order`),
  KEY `monitored_systems_team_id_is_active_index` (`team_id`,`is_active`),
  CONSTRAINT `monitored_systems_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=464 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.parameter_daily_metrics definição

CREATE TABLE `parameter_daily_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `monitored_system_id` bigint unsigned NOT NULL,
  `parameter_id` bigint unsigned NOT NULL,
  `measured_date` date NOT NULL,
  `values_count` int unsigned NOT NULL DEFAULT '0',
  `average_value` double DEFAULT NULL,
  `minimum_value` double DEFAULT NULL,
  `maximum_value` double DEFAULT NULL,
  `out_of_limit_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdm_scope_date_unique` (`team_id`,`monitored_system_id`,`parameter_id`,`measured_date`),
  KEY `daily_metrics_parameter_scope_fk` (`parameter_id`,`team_id`,`monitored_system_id`),
  KEY `pdm_team_date_idx` (`team_id`,`measured_date`),
  KEY `pdm_team_system_date_idx` (`team_id`,`monitored_system_id`,`measured_date`),
  CONSTRAINT `daily_metrics_parameter_scope_fk` FOREIGN KEY (`parameter_id`, `team_id`, `monitored_system_id`) REFERENCES `parameters` (`id`, `team_id`, `monitored_system_id`) ON DELETE CASCADE,
  CONSTRAINT `parameter_daily_metrics_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1418181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.parameter_values definição

CREATE TABLE `parameter_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `monitored_system_id` bigint unsigned NOT NULL,
  `parameter_id` bigint unsigned NOT NULL,
  `measured_at` timestamp NOT NULL,
  `measured_date` date NOT NULL,
  `value` double DEFAULT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pv_parameter_measured_unique` (`parameter_id`,`measured_at`),
  KEY `parameter_values_created_by_foreign` (`created_by`),
  KEY `parameter_values_updated_by_foreign` (`updated_by`),
  KEY `parameter_values_parameter_scope_fk` (`parameter_id`,`team_id`,`monitored_system_id`),
  KEY `pv_team_param_time_idx` (`team_id`,`parameter_id`,`measured_at`),
  KEY `pv_team_system_time_idx` (`team_id`,`monitored_system_id`,`measured_at`),
  KEY `pv_team_date_idx` (`team_id`,`measured_date`),
  CONSTRAINT `parameter_values_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parameter_values_parameter_scope_fk` FOREIGN KEY (`parameter_id`, `team_id`, `monitored_system_id`) REFERENCES `parameters` (`id`, `team_id`, `monitored_system_id`) ON DELETE RESTRICT,
  CONSTRAINT `parameter_values_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parameter_values_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5548071 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.parameters definição

CREATE TABLE `parameters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `monitored_system_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decimals` tinyint unsigned NOT NULL DEFAULT '2',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `alert_1_min` double DEFAULT NULL,
  `alert_1_max` double DEFAULT NULL,
  `alert_2_min` double DEFAULT NULL,
  `alert_2_max` double DEFAULT NULL,
  `alert_3_min` double DEFAULT NULL,
  `alert_3_max` double DEFAULT NULL,
  `alert_4_min` double DEFAULT NULL,
  `alert_4_max` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parameters_id_team_unique` (`id`,`team_id`),
  UNIQUE KEY `parameters_scope_unique` (`id`,`team_id`,`monitored_system_id`),
  UNIQUE KEY `parameters_system_code_unique` (`team_id`,`monitored_system_id`,`code`),
  KEY `parameters_system_team_fk` (`monitored_system_id`,`team_id`),
  KEY `parameters_system_order_idx` (`team_id`,`monitored_system_id`,`sort_order`),
  KEY `parameters_team_id_is_active_index` (`team_id`,`is_active`),
  CONSTRAINT `parameters_system_team_fk` FOREIGN KEY (`monitored_system_id`, `team_id`) REFERENCES `monitored_systems` (`id`, `team_id`) ON DELETE RESTRICT,
  CONSTRAINT `parameters_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4739 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.passkeys definição

CREATE TABLE `passkeys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credential_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `credential` json NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkeys_credential_id_unique` (`credential_id`),
  KEY `passkeys_user_id_index` (`user_id`),
  CONSTRAINT `passkeys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.report_items definição

CREATE TABLE `report_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `report_id` bigint unsigned NOT NULL,
  `monitored_system_id` bigint unsigned DEFAULT NULL,
  `parameter_id` bigint unsigned DEFAULT NULL,
  `parent_report_item_id` bigint unsigned DEFAULT NULL,
  `date_reference` timestamp NULL DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `show_data_results` tinyint(1) NOT NULL DEFAULT '1',
  `hide_data` tinyint(1) NOT NULL DEFAULT '0',
  `is_stopped` tinyint(1) NOT NULL DEFAULT '0',
  `comment` longtext COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_items_parent_report_item_id_foreign` (`parent_report_item_id`),
  KEY `report_items_report_team_fk` (`report_id`,`team_id`),
  KEY `report_items_parameter_scope_fk` (`parameter_id`,`team_id`,`monitored_system_id`),
  KEY `report_items_order_idx` (`team_id`,`report_id`,`sort_order`),
  KEY `report_items_team_id_date_reference_index` (`team_id`,`date_reference`),
  CONSTRAINT `report_items_parameter_scope_fk` FOREIGN KEY (`parameter_id`, `team_id`, `monitored_system_id`) REFERENCES `parameters` (`id`, `team_id`, `monitored_system_id`) ON DELETE RESTRICT,
  CONSTRAINT `report_items_parent_report_item_id_foreign` FOREIGN KEY (`parent_report_item_id`) REFERENCES `report_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `report_items_report_team_fk` FOREIGN KEY (`report_id`, `team_id`) REFERENCES `reports` (`id`, `team_id`) ON DELETE CASCADE,
  CONSTRAINT `report_items_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.reports definição

CREATE TABLE `reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `finished_by` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_reference` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `comment` text COLLATE utf8mb4_unicode_ci,
  `finished_at` timestamp NULL DEFAULT NULL,
  `emailed_at` timestamp NULL DEFAULT NULL,
  `email_count` int unsigned NOT NULL DEFAULT '0',
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reports_id_team_unique` (`id`,`team_id`),
  KEY `reports_created_by_foreign` (`created_by`),
  KEY `reports_finished_by_foreign` (`finished_by`),
  KEY `reports_team_id_date_reference_index` (`team_id`,`date_reference`),
  KEY `reports_status_index` (`status`),
  CONSTRAINT `reports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_finished_by_foreign` FOREIGN KEY (`finished_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4642 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.team_members definição

CREATE TABLE `team_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_user_team_id_user_id_unique` (`team_id`,`user_id`),
  KEY `team_user_user_id_is_default_index` (`user_id`,`is_default`),
  KEY `team_members_user_id_is_default_index` (`user_id`,`is_default`),
  CONSTRAINT `team_user_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `team_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.teams definição

CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_personal` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `teams_owner_id_foreign` (`owner_id`),
  KEY `teams_is_active_index` (`is_active`),
  CONSTRAINT `teams_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- siac_ia.users definição

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_team_id` bigint unsigned DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_current_team_id_foreign` (`current_team_id`),
  KEY `users_is_admin_index` (`is_admin`),
  CONSTRAINT `users_current_team_id_foreign` FOREIGN KEY (`current_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
