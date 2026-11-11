<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();
$admin = admin_courant();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['publie','rejete'], true)) {
        $pdo->prepare("UPDATE commentaires SET statut=?, modere_le=NOW(), modere_par=? WHERE id=?")
            ->execute([$action, $admin['id'], $id]);
        flash($action === 'publie' ? 'Commentaire publié.' : 'Commentaire rejeté.');
    } elseif ($id && $action === 'supprimer') {
        $pdo->prepare("DELETE FROM commentaires WHERE id=?")->execute([$id]);
        flash('Commentaire supprimé.');
    }
    header('Location: commentaires.php' . (!empty($_POST['f']) ? '?f=' . urlencode($_POST['f']) : '')); exit;
}

$filtre = $_GET['f'] ?? 'en_attente';
$valides = ['en_attente','publie','rejete'];
if (!in_array($filtre, $valides, true)) { $filtre = 'en_attente'; }

$st = $pdo->prepare(
    "SELECT c.*, a.titre AS article, a.slug
     FROM commentaires c JOIN actualites a ON a.id=c.actualite_id
     WHERE c.statut=? ORDER BY c.cree_le DESC");
$st->execute([$filtre]);
$lignes = $st->fetchAll();

$titre = 'Commentaires';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<div class="ligne-actions" style="margin-bottom:18px">
  <?php foreach (['en_attente'=>'À modérer','publie'=>'Publiés','rejete'=>'Rejetés'] as $k=>$lib): ?>
    <a class="btn <?= $filtre===$k?'':'sec' ?> sm" href="?f=<?= $k ?>"><?= $lib ?></a>
  <?php endforeach; ?>
</div>
<table>
  <tr><th>Auteur</th><th>Article</th><th>Commentaire</th><th>Reçu le</th><th>Actions</th></tr>
  <?php foreach ($lignes as $c): ?>
  <tr>
    <td><b><?= e($c['prenom'].' '.$c['nom']) ?></b><br><span class="aide"><?= e($c['email']) ?></span></td>
    <td><?= e($c['article']) ?></td>
    <td><?= nl2br(e($c['message'])) ?></td>
    <td><?= e(substr($c['cree_le'],0,16)) ?></td>
    <td><div class="ligne-actions">
      <form method="post"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="f" value="<?= e($filtre) ?>">
        <?php if ($filtre!=='publie'): ?><button class="btn sm" name="action" value="publie">Publier</button><?php endif; ?>
        <?php if ($filtre!=='rejete'): ?><button class="btn gris sm" name="action" value="rejete">Rejeter</button><?php endif; ?>
      </form>
      <form method="post" onsubmit="return confirm('Supprimer définitivement ?')">
        <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="f" value="<?= e($filtre) ?>">
        <button class="btn danger sm" name="action" value="supprimer">Supprimer</button>
      </form>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="5" style="color:#A99490">Aucun commentaire dans cette catégorie.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
