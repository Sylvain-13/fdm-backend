<?php
/**
 * Connexion à la base de données (PDO MySQL).
 * Fournit une instance unique (singleton) réutilisée dans toute la requête.
 */

function fdm_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $chemin = __DIR__ . '/../config/config.php';
        if (!is_file($chemin)) {
            http_response_code(500);
            exit('Configuration absente : copiez config/config.sample.php vers config/config.php.');
        }
        $cfg = require $chemin;
        if (!empty($cfg['site']['timezone'])) {
            date_default_timezone_set($cfg['site']['timezone']);
        }
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = fdm_config()['db'];

    if (!empty($c['socket'])) {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s',
            $c['socket'], $c['name'], $c['charset']);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $c['host'], $c['port'], $c['name'], $c['charset']);
    }

    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Requêtes réellement préparées côté serveur (anti-injection)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        // On n'expose jamais le détail de l'erreur au public.
        error_log('[FDM] Connexion BDD impossible : ' . $e->getMessage());
        exit('Service momentanément indisponible.');
    }
    return $pdo;
}
