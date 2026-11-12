<?php
/** Diagnostic connexion base — protégé par INSTALL_TOKEN. À supprimer après. */
require_once __DIR__ . '/lib/db.php';
header('Content-Type: text/plain; charset=utf-8');
$token = getenv('INSTALL_TOKEN');
if (!$token || !hash_equals($token, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit("Jeton requis : ?token=...\n"); }

echo "=== 1. Variables d'environnement vues par PHP ===\n";
foreach (['MYSQLHOST','MYSQLPORT','MYSQLDATABASE','MYSQLUSER'] as $k) {
    $v = getenv($k); echo str_pad($k,14)." = ".($v === false ? '(ABSENTE)' : $v)."\n";
}
$p = getenv('MYSQLPASSWORD');
echo str_pad('MYSQLPASSWORD',14)." = ".($p ? '(definie, '.strlen($p).' caracteres)' : '(ABSENTE)')."\n\n";

echo "=== 2. Valeurs réellement utilisées par config.php ===\n";
$c = fdm_config()['db'];
echo "host={$c['host']}  port={$c['port']}  name={$c['name']}  user={$c['user']}  pass=".($c['pass'] !== '' ? '(definie)' : '(VIDE)')."\n";
echo "--> Si host=127.0.0.1 alors que MYSQLHOST existe ci-dessus : config.php est l'ANCIENNE version.\n\n";

echo "=== 3. Tentative de connexion directe ===\n";
try {
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "CONNEXION OK\n";
    $n = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
    echo "Nombre de tables dans la base : $n\n";
} catch (Throwable $e) {
    echo "ECHEC : " . $e->getMessage() . "\n";
}