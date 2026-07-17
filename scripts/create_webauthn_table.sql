-- Tier 2: WebAuthn Global Table Schema
-- Must be executed on all environment databases (loaneasyfinance, partyplatform, etc.)

CREATE TABLE IF NOT EXISTS `webauthn_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Maps to users.id or managers.id depending on app',
  `role` varchar(50) NOT NULL DEFAULT 'users' COMMENT 'e.g. users, admin, manager',
  `credential_id` text NOT NULL COMMENT 'Base64 encoded WebAuthn Credential ID',
  `public_key` text NOT NULL COMMENT 'Base64 encoded Public Key',
  `sign_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Signature counter to prevent cloning',
  `device_name` varchar(255) DEFAULT NULL COMMENT 'e.g., iPhone FaceID, Macbook TouchID',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_role` (`user_id`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

