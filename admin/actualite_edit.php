<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();
$admin = admin_courant();

$cats = ['campagne' => 'Campagne', 'candidatures' => 'Appel à candidatures',
         'evenement' => 'Événement', 'fodes' => 'FODES', 'alumni' => 'Alumni', 'bourses' => 'Bourses'];

$id = (int)($_GET['id'] ?? 0);
$actu = ['id'=>0,'titre'=>'','categorie'=>'campagne','chapeau'=>'','corps'=>'','image'=>null,
         'temps_lecture'=>'','date_publication'=>date('Y-m-d'),'statut'=>'brouillon'];
if ($id) {
    $st = $pdo->prepare("SELECT * FROM actualites WHERE id=?");
    $st->execute([$id]);
    $actu = $st->fetch() ?: $actu;
}

$erreur = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Session expirée.';
    } else {
        $titre_a = propre($_POST['titre'] ?? '');
        $categorie = array_key_exists($_POST['categorie'] ?? '', $cats) ? $_POST['categorie'] : 'campagne';
        $chapeau = propre($_POST['chapeau'] ?? '');
        $corps = trim((string)($_POST['corps'] ?? '')); // HTML riche conservé
        $tl = ($_POST['temps_lecture'] ?? '') !== '' ? (int)$_POST['temps_lecture'] : null;
        $datePub = propre($_POST['date_publication'] ?? date('Y-m-d'));
        $statut = ($_POST['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon';

        if ($titre_a === '') { $erreur = 'Le titre est obligatoire.'; }

        if (!$erreur) {
            try {
                $image = $actu['image'];
                $nouvelle = televerser_image('image', 'actualites');
                if ($nouvelle) { supprimer_image($image, 'actualites'); $image = $nouvelle; }

                if ($id) {
                    $slug = slug_unique($pdo, 'actualites', $actu['slug'] ?? $titre_a, $id);
                    $st = $pdo->prepare(
                        "UPDATE actualites SET titre=?, slug=?, categorie=?, chapeau=?, corps=?,
                            image=?, temps_lecture=?, date_publication=?, statut=? WHERE id=?");
                    $st->execute([$titre_a,$slug,$categorie,$chapeau,$corps,$image,$tl,$datePub,$statut,$id]);
                    flash('Actualité mise à jour.');
                } else {
                    $slug = slug_unique($pdo, 'actualites', $titre_a);
                    $st = $pdo->prepare(
                        "INSERT INTO actualites (titre,slug,categorie,chapeau,corps,image,temps_lecture,
                            date_publication,statut,auteur_id)
                         VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $st->execute([$titre_a,$slug,$categorie,$chapeau,$corps,$image,$tl,$datePub,$statut,$admin['id']]);
                    flash('Actualité créée.');
                }
                header('Location: actualites.php'); exit;
            } catch (RuntimeException $ex) {
                $erreur = $ex->getMessage();
            }
        }
        // réinjecte les valeurs saisies en cas d'erreur
        $actu = array_merge($actu, ['titre'=>$titre_a,'categorie'=>$categorie,'chapeau'=>$chapeau,
            'corps'=>$corps,'temps_lecture'=>$tl,'date_publication'=>$datePub,'statut'=>$statut]);
    }
}

$titre = $id ? 'Modifier une actualité' : 'Nouvelle actualité';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<?php if ($erreur): ?><div class="flash err"><?= e($erreur) ?></div><?php endif; ?>
<form class="bloc" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
  <label>Titre *</label>
  <input type="text" name="titre" required value="<?= e($actu['titre']) ?>">

  <label>Catégorie</label>
  <select name="categorie">
    <?php foreach ($cats as $k => $lib): ?>
      <option value="<?= $k ?>" <?= $actu['categorie']===$k?'selected':'' ?>><?= e($lib) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Chapeau (résumé affiché sur la carte et lors du partage)</label>
  <textarea name="chapeau" style="min-height:70px"><?= e($actu['chapeau']) ?></textarea>

  <label>Corps de l'article (HTML autorisé : &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;…)</label>
  <textarea name="corps" style="min-height:220px"><?= e($actu['corps']) ?></textarea>

  <label>Image mise en avant</label>
  <?php if (!empty($actu['image'])): ?>
    <p class="aide">Image actuelle : <?= e($actu['image']) ?> — téléversez-en une nouvelle pour la remplacer.</p>
  <?php endif; ?>
  <input type="file" name="image" accept="image/*">

  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <div style="flex:1;min-width:180px">
      <label>Date de publication</label>
      <input type="date" name="date_publication" value="<?= e(substr($actu['date_publication'],0,10)) ?>">
    </div>
    <div style="flex:1;min-width:140px">
      <label>Temps de lecture (min)</label>
      <input type="number" name="temps_lecture" min="1" value="<?= e($actu['temps_lecture']) ?>">
    </div>
    <div style="flex:1;min-width:160px">
      <label>Statut</label>
      <select name="statut">
        <option value="brouillon" <?= $actu['statut']==='brouillon'?'selected':'' ?>>Brouillon</option>
        <option value="publie" <?= $actu['statut']==='publie'?'selected':'' ?>>Publié</option>
      </select>
    </div>
  </div>

  <div class="ligne-actions" style="margin-top:22px">
    <button class="btn" type="submit">Enregistrer</button>
    <a class="btn sec" href="actualites.php">Annuler</a>
  </div>
</form>
<?php require __DIR__ . '/inc/footer.php'; ?>
