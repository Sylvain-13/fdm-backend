<?php
/**
 * Candidature bénévole (public).
 *   POST { nom, prenom, email, tel?, domaine?, message? }
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('POST');
$pdo = db();
$in = corps_json() ?: $_POST;

if (!anti_spam($in)) { json_out(['ok' => true]); }

$nom    = propre($in['nom'] ?? '');
$prenom = propre($in['prenom'] ?? '');
$email  = propre($in['email'] ?? '');
$erreurs = [];
if ($nom === '')        { $erreurs['nom'] = 'Le nom est obligatoire.'; }
if ($prenom === '')     { $erreurs['prenom'] = 'Le prénom est obligatoire.'; }
if (!est_email($email)) { $erreurs['email'] = 'Un e-mail valide est obligatoire.'; }
if ($erreurs) { json_erreur('Formulaire incomplet.', 422, ['champs' => $erreurs]); }

$st = $pdo->prepare(
    "INSERT INTO benevoles (nom, prenom, email, telephone, domaine, message, ip)
     VALUES (?, ?, ?, ?, ?, ?, ?)");
$st->execute([
    $nom, $prenom, $email,
    propre($in['tel'] ?? ($in['telephone'] ?? '')) ?: null,
    propre($in['domaine'] ?? '') ?: null,
    propre($in['message'] ?? '') ?: null,
    ip_binaire(),
]);

$admin = fdm_config()['site']['email_admin'];
@envoyer_mail($admin, 'Nouvelle candidature bénévole — site FDM',
    '<p>Une nouvelle candidature bénévole a été reçue.</p>'
    . '<p><b>' . e($prenom . ' ' . $nom) . '</b> (' . e($email) . ')</p>'
    . '<p>Domaine : ' . e($in['domaine'] ?? '') . '</p>'
    . '<p>' . nl2br(e($in['message'] ?? '')) . '</p>');

json_out(['ok' => true, 'message' => 'Merci pour votre engagement ! Nous vous recontacterons.'], 201);
