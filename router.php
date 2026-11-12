<?php
/**
 * Routeur du serveur PHP intégré.
 * Reproduit les protections des .htaccess : interdit l'accès web direct à
 * config/ lib/ sql/ logs/ et l'exécution de scripts dans uploads/.
 */
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$full = __DIR__ . $path;

if (preg_match('#^/(config|lib|sql|logs)(/|$)#i', $path)) { http_response_code(403); echo 'Forbidden'; return true; }
if (preg_match('#^/uploads/.+\.(php|phtml|phar|pl|py|sh|cgi)$#i', $path)) { http_response_code(403); echo 'Forbidden'; return true; }

if ($path === '/' || is_dir($full)) {
    $dir = rtrim($full, '/');
    if (is_file($dir.'/index.php'))  { require $dir.'/index.php';  return true; }
    if (is_file($dir.'/index.html')) { header('Content-Type: text/html; charset=utf-8'); readfile($dir.'/index.html'); return true; }
    http_response_code(404); echo 'Not found'; return true;
}
if (is_file($full)) { return false; }  // fichier existant : .php exécuté, statique servi
http_response_code(404); echo 'Not found'; return true;