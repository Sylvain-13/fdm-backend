<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();

/* ---------- EXPORT CSV (pour Brevo / envois groupés) ---------- */
if (($_GET['export'] ?? '') === 'brevo') {
    // Seuls les alumnis ayant consenti à recevoir des e-mails.
    $rows = $pdo->query("SELECT email,prenom,nom,telephone,promotion,filiere,ville,pays
                         FROM alumnis WHERE consentement_email=1 ORDER BY nom,prenom")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alumnis_brevo_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    // En-têtes compatibles Brevo (EMAIL obligatoire, puis attributs)
    fputcsv($out, ['EMAIL','PRENOM','NOM','SMS','PROMOTION','FILIERE','VILLE','PAYS'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'],$r['prenom'],$r['nom'],$r['telephone'],
                       $r['promotion'],$r['filiere'],$r['ville'],$r['pays']], ';');
    }
    fclose($out); exit;
}

/* ---------- ACTIONS ---------- */
$rapport = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'importer' && !empty($_FILES['csv']['tmp_name'])) {
        $rapport = importer_csv_alumnis($pdo, $_FILES['csv']['tmp_name']);
        flash("Import terminé : {$rapport['ajouts']} ajout(s), {$rapport['maj']} mise(s) à jour, {$rapport['ignores']} ligne(s) ignorée(s).");
        header('Location: alumnis.php'); exit;
    }
    if ($action === 'enregistrer') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = propre($_POST['nom'] ?? ''); $prenom = propre($_POST['prenom'] ?? '');
        $email = propre($_POST['email'] ?? '');
        if ($nom && $prenom && est_email($email)) {
            $champs = [$nom,$prenom,$email,propre($_POST['telephone']??'')?:null,propre($_POST['promotion']??'')?:null,
                       propre($_POST['filiere']??'')?:null,propre($_POST['ville']??'')?:null,propre($_POST['pays']??'')?:null,
                       !empty($_POST['consentement'])?1:0];
            try {
                if ($id) {
                    $champs[] = $id;
                    $pdo->prepare("UPDATE alumnis SET nom=?,prenom=?,email=?,telephone=?,promotion=?,filiere=?,ville=?,pays=?,consentement_email=? WHERE id=?")->execute($champs);
                    flash('Alumni mis à jour.');
                } else {
                    $pdo->prepare("INSERT INTO alumnis (nom,prenom,email,telephone,promotion,filiere,ville,pays,consentement_email,source) VALUES (?,?,?,?,?,?,?,?,?, 'saisie')")->execute($champs);
                    flash('Alumni ajouté.');
                }
            } catch (PDOException $ex) {
                flash(($ex->getCode()==='23000') ? 'Cet e-mail existe déjà dans la base.' : 'Erreur d\'enregistrement.', 'err');
            }
        } else { flash('Nom, prénom et e-mail valide sont obligatoires.', 'err'); }
        header('Location: alumnis.php'); exit;
    }
    if ($action === 'supprimer' && !empty($_POST['id'])) {
        $pdo->prepare("DELETE FROM alumnis WHERE id=?")->execute([(int)$_POST['id']]);
        flash('Alumni supprimé.'); header('Location: alumnis.php'); exit;
    }
}

/** Importe/actualise des alumnis depuis un fichier CSV (séparateur ; ou ,). */
function importer_csv_alumnis(PDO $pdo, string $chemin): array
{
    $ajouts=0;$maj=0;$ignores=0;
    if (($fp = fopen($chemin, 'r')) === false) { return compact('ajouts','maj','ignores'); }
    $sep = ';';
    $premiere = fgets($fp);
    if ($premiere !== false && substr_count($premiere, ',') > substr_count($premiere, ';')) { $sep = ','; }
    rewind($fp);
    $entetes = fgetcsv($fp, 0, $sep);
    if (!$entetes) { fclose($fp); return compact('ajouts','maj','ignores'); }
    // Normalise les noms de colonnes -> indices
    $map = [];
    foreach ($entetes as $i => $h) {
        $h = strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string)$h)));
        $map[$h] = $i;
    }
    $col = function(array $l, array $noms) use ($map) {
        foreach ($noms as $n) { if (isset($map[$n]) && isset($l[$map[$n]])) { return trim((string)$l[$map[$n]]); } }
        return '';
    };
    $stSel = $pdo->prepare("SELECT id FROM alumnis WHERE email=?");
    $stIns = $pdo->prepare("INSERT INTO alumnis (nom,prenom,email,telephone,promotion,filiere,ville,pays,consentement_email,source) VALUES (?,?,?,?,?,?,?,?,?, 'import_csv')");
    $stUpd = $pdo->prepare("UPDATE alumnis SET nom=?,prenom=?,telephone=?,promotion=?,filiere=?,ville=?,pays=? WHERE id=?");
    while (($l = fgetcsv($fp, 0, $sep)) !== false) {
        $email = strtolower($col($l, ['email','e-mail','mail']));
        $nom = $col($l, ['nom','name']); $prenom = $col($l, ['prenom','prénom','firstname']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $nom==='' || $prenom==='') { $ignores++; continue; }
        $tel=$col($l,['telephone','téléphone','tel','sms','phone'])?:null;
        $promo=$col($l,['promotion','promo','annee','année'])?:null;
        $fil=$col($l,['filiere','filière','formation'])?:null;
        $ville=$col($l,['ville','city'])?:null; $pays=$col($l,['pays','country'])?:null;
        $stSel->execute([$email]); $id = $stSel->fetchColumn();
        if ($id) { $stUpd->execute([$nom,$prenom,$tel,$promo,$fil,$ville,$pays,$id]); $maj++; }
        else { $stIns->execute([$nom,$prenom,$email,$tel,$promo,$fil,$ville,$pays,1]); $ajouts++; }
    }
    fclose($fp);
    return compact('ajouts','maj','ignores');
}

/* ---------- AFFICHAGE ---------- */
$q = propre($_GET['q'] ?? '');
[$page,$parPage,$offset] = pagination(25, 100);
$where=''; $args=[];
if ($q!=='') { $where="WHERE nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR promotion LIKE ?"; $args=array_fill(0,4,"%$q%"); }
$total = (int)(function() use($pdo,$where,$args){ $s=$pdo->prepare("SELECT COUNT(*) FROM alumnis $where"); $s->execute($args); return $s->fetchColumn(); })();
$st=$pdo->prepare("SELECT * FROM alumnis $where ORDER BY nom,prenom LIMIT $parPage OFFSET $offset"); $st->execute($args);
$lignes=$st->fetchAll();
$edit=null; if(!empty($_GET['id'])){ $s=$pdo->prepare("SELECT * FROM alumnis WHERE id=?"); $s->execute([(int)$_GET['id']]); $edit=$s->fetch()?:null; }

$titre = 'Alumnis';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<div class="cartes" style="margin-bottom:20px">
  <div class="carte"><div class="n"><?= $total ?></div><div class="l">Alumnis enregistrés</div></div>
  <div class="carte" style="display:flex;flex-direction:column;justify-content:center;gap:10px">
    <a class="btn" href="?export=brevo">Exporter pour Brevo (CSV)</a>
    <span class="aide">Export des alumnis ayant accepté de recevoir des e-mails.</span>
  </div>
</div>

<div style="display:flex;gap:20px;flex-wrap:wrap">
  <form class="bloc" method="post" style="flex:1;min-width:300px">
    <h3 style="margin:0 0 6px"><?= $edit ? 'Modifier un alumni' : 'Ajouter un alumni' ?></h3>
    <input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="action" value="enregistrer">
    <?php if($edit):?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif;?>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:120px"><label>Nom *</label><input type="text" name="nom" required value="<?= e($edit['nom']??'') ?>"></div>
      <div style="flex:1;min-width:120px"><label>Prénom *</label><input type="text" name="prenom" required value="<?= e($edit['prenom']??'') ?>"></div>
    </div>
    <label>E-mail *</label><input type="email" name="email" required value="<?= e($edit['email']??'') ?>">
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:120px"><label>Téléphone</label><input type="text" name="telephone" value="<?= e($edit['telephone']??'') ?>"></div>
      <div style="flex:1;min-width:120px"><label>Promotion</label><input type="text" name="promotion" value="<?= e($edit['promotion']??'') ?>"></div>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:120px"><label>Filière</label><input type="text" name="filiere" value="<?= e($edit['filiere']??'') ?>"></div>
      <div style="flex:1;min-width:110px"><label>Ville</label><input type="text" name="ville" value="<?= e($edit['ville']??'') ?>"></div>
      <div style="flex:1;min-width:110px"><label>Pays</label><input type="text" name="pays" value="<?= e($edit['pays']??'') ?>"></div>
    </div>
    <label style="display:flex;align-items:center;gap:8px;margin-top:12px">
      <input type="checkbox" name="consentement" value="1" style="width:auto" <?= (!$edit||$edit['consentement_email'])?'checked':'' ?>> Accepte de recevoir des e-mails
    </label>
    <div class="ligne-actions" style="margin-top:16px"><button class="btn" type="submit">Enregistrer</button>
      <?php if($edit):?><a class="btn sec" href="alumnis.php">Annuler</a><?php endif;?></div>
  </form>

  <form class="bloc" method="post" enctype="multipart/form-data" style="flex:1;min-width:300px;align-self:flex-start">
    <h3 style="margin:0 0 6px">Importer un fichier CSV</h3>
    <input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="action" value="importer">
    <p class="aide">Colonnes reconnues (1re ligne) : <b>email, nom, prenom</b> (obligatoires), puis
       telephone, promotion, filiere, ville, pays. Séparateur « ; » ou « , ».
       Les e-mails déjà présents sont mis à jour (pas de doublon).</p>
    <label>Fichier CSV</label><input type="file" name="csv" accept=".csv,text/csv" required>
    <div class="ligne-actions" style="margin-top:16px"><button class="btn" type="submit">Importer</button></div>
  </form>
</div>

<form method="get" class="ligne-actions" style="margin:24px 0 14px">
  <input type="text" name="q" placeholder="Rechercher (nom, e-mail, promotion)…" value="<?= e($q) ?>" style="max-width:320px">
  <button class="btn sec sm" type="submit">Rechercher</button>
  <?php if($q):?><a class="btn gris sm" href="alumnis.php">Réinitialiser</a><?php endif;?>
</form>
<table>
  <tr><th>Nom</th><th>E-mail</th><th>Promotion</th><th>Ville / Pays</th><th>Mailing</th><th>Actions</th></tr>
  <?php foreach ($lignes as $a): ?>
  <tr>
    <td><b><?= e($a['prenom'].' '.$a['nom']) ?></b></td>
    <td><?= e($a['email']) ?></td>
    <td><?= e($a['promotion']) ?><?php if($a['filiere']):?><br><span class="aide"><?= e($a['filiere']) ?></span><?php endif;?></td>
    <td><?= e(trim($a['ville'].' '.$a['pays'])) ?></td>
    <td><span class="pastille <?= $a['consentement_email']?'p-publie':'p-rejete' ?>"><?= $a['consentement_email']?'Oui':'Non' ?></span></td>
    <td><div class="ligne-actions">
      <a class="btn sec sm" href="?id=<?= (int)$a['id'] ?>">Modifier</a>
      <form method="post" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
        <input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
        <button class="btn danger sm">×</button></form>
    </div></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$lignes): ?><tr><td colspan="6" style="color:#A99490">Aucun alumni.</td></tr><?php endif; ?>
</table>
<?php if ($total > $parPage): $pages=(int)ceil($total/$parPage); ?>
  <div class="ligne-actions" style="margin-top:16px">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a class="btn <?= $i===$page?'':'sec' ?> sm" href="?<?= http_build_query(array_filter(['q'=>$q,'page'=>$i])) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/inc/footer.php'; ?>
