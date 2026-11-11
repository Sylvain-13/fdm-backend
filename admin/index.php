<?php
/**
 * Connexion à l'administration (étape 1 : e-mail + mot de passe).
 * Si la double authentification est requise, on redirige vers 2fa.php.
 */
require_once __DIR__ . '/inc/auth.php';

// Déjà connecté ? -> tableau de bord
if (admin_courant()) { header('Location: dashboard.php'); exit; }

$erreur = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_valide($_POST['csrf'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $email = propre($_POST['email'] ?? '');
        $motdepasse = (string)($_POST['mot_de_passe'] ?? '');

        $st = db()->prepare("SELECT * FROM administrateurs WHERE email=? AND actif=1 LIMIT 1");
        $st->execute([$email]);
        $admin = $st->fetch();

        if (!$admin || !password_verify($motdepasse, $admin['mot_de_passe'])) {
            $erreur = 'Identifiants incorrects.';
            usleep(400000); // léger ralentissement anti force brute
        } else {
            if (exige_2fa($admin)) {
                $_SESSION['pre_auth'] = (int)$admin['id'];
                if (envoyer_code_2fa($admin)) {
                    header('Location: 2fa.php'); exit;
                }
                $erreur = 'Impossible d\'envoyer le code de vérification. Contactez l\'administrateur technique.';
            } else {
                ouvrir_session_admin($admin, false);
                header('Location: dashboard.php'); exit;
            }
        }
    }
}
$jeton = csrf_jeton();
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion Administration FDM</title>
<style>
  body{margin:0;font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#FCFAF7;color:#2B1212;
       display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}
  .box{width:100%;max-width:400px;background:#fff;border:1px solid #EDE4DE;border-radius:22px;
       padding:38px;box-shadow:0 20px 50px rgba(43,18,18,.08)}
  h1{font-size:21px;color:#990103;margin:0 0 4px}
  p.s{font-size:13px;color:#7A6462;margin:0 0 22px}
  label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px;color:#5C4442}
  input{width:100%;padding:12px 14px;border:1px solid #EDE4DE;border-radius:10px;font-size:14px;background:#FCFAF7;box-sizing:border-box}
  button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:999px;cursor:pointer;
         background:linear-gradient(180deg,#A80406,#8A0103);color:#fff;font-size:15px;font-weight:700}
  .err{background:#FBECEC;color:#A4161A;padding:11px 14px;border-radius:10px;font-size:13.5px;margin-bottom:8px}
</style></head>
<body>
  <form class="box" method="post" autocomplete="off">
    <h1>Administration</h1>
    <p class="s">Fondation Denis Maugenest</p>
    <?php if ($erreur): ?><div class="err"><?= e($erreur) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e($jeton) ?>">
    <label>Adresse e-mail</label>
    <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
    <label>Mot de passe</label>
    <input type="password" name="mot_de_passe" required>
    <button type="submit">Se connecter</button>
  </form>
</body></html>
