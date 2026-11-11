<?php
/**
 * Fonctions utilitaires partagées (API + admin).
 */

/* ------------------------------------------------------------------ */
/*  Réponses JSON (API)                                               */
/* ------------------------------------------------------------------ */

function json_out($donnees, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_erreur(string $message, int $code = 400, array $extra = []): void
{
    json_out(array_merge(['ok' => false, 'erreur' => $message], $extra), $code);
}

/** Corps JSON reçu (POST application/json) ou tableau vide. */
function corps_json(): array
{
    $brut = file_get_contents('php://input');
    if ($brut === '' || $brut === false) {
        return [];
    }
    $d = json_decode($brut, true);
    return is_array($d) ? $d : [];
}

/** Impose une ou plusieurs méthodes HTTP, sinon 405. */
function exiger_methode(string ...$methodes): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methodes, true)) {
        header('Allow: ' . implode(', ', $methodes));
        json_erreur('Méthode non autorisée.', 405);
    }
}

/* ------------------------------------------------------------------ */
/*  Échappement / nettoyage                                           */
/* ------------------------------------------------------------------ */

/** Échappe pour affichage HTML (anti-XSS). À utiliser dans l'admin. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Nettoie une chaîne simple (trim + suppression des caractères de contrôle). */
function propre($v): string
{
    $v = trim((string)$v);
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
}

function est_email(string $v): bool
{
    return (bool)filter_var($v, FILTER_VALIDATE_EMAIL);
}

/** Génère un identifiant d'URL (slug) à partir d'un titre. */
function slugifier(string $texte): string
{
    $texte = (string)preg_replace('/[\x00-\x1F]/u', '', $texte);
    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
        if ($translit !== false) { $texte = $translit; }
    }
    $texte = strtolower($texte);
    $texte = preg_replace('/[^a-z0-9]+/', '-', $texte);
    $texte = trim($texte, '-');
    return $texte !== '' ? $texte : 'element';
}

/** Rend un slug unique dans une table (ajoute -2, -3, … si besoin). */
function slug_unique(PDO $pdo, string $table, string $base, ?int $sauf_id = null): string
{
    $base = slugifier($base);
    $slug = $base;
    $i = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE slug = ?";
        $args = [$slug];
        if ($sauf_id !== null) { $sql .= " AND id <> ?"; $args[] = $sauf_id; }
        $st = $pdo->prepare($sql);
        $st->execute($args);
        if ((int)$st->fetchColumn() === 0) { return $slug; }
        $i++;
        $slug = $base . '-' . $i;
    }
}

/* ------------------------------------------------------------------ */
/*  Pagination                                                        */
/* ------------------------------------------------------------------ */

/** Retourne [page, parPage, offset] à partir des paramètres GET. */
function pagination(int $defautParPage = 9, int $maxParPage = 50): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $parPage = (int)($_GET['par_page'] ?? $defautParPage);
    $parPage = max(1, min($maxParPage, $parPage));
    return [$page, $parPage, ($page - 1) * $parPage];
}

/* ------------------------------------------------------------------ */
/*  Divers                                                            */
/* ------------------------------------------------------------------ */

/** IP du client au format binaire (pour colonne VARBINARY(16)). */
function ip_binaire(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $bin = @inet_pton($ip);
    return $bin !== false ? $bin : null;
}

/** Jeton CSRF stocké en session (protège les formulaires de l'admin). */
function csrf_jeton(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valide(?string $jeton): bool
{
    return !empty($_SESSION['csrf']) && is_string($jeton)
        && hash_equals($_SESSION['csrf'], $jeton);
}

/** Génère une référence de don unique et lisible (ex. FDM-2026-A1B2C3). */
function reference_don(): string
{
    return 'FDM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Téléverse une image depuis un champ de formulaire ($_FILES[$champ]).
 * Valide le type MIME réel et la taille, puis enregistre le fichier dans
 * public/uploads/<sousDossier>/ sous un nom aléatoire sûr.
 * Retourne le nom de fichier enregistré, ou null si aucun fichier /
 * lève une RuntimeException en cas de fichier invalide.
 */
function televerser_image(string $champ, string $sousDossier): ?string
{
    if (empty($_FILES[$champ]) || ($_FILES[$champ]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // aucun fichier fourni : ce n'est pas une erreur
    }
    $f = $_FILES[$champ];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Téléversement interrompu (code ' . $f['error'] . ').');
    }
    $cfg = fdm_config()['uploads'];
    if ($f['size'] > $cfg['max_octets']) {
        throw new RuntimeException('Image trop lourde (maximum '
            . round($cfg['max_octets'] / 1048576, 1) . ' Mo).');
    }
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    if (!in_array($mime, $cfg['types'], true)) {
        throw new RuntimeException('Format non autorisé (JPEG, PNG, WebP ou GIF).');
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
    $dir = rtrim($cfg['dir'], '/') . '/' . $sousDossier;
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $nom = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $nom)) {
        // move_uploaded_file échoue hors contexte HTTP réel : repli copie
        if (!@rename($f['tmp_name'], $dir . '/' . $nom)) {
            throw new RuntimeException('Impossible d\'enregistrer l\'image.');
        }
    }
    return $nom;
}

/** Supprime une image téléversée (ignore si absente). */
function supprimer_image(?string $fichier, string $sousDossier): void
{
    if (!$fichier) { return; }
    $chemin = rtrim(fdm_config()['uploads']['dir'], '/') . '/' . $sousDossier . '/' . basename($fichier);
    if (is_file($chemin)) { @unlink($chemin); }
}
