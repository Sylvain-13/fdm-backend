<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

// Actions rapides (publier / dépublier / supprimer)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'publier') {
        $pdo->prepare("UPDATE actualites SET statut='publie' WHERE id=?")->execute([$id]);
        flash('Actualité publiée.');
    } elseif ($id && $action === 'depublier') {
        $pdo->prepare("UPDATE actualites SET statut='brouillon' WHERE id=?")->execute([$id]);
        flash('Actualité repassée en brouillon.');
    } elseif ($id && $action === 'supprimer') {
        $img = $pdo->query("SELECT image FROM actualites WHERE id=" . $id)->fetchColumn();
        supprimer_image($img ?: null, 'actualites');
        $pdo->prepare("DELETE FROM actualites WHERE id=?")->execute([$id]);
        flash('Actualité supprimée.');
    }
    header('Location: actualites.php'); exit;
}

$lignes = $pdo->query(
    "SELECT a.*, (SELECT COUNT(*) FROM commentaires c WHERE c.actualite_id=a.id AND c.statut='en_attente') AS com_attente
     FROM actualites a ORDER BY a.date_publication DESC, a.id DESC")->fetchAll();

$titre = 'Actualités';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<div class="ligne-actions" style="margin-bottom:18px">
  <a class="btn" href="actualite_edit.php">+ Nouvelle actualité</a>
</div>
<table>
  <tr><th>Titre</th><th>Catégorie</th><th>Date</th><th>Statut</th><th>Vues</th><th>Actions</th></tr>
  <?php foreach ($lignes as $a): ?>
  <tr>
    <td><b><?= e($a['titre']) ?></b>
      <?php if ($a['com_attente'] > 0): ?><span class="pastille p-attente"><?= $a['com_attente'] ?> com. à modérer</span><?php endif; ?></td>
    <td><?= e($a['categorie']) ?></td>
    <td><?= e($a['date_publication']) ?></td>
    <td><span class="pastille <?= $a['statut']==='publie'?'p-publie':'p-attente' ?>"><?= e($a['statut']) ?></span></td>
    <td><?= (int)$a['vues'] ?></td>
    <td><div class="ligne-actions">
      <a class="btn sec sm" href="actualite_edit.php?id=<?= (int)$a['id'] ?>">Modifier</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <?php if ($a['statut']==='publie'): ?>
          <button class="btn gris sm" name="action" value="depublier">Dépublier</button>
        <?php else: ?>
          <button class="btn sm" name="action" value="publier">Publier</button>
        <?php endif; ?>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette actualité ?')">
        <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="btn danger sm" name="action" value="supprimer">Supprimer</button>
      </form>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="6" style="color:#A99490">Aucune actualité pour le moment.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
