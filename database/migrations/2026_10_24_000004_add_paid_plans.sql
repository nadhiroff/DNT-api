-- =============================================================================
--  NovaBase — Offres payantes (affichées « À venir » tant que le paiement n'est pas activé)
-- =============================================================================

ALTER TABLE plans
    ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1 AFTER is_default,
    ADD COLUMN is_featured  TINYINT(1) NOT NULL DEFAULT 0 AFTER is_available;

UPDATE plans SET description = 'Pour démarrer et prototyper vos applications.', sort = 0 WHERE slug = 'free';

-- Limites en octets ; clé absente ou NULL = illimité.
INSERT IGNORE INTO plans (name, slug, description, price, currency, billing_interval, limits, features, is_public, is_default, is_available, is_featured, sort)
VALUES
(
    'Pro', 'pro', 'Pour les applications en production qui grandissent.', 20.00, 'EUR', 'month',
    JSON_OBJECT(
        'db_storage_bytes', 8589934592,
        'file_storage_bytes', 107374182400,
        'projects', 10,
        'tables_per_project', 200,
        'api_requests_month', 5000000,
        'emails_day', 10000,
        'rate_limit_per_minute', 600,
        'max_upload_bytes', 1073741824
    ),
    JSON_ARRAY('10 projets', '8 Go de base de données', '100 Go de stockage de fichiers', '5 millions de requêtes API / mois', '10 000 e-mails / jour', 'Sauvegardes quotidiennes', 'Support par e-mail'),
    1, 0, 0, 1, 10
),
(
    'Équipe', 'team', 'Pour les équipes qui collaborent sur plusieurs produits.', 100.00, 'EUR', 'month',
    JSON_OBJECT(
        'db_storage_bytes', 53687091200,
        'file_storage_bytes', 536870912000,
        'projects', 50,
        'api_requests_month', 25000000,
        'emails_day', 50000,
        'rate_limit_per_minute', 1200,
        'max_upload_bytes', 5368709120
    ),
    JSON_ARRAY('50 projets', '50 Go de base de données', '500 Go de stockage de fichiers', '25 millions de requêtes API / mois', '50 000 e-mails / jour', 'Membres et rôles par projet', 'Journal d''audit étendu', 'Support prioritaire'),
    1, 0, 0, 0, 20
),
(
    'Entreprise', 'enterprise', 'Pour les organisations aux exigences élevées.', 500.00, 'EUR', 'month',
    JSON_OBJECT(
        'db_storage_bytes', 268435456000,
        'file_storage_bytes', 2199023255552,
        'emails_day', 200000,
        'rate_limit_per_minute', 6000,
        'max_upload_bytes', 10737418240
    ),
    JSON_ARRAY('Projets illimités', '250 Go de base de données', '2 To de stockage de fichiers', 'Requêtes API illimitées', '200 000 e-mails / jour', 'SLA 99,9 % et support dédié', 'Accompagnement à la migration'),
    1, 0, 0, 0, 30
);
