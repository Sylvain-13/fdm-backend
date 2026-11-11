# Site de la Fondation Denis Maugenest — Backend PHP / MySQL

Backend complet du site de la Fondation : **interface publique** alimentée par une
**API REST JSON**, **interface d'administration** sécurisée (session + mot de passe
haché + double authentification par e-mail), base **MySQL**, le tout conçu pour
**WampServer** (Apache + PHP 8 + MySQL/MariaDB).

Stack : **HTML / CSS / JS** (front) · **PHP** (API + admin) · **MySQL** (données) · **WampServer** (serveur local).

---

## 1. Ce que fait le backend

- **Actualités** : publication, catégories, image à la une, recherche, pagination, compteur de vues.
- **Événements** : agenda avec affiche, filtrage par mois.
- **Commentaires** : soumis par le public (nom + prénom + e-mail obligatoire mais jamais publié), **modération obligatoire** avant publication.
- **Partenaires** : logos et liens (bas de page + page dédiée).
- **Formulaires** : contact, candidature bénévole, inscription newsletter — avec validation et notification interne par e-mail.
- **Dons** : via **Dohone** (confirmation par **webhook serveur-à-serveur**, jamais sur capture d'écran) et par **virement** (rapprochement manuel depuis le relevé de la Fondation).
- **Alumnis** : base gérée côté admin, **saisie unitaire** ou **import CSV**, et **export CSV** prêt pour **Brevo** (envois groupés).
- **Images du site** : module *Images du site* (admin) pour téléverser/remplacer les grands visuels — en-tête d'accueil, bannières de chaque page, portrait — qui remplacent automatiquement les emplacements en pointillés de la maquette.
- **Administrateurs** : plusieurs comptes (gouvernance : au moins 2), mots de passe hachés, 2FA e-mail au-delà de 30 jours.

---

## 2. Arborescence

```
fdm/
├── index.html                ← page d'accueil du site (à déposer : la maquette front)
├── exemple-integration.html  ← démonstration : l'API qui alimente un front réel
├── assets/
│   └── fdm-api.js            ← couche JS reliant le front à l'API
├── api/                      ← API REST publique (JSON)
│   ├── _bootstrap.php
│   ├── actualites.php  evenements.php  partenaires.php
│   ├── commentaires.php  contact.php  benevoles.php  newsletter.php
│   ├── recherche.php
│   └── don_init.php  don_webhook.php  don_retour.php
├── admin/                    ← interface d'administration (accès protégé)
│   ├── index.php (connexion)  2fa.php  logout.php  dashboard.php
│   ├── actualites.php / actualite_edit.php
│   ├── evenements.php / evenement_edit.php
│   ├── commentaires.php  partenaires.php  benevoles.php  contacts.php
│   ├── dons.php  alumnis.php  newsletter.php  medias.php  administrateurs.php
│   └── inc/ (auth.php, header.php, footer.php)
├── lib/                      ← socle (BDD, utilitaires, e-mail, Dohone) — non public
├── config/
│   ├── config.sample.php     ← modèle à copier
│   └── config.php            ← VOTRE configuration (à créer, contient les secrets)
├── sql/
│   └── schema.sql            ← création de la base + compte admin de départ
├── uploads/                  ← images téléversées (actualites / evenements / partenaires)
└── logs/                     ← journaux (e-mails en mode test, notifications Dohone)
```

Les dossiers `config/`, `lib/`, `sql/`, `logs/` sont protégés par un `.htaccess`
(`Require all denied`) et `uploads/` interdit l'exécution de scripts.

---

## 3. Installation sur WampServer (pas à pas)

1. **Installer WampServer** (https://www.wampserver.com) et le démarrer : l'icône doit être **verte**.
2. **Copier le dossier** `fdm/` dans `C:\wamp64\www\`. Le site est alors accessible à `http://localhost/fdm`.
3. **Créer la base de données** :
   - Ouvrir `http://localhost/phpmyadmin` (utilisateur `root`, mot de passe vide par défaut).
   - Onglet **Importer** → choisir `fdm/sql/schema.sql` → **Exécuter**.
   - La base `fdm` et ses tables sont créées, avec un compte administrateur de départ.
4. **Configurer** :
   - Copier `config/config.sample.php` en `config/config.php`.
   - Vérifier la section `db` (sur WampServer, `root` / mot de passe vide fonctionnent).
   - Ajuster `site.base_url` (ex. `http://localhost/fdm`) et `uploads.url` (ex. `/fdm/uploads`).
5. **Tester** : ouvrir `http://localhost/fdm/exemple-integration.html`.
   Les actualités, événements et partenaires doivent s'afficher (vides au début, c'est normal).
6. **Se connecter à l'admin** : `http://localhost/fdm/admin`
   - Identifiant : `admin@fondation-dm.org`
   - Mot de passe : `ChangeMoi!2026`
   - **Changez ce mot de passe immédiatement** (menu *Administrateurs*) et **créez un 2ᵉ compte**.

> En mode local, la 2FA par e-mail n'a pas de serveur SMTP : le code de connexion
> est écrit dans `logs/mail.log`. Ouvrez ce fichier pour y lire le code pendant les tests.

---

## 4. Configurer l'envoi d'e-mails (2FA + notifications)

Dans `config/config.php`, section `mail.transport` :

- `'log'` : aucun envoi réel, les e-mails sont écrits dans `logs/mail.log` (développement).
- `'smtp'` : envoi réel. Renseignez la section `smtp` avec une boîte de la Fondation
  (Zoho Mail ou Hostinger Mail) :

```php
'smtp' => [
    'hote'     => 'smtp.zoho.com',   // ou smtp.hostinger.com
    'port'     => 587,
    'securite' => 'tls',             // 'tls' (587) ou 'ssl' (465)
    'user'     => 'no-reply@fondation-dm.org',
    'pass'     => 'MOT_DE_PASSE_DE_LA_BOITE',
],
```

Le client SMTP est intégré (STARTTLS + AUTH LOGIN) : **aucune bibliothèque à installer**.

---

## 5. Configurer les dons Dohone

Dans `config/config.php`, section `dohone` :

- `marchand` et `secret` : fournis par votre compte marchand Dohone.
- `url_notify` : l'adresse **publique** du webhook, ex. `https://www.fondation-dm.org/api/don_webhook.php`.
  C'est Dohone qui l'appelle, de serveur à serveur, pour **confirmer** le don.
- `url_retour` : page où le navigateur du donateur revient après paiement (informative).
- `signature_modele` : ordre de calcul de la signature des notifications —
  **à adapter à la documentation de votre compte Dohone**. Par défaut : `{rN}{mt}{idPaiement}{secret}`.

Points importants :
- La confirmation d'un don **ne repose jamais** sur une capture d'écran du donateur :
  seule la notification signée de Dohone confirme (montant vérifié, traitement idempotent).
- Dohone impose une **IP fixe** : prévoir un hébergement adapté en production
  (VPS infogéré avec IP dédiée — voir le cahier des charges).
- Les **virements bancaires** se confirment à la main dans *Admin → Dons*, après
  vérification sur le relevé, à l'aide de la **référence unique** communiquée au donateur.

---

## 6. Relier la maquette front à l'API

Le front est une page unique (SPA). Pour l'alimenter :

1. Déposer la maquette sous `fdm/index.html`.
2. Avant `</body>`, ajouter :
   ```html
   <script>window.FDM_BASE = '/fdm/api';</script>
   <script src="assets/fdm-api.js"></script>
   ```
3. **Formulaires** : ajouter sur chaque `<form>` l'attribut `data-fdm` :
   `data-fdm="contact"`, `"benevole"`, `"newsletter"`, `"commentaire"` (avec un champ
   caché `slug` de l'article) ou `"don"`. L'envoi passe alors par l'API, sans rechargement,
   avec message de succès/erreur automatique. Les champs doivent porter les `name`
   attendus (`nom`, `prenom`, `email`, `message`, `objet`, `tel`, `montant`, `moyen`…).
4. **Listes** (actualités, événements, partenaires) : remplir les conteneurs avec
   les fonctions fournies :
   ```js
   FDM.actualites({ par_page: 9, categorie: 'bourses' })
      .then(d => FDM.rendreActualites('#fdm-grille-actus', d.items));
   FDM.evenements({ mois: 10 }).then(d => FDM.rendreEvenements('#fdm-grille-even', d.items));
   FDM.partenaires().then(d => FDM.rendrePartenaires('#mes-partenaires', d.items));
   FDM.article('mon-slug').then(d => { /* page article + d.item.corps */ });
   FDM.commentaires('mon-slug').then(d => { /* liste des commentaires publiés */ });
   ```

**`exemple-integration.html`** montre tout cela en fonctionnement : c'est le patron à reporter dans la maquette.

---

## 7. Points de sécurité intégrés

- Mots de passe administrateurs **hachés** (`password_hash` / bcrypt), jamais en clair.
- **Double authentification** par e-mail au-delà de 30 jours depuis la dernière vérification.
- Toutes les requêtes SQL en **requêtes préparées** (protection anti-injection).
- Sorties HTML **échappées** côté admin (protection anti-XSS).
- **Jeton CSRF** sur tous les formulaires d'administration.
- **Modération** obligatoire des commentaires ; l'e-mail du commentateur n'est jamais exposé.
- Téléversements : **type réel vérifié** (JPEG/PNG/WebP/GIF), taille limitée, nom de fichier aléatoire.
- Dossiers sensibles **interdits d'accès web** ; `uploads/` **n'exécute aucun script**.
- Confirmation des dons par **webhook signé** uniquement.

---

## 8. Passage en production (rappels)

- Créer un **utilisateur MySQL dédié** (pas `root`) avec droits limités à la base `fdm`.
- Servir le site en **HTTPS** et activer le cookie de session `secure`
  (décommenter `'secure' => true` dans `admin/inc/auth.php`).
- Renseigner un **vrai SMTP** (`mail.transport = 'smtp'`) pour que la 2FA et les notifications partent.
- Hébergement avec **IP fixe/dédiée** pour Dohone, `url_notify` en **HTTPS** et publiquement joignable.
- Sauvegarder régulièrement la base (`mysqldump`) et le dossier `uploads/`.
- Prévoir **au moins deux administrateurs** actifs (gouvernance, pas de point unique de défaillance).

---

*Identifiants de départ — à changer dès la première connexion :*
`admin@fondation-dm.org` / `ChangeMoi!2026`
#   f d m - b a c k e n d  
 