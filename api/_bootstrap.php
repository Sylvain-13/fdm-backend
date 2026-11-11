<?php
/**
 * Amorce commune à tous les points d'entrée de l'API.
 * - charge la configuration et les librairies ;
 * - prépare quelques utilitaires propres à l'API (dates FR, URL d'images).
 * L'API renvoie exclusivement du JSON.
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/dohone.php';

fdm_config(); // initialise le fuseau horaire

// L'API et le site sont servis sur le même domaine : en-têtes simples.
header('X-Content-Type-Options: nosniff');

/** Réponses aux requêtes de pré-vérification (CORS), au cas où. */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

/* ------------------------------------------------------------------ */
/*  Utilitaires d'affichage                                           */
/* ------------------------------------------------------------------ */

const FDM_MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** Date SQL (Y-m-d) -> "12 octobre 2026" (ou "12 octobre" si $annee=false). */
function date_fr(?string $dateSql, bool $annee = true): string
{
    if (!$dateSql) { return ''; }
    $t = strtotime($dateSql);
    if ($t === false) { return ''; }
    $j = (int)date('j', $t);
    $m = FDM_MOIS[(int)date('n', $t)];
    return $annee ? "$j $m " . date('Y', $t) : "$j $m";
}

/** URL web complète d'une image téléversée (ou null si vide). */
function url_upload(string $sousDossier, ?string $fichier): ?string
{
    if (!$fichier) { return null; }
    $base = rtrim(fdm_config()['uploads']['url'], '/');
    return $base . '/' . $sousDossier . '/' . rawurlencode($fichier);
}

/**
 * Anti-spam léger pour les formulaires publics :
 * - champ "piège" (honeypot) qui doit rester vide ;
 * - délai minimal entre deux soumissions depuis la même IP.
 * Retourne true si la soumission est jugée légitime.
 */
function anti_spam(array $data, string $champPiege = 'site'): bool
{
    if (!empty($data[$champPiege])) { return false; } // un bot a rempli le piège
    return true;
}
