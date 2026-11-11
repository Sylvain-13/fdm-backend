<?php
/**
 * Page de retour du donateur (ouverte dans son navigateur après le
 * paiement). Elle est purement informative : la confirmation réelle du
 * don provient du webhook serveur-à-serveur, pas de cette page.
 */
require_once __DIR__ . '/_bootstrap.php';
$pdo = db();

$reference = propre($_GET['reference'] ?? ($_GET['rN'] ?? ''));
$statut = null;
if ($reference !== '') {
    $st = $pdo->prepare("SELECT statut, montant, devise FROM dons WHERE reference=? LIMIT 1");
    $st->execute([$reference]);
    $statut = $st->fetch() ?: null;
}

header('Content-Type: text/html; charset=utf-8');
$msg = 'Nous avons bien reçu votre demande.';
if ($statut && $statut['statut'] === 'confirme') {
    $msg = 'Votre don de ' . e($statut['montant']) . ' ' . e($statut['devise'])
         . ' est confirmé. Merci de votre générosité !';
} elseif ($statut) {
    $msg = 'Votre paiement est en cours de traitement. Vous recevrez une '
         . 'confirmation par e-mail dès qu\'il sera validé.';
}
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Merci — Fondation Denis Maugenest</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#FCFAF7;color:#2B1212;
       display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px}
  .c{max-width:520px;background:#fff;border:1px solid #EDE4DE;border-radius:24px;padding:44px;
     box-shadow:0 20px 50px rgba(43,18,18,.08);text-align:center}
  h1{color:#990103;font-size:26px;margin:0 0 14px}
  p{line-height:1.6;color:#5C4442}
  a{display:inline-block;margin-top:22px;padding:12px 26px;border-radius:999px;
    background:linear-gradient(180deg,#A80406,#8A0103);color:#fff;text-decoration:none;font-weight:700}
  .ref{margin-top:16px;font-size:13px;color:#A99490}
</style></head>
<body><div class="c">
  <h1>Merci</h1>
  <p><?= $msg ?></p>
  <?php if ($reference !== ''): ?><div class="ref">Référence : <?= e($reference) ?></div><?php endif; ?>
  <a href="<?= e(rtrim(fdm_config()['site']['base_url'], '/')) ?>/">Retour au site</a>
</div></body></html>
