SET NAMES utf8mb4;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `is_active`, `force_password_change`, `created_at`, `updated_at`)
VALUES
  (1, 'Administrador do Sistema', 'admin@seplad.local', '$2y$12$kzQyhCZu9MpbsOYxuFtHp.0vLRZcwgWvf44u0Yemman10SF7dCWq6', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `email` = VALUES(`email`),
  `password` = VALUES(`password`),
  `is_active` = VALUES(`is_active`),
  `force_password_change` = VALUES(`force_password_change`),
  `deleted_at` = NULL,
  `updated_at` = NOW();
