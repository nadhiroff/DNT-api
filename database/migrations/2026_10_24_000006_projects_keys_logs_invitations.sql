-- =============================================================================
--  NovaBase — Projets (une base MySQL isolée chacun), clés API, usage, connexions, invitations
-- =============================================================================

CREATE TABLE IF NOT EXISTS projects (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ref            CHAR(20)        NOT NULL,
    owner_id       BIGINT UNSIGNED NOT NULL,
    name           VARCHAR(80)     NOT NULL,
    db_name        VARCHAR(64)     NOT NULL,
    db_user        VARCHAR(32)     NOT NULL,
    db_pass_enc    VARCHAR(255)    NOT NULL,
    jwt_secret_enc VARCHAR(255)    NOT NULL,
    cors_origins   JSON            NULL,
    status         ENUM('provisioning','active','suspended','failed') NOT NULL DEFAULT 'provisioning',
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_ref (ref),
    UNIQUE KEY uq_projects_db_name (db_name),
    KEY idx_projects_owner (owner_id, status),
    KEY idx_projects_created (created_at),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Clés API : seul le SHA-256 est stocké ; « prefix » sert à l'identification visuelle.
CREATE TABLE IF NOT EXISTS api_keys (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(80)     NOT NULL,
    type         ENUM('public','secret','custom') NOT NULL,
    prefix       VARCHAR(24)     NOT NULL,
    key_hash     CHAR(64)        NOT NULL,
    permissions  JSON            NULL,
    rate_limit   INT UNSIGNED    NULL,
    allowed_ips  JSON            NULL,
    expires_at   DATETIME        NULL,
    last_used_at DATETIME        NULL,
    revoked_at   DATETIME        NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_keys_hash (key_hash),
    KEY idx_api_keys_project (project_id, revoked_at),
    CONSTRAINT fk_api_keys_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Requêtes API agrégées par heure et par projet.
CREATE TABLE IF NOT EXISTS api_usage_hourly (
    project_id BIGINT UNSIGNED NOT NULL,
    hour       DATETIME        NOT NULL,
    requests   INT UNSIGNED    NOT NULL DEFAULT 0,
    errors     INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (project_id, hour),
    KEY idx_api_usage_hour (hour),
    CONSTRAINT fk_api_usage_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Journal des connexions (tableau de bord sécurité du superadmin).
CREATE TABLE IF NOT EXISTS login_events (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,
    email       VARCHAR(254)    NULL,
    event       ENUM('login','login_failed','logout','2fa_failed','locked','invitation_accepted') NOT NULL,
    ip          VARCHAR(45)     NOT NULL,
    user_agent  VARCHAR(255)    NOT NULL DEFAULT '',
    browser     VARCHAR(40)     NOT NULL DEFAULT '',
    os          VARCHAR(40)     NOT NULL DEFAULT '',
    device      ENUM('desktop','mobile','tablet','bot','unknown') NOT NULL DEFAULT 'unknown',
    created_at  DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY idx_login_events_created (created_at),
    KEY idx_login_events_user (user_id, created_at),
    KEY idx_login_events_event (event, created_at),
    KEY idx_login_events_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Cache de géolocalisation des IP (évite de réinterroger le fournisseur).
CREATE TABLE IF NOT EXISTS geoip_cache (
    ip          VARCHAR(45)  NOT NULL,
    country     VARCHAR(80)  NULL,
    country_code CHAR(2)     NULL,
    city        VARCHAR(80)  NULL,
    resolved_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Invitations (proches / amis) : le compte reste « pending » jusqu'à l'acceptation.
CREATE TABLE IF NOT EXISTS invitations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email       VARCHAR(254)    NOT NULL,
    user_id     BIGINT UNSIGNED NULL,
    invited_by  BIGINT UNSIGNED NULL,
    token_hash  CHAR(64)        NOT NULL,
    message     VARCHAR(500)    NULL,
    status      ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending',
    expires_at  DATETIME        NOT NULL,
    accepted_at DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invitations_token (token_hash),
    KEY idx_invitations_email (email, status),
    KEY idx_invitations_status (status, created_at),
    CONSTRAINT fk_invitations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_invitations_inviter FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
