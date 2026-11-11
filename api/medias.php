<?php
/**
 * Images du site (public).
 *   GET -> { ok, items: { cle: url, ... } }
 * Le front remplace chaque emplacement data-fdm-img="cle" par l'image.
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('GET');
$pdo = db();

$items = [];
foreach ($pdo->query("SELECT cle, fichier FROM medias")->fetchAll() as $r) {
    $items[$r['cle']] = url_upload('site', $r['fichier']);
}
json_out(['ok' => true, 'items' => $items]);
