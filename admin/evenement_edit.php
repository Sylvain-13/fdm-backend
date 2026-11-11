<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$ev = ['id'=>0,'titre'=>'','affiche'=>null,'description'=>'','lieu'=>'',
       'date_debut'=>date('Y-m-d'),'date_fin'=>'','statut'=>'brouillon'];
if ($id) { $st=$pdo->prepare("SELECT * FROM evenements WHERE id=?"); $st->execute([$id]); $ev=$st->fetch()?:$ev; }

$erreur = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valide($_POST['csrf'] ?? null)) { $erreur = 'Session expirée.'; }
    else {
        $titre_e = propre($_POST['titre'] ?? '');
        $desc = propre($_POST['description'] ?? '');
        $lieu = propre($_POST['lieu'] ?? '');
        $dd = propre($_POST['date_debut'] ?? date('Y-m-d'));
        $df = propre($_POST['date_fin'] ?? '') ?: null;
        $statut = ($_POST['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon';
        $ts = strtotime($dd) ?: time();
        $mois = (int)date('n', $ts); $annee = (int)date('Y', $ts);
        if ($titre_e === '') { $erreur = 'Le titre est obligatoire.'; }
        if (!$erreur) {
            try {
                $affiche = $ev['affiche'];
                $nouvelle = televerser_image('affiche', 'evenements');
                if ($nouvelle) { supprimer_image($affiche, 'evenements'); $affiche = $nouvelle; }
                if ($id) {
                    $pdo->prepare("UPDATE evenements SET titre=?,affiche=?,description=?,lieu=?,date_debut=?,date_fin=?,mois=?,annee=?,statut=? WHERE id=?")
                        ->execute([$titre_e,$affiche,$desc,$lieu,$dd,$df,$mois,$annee,$statut,$id]);
                    flash('Événement mis à jour.');
                } else {
                    $pdo->prepare("INSERT INTO evenements (titre,affiche,description,lieu,date_debut,date_fin,mois,annee,statut) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$titre_e,$affiche,$desc,$lieu,$dd,$df,$mois,$annee,$statut]);
                    flash('Événement créé.');
                }
                header('Location: evenements.php'); exit;
            } catch (RuntimeException $ex) { $erreur = $ex->getMessage(); }
        }
        $ev = array_merge($ev, ['titre'=>$titre_e,'description'=>$desc,'lieu'=>$lieu,'date_debut'=>$dd,'date_fin'=>$df,'statut'=>$statut]);
    }
}

$titre = $id ? 'Modifier un événement' : 'Nouvel événement';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<?php if ($erreur): ?><div class="flash err"><?= e($erreur) ?></div><?php endif; ?>
<form class="bloc" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
  <label>Titre *</label><input type="text" name="titre" required value="<?= e($ev['titre']) ?>">
  <label>Description</label><textarea name="description"><?= e($ev['description']) ?></textarea>
  <label>Lieu</label><input type="text" name="lieu" value="<?= e($ev['lieu']) ?>">
  <label>Affiche</label>
  <?php if (!empty($ev['affiche'])): ?><p class="aide">Affiche actuelle : <?= e($ev['affiche']) ?></p><?php endif; ?>
  <input type="file" name="affiche" accept="image/*">
  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <div style="flex:1;min-width:160px"><label>Date de début *</label>
      <input type="date" name="date_debut" required value="<?= e(substr($ev['date_debut'],0,10)) ?>">
      <p class="aide">Le mois du filtre public est déduit de cette date.</p></div>
    <div style="flex:1;min-width:160px"><label>Date de fin</label>
      <input type="date" name="date_fin" value="<?= e($ev['date_fin']?substr($ev['date_fin'],0,10):'') ?>"></div>
    <div style="flex:1;min-width:160px"><label>Statut</label>
      <select name="statut"><option value="brouillon" <?= $ev['statut']==='brouillon'?'selected':'' ?>>Brouillon</option>
      <option value="publie" <?= $ev['statut']==='publie'?'selected':'' ?>>Publié</option></select></div>
  </div>
  <div class="ligne-actions" style="margin-top:22px">
    <button class="btn" type="submit">Enregistrer</button><a class="btn sec" href="evenements.php">Annuler</a>
  </div>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
