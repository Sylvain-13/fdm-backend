<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'publier')   { $pdo->prepare("UPDATE evenements SET statut='publie' WHERE id=?")->execute([$id]); flash('Événement publié.'); }
    elseif ($id && $action === 'depublier'){ $pdo->prepare("UPDATE evenements SET statut='brouillon' WHERE id=?")->execute([$id]); flash('Événement en brouillon.'); }
    elseif ($id && $action === 'supprimer'){ $img=$pdo->query("SELECT affiche FROM evenements WHERE id=".$id)->fetchColumn(); supprimer_image($img?:null,'evenements'); $pdo->prepare("DELETE FROM evenements WHERE id=?")->execute([$id]); flash('Événement supprimé.'); }
    header('Location: evenements.php'); exit;
}
$lignes = $pdo->query("SELECT * FROM evenements ORDER BY date_debut DESC, id DESC")->fetchAll();
$MOIS = [1=>'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

$titre = 'Événements';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<div class="ligne-actions" style="margin-bottom:18px"><a class="btn" href="evenement_edit.php">+ Nouvel événement</a></div>
<table>
  <tr><th>Titre</th><th>Date</th><th>Mois</th><th>Lieu</th><th>Statut</th><th>Actions</th></tr>
  <?php foreach ($lignes as $ev): ?>
  <tr>
    <td><b><?= e($ev['titre']) ?></b></td>
    <td><?= e($ev['date_debut']) ?></td>
    <td><?= e($MOIS[(int)$ev['mois']] ?? '') ?> <?= (int)$ev['annee'] ?></td>
    <td><?= e($ev['lieu']) ?></td>
    <td><span class="pastille <?= $ev['statut']==='publie'?'p-publie':'p-attente' ?>"><?= e($ev['statut']) ?></span></td>
    <td><div class="ligne-actions">
      <a class="btn sec sm" href="evenement_edit.php?id=<?= (int)$ev['id'] ?>">Modifier</a>
      <form method="post"><input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
        <?php if ($ev['statut']==='publie'): ?><button class="btn gris sm" name="action" value="depublier">Dépublier</button>
        <?php else: ?><button class="btn sm" name="action" value="publier">Publier</button><?php endif; ?></form>
      <form method="post" onsubmit="return confirm('Supprimer cet événement ?')"><input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
        <button class="btn danger sm" name="action" value="supprimer">Supprimer</button></form>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="6" style="color:#A99490">Aucun événement.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
