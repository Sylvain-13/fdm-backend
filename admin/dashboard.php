<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

$n = function (string $sql) use ($pdo): int { return (int)$pdo->query($sql)->fetchColumn(); };
$stats = [
    'Actualités publiées'   => $n("SELECT COUNT(*) FROM actualites WHERE statut='publie'"),
    'Brouillons'            => $n("SELECT COUNT(*) FROM actualites WHERE statut='brouillon'"),
    'Commentaires à modérer'=> $n("SELECT COUNT(*) FROM commentaires WHERE statut='en_attente'"),
    'Événements publiés'    => $n("SELECT COUNT(*) FROM evenements WHERE statut='publie'"),
    'Dons confirmés'        => $n("SELECT COUNT(*) FROM dons WHERE statut='confirme'"),
    'Virements à vérifier'  => $n("SELECT COUNT(*) FROM dons WHERE moyen='virement' AND statut='initie'"),
    'Bénévoles (nouveaux)'  => $n("SELECT COUNT(*) FROM benevoles WHERE statut='nouveau'"),
    'Messages (nouveaux)'   => $n("SELECT COUNT(*) FROM contacts WHERE statut='nouveau'"),
    'Alumnis'               => $n("SELECT COUNT(*) FROM alumnis"),
    'Abonnés newsletter'    => $n("SELECT COUNT(*) FROM newsletter WHERE actif=1"),
];
$totalDons = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM dons WHERE statut='confirme'")->fetchColumn();
$nbAdmins = $n("SELECT COUNT(*) FROM administrateurs WHERE actif=1");

$titre = 'Tableau de bord';
require __DIR__ . '/inc/header.php';
?>
<?php if ($nbAdmins < 2): ?>
  <div class="flash err">Gouvernance : il n'existe qu'<b>un seul</b> administrateur actif.
    Créez-en un second (<a href="administrateurs.php">Administrateurs</a>) pour éviter tout point unique de défaillance.</div>
<?php endif; ?>

<div class="cartes">
  <?php foreach ($stats as $libelle => $valeur): ?>
    <div class="carte"><div class="n"><?= $valeur ?></div><div class="l"><?= e($libelle) ?></div></div>
  <?php endforeach; ?>
  <div class="carte"><div class="n"><?= number_format($totalDons, 0, ',', ' ') ?></div>
    <div class="l">Total des dons confirmés (XAF)</div></div>
</div>

<p style="margin-top:28px;color:#7A6462;font-size:14px;line-height:1.6">
  Bienvenue dans l'administration du site de la Fondation. Utilisez le menu de gauche
  pour publier des actualités et des événements, modérer les commentaires, suivre les dons
  et gérer la base des alumnis (import et export CSV pour les envois groupés).
</p>
<?php require __DIR__ . '/inc/footer.php'; ?>
