<?php
/**
 * Initiation d'un don (public).
 *   POST { montant, moyen(dohone|virement), nom?, email?, tel?, message? }
 *
 * Crée un don au statut « initie » avec une référence unique, et renvoie
 * les paramètres nécessaires :
 *   - moyen=dohone   -> paramètres à transmettre à Dohone pour le paiement.
 *                       La confirmation viendra ENSUITE du webhook Dohone.
 *   - moyen=virement -> la référence unique à rappeler lors du virement ;
 *                       le rapprochement est fait à la main côté admin.
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('POST');
$pdo = db();
$in = corps_json() ?: $_POST;

$montant = (float)($in['montant'] ?? 0);
$moyen   = ($in['moyen'] ?? 'dohone') === 'virement' ? 'virement' : 'dohone';

if ($montant <= 0) {
    json_erreur('Le montant du don doit être supérieur à zéro.', 422,
        ['champs' => ['montant' => 'Montant invalide.']]);
}

$email = propre($in['email'] ?? '');
if ($email !== '' && !est_email($email)) {
    json_erreur('E-mail invalide.', 422, ['champs' => ['email' => 'E-mail invalide.']]);
}

$cfg = fdm_config()['dohone'];
$reference = reference_don();

$st = $pdo->prepare(
    "INSERT INTO dons (reference, donateur_nom, donateur_email, donateur_tel,
                       montant, devise, moyen, message, statut)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'initie')");
$st->execute([
    $reference,
    propre($in['nom'] ?? '') ?: null,
    $email ?: null,
    propre($in['tel'] ?? '') ?: null,
    $montant,
    $cfg['devise'],
    $moyen,
    propre($in['message'] ?? '') ?: null,
]);

$don = [
    'reference' => $reference, 'montant' => $montant,
    'donateur_nom' => $in['nom'] ?? '', 'donateur_email' => $email,
    'donateur_tel' => $in['tel'] ?? '',
];

if ($moyen === 'virement') {
    json_out(['ok' => true, 'moyen' => 'virement', 'reference' => $reference,
        'message' => 'Merci. Indiquez impérativement la référence « ' . $reference
            . ' » lors de votre virement afin que nous puissions l\'identifier.'], 201);
}

json_out([
    'ok' => true, 'moyen' => 'dohone', 'reference' => $reference,
    'dohone' => dohone_parametres_paiement($don),
    'message' => 'Don initié. Vous allez être dirigé vers le paiement sécurisé.',
], 201);
