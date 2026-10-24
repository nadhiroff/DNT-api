-- =============================================================================
--  NovaBase — Phase 1 : tables cœur de la plateforme
--  Rejouable : toutes les créations sont idempotentes (IF NOT EXISTS / INSERT IGNORE).
--  Toutes les dates sont en UTC (time_zone = '+00:00' imposé à la connexion).
-- =============================================================================

-- Plans tarifaires. limits (JSON) : NULL ou clé absente = illimité.
CREATE TABLE IF NOT EXISTS plans (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(80)  NOT NULL,
    slug             VARCHAR(40)  NOT NULL,
    description      VARCHAR(255) NULL,
    price            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency         CHAR(3)      NOT NULL DEFAULT 'EUR',
    -- « interval » est un mot réservé MySQL : colonne nommée billing_interval.
    billing_interval ENUM('month','year') NOT NULL DEFAULT 'month',
    limits           JSON         NOT NULL,
    features         JSON         NULL,
    is_public        TINYINT(1)   NOT NULL DEFAULT 1,
    is_default       TINYINT(1)   NOT NULL DEFAULT 0,
    sort             SMALLINT     NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plans_slug (slug),
    KEY idx_plans_public_sort (is_public, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email             VARCHAR(254) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    name              VARCHAR(120) NOT NULL,
    role              ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user',
    plan_id           INT UNSIGNED NULL,
    status            ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME     NULL,
    totp_secret_enc   VARCHAR(255) NULL,
    totp_enabled_at   DATETIME     NULL,
    -- Dernier pas TOTP accepté : empêche le rejeu d'un même code.
    totp_last_step    BIGINT UNSIGNED NULL,
    last_login_at     DATETIME     NULL,
    last_login_ip     VARCHAR(45)  NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role),
    KEY idx_users_status (status),
    KEY idx_users_plan (plan_id),
    KEY idx_users_created (created_at),
    CONSTRAINT fk_users_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Surcharges de limites par utilisateur. Ligne absente = limite du plan ; value NULL = illimité.
CREATE TABLE IF NOT EXISTS user_limits (
    user_id    BIGINT UNSIGNED NOT NULL,
    limit_key  VARCHAR(64)     NOT NULL,
    value      BIGINT UNSIGNED NULL,
    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, limit_key),
    CONSTRAINT fk_user_limits_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Codes de secours 2FA (HMAC, usage unique).
CREATE TABLE IF NOT EXISTS user_recovery_codes (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    code_hash  CHAR(64)        NOT NULL,
    used_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recovery_codes_hash (code_hash),
    KEY idx_recovery_codes_user (user_id, used_at),
    CONSTRAINT fk_recovery_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Sessions web (id = SHA-256 de l'identifiant de session, jamais en clair).
CREATE TABLE IF NOT EXISTS sessions (
    id            CHAR(64)        NOT NULL,
    user_id       BIGINT UNSIGNED NULL,
    ip            VARCHAR(45)     NOT NULL DEFAULT '',
    user_agent    VARCHAR(255)    NOT NULL DEFAULT '',
    payload       MEDIUMBLOB      NOT NULL,
    last_activity INT UNSIGNED    NOT NULL,
    created_at    INT UNSIGNED    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id, last_activity),
    KEY idx_sessions_last_activity (last_activity),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Journal d'audit immuable (pas de clé étrangère : il survit à la suppression des comptes).
CREATE TABLE IF NOT EXISTS audit_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id   BIGINT UNSIGNED NULL,
    actor_type ENUM('user','system','cli') NOT NULL DEFAULT 'user',
    action     VARCHAR(64)     NOT NULL,
    target     VARCHAR(191)    NULL,
    ip         VARCHAR(45)     NULL,
    metadata   JSON            NULL,
    created_at DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_audit_actor (actor_id, created_at),
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_target (target),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- File d'attente (réservation par SELECT … FOR UPDATE SKIP LOCKED).
CREATE TABLE IF NOT EXISTS jobs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue        VARCHAR(64)     NOT NULL DEFAULT 'default',
    type         VARCHAR(191)    NOT NULL,
    payload      JSON            NOT NULL,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    available_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at  DATETIME        NULL,
    reserved_by  VARCHAR(64)     NULL,
    last_error   TEXT            NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_jobs_pick (queue, reserved_at, available_at),
    KEY idx_jobs_reserved (reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS failed_jobs (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id    BIGINT UNSIGNED NOT NULL,
    queue     VARCHAR(64)     NOT NULL,
    type      VARCHAR(191)    NOT NULL,
    payload   JSON            NOT NULL,
    attempts  TINYINT UNSIGNED NOT NULL,
    exception TEXT            NOT NULL,
    failed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_failed_jobs_failed (failed_at),
    KEY idx_failed_jobs_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Repli du rate limiting quand ni APCu ni Redis ne sont disponibles (« key » est réservé).
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket_key   CHAR(64)     NOT NULL,
    window_start INT UNSIGNED NOT NULL,
    hits         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket_key, window_start),
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Plan par défaut (Gratuit). Octets : 500 Mo base, 1 Go fichiers, 50 Mo par fichier.
INSERT IGNORE INTO plans (name, slug, description, price, currency, billing_interval, limits, features, is_public, is_default, sort)
VALUES (
    'Gratuit', 'free', 'Pour démarrer et prototyper vos applications.', 0.00, 'EUR', 'month',
    JSON_OBJECT(
        'db_storage_bytes', 524288000,
        'file_storage_bytes', 1073741824,
        'projects', 2,
        'tables_per_project', 50,
        'api_requests_month', 50000,
        'emails_day', 300,
        'rate_limit_per_minute', 60,
        'max_upload_bytes', 52428800
    ),
    JSON_ARRAY('2 projets', '500 Mo de base de données', '1 Go de stockage de fichiers', '50 000 requêtes API / mois', '300 e-mails / jour'),
    1, 1, 0
);
