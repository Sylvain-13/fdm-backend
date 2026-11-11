<?php
/**
 * Commentaires d'un article (public).
 *   GET  ?slug=... (ou ?actualite_id=)  -> commentaires PUBLIÉS (sans e-mail)
 *   POST { slug|actualite_id, nom, prenom, email, message }
 *        -> enregistre un commentaire EN ATTENTE de modération.
 *
 * L'e-mail est obligatoire mais n'est jamais renvoyé au public.
 * Rien n'est publié tant qu'un administrateur ne l'a pas validé.
 */
require_once __DIR__ . '/_bootstrap.php';
$pdo = db();

/** Résout l'id d'actualité depuis slug ou id. */
function resoudre_actualite(PDO $pdo, $slug, $id): ?int
{
    if ($id) {
        $st = $pdo->prepare("SELECT id FROM actualites WHERE id=? AND statut='publie'");
        $st->execute([(int)$id]);
    } elseif ($slug) {
        $st = $pdo->prepare("SELECT id FROM actualites WHERE slug=? AND statut='publie'");
        $st->execute([propre($slug)]);
    } else {
        return null;
    }
    $v = $st->fetchColumn();
    return $v ? (int)$v : null;
}

/* ---------- LISTE (GET) ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $actuId = resoudre_actualite($pdo, $_GET['slug'] ?? null, $_GET['actualite_id'] ?? null);
    if (!$actuId) { json_erreur('Actualité introuvable.', 404); }

    $st = $pdo->prepare(
        "SELECT nom, prenom, message, cree_le
         FROM commentaires
         WHERE actualite_id = ? AND statut = 'publie'
         ORDER BY cree_le ASC");
    $st->execute([$actuId]);

    $items = array_map(function ($r) {
        return [
            'nom' => $r['nom'], 'prenom' => $r['prenom'], 'message' => $r['message'],
            'date_fr' => date_fr(substr($r['cree_le'], 0, 10)),
        ];
    }, $st->fetchAll());

    json_out(['ok' => true, 'total' => count($items), 'items' => $items]);
}

/* ---------- NOUVEAU (POST) ---------- */
exiger_methode('POST');
$in = corps_json() ?: $_POST;

if (!anti_spam($in)) { json_out(['ok' => true]); } // on ignore silencieusement les bots

$actuId = resoudre_actualite($pdo, $in['slug'] ?? null, $in['actualite_id'] ?? null);
if (!$actuId) { json_erreur('Actualité introuvable.', 404); }

$nom     = propre($in['nom'] ?? '');
$prenom  = propre($in['prenom'] ?? '');
$email   = propre($in['email'] ?? '');
$message = propre($in['message'] ?? '');

$erreurs = [];
if ($nom === '')            { $erreurs['nom'] = 'Le nom est obligatoire.'; }
if ($prenom === '')         { $erreurs['prenom'] = 'Le prénom est obligatoire.'; }
if (!est_email($email))     { $erreurs['email'] = 'Un e-mail valide est obligatoire.'; }
if (mb_strlen($message) < 3){ $erreurs['message'] = 'Le commentaire est trop court.'; }
if ($erreurs) { json_erreur('Formulaire incomplet.', 422, ['champs' => $erreurs]); }

$st = $pdo->prepare(
    "INSERT INTO commentaires (actualite_id, nom, prenom, email, message, ip)
     VALUES (?, ?, ?, ?, ?, ?)");
$st->execute([$actuId, $nom, $prenom, $email, $message, ip_binaire()]);

json_out(['ok' => true, 'message' =>
    'Merci ! Votre commentaire a été envoyé et sera publié après validation.'], 201);
