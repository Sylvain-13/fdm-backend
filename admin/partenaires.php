<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'supprimer' && $id) {
        $logo = $pdo->query("SELECT logo FROM partenaires WHERE id=".$id)->fetchColumn();
        supprimer_image($logo ?: null, 'partenaires');
        $pdo->prepare("DELETE FROM partenaires WHERE id=?")->execute([$id]);
        flash('Partenaire supprimé.');
    } elseif ($action === 'enregistrer') {
        $nom = propre($_POST['nom'] ?? '');
        $desc = propre($_POST['description'] ?? '');
        $site = propre($_POST['site_web'] ?? '');
        $ordre = (int)($_POST['ordre'] ?? 0);
        $actif = !empty($_POST['actif']) ? 1 : 0;
        if ($nom !== '') {
            try {
                if ($id) {
                    $logo = $pdo->query("SELECT logo FROM partenaires WHERE id=".$id)->fetchColumn() ?: null;
                    $nouveau = televerser_image('logo','partenaires');
                    if ($nouveau) { supprimer_image($logo,'partenaires'); $logo=$nouveau; }
                    $pdo->prepare("UPDATE partenaires SET nom=?,description=?,site_web=?,ordre=?,actif=?,logo=? WHERE id=?")
                        ->execute([$nom,$desc,$site?:null,$ordre,$actif,$logo,$id]);
                    flash('Partenaire mis à jour.');
                } else {
                    $logo = televerser_image('logo','partenaires');
                    $pdo->prepare("INSERT INTO partenaires (nom,logo,description,site_web,ordre,actif) VALUES (?,?,?,?,?,?)")
                        ->execute([$nom,$logo,$desc,$site?:null,$ordre,$actif]);
                    flash('Partenaire ajouté.');
                }
            } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
        } else { flash('Le nom est obligatoire.', 'err'); }
    }
    header('Location: partenaires.php'); exit;
}

$edit = null;
if (!empty($_GET['id'])) { $st=$pdo->prepare("SELECT * FROM partenaires WHERE id=?"); $st->execute([(int)$_GET['id']]); $edit=$st->fetch()?:null; }
$lignes = $pdo->query("SELECT * FROM partenaires ORDER BY ordre ASC, nom ASC")->fetchAll();

$titre = 'Partenaires';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<form class="bloc" method="post" enctype="multipart/form-data" style="margin-bottom:24px">
  <h3 style="margin:0 0 6px"><?= $edit ? 'Modifier le partenaire' : 'Ajouter un partenaire' ?></h3>
  <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
  <input type="hidden" name="action" value="enregistrer">
  <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <div style="flex:2;min-width:220px"><label>Nom *</label><input type="text" name="nom" required value="<?= e($edit['nom'] ?? '') ?>"></div>
    <div style="flex:1;min-width:120px"><label>Ordre</label><input type="number" name="ordre" value="<?= e($edit['ordre'] ?? 0) ?>"></div>
  </div>
  <label>Description</label><textarea name="description" style="min-height:70px"><?= e($edit['description'] ?? '') ?></textarea>
  <label>Site web</label><input type="url" name="site_web" placeholder="https://..." value="<?= e($edit['site_web'] ?? '') ?>">
  <label>Logo</label>
  <?php if (!empty($edit['logo'])): ?><p class="aide">Logo actuel : <?= e($edit['logo']) ?></p><?php endif; ?>
  <input type="file" name="logo" accept="image/*">
  <label style="display:flex;align-items:center;gap:8px;margin-top:14px">
    <input type="checkbox" name="actif" value="1" style="width:auto" <?= (!$edit || $edit['actif']) ? 'checked':'' ?>> Afficher sur le site
  </label>
  <div class="ligne-actions" style="margin-top:18px">
    <button class="btn" type="submit">Enregistrer</button>
    <?php if ($edit): ?><a class="btn sec" href="partenaires.php">Annuler la modification</a><?php endif; ?>
  </div>
</form>

<table>
  <tr><th>Ordre</th><th>Nom</th><th>Site</th><th>Affiché</th><th>Actions</th></tr>
  <?php foreach ($lignes as $p): ?>
  <tr>
    <td><?= (int)$p['ordre'] ?></td>
    <td><b><?= e($p['nom']) ?></b><?php if($p['logo']):?><br><span class="aide">🖼 <?= e($p['logo']) ?></span><?php endif;?></td>
    <td><?= $p['site_web'] ? '<a href="'.e($p['site_web']).'" target="_blank">'.e($p['site_web']).'</a>' : '—' ?></td>
    <td><span class="pastille <?= $p['actif']?'p-publie':'p-rejete' ?>"><?= $p['actif']?'Oui':'Non' ?></span></td>
    <td><div class="ligne-actions">
      <a class="btn sec sm" href="?id=<?= (int)$p['id'] ?>">Modifier</a>
      <form method="post" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn danger sm" name="action" value="supprimer">Supprimer</button></form>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="5" style="color:#A99490">Aucun partenaire.</td></tr><?php endif; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
