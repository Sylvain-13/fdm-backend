<?php
/**
 * Authentification de l'administration.
 * - démarre la session de façon sécurisée ;
 * - gère la connexion (mot de passe haché) et la double authentification
 *   par e-mail (2FA) exigée au-delà de N jours depuis la dernière
 *   vérification (voir config 'jours_2fa', par défaut 30) ;
 * - protège les pages par exiger_connexion().
 */

require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/mailer.php';

$cfg = fdm_config();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($cfg['securite']['session_nom']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // à activer quand le site est en HTTPS
    ]);
    session_start();
}

/** Admin pleinement connecté (login + 2FA validée), ou null. */
function admin_courant(): ?array
{
    return $_SESSION['admin'] ?? null;
}

/** Message flash (affiché une fois au prochain chargement de page). */
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['m' => $msg, 't' => $type];
}

/** Protège une page : redirige vers la connexion si non authentifié. */
function exiger_connexion(): void
{
    if (!admin_courant()) {
        header('Location: index.php');
        exit;
    }
}

/** Faut-il demander la 2FA à cet admin ? (jamais vérifiée ou trop ancienne) */
function exige_2fa(array $admin): bool
{
    $jours = (int) fdm_config()['securite']['jours_2fa'];
    if (empty($admin['derniere_verif_2fa'])) { return true; }
    $delta = time() - strtotime($admin['derniere_verif_2fa']);
    return $delta > $jours * 86400;
}

/** Génère un code 2FA, l'enregistre (haché) et l'envoie par e-mail. */
function envoyer_code_2fa(array $admin): bool
{
    $s = fdm_config()['securite'];
    $code = str_pad((string) random_int(0, (10 ** $s['code_longueur']) - 1),
        $s['code_longueur'], '0', STR_PAD_LEFT);
    $pdo = db();
    // Invalide les anciens codes non utilisés
    $pdo->prepare("UPDATE admin_codes_2fa SET utilise=1 WHERE admin_id=? AND utilise=0")
        ->execute([$admin['id']]);
    $pdo->prepare(
        "INSERT INTO admin_codes_2fa (admin_id, code_hash, expire_le)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))")
        ->execute([$admin['id'], password_hash($code, PASSWORD_BCRYPT), (int)$s['code_minutes']]);

    return envoyer_mail(
        $admin['email'],
        'Votre code de connexion — Administration FDM',
        '<p>Bonjour ' . e($admin['nom']) . ',</p>'
        . '<p>Votre code de connexion à l\'administration du site est :</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:4px;color:#990103">' . $code . '</p>'
        . '<p>Ce code est valable ' . (int)$s['code_minutes'] . ' minutes. '
        . 'Si vous n\'êtes pas à l\'origine de cette connexion, ignorez ce message '
        . 'et changez votre mot de passe.</p>');
}

/** Vérifie un code 2FA saisi pour l'admin en attente (pré-auth). */
function verifier_code_2fa(int $adminId, string $codeSaisi): bool
{
    $pdo = db();
    $st = $pdo->prepare(
        "SELECT * FROM admin_codes_2fa
         WHERE admin_id=? AND utilise=0 AND expire_le > NOW()
         ORDER BY id DESC LIMIT 1");
    $st->execute([$adminId]);
    $code = $st->fetch();
    if (!$code) { return false; }

    if ($code['tentatives'] >= (int) fdm_config()['securite']['code_essais']) {
        $pdo->prepare("UPDATE admin_codes_2fa SET utilise=1 WHERE id=?")->execute([$code['id']]);
        return false;
    }
    if (password_verify(trim($codeSaisi), $code['code_hash'])) {
        $pdo->prepare("UPDATE admin_codes_2fa SET utilise=1 WHERE id=?")->execute([$code['id']]);
        return true;
    }
    $pdo->prepare("UPDATE admin_codes_2fa SET tentatives = tentatives + 1 WHERE id=?")
        ->execute([$code['id']]);
    return false;
}

/** Finalise la connexion : enregistre l'admin en session. */
function ouvrir_session_admin(array $admin, bool $marquer2fa): void
{
    session_regenerate_id(true);
    $pdo = db();
    if ($marquer2fa) {
        $pdo->prepare("UPDATE administrateurs SET derniere_verif_2fa=NOW(), derniere_connexion=NOW() WHERE id=?")
            ->execute([$admin['id']]);
        $admin['derniere_verif_2fa'] = date('Y-m-d H:i:s');
    } else {
        $pdo->prepare("UPDATE administrateurs SET derniere_connexion=NOW() WHERE id=?")
            ->execute([$admin['id']]);
    }
    unset($_SESSION['pre_auth']);
    $_SESSION['admin'] = [
        'id' => (int)$admin['id'], 'nom' => $admin['nom'],
        'email' => $admin['email'], 'role' => $admin['role'],
    ];
}
