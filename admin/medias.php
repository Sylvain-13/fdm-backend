<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

/* Emplacements d'images gérables sur le site (clé => libellé lisible). */
$SLOTS = [
    'accueil_hero'         => 'Accueil : grande image d\'en-tête',
    'banniere_fondation'   => 'Bannière : La Fondation',
    'banniere_biographie'  => 'Bannière : Biographie',
    'biographie_portrait'  => 'Biographie : portrait du Président',
    'banniere_missions'    => 'Bannière : Nos missions',
    'banniere_programmes'  => 'Bannière : Programmes',
    'banniere_actualites'  => 'Bannière : Actualités',
    'banniere_evenements'  => 'Bannière : Événements',
    'banniere_partenaires' => 'Bannière : Partenaires',
    'banniere_don'         => 'Bannière : Faire un don',
    'banniere_contact'     => 'Bannière : Contact',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $cle = $_POST['cle'] ?? '';
    if (!array_key_exists($cle, $SLOTS)) {
        flash('Emplacement inconnu.', 'err'); header('Location: medias.php'); exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'supprimer') {
        $f = $pdo->prepare("SELECT fichier FROM medias WHERE cle=?"); $f->execute([$cle]);
        supprimer_image($f->fetchColumn() ?: null, 'site');
        $pdo->prepare("DELETE FROM medias WHERE cle=?")->execute([$cle]);
        flash('Image retirée.');
    } else {
        try {
            $nom = televerser_image('image', 'site');
            if (!$nom) { flash('Veuillez choisir une image.', 'err'); }
            else {
                $anc = $pdo->prepare("SELECT fichier FROM medias WHERE cle=?"); $anc->execute([$cle]);
                $ancien = $anc->fetchColumn();
                $pdo->prepare("INSERT INTO medias (cle, fichier) VALUES (?, ?)
                               ON DUPLICATE KEY UPDATE fichier=VALUES(fichier)")->execute([$cle, $nom]);
                if ($ancien) { supprimer_image($ancien, 'site'); }
                flash('Image mise à jour.');
            }
        } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); }
    }
    header('Location: medias.php'); exit;
}

$actuelles = [];
foreach ($pdo->query("SELECT cle, fichier FROM medias")->fetchAll() as $r) { $actuelles[$r['cle']] = $r['fichier']; }

$titre = 'Images du site';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
$base = rtrim(fdm_config()['uploads']['url'], '/') . '/site/';
?>
<p class="aide" style="margin-bottom:18px">Téléversez ici les visuels des grandes zones du site (en-tête d'accueil,
  bannières de chaque page, portrait). Ils remplacent automatiquement les emplacements en pointillés de la maquette.
  Formats : JPEG, PNG, WebP. Privilégiez des images larges (paysage) et nettes.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:18px">
  <?php foreach ($SLOTS as $cle => $lib): $f = $actuelles[$cle] ?? null; ?>
    <div class="carte" style="padding:16px">
      <div style="font-weight:700;font-size:14px;margin-bottom:10px"><?= e($lib) ?></div>
      <div style="height:150px;border-radius:12px;border:1px solid var(--bord);background:#FCFAF7 center/cover no-repeat<?= $f ? ';background-image:url('.e($base.rawurlencode($f)).')' : '' ?>;display:flex;align-items:center;justify-content:center;color:#A99490;font-size:12.5px;margin-bottom:12px">
        <?= $f ? '' : 'Aucune image' ?>
      </div>
      <form method="post" enctype="multipart/form-data" class="ligne-actions" style="align-items:center">
        <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="cle" value="<?= e($cle) ?>">
        <input type="file" name="image" accept="image/*" required style="font-size:12.5px;max-width:170px">
        <button class="btn sm" type="submit"><?= $f ? 'Remplacer' : 'Téléverser' ?></button>
        <?php if ($f): ?>
          <button class="btn danger sm" name="action" value="supprimer"
            onclick="return confirm('Retirer cette image ?')">Retirer</button>
        <?php endif; ?>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/inc/footer.php'; ?>
