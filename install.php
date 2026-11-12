<?php
/**
 * INSTALLATEUR UNIQUE — importe le schéma dans la base.
 * À SUPPRIMER du dépôt juste après usage.
 * Protégé par la variable d'environnement INSTALL_TOKEN.
 */
require_once __DIR__ . '/lib/db.php';
header('Content-Type: text/plain; charset=utf-8');

$token = getenv('INSTALL_TOKEN');
if (!$token) { http_response_code(403); exit("Definissez d'abord la variable INSTALL_TOKEN dans Railway, puis rechargez avec ?token=...\n"); }
if (!hash_equals($token, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit("Jeton invalide. Ajoutez ?token=VOTRE_INSTALL_TOKEN a l'URL.\n"); }

$sql = @file_get_contents(__DIR__ . '/sql/schema.sql');
if ($sql === false) { http_response_code(500); exit("Impossible de lire sql/schema.sql\n"); }

// La base Railway existe deja : on retire CREATE DATABASE ...; et USE `fdm`;
$sql = preg_replace('/CREATE\s+DATABASE.*?;/is', '', $sql, 1);
$sql = preg_replace('/USE\s+`fdm`\s*;/i', '', $sql);

try {
    db()->exec($sql);
    $n = db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    echo "OK - schema importe. Nombre de tables : $n\n";
    echo "=> SUPPRIMEZ maintenant install.php du depot (et la variable INSTALL_TOKEN).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}