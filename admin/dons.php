<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();
$admin = admin_courant();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    // Rapprochement d'un VIREMENT bancaire : validé à la main par un admin
    // après vérification sur le relevé de la Fondation.
    if ($id && $action === 'confirmer_virement') {
        $pdo->prepare("UPDATE dons SET statut='confirme', confirme_le=NOW(), rapproche_par=?
                       WHERE id=? AND moyen='virement'")->execute([$admin['id'], $id]);
        flash('Virement rapproché et don confirmé.');
    } elseif ($id && $action === 'annuler') {
        $pdo->prepare("UPDATE dons SET statut='annule' WHERE id=?")->execute([$id]);
        flash('Don annulé.');
    }
    header('Location: dons.php' . (!empty($_POST['qs']) ? '?'.$_POST['qs'] : '')); exit;
}

// Export CSV des dons confirmés
if (($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query("SELECT reference,donateur_nom,donateur_email,montant,devise,moyen,canal,statut,confirme_le,cree_le
                         FROM dons ORDER BY cree_le DESC")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dons_fdm_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($out, ['Reference','Nom','Email','Montant','Devise','Moyen','Canal','Statut','Confirme_le','Cree_le'], ';');
    foreach ($rows as $r) { fputcsv($out, $r, ';'); }
    fclose($out); exit;
}

$fMoyen = $_GET['moyen'] ?? '';
$fStatut = $_GET['statut'] ?? '';
$where = []; $args = [];
if (in_array($fMoyen, ['dohone','virement'], true)) { $where[]='moyen=?'; $args[]=$fMoyen; }
if (in_array($fStatut, ['initie','confirme','echoue','annule'], true)) { $where[]='statut=?'; $args[]=$fStatut; }
$clause = $where ? 'WHERE '.implode(' AND ',$where) : '';
$st = $pdo->prepare("SELECT * FROM dons $clause ORDER BY cree_le DESC LIMIT 500");
$st->execute($args);
$lignes = $st->fetchAll();
$qs = http_build_query(array_filter(['moyen'=>$fMoyen,'statut'=>$fStatut]));

$titre = 'Dons';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
$pcl = ['initie'=>'p-attente','confirme'=>'p-publie','echoue'=>'p-rejete','annule'=>'p-rejete'];
?>
<div class="ligne-actions" style="margin-bottom:18px;justify-content:space-between">
  <div class="ligne-actions">
    <a class="btn <?= $fMoyen===''&&$fStatut===''?'':'sec' ?> sm" href="dons.php">Tous</a>
    <a class="btn <?= $fMoyen==='dohone'?'':'sec' ?> sm" href="?moyen=dohone">Dohone</a>
    <a class="btn <?= $fMoyen==='virement'?'':'sec' ?> sm" href="?moyen=virement">Virements</a>
    <a class="btn <?= $fStatut==='initie'?'':'sec' ?> sm" href="?statut=initie">À confirmer</a>
    <a class="btn <?= $fStatut==='confirme'?'':'sec' ?> sm" href="?statut=confirme">Confirmés</a>
  </div>
  <a class="btn sec sm" href="?export=csv">Exporter en CSV</a>
</div>
<p class="aide" style="margin-bottom:14px">Les dons Dohone sont confirmés automatiquement par la notification
  sécurisée de Dohone. Les virements bancaires se confirment ici, à la main, après vérification sur le relevé.</p>
<table>
  <tr><th>Référence</th><th>Donateur</th><th>Montant</th><th>Moyen</th><th>Canal</th><th>Statut</th><th>Reçu le</th><th>Action</th></tr>
  <?php foreach ($lignes as $d): ?>
  <tr>
    <td><b><?= e($d['reference']) ?></b></td>
    <td><?= e($d['donateur_nom'] ?: '—') ?><?php if($d['donateur_email']):?><br><span class="aide"><?= e($d['donateur_email']) ?></span><?php endif;?></td>
    <td><b><?= number_format((float)$d['montant'],0,',',' ') ?></b> <?= e($d['devise']) ?></td>
    <td><?= e($d['moyen']) ?></td>
    <td><?= e($d['canal'] ?: '—') ?></td>
    <td><span class="pastille <?= $pcl[$d['statut']] ?>"><?= e($d['statut']) ?></span></td>
    <td><?= e(substr($d['cree_le'],0,16)) ?></td>
    <td><div class="ligne-actions">
      <?php if ($d['moyen']==='virement' && $d['statut']==='initie'): ?>
        <form method="post" onsubmit="return confirm('Confirmer la réception de ce virement ?')">
          <input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <input type="hidden" name="qs" value="<?= e($qs) ?>">
          <button class="btn sm" name="action" value="confirmer_virement">Confirmer</button></form>
      <?php endif; ?>
      <?php if ($d['statut']==='initie'): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <input type="hidden" name="qs" value="<?= e($qs) ?>"><button class="btn gris sm" name="action" value="annuler">Annuler</button></form>
      <?php endif; ?>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="8" style="color:#A99490">Aucun don.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
