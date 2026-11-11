<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && in_array($action, ['nouveau','traite','archive'], true)) {
        $pdo->prepare("UPDATE contacts SET statut=? WHERE id=?")->execute([$action, $id]); flash('Statut mis à jour.');
    } elseif ($id && $action === 'supprimer') {
        $pdo->prepare("DELETE FROM contacts WHERE id=?")->execute([$id]); flash('Message supprimé.');
    }
    header('Location: contacts.php'); exit;
}

$lignes = $pdo->query("SELECT * FROM contacts ORDER BY cree_le DESC")->fetchAll();
$titre = 'Messages de contact';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<table>
  <tr><th>Expéditeur</th><th>Objet</th><th>Message</th><th>Reçu le</th><th>Statut</th><th>Actions</th></tr>
  <?php foreach ($lignes as $c): $p=['nouveau'=>'p-nouveau','traite'=>'p-publie','archive'=>'p-rejete'][$c['statut']]; ?>
  <tr>
    <td><b><?= e(trim($c['prenom'].' '.$c['nom'])) ?></b>
      <?php if($c['organisation']):?><br><span class="aide"><?= e($c['organisation']) ?></span><?php endif;?>
      <br><span class="aide"><?= e($c['email']) ?></span>
      <?php if($c['telephone']):?><br><span class="aide"><?= e($c['telephone']) ?></span><?php endif;?></td>
    <td><?= e($c['objet']) ?></td>
    <td><?= nl2br(e($c['message'])) ?></td>
    <td><?= e(substr($c['cree_le'],0,16)) ?></td>
    <td><span class="pastille <?= $p ?>"><?= e($c['statut']) ?></span></td>
    <td><form method="post" class="ligne-actions"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn sm" name="action" value="traite">Traité</button>
        <button class="btn gris sm" name="action" value="archive">Archiver</button>
        <button class="btn danger sm" name="action" value="supprimer" onclick="return confirm('Supprimer ?')">×</button>
    </form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="6" style="color:#A99490">Aucun message.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
