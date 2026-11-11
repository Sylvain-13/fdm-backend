<?php
/**
 * Événements (public).
 *   GET ?mois=1..12 & annee= & page=   -> liste paginée des événements publiés
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('GET');
$pdo = db();

[$page, $parPage, $offset] = pagination(12);
$where = ["statut = 'publie'"];
$args = [];

if (!empty($_GET['mois'])) {
    $m = (int)$_GET['mois'];
    if ($m >= 1 && $m <= 12) { $where[] = 'mois = ?'; $args[] = $m; }
}
if (!empty($_GET['annee'])) {
    $where[] = 'annee = ?'; $args[] = (int)$_GET['annee'];
}
$clause = implode(' AND ', $where);

$stc = $pdo->prepare("SELECT COUNT(*) FROM evenements WHERE $clause");
$stc->execute($args);
$total = (int)$stc->fetchColumn();

$sql = "SELECT id, titre, affiche, description, lieu, date_debut, date_fin, mois, annee
        FROM evenements WHERE $clause
        ORDER BY date_debut DESC, id DESC
        LIMIT $parPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($args);

$items = array_map(function ($r) {
    return [
        'id' => (int)$r['id'], 'titre' => $r['titre'],
        'affiche_url' => url_upload('evenements', $r['affiche']),
        'description' => $r['description'], 'lieu' => $r['lieu'],
        'mois' => (int)$r['mois'], 'annee' => (int)$r['annee'],
        'date_fr' => date_fr($r['date_debut']),
        'date_debut' => $r['date_debut'], 'date_fin' => $r['date_fin'],
    ];
}, $st->fetchAll());

json_out([
    'ok' => true, 'total' => $total, 'page' => $page, 'par_page' => $parPage,
    'pages' => (int)ceil($total / $parPage), 'items' => $items,
]);
