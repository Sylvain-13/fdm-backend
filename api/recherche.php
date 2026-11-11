<?php
/**
 * Recherche indexée (public).
 *   GET ?q=...   -> actualités + événements publiés correspondant au terme.
 * Alimente la page « recherche » de la SPA.
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('GET');
$pdo = db();

$q = trim(propre($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    json_out(['ok' => true, 'q' => $q, 'total' => 0, 'items' => []]);
}
$like = '%' . $q . '%';

// Actualités
$sa = $pdo->prepare(
    "SELECT slug, titre, chapeau, categorie, date_publication
     FROM actualites
     WHERE statut='publie' AND (titre LIKE ? OR chapeau LIKE ? OR corps LIKE ?)
     ORDER BY date_publication DESC LIMIT 30");
$sa->execute([$like, $like, $like]);
$items = array_map(function ($r) {
    return [
        'type' => 'actualite', 'titre' => $r['titre'],
        'extrait' => $r['chapeau'], 'categorie' => $r['categorie'],
        'slug' => $r['slug'], 'url' => 'actualite.' . $r['slug'],
        'date_fr' => date_fr($r['date_publication']),
    ];
}, $sa->fetchAll());

// Événements
$se = $pdo->prepare(
    "SELECT id, titre, description, date_debut
     FROM evenements
     WHERE statut='publie' AND (titre LIKE ? OR description LIKE ? OR lieu LIKE ?)
     ORDER BY date_debut DESC LIMIT 30");
$se->execute([$like, $like, $like]);
foreach ($se->fetchAll() as $r) {
    $items[] = [
        'type' => 'evenement', 'titre' => $r['titre'],
        'extrait' => $r['description'], 'categorie' => 'evenement',
        'id' => (int)$r['id'], 'url' => 'evenements',
        'date_fr' => date_fr($r['date_debut']),
    ];
}

json_out(['ok' => true, 'q' => $q, 'total' => count($items), 'items' => $items]);
