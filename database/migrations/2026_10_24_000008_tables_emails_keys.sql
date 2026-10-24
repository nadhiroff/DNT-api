-- =============================================================================
--  NovaBase — Éditeur de tables, API de données, e-mails (SMTP, modèles, historique),
--  clés récupérables, plan Gratuit à 5 projets
-- =============================================================================

-- Clés API : copie chiffrée (libsodium) pour pouvoir les recopier à tout moment ; le hash sert à l'authentification.
ALTER TABLE api_keys ADD COLUMN key_enc VARCHAR(255) NULL AFTER key_hash;

-- Plan Gratuit : 5 projets.
UPDATE plans
SET limits = JSON_SET(limits, '$.projects', 5),
    features = JSON_ARRAY('5 projets', '500 Mo de base de données', '1 Go de stockage de fichiers', '50 000 requêtes API / mois', '300 e-mails / jour')
WHERE slug = 'free';

-- Définition des tables créées dans le Table Editor (la structure réelle vit dans proj_<ref>).
CREATE TABLE IF NOT EXISTS table_definitions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    name         VARCHAR(64)     NOT NULL,
    pk_type      ENUM('bigint','uuid') NOT NULL DEFAULT 'bigint',
    access_rules JSON            NOT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_table_definitions (project_id, name),
    CONSTRAINT fk_table_definitions_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS column_definitions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_id      BIGINT UNSIGNED NOT NULL,
    name          VARCHAR(64)     NOT NULL,
    type          VARCHAR(20)     NOT NULL,
    length        SMALLINT UNSIGNED NULL,
    nullable      TINYINT(1)      NOT NULL DEFAULT 1,
    is_unique     TINYINT(1)      NOT NULL DEFAULT 0,
    is_indexed    TINYINT(1)      NOT NULL DEFAULT 0,
    default_value VARCHAR(255)    NULL,
    fk_table      VARCHAR(64)     NULL,
    fk_on_delete  ENUM('CASCADE','SET NULL','RESTRICT') NULL,
    position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_column_definitions (table_id, name),
    CONSTRAINT fk_column_definitions_table FOREIGN KEY (table_id) REFERENCES table_definitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Historique et audit des modifications de schéma.
CREATE TABLE IF NOT EXISTS schema_changes (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NULL,
    action       VARCHAR(40)     NOT NULL,
    sql_executed TEXT            NOT NULL,
    status       ENUM('success','failed') NOT NULL,
    error        VARCHAR(500)    NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_schema_changes_project (project_id, created_at),
    CONSTRAINT fk_schema_changes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- SMTP : project_id NULL = configuration globale de la plateforme.
CREATE TABLE IF NOT EXISTS smtp_settings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NULL,
    host         VARCHAR(255)    NOT NULL,
    port         SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    encryption   ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    username     VARCHAR(255)    NULL,
    password_enc VARCHAR(500)    NULL,
    from_email   VARCHAR(254)    NOT NULL,
    from_name    VARCHAR(120)    NOT NULL DEFAULT '',
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Une ligne par projet ; l'unicité de la ligne globale (NULL) est garantie par l'application.
    PRIMARY KEY (id),
    UNIQUE KEY uq_smtp_project (project_id),
    CONSTRAINT fk_smtp_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Modèles d'e-mails personnalisés (project_id NULL = e-mails de la plateforme).
CREATE TABLE IF NOT EXISTS email_templates (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id   BIGINT UNSIGNED NULL,
    template_key VARCHAR(40)     NOT NULL,
    subject      VARCHAR(200)    NOT NULL,
    heading      VARCHAR(200)    NOT NULL,
    body         TEXT            NOT NULL,
    button_label VARCHAR(80)     NOT NULL DEFAULT '',
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_templates (project_id, template_key),
    KEY idx_email_templates_key (template_key),
    CONSTRAINT fk_email_templates_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Historique des e-mails envoyés par l'API des projets.
CREATE TABLE IF NOT EXISTS email_logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    api_key_id BIGINT UNSIGNED NULL,
    recipients VARCHAR(1000)   NOT NULL,
    subject    VARCHAR(200)    NOT NULL,
    status     ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    error      VARCHAR(1000)   NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sent_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_logs_project (project_id, created_at),
    KEY idx_email_logs_status (project_id, status),
    CONSTRAINT fk_email_logs_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Taille des bases mesurée périodiquement (jamais de requête lourde à chaque appel API).
CREATE TABLE IF NOT EXISTS usage_snapshots (
    project_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    db_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_bytes  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    computed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id),
    KEY idx_usage_snapshots_user (user_id),
    CONSTRAINT fk_usage_snapshots_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE projects ALTER COLUMN cors_origins SET DEFAULT (JSON_ARRAY());
