<?php
/**
 * Partenaires (public) : logos du bas de page + page dédiée.
 *   GET -> liste des partenaires actifs, triés par ordre d'affichage.
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('GET');
$pdo = db();

$st = $pdo->query(
    "SELECT id, nom, logo, description, site_web
     FROM partenaires WHERE actif = 1
     ORDER BY ordre ASC, nom ASC");

$items = array_map(function ($r) {
    return [
        'id' => (int)$r['id'], 'nom' => $r['nom'],
        'logo_url' => url_upload('partenaires', $r['logo']),
        'description' => $r['description'], 'site_web' => $r['site_web'],
    ];
}, $st->fetchAll());

json_out(['ok' => true, 'total' => count($items), 'items' => $items]);
