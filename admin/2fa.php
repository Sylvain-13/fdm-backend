<?php
/**
 * Connexion à l'administration (étape 2 : double authentification).
 * Accessible uniquement après l'étape mot de passe ($_SESSION['pre_auth']).
 */
require_once __DIR__ . '/inc/auth.php';

if (admin_courant()) { header('Location: dashboard.php'); exit; }
$preAuth = $_SESSION['pre_auth'] ?? null;
if (!$preAuth) { header('Location: index.php'); exit; }

$erreur = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Session expirée, veuillez recommencer la connexion.';
    } elseif (!empty($_POST['renvoyer'])) {
        $st = db()->prepare("SELECT * FROM administrateurs WHERE id=? AND actif=1");
        $st->execute([$preAuth]);
        if ($a = $st->fetch()) { envoyer_code_2fa($a); $erreur = 'Un nouveau code vient de vous être envoyé.'; }
    } else {
        $code = propre($_POST['code'] ?? '');
        if (verifier_code_2fa((int)$preAuth, $code)) {
            $st = db()->prepare("SELECT * FROM administrateurs WHERE id=? AND actif=1");
            $st->execute([$preAuth]);
            $admin = $st->fetch();
            if ($admin) { ouvrir_session_admin($admin, true); header('Location: dashboard.php'); exit; }
            $erreur = 'Compte introuvable.';
        } else {
            $erreur = 'Code incorrect ou expiré.';
        }
    }
}
$jeton = csrf_jeton();
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vérification — Administration FDM</title>
<style>
  body{margin:0;font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#FCFAF7;color:#2B1212;
       display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}
  .box{width:100%;max-width:400px;background:#fff;border:1px solid #EDE4DE;border-radius:22px;
       padding:38px;box-shadow:0 20px 50px rgba(43,18,18,.08)}
  h1{font-size:21px;color:#990103;margin:0 0 4px}
  p.s{font-size:13px;color:#7A6462;margin:0 0 22px;line-height:1.5}
  input[type=text]{width:100%;padding:14px;border:1px solid #EDE4DE;border-radius:10px;font-size:22px;
       letter-spacing:8px;text-align:center;background:#FCFAF7;box-sizing:border-box}
  button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:999px;cursor:pointer;
         background:linear-gradient(180deg,#A80406,#8A0103);color:#fff;font-size:15px;font-weight:700}
  .lien{margin-top:16px;text-align:center}
  .lien button{background:none;color:#990103;font-weight:700;width:auto;margin:0;padding:0;font-size:13px;text-decoration:underline}
  .err{background:#FBECEC;color:#A4161A;padding:11px 14px;border-radius:10px;font-size:13.5px;margin-bottom:10px}
</style></head>
<body>
  <form class="box" method="post" autocomplete="off">
    <h1>Vérification</h1>
    <p class="s">Pour votre sécurité, un code à usage unique vous a été envoyé par e-mail
       (demandé car votre dernière connexion vérifiée remonte à plus de 30 jours).</p>
    <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
           maxlength="8" required autofocus placeholder="______">
    <button type="submit">Valider</button>
    <div class="lien"><button type="submit" name="renvoyer" value="1">Renvoyer le code</button></div>
  </form>
</body></html>
