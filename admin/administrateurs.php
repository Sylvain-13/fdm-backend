<?php
require_once __DIR__ . '/inc/auth.php';
exiger_connexion();
$pdo = db();
$moi = admin_courant();

$nbActifs = function() use($pdo){ return (int)$pdo->query("SELECT COUNT(*) FROM administrateurs WHERE actif=1")->fetchColumn(); };

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valide($_POST['csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';

    if ($action === 'creer') {
        $nom = propre($_POST['nom'] ?? ''); $email = propre($_POST['email'] ?? '');
        $mdp = (string)($_POST['mot_de_passe'] ?? ''); $role = ($_POST['role'] ?? 'admin')==='editeur'?'editeur':'admin';
        if ($nom && est_email($email) && strlen($mdp) >= 8) {
            try {
                $pdo->prepare("INSERT INTO administrateurs (nom,email,mot_de_passe,role,actif) VALUES (?,?,?,?,1)")
                    ->execute([$nom,$email,password_hash($mdp, PASSWORD_BCRYPT),$role]);
                flash('Administrateur créé.');
            } catch (PDOException $ex) { flash($ex->getCode()==='23000'?'Cet e-mail existe déjà.':'Erreur.', 'err'); }
        } else { flash('Nom, e-mail valide et mot de passe (8 caractères min.) requis.', 'err'); }
    }
    elseif ($action === 'mot_de_passe') {
        // Chaque admin ne change que SON mot de passe
        $actuel = (string)($_POST['actuel'] ?? ''); $nouveau = (string)($_POST['nouveau'] ?? '');
        $st=$pdo->prepare("SELECT mot_de_passe FROM administrateurs WHERE id=?"); $st->execute([$moi['id']]);
        if (password_verify($actuel, (string)$st->fetchColumn()) && strlen($nouveau) >= 8) {
            $pdo->prepare("UPDATE administrateurs SET mot_de_passe=? WHERE id=?")
                ->execute([password_hash($nouveau, PASSWORD_BCRYPT), $moi['id']]);
            flash('Votre mot de passe a été changé.');
        } else { flash('Mot de passe actuel incorrect ou nouveau trop court (8 min.).', 'err'); }
    }
    elseif ($action === 'activer') {
        $pdo->prepare("UPDATE administrateurs SET actif=1 WHERE id=?")->execute([(int)$_POST['id']]); flash('Compte activé.');
    }
    elseif ($action === 'desactiver') {
        $id=(int)$_POST['id'];
        if ($id === (int)$moi['id']) { flash('Vous ne pouvez pas désactiver votre propre compte.', 'err'); }
        elseif ($nbActifs() <= 2) { flash('Impossible : il faut conserver au moins 2 administrateurs actifs (gouvernance).', 'err'); }
        else { $pdo->prepare("UPDATE administrateurs SET actif=0 WHERE id=?")->execute([$id]); flash('Compte désactivé.'); }
    }
    header('Location: administrateurs.php'); exit;
}

$lignes = $pdo->query("SELECT id,nom,email,role,actif,derniere_connexion,derniere_verif_2fa FROM administrateurs ORDER BY actif DESC, nom")->fetchAll();
$titre = 'Administrateurs';
require __DIR__ . '/inc/header.php';
$jeton = csrf_jeton();
?>
<?php if ($nbActifs() < 2): ?>
  <div class="flash err">Gouvernance : prévoyez <b>au moins deux</b> administrateurs actifs pour éviter tout point unique de défaillance.</div>
<?php endif; ?>

<div style="display:flex;gap:20px;flex-wrap:wrap">
  <form class="bloc" method="post" style="flex:1;min-width:300px">
    <h3 style="margin:0 0 6px">Créer un administrateur</h3>
    <input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="action" value="creer">
    <label>Nom</label><input type="text" name="nom" required>
    <label>E-mail</label><input type="email" name="email" required>
    <label>Mot de passe (8 caractères min.)</label><input type="password" name="mot_de_passe" required>
    <label>Rôle</label><select name="role"><option value="admin">Administrateur</option><option value="editeur">Éditeur</option></select>
    <div class="ligne-actions" style="margin-top:16px"><button class="btn" type="submit">Créer</button></div>
  </form>

  <form class="bloc" method="post" style="flex:1;min-width:300px;align-self:flex-start">
    <h3 style="margin:0 0 6px">Changer mon mot de passe</h3>
    <input type="hidden" name="csrf" value="<?= e($jeton) ?>"><input type="hidden" name="action" value="mot_de_passe">
    <label>Mot de passe actuel</label><input type="password" name="actuel" required>
    <label>Nouveau mot de passe</label><input type="password" name="nouveau" required>
    <div class="ligne-actions" style="margin-top:16px"><button class="btn" type="submit">Mettre à jour</button></div>
  </form>
</div>

<table style="margin-top:24px">
  <tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr>
  <?php foreach ($lignes as $a): ?>
  <tr>
    <td><b><?= e($a['nom']) ?></b><?= $a['id']==$moi['id']?' <span class="aide">(vous)</span>':'' ?></td>
    <td><?= e($a['email']) ?></td>
    <td><?= e($a['role']) ?></td>
    <td><?= e($a['derniere_connexion'] ? substr($a['derniere_connexion'],0,16) : '—') ?></td>
    <td><span class="pastille <?= $a['actif']?'p-publie':'p-rejete' ?>"><?= $a['actif']?'Actif':'Inactif' ?></span></td>
    <td><form method="post" class="ligne-actions"><input type="hidden" name="csrf" value="<?= e($jeton) ?>">
      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
      <?php if ($a['actif'] && $a['id']!=$moi['id']): ?><button class="btn gris sm" name="action" value="desactiver">Désactiver</button>
      <?php elseif (!$a['actif']): ?><button class="btn sm" name="action" value="activer">Activer</button><?php endif; ?>
    </form></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php require __DIR__ . '/inc/footer.php'; ?>
