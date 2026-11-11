<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['nouveau','traite','archive'], true)) {
        $pdo->prepare("UPDATE benevoles SET statut=? WHERE id=?")->execute([$action, $id]);
        flash('Statut mis à jour.');
    } elseif ($id && $action === 'supprimer') {
        $pdo->prepare("DELETE FROM benevoles WHERE id=?")->execute([$id]); flash('Candidature supprimée.');
    }
    header('Location: benevoles.php'); exit;
}

$lignes = $pdo->query("SELECT * FROM benevoles ORDER BY cree_le DESC")->fetchAll();
$titre = 'Bénévoles';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<table>
  <tr><th>Nom</th><th>Contact</th><th>Domaine</th><th>Message</th><th>Reçu le</th><th>Statut</th><th>Actions</th></tr>
  <?php foreach ($lignes as $b): $p=['nouveau'=>'p-nouveau','traite'=>'p-publie','archive'=>'p-rejete'][$b['statut']]; ?>
  <tr>
    <td><b><?= e($b['prenom'].' '.$b['nom']) ?></b></td>
    <td><?= e($b['email']) ?><?php if($b['telephone']):?><br><span class="aide"><?= e($b['telephone']) ?></span><?php endif;?></td>
    <td><?= e($b['domaine']) ?></td>
    <td><?= nl2br(e($b['message'])) ?></td>
    <td><?= e(substr($b['cree_le'],0,16)) ?></td>
    <td><span class="pastille <?= $p ?>"><?= e($b['statut']) ?></span></td>
    <td><form method="post" class="ligne-actions"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <button class="btn sm" name="action" value="traite">Traité</button>
        <button class="btn gris sm" name="action" value="archive">Archiver</button>
        <button class="btn danger sm" name="action" value="supprimer" onclick="return confirm('Supprimer ?')">×</button>
    </form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="7" style="color:#A99490">Aucune candidature.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
