<?php
/**
 * Inscription à la newsletter (public).
 *   POST { email }
 * L'e-mail est stocké une seule fois (réinscription réactive l'abonnement).
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('POST');
$pdo = db();
$in = corps_json() ?: $_POST;

if (!anti_spam($in)) { json_out(['ok' => true]); }

$email = propre($in['email'] ?? '');
if (!est_email($email)) {
    json_erreur('Un e-mail valide est obligatoire.', 422, ['champs' => ['email' => 'E-mail invalide.']]);
}

// INSERT ... ON DUPLICATE : évite les doublons, réactive si déjà présent.
$st = $pdo->prepare(
    "INSERT INTO newsletter (email, actif, ip) VALUES (?, 1, ?)
     ON DUPLICATE KEY UPDATE actif = 1");
$st->execute([$email, ip_binaire()]);

json_out(['ok' => true, 'message' => 'Inscription confirmée. Merci !'], 201);
