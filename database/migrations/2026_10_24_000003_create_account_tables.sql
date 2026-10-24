-- =============================================================================
--  NovaBase — Phase 2 : comptes (jetons e-mail / mot de passe, anti-brute-force)
-- =============================================================================

-- Jetons à usage unique (vérification d'e-mail, réinitialisation du mot de passe).
-- Seul le SHA-256 du jeton est stocké ; validité 1 h.
CREATE TABLE IF NOT EXISTS user_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    type       ENUM('email_verification','password_reset') NOT NULL,
    token_hash CHAR(64)        NOT NULL,
    expires_at DATETIME        NOT NULL,
    used_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_tokens_hash (token_hash),
    KEY idx_user_tokens_user_type (user_id, type, used_at),
    KEY idx_user_tokens_expires (expires_at),
    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Échecs de connexion par compte et par IP (clé = SHA-256 de « account:<email> » ou « ip:<ip> »).
-- Au-delà du seuil, blocage progressif (durée doublée à chaque nouvel échec).
CREATE TABLE IF NOT EXISTS login_throttles (
    throttle_key    CHAR(64)          NOT NULL,
    failures        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME          NULL,
    last_failure_at DATETIME          NOT NULL,
    PRIMARY KEY (throttle_key),
    KEY idx_login_throttles_last (last_failure_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
