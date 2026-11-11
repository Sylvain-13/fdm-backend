<?php
/**
 * Actualités (public).
 *   GET ?slug=mon-article        -> le détail d'un article + articles liés
 *   GET ?categorie=&q=&page=     -> liste paginée (seulement les publiées)
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('GET');
$pdo = db();

/* ---------- DÉTAIL ---------- */
if (!empty($_GET['slug'])) {
    $st = $pdo->prepare(
        "SELECT a.*, adm.nom AS auteur
         FROM actualites a
         LEFT JOIN administrateurs adm ON adm.id = a.auteur_id
         WHERE a.slug = ? AND a.statut = 'publie' LIMIT 1");
    $st->execute([propre($_GET['slug'])]);
    $a = $st->fetch();
    if (!$a) { json_erreur('Actualité introuvable.', 404); }

    // Incrémente le compteur de vues (sans bloquer la lecture)
    $pdo->prepare("UPDATE actualites SET vues = vues + 1 WHERE id = ?")->execute([$a['id']]);

    // Articles liés (même catégorie, les plus récents, hors courant)
    $stl = $pdo->prepare(
        "SELECT slug, titre, categorie, image, date_publication
         FROM actualites
         WHERE statut='publie' AND categorie=? AND id<>?
         ORDER BY date_publication DESC, id DESC LIMIT 3");
    $stl->execute([$a['categorie'], $a['id']]);
    $lies = array_map(function ($r) {
        return [
            'slug' => $r['slug'], 'titre' => $r['titre'], 'categorie' => $r['categorie'],
            'image_url' => url_upload('actualites', $r['image']),
            'date_fr' => date_fr($r['date_publication'], false),
        ];
    }, $stl->fetchAll());

    json_out(['ok' => true, 'item' => [
        'id' => (int)$a['id'], 'slug' => $a['slug'], 'titre' => $a['titre'],
        'categorie' => $a['categorie'], 'chapeau' => $a['chapeau'], 'corps' => $a['corps'],
        'image_url' => url_upload('actualites', $a['image']),
        'temps_lecture' => $a['temps_lecture'] ? (int)$a['temps_lecture'] : null,
        'date_fr' => date_fr($a['date_publication']), 'auteur' => $a['auteur'],
        'vues' => (int)$a['vues'] + 1,
    ], 'lies' => $lies]);
}

/* ---------- LISTE ---------- */
[$page, $parPage, $offset] = pagination(9);
$where = ["statut = 'publie'"];
$args = [];

$cats = ['campagne', 'candidatures', 'evenement', 'fodes', 'alumni', 'bourses'];
if (!empty($_GET['categorie']) && in_array($_GET['categorie'], $cats, true)) {
    $where[] = 'categorie = ?';
    $args[] = $_GET['categorie'];
}
if (!empty($_GET['q'])) {
    $q = '%' . propre($_GET['q']) . '%';
    $where[] = '(titre LIKE ? OR chapeau LIKE ? OR corps LIKE ?)';
    array_push($args, $q, $q, $q);
}
$clause = implode(' AND ', $where);

$stc = $pdo->prepare("SELECT COUNT(*) FROM actualites WHERE $clause");
$stc->execute($args);
$total = (int)$stc->fetchColumn();

$sql = "SELECT id, slug, titre, categorie, chapeau, image, temps_lecture, date_publication
        FROM actualites WHERE $clause
        ORDER BY date_publication DESC, id DESC
        LIMIT $parPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($args);

$items = array_map(function ($r) {
    return [
        'id' => (int)$r['id'], 'slug' => $r['slug'], 'titre' => $r['titre'],
        'categorie' => $r['categorie'], 'chapeau' => $r['chapeau'],
        'image_url' => url_upload('actualites', $r['image']),
        'temps_lecture' => $r['temps_lecture'] ? (int)$r['temps_lecture'] : null,
        'date_fr' => date_fr($r['date_publication']),
    ];
}, $st->fetchAll());

json_out([
    'ok' => true, 'total' => $total, 'page' => $page, 'par_page' => $parPage,
    'pages' => (int)ceil($total / $parPage), 'items' => $items,
]);
