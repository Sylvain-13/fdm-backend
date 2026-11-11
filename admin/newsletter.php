<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query("SELECT email,cree_le FROM newsletter WHERE actif=1 ORDER BY cree_le DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter_fdm_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['EMAIL','INSCRIT_LE'], ';');
    foreach ($rows as $r) { fputcsv($out, [$r['email'], $r['cree_le']], ';'); }
    fclose($out); exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id && ($_POST['action'] ?? '') === 'desactiver') {
        $pdo->prepare("UPDATE newsletter SET actif=0 WHERE id=?")->execute([$id]); flash('Abonné désactivé.');
    } elseif ($id && ($_POST['action'] ?? '') === 'supprimer') {
        $pdo->prepare("DELETE FROM newsletter WHERE id=?")->execute([$id]); flash('Abonné supprimé.');
    }
    header('Location: newsletter.php'); exit;
}

$lignes = $pdo->query("SELECT * FROM newsletter ORDER BY actif DESC, cree_le DESC")->fetchAll();
$titre = 'Newsletter';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<div class="ligne-actions" style="margin-bottom:18px">
  <a class="btn" href="?export=csv">Exporter les abonnés (CSV)</a>
</div>
<p class="aide" style="margin-bottom:14px">Pour un envoi groupé, exportez ce fichier puis importez-le dans Brevo
  (ou la plateforme d'e-mailing retenue).</p>
<table>
  <tr><th>E-mail</th><th>Inscrit le</th><th>Statut</th><th>Actions</th></tr>
  <?php foreach ($lignes as $n): ?>
  <tr>
    <td><?= e($n['email']) ?></td>
    <td><?= e(substr($n['cree_le'],0,16)) ?></td>
    <td><span class="pastille <?= $n['actif']?'p-publie':'p-rejete' ?>"><?= $n['actif']?'Actif':'Désactivé' ?></span></td>
    <td><form method="post" class="ligne-actions"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
      <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
      <?php if($n['actif']):?><button class="btn gris sm" name="action" value="desactiver">Désactiver</button><?php endif;?>
      <button class="btn danger sm" name="action" value="supprimer" onclick="return confirm('Supprimer ?')">×</button>
    </form></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="4" style="color:#A99490">Aucun abonné.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
