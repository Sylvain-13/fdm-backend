<?php
/**
 * En-tête commun des pages d'administration (après connexion).
 * À inclure APRÈS avoir appelé exiger_connexion().
 * Variable attendue : $titre (titre de la page).
 */
require_once __DIR__ . '/auth.php';
exiger_connexion();
$admin = admin_courant();
$titre = $titre ?? 'Administration';
$page  = basename($_SERVER['SCRIPT_NAME']);

// flash() est défini dans inc/auth.php (chargé avant les traitements POST).
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$menu = [
    'dashboard.php'      => ['Tableau de bord', ''],
    'actualites.php'     => ['Actualités', ''],
    'evenements.php'     => ['Événements', ''],
    'commentaires.php'   => ['Commentaires', ''],
    'partenaires.php'    => ['Partenaires', ''],
    'medias.php'         => ['Images du site', ''],
    'benevoles.php'      => ['Bénévoles', ''],
    'contacts.php'       => ['Messages de contact', ''],
    'dons.php'           => ['Dons', ''],
    'alumnis.php'        => ['Alumnis', ''],
    'newsletter.php'     => ['Newsletter', ''],
    'administrateurs.php'=> ['Administrateurs', ''],
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> Administration FDM</title>
<style>
  :root{--vin:#990103;--vin2:#8A0103;--creme:#FCFAF7;--bord:#EDE4DE;--texte:#2B1212;--doux:#7A6462}
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--creme);color:var(--texte)}
  a{color:inherit;text-decoration:none}
  .layout{display:flex;min-height:100vh}
  aside{width:250px;background:#fff;border-right:1px solid var(--bord);padding:22px 0;position:sticky;top:0;height:100vh;overflow:auto}
  aside .marque{padding:0 22px 18px;font-weight:800;font-size:17px;color:var(--vin);border-bottom:1px solid var(--bord);margin-bottom:12px}
  aside .marque small{display:block;font-weight:500;color:var(--doux);font-size:11.5px;margin-top:2px}
  nav a{display:block;padding:11px 22px;font-size:14px;color:var(--doux);font-weight:600;border-left:3px solid transparent}
  nav a:hover{background:#FBF2F2;color:var(--vin)}
  nav a.on{background:#FBF2F2;color:var(--vin);border-left-color:var(--vin)}
  main{flex:1;padding:28px 34px;max-width:1120px}
  .tete{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap}
  .tete h1{font-size:26px;margin:0}
  .qui{font-size:13px;color:var(--doux)}
  .qui a{color:var(--vin);font-weight:700}
  .flash{padding:13px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;font-weight:600}
  .flash.ok{background:#EFF6F0;color:#2F6B37}
  .flash.err{background:#FBECEC;color:#A4161A}
  table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--bord);border-radius:14px;overflow:hidden}
  th,td{padding:12px 14px;text-align:left;font-size:13.5px;border-bottom:1px solid var(--bord);vertical-align:top}
  th{background:#FBF7F4;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--doux)}
  tr:last-child td{border-bottom:0}
  .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:999px;border:0;cursor:pointer;font-size:13.5px;font-weight:700;background:linear-gradient(180deg,#A80406,#8A0103);color:#fff}
  .btn.sec{background:#fff;border:1px solid var(--bord);color:var(--vin)}
  .btn.gris{background:#F1EBE7;color:var(--doux)}
  .btn.sm{padding:6px 12px;font-size:12.5px}
  .btn.danger{background:#fff;border:1px solid #E7B8B8;color:#A4161A}
  .cartes{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}
  .carte{background:#fff;border:1px solid var(--bord);border-radius:16px;padding:20px}
  .carte .n{font-size:30px;font-weight:800;color:var(--vin)}
  .carte .l{font-size:13px;color:var(--doux);margin-top:4px}
  form.bloc{background:#fff;border:1px solid var(--bord);border-radius:16px;padding:24px;max-width:760px}
  label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px;color:#5C4442}
  input[type=text],input[type=email],input[type=password],input[type=date],input[type=number],input[type=url],select,textarea{
    width:100%;padding:11px 13px;border:1px solid var(--bord);border-radius:10px;font-family:inherit;font-size:14px;background:#FCFAF7}
  textarea{min-height:120px;resize:vertical}
  .pastille{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11.5px;font-weight:700}
  .p-attente{background:#FDF4E7;color:#9A6700}
  .p-publie{background:#EFF6F0;color:#2F6B37}
  .p-rejete{background:#FBECEC;color:#A4161A}
  .p-nouveau{background:#EAF1FB;color:#1F5FB0}
  .ligne-actions{display:flex;gap:8px;flex-wrap:wrap}
  .aide{font-size:12.5px;color:var(--doux);margin-top:4px}
  @media(max-width:820px){.layout{flex-direction:column}aside{width:100%;height:auto;position:static}}
</style>
</head>
<body>
<div class="layout">
  <aside>
    <div class="marque">Fondation Denis Maugenest<small>Administration du site</small></div>
    <nav>
      <?php foreach ($menu as $f => $m): ?>
        <a href="<?= $f ?>" class="<?= $page === $f ? 'on' : '' ?>"><?= e($m[0]) ?></a>
      <?php endforeach; ?>
      <a href="logout.php" style="margin-top:12px;color:#A4161A">Se déconnecter</a>
    </nav>
  </aside>
  <main>
    <div class="tete">
      <h1><?= e($titre) ?></h1>
      <div class="qui">Connecté : <a href="administrateurs.php"><?= e($admin['nom']) ?></a></div>
    </div>
    <?php if ($flash): ?>
      <div class="flash <?= $flash['t'] === 'err' ? 'err' : 'ok' ?>"><?= e($flash['m']) ?></div>
    <?php endif; ?>
