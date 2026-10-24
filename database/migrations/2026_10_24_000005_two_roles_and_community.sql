-- =============================================================================
--  NovaBase — Deux rôles seulement (user, superadmin) + section communauté
-- =============================================================================

-- Le rôle « admin » est supprimé : le superadmin gère toute la plateforme.
UPDATE users SET role = 'user' WHERE role = 'admin';
ALTER TABLE users MODIFY role ENUM('superadmin','user') NOT NULL DEFAULT 'user';

-- Témoignages affichés sur la page d'accueil (gérés par le superadmin).
CREATE TABLE IF NOT EXISTS testimonials (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_name  VARCHAR(120) NOT NULL,
    handle       VARCHAR(60)  NOT NULL,
    platform     ENUM('x','linkedin','github','facebook','instagram','other') NOT NULL DEFAULT 'x',
    profile_url  VARCHAR(500) NULL,
    avatar_file  VARCHAR(64)  NULL,
    content      TEXT         NOT NULL,
    is_published TINYINT(1)   NOT NULL DEFAULT 0,
    sort         SMALLINT     NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_testimonials_published (is_published, sort, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Réglages éditables du site public (clé/valeur).
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64)  NOT NULL,
    value       TEXT         NULL,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO site_settings (setting_key, value) VALUES
    ('community_title', 'Rejoignez la communauté'),
    ('community_subtitle', 'Découvrez ce que nos utilisateurs disent de leur expérience avec NovaBase.'),
    ('community_button_label', 'Rejoignez-nous sur Discord'),
    ('community_button_url', '');

-- Exemples en BROUILLON : invisibles du public, à remplacer par de vrais témoignages
-- depuis Administration → Témoignages, puis à publier.
INSERT INTO testimonials (author_name, handle, platform, content, is_published, sort)
SELECT * FROM (
    SELECT 'Exemple 1' AS a, '@exemple_dev' AS h, 'x' AS p, 'J\'ai enfin essayé NovaBase aujourd\'hui… pourquoi j\'ai attendu si longtemps ? Authentification, base de données et API opérationnelles en 20 minutes.' AS c, 0 AS pub, 10 AS s
    UNION ALL SELECT 'Exemple 2', '@exemple_flutter', 'x', 'Premier projet Flutter branché sur NovaBase : la clé publique dans l\'app, les règles d\'accès par table, et c\'est tout. Très bonne expérience développeur.', 0, 20
    UNION ALL SELECT 'Exemple 3', '@exemple_saas', 'linkedin', 'Pour notre SaaS, l\'isolation d\'une base par client nous a convaincus. On dort mieux.', 0, 30
    UNION ALL SELECT 'Exemple 4', '@exemple_startup', 'x', 'Auto-hébergé sur un simple VPS, aucune surprise sur la facture. Exactement ce qu\'il nous fallait.', 0, 40
    UNION ALL SELECT 'Exemple 5', '@exemple_mobile', 'github', 'Le Table Editor est un vrai gain de temps : je crée mes tables sans écrire une ligne de SQL et l\'API est prête immédiatement.', 0, 50
    UNION ALL SELECT 'Exemple 6', '@exemple_agence', 'x', 'Nous l\'utilisons pour tous les projets clients de l\'agence. Les e-mails via notre propre SMTP, c\'est parfait.', 0, 60
    UNION ALL SELECT 'Exemple 7', '@exemple_solo', 'x', 'En tant que développeur solo, la 2FA, les clés révocables et le journal d\'audit me rassurent énormément.', 0, 70
    UNION ALL SELECT 'Exemple 8', '@exemple_ecommerce', 'linkedin', 'Notre app e-commerce tourne dessus depuis le lancement. Les filtres de l\'API (eq, gte, in) couvrent tous nos besoins.', 0, 80
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM testimonials);
