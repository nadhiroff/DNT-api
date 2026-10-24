<?php

declare(strict_types=1);

/*
 * Point d'entrée pour un hébergement dont la racine web est le dossier du projet (et non public/).
 * Avec mod_rewrite + .htaccess, les requêtes sont envoyées directement à public/index.php ;
 * sans réécriture, ce fichier sert l'application et les liens prennent la forme index.php/chemin.
 */

require __DIR__ . '/public/index.php';
