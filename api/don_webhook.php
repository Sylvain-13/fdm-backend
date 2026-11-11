<?php
/**
 * Webhook Dohone (notify) — appelé par les serveurs de Dohone,
 * JAMAIS par le navigateur du donateur.
 *
 * C'est l'UNIQUE source de vérité pour confirmer un don en ligne :
 *   1. on vérifie la signature de la notification ;
 *   2. on retrouve le don par sa référence ;
 *   3. on contrôle le montant ;
 *   4. on confirme — de façon idempotente (une 2e notification ne
 *      recompte pas le don).
 *
 * Les noms de champs reçus dépendent de votre compte Dohone : adaptez
 * la correspondance ci-dessous ($champ_*) à votre documentation.
 */
require_once __DIR__ . '/_bootstrap.php';
$pdo = db();

// Dohone peut notifier en GET ou en POST ; on fusionne les deux.
$recu = array_merge($_GET, $_POST);
if (!$recu) { $recu = corps_json(); }

// Journalise toute notification entrante (audit / débogage).
$dir = __DIR__ . '/../logs';
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
@file_put_contents($dir . '/dohone.log',
    '[' . date('Y-m-d H:i:s') . '] ' . json_encode($recu, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX);

/* ---- Correspondance des champs (à adapter au compte Dohone) ---- */
$champ_reference   = $recu['rN']  ?? ($recu['reference'] ?? '');
$champ_montant     = $recu['mt']  ?? ($recu['montant'] ?? '');
$champ_transaction = $recu['idPaiement'] ?? ($recu['transaction'] ?? '');
$champ_canal       = $recu['mode'] ?? ($recu['canal'] ?? null);
$champ_statut      = strtolower((string)($recu['statut'] ?? $recu['status'] ?? 'success'));

/* ---- 1. Vérification de la signature ---- */
$verif = dohone_verifier_notification($recu, 'hash');
if (!$verif['valide']) {
    http_response_code(403);
    error_log('[FDM][Dohone] Notification rejetée : ' . $verif['raison']);
    echo 'REFUSE';
    exit;
}

/* ---- 2. Retrouver le don ---- */
$reference = propre((string)$champ_reference);
if ($reference === '') { http_response_code(400); echo 'SANS_REFERENCE'; exit; }

$st = $pdo->prepare("SELECT * FROM dons WHERE reference = ? LIMIT 1");
$st->execute([$reference]);
$don = $st->fetch();
if (!$don) { http_response_code(404); echo 'INCONNU'; exit; }

/* ---- 3. Idempotence : déjà confirmé ? ---- */
if ($don['statut'] === 'confirme') { echo 'OK'; exit; }

/* ---- 4. Contrôle du montant ---- */
$montantRecu = (float)$champ_montant;
if ($montantRecu > 0 && abs($montantRecu - (float)$don['montant']) > 0.5) {
    $pdo->prepare("UPDATE dons SET statut='echoue', dohone_payload=? WHERE id=?")
        ->execute([json_encode($recu, JSON_UNESCAPED_UNICODE), $don['id']]);
    error_log('[FDM][Dohone] Montant incohérent pour ' . $reference);
    http_response_code(409); echo 'MONTANT'; exit;
}

/* ---- 5. Statut de paiement ---- */
$succes = in_array($champ_statut, ['success', 'ok', 'confirme', 'completed', '1', 'true'], true);
$nouveau = $succes ? 'confirme' : 'echoue';

$up = $pdo->prepare(
    "UPDATE dons SET statut=?, canal=?, dohone_transaction=?, dohone_payload=?,
            confirme_le=" . ($succes ? 'NOW()' : 'NULL') . "
     WHERE id=?");
$up->execute([
    $nouveau,
    $champ_canal ? propre((string)$champ_canal) : null,
    $champ_transaction ? propre((string)$champ_transaction) : null,
    json_encode($recu, JSON_UNESCAPED_UNICODE),
    $don['id'],
]);

/* ---- 6. Notification interne + reçu au donateur (si confirmé) ---- */
if ($succes) {
    $admin = fdm_config()['site']['email_admin'];
    @envoyer_mail($admin, 'Don confirmé — ' . $reference,
        '<p>Un don a été confirmé par Dohone.</p>'
        . '<p>Référence : <b>' . e($reference) . '</b><br>'
        . 'Montant : <b>' . e($don['montant']) . ' ' . e($don['devise']) . '</b><br>'
        . 'Canal : ' . e($champ_canal ?? '') . '</p>');
    if (!empty($don['donateur_email'])) {
        @envoyer_mail($don['donateur_email'], 'Merci pour votre don — Fondation Denis Maugenest',
            '<p>Nous confirmons la réception de votre don de <b>' . e($don['montant'])
            . ' ' . e($don['devise']) . '</b>.</p>'
            . '<p>Référence : ' . e($reference) . '</p>'
            . '<p>Merci de votre générosité.</p>');
    }
}

echo 'OK';
