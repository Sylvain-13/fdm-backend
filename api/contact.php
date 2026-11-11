<?php
/**
 * Formulaire de contact (public).
 *   POST { organisation?, nom, prenom?, email, tel?, objet?, message }
 */
require_once __DIR__ . '/_bootstrap.php';
exiger_methode('POST');
$pdo = db();
$in = corps_json() ?: $_POST;

if (!anti_spam($in)) { json_out(['ok' => true]); }

$nom     = propre($in['nom'] ?? '');
$email   = propre($in['email'] ?? '');
$message = propre($in['message'] ?? '');
$erreurs = [];
if ($nom === '')        { $erreurs['nom'] = 'Le nom est obligatoire.'; }
if (!est_email($email)) { $erreurs['email'] = 'Un e-mail valide est obligatoire.'; }
if (mb_strlen($message) < 3) { $erreurs['message'] = 'Le message est trop court.'; }
if ($erreurs) { json_erreur('Formulaire incomplet.', 422, ['champs' => $erreurs]); }

$st = $pdo->prepare(
    "INSERT INTO contacts (organisation, nom, prenom, email, telephone, objet, message, ip)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$st->execute([
    propre($in['organisation'] ?? '') ?: null,
    $nom,
    propre($in['prenom'] ?? '') ?: null,
    $email,
    propre($in['tel'] ?? ($in['telephone'] ?? '')) ?: null,
    propre($in['objet'] ?? '') ?: null,
    $message,
    ip_binaire(),
]);

// Notification interne (ne bloque pas la réponse en cas d'échec d'envoi)
$admin = fdm_config()['site']['email_admin'];
@envoyer_mail($admin, 'Nouveau message de contact — site FDM',
    '<p>Un nouveau message de contact a été reçu.</p>'
    . '<p><b>' . e($nom) . '</b> (' . e($email) . ')</p>'
    . '<p>Objet : ' . e($in['objet'] ?? '') . '</p>'
    . '<p>' . nl2br(e($message)) . '</p>');

json_out(['ok' => true, 'message' => 'Merci, votre message a bien été envoyé.'], 201);
