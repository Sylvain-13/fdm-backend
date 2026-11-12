-- =====================================================================
--  Fondation Denis Maugenest (FDM) — Base de données du site
--  SGBD : MySQL / MariaDB (WampServer)
--  Encodage : utf8mb4 (accents, caractères spéciaux, emojis)
--  Moteur  : InnoDB (clés étrangères, transactions)
-- =====================================================================
--
--  INSTALLATION
--  1. Ouvrir phpMyAdmin (http://localhost/phpmyadmin)
--  2. Importer ce fichier, OU copier/coller son contenu dans l'onglet SQL
--  3. La base « fdm » et toutes ses tables sont créées.
--  4. Un compte administrateur de départ est créé (voir plus bas) :
--        identifiant e-mail : admin@fondation-dm.org
--        mot de passe        : ChangeMoi!2026
--     >>> SE CONNECTER PUIS CHANGER CE MOT DE PASSE IMMÉDIATEMENT <<<
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `railway`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `railway`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
--  1. ADMINISTRATEURS
--     Comptes d'accès à l'interface d'administration.
--     Le mot de passe n'est JAMAIS stocké en clair : uniquement son
--     empreinte (password_hash / bcrypt).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `administrateurs`;
CREATE TABLE `administrateurs` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`               VARCHAR(120)  NOT NULL,
  `email`             VARCHAR(190)  NOT NULL,
  `mot_de_passe`      VARCHAR(255)  NOT NULL,        -- empreinte bcrypt
  `role`              ENUM('admin','editeur') NOT NULL DEFAULT 'admin',
  `actif`             TINYINT(1)    NOT NULL DEFAULT 1,
  `derniere_verif_2fa` DATETIME     NULL DEFAULT NULL, -- dernière validation 2FA réussie
  `derniere_connexion` DATETIME     NULL DEFAULT NULL,
  `cree_le`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  2. CODES 2FA (double authentification par e-mail)
--     Un code à 6 chiffres est envoyé par e-mail lorsque la dernière
--     vérification 2FA remonte à plus de 30 jours (ou première connexion).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_codes_2fa`;
CREATE TABLE `admin_codes_2fa` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`      INT UNSIGNED NOT NULL,
  `code_hash`     VARCHAR(255) NOT NULL,            -- empreinte du code (jamais en clair)
  `expire_le`     DATETIME     NOT NULL,            -- validité courte (ex. 15 min)
  `tentatives`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `utilise`       TINYINT(1)   NOT NULL DEFAULT 0,
  `cree_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code_admin` (`admin_id`),
  CONSTRAINT `fk_code_admin` FOREIGN KEY (`admin_id`)
      REFERENCES `administrateurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  3. ACTUALITÉS (fil d'actualité, articles)
--     Catégories reprises du front :
--     campagne | candidatures | evenement | fodes | alumni | bourses
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `actualites`;
CREATE TABLE `actualites` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`          VARCHAR(220) NOT NULL,            -- identifiant d'URL (unique)
  `titre`         VARCHAR(255) NOT NULL,
  `categorie`     ENUM('campagne','candidatures','evenement','fodes','alumni','bourses')
                  NOT NULL DEFAULT 'campagne',
  `chapeau`       TEXT         NULL,                -- résumé (carte + partage)
  `corps`         MEDIUMTEXT   NULL,                -- contenu HTML riche de l'article
  `image`         VARCHAR(255) NULL,                -- nom de fichier dans /uploads/actualites
  `temps_lecture` SMALLINT UNSIGNED NULL,           -- minutes (facultatif)
  `date_publication` DATE      NOT NULL,
  `statut`        ENUM('brouillon','publie') NOT NULL DEFAULT 'brouillon',
  `auteur_id`     INT UNSIGNED NULL,
  `vues`          INT UNSIGNED NOT NULL DEFAULT 0,
  `cree_le`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_actu_slug` (`slug`),
  KEY `idx_actu_cat` (`categorie`),
  KEY `idx_actu_statut_date` (`statut`, `date_publication`),
  KEY `fk_actu_auteur` (`auteur_id`),
  CONSTRAINT `fk_actu_auteur` FOREIGN KEY (`auteur_id`)
      REFERENCES `administrateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  4. COMMENTAIRES (sous les articles)
--     Nom + prénom + e-mail (obligatoire, jamais publié) + message.
--     Modération obligatoire AVANT publication.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `commentaires`;
CREATE TABLE `commentaires` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actualite_id` INT UNSIGNED NOT NULL,
  `nom`          VARCHAR(120) NOT NULL,
  `prenom`       VARCHAR(120) NOT NULL,
  `email`        VARCHAR(190) NOT NULL,             -- jamais affiché publiquement
  `message`      TEXT         NOT NULL,
  `statut`       ENUM('en_attente','publie','rejete') NOT NULL DEFAULT 'en_attente',
  `ip`           VARBINARY(16) NULL,                -- anti-spam / traçabilité
  `cree_le`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modere_le`    DATETIME     NULL DEFAULT NULL,
  `modere_par`   INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_com_actu_statut` (`actualite_id`, `statut`),
  CONSTRAINT `fk_com_actu` FOREIGN KEY (`actualite_id`)
      REFERENCES `actualites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_moder` FOREIGN KEY (`modere_par`)
      REFERENCES `administrateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  5. ÉVÉNEMENTS (agenda, grille d'affiches filtrable par mois)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `evenements`;
CREATE TABLE `evenements` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titre`       VARCHAR(255) NOT NULL,
  `affiche`     VARCHAR(255) NULL,                  -- fichier dans /uploads/evenements
  `description` TEXT         NULL,
  `lieu`        VARCHAR(255) NULL,
  `date_debut`  DATE         NOT NULL,
  `date_fin`    DATE         NULL,
  `mois`        TINYINT UNSIGNED NOT NULL,          -- 1..12, dérivé de date_debut (filtre)
  `annee`       SMALLINT UNSIGNED NOT NULL,
  `statut`      ENUM('brouillon','publie') NOT NULL DEFAULT 'brouillon',
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_even_mois` (`statut`, `annee`, `mois`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  6. PARTENAIRES (logos en bas de page + page dédiée)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `partenaires`;
CREATE TABLE `partenaires` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(190) NOT NULL,
  `logo`        VARCHAR(255) NULL,                  -- fichier dans /uploads/partenaires
  `description` TEXT         NULL,
  `site_web`    VARCHAR(255) NULL,
  `ordre`       SMALLINT     NOT NULL DEFAULT 0,    -- ordre d'affichage
  `actif`       TINYINT(1)   NOT NULL DEFAULT 1,
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_part_actif_ordre` (`actif`, `ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  7. BÉNÉVOLES (candidatures via le formulaire public)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `benevoles`;
CREATE TABLE `benevoles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`        VARCHAR(120) NOT NULL,
  `prenom`     VARCHAR(120) NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `telephone`  VARCHAR(40)  NULL,
  `domaine`    VARCHAR(190) NULL,                   -- domaine d'engagement souhaité
  `message`    TEXT         NULL,
  `statut`     ENUM('nouveau','traite','archive') NOT NULL DEFAULT 'nouveau',
  `ip`         VARBINARY(16) NULL,
  `cree_le`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_benev_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  8. CONTACTS (formulaire de contact général)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organisation` VARCHAR(190) NULL,
  `nom`         VARCHAR(120) NOT NULL,
  `prenom`      VARCHAR(120) NULL,
  `email`       VARCHAR(190) NOT NULL,
  `telephone`   VARCHAR(40)  NULL,
  `objet`       VARCHAR(190) NULL,
  `message`     TEXT         NOT NULL,
  `statut`      ENUM('nouveau','traite','archive') NOT NULL DEFAULT 'nouveau',
  `ip`          VARBINARY(16) NULL,
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  9. ALUMNIS (base des anciens — gérée côté admin, une par une ou CSV)
--     Sert de source pour l'export CSV vers Brevo (mailing groupé).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `alumnis`;
CREATE TABLE `alumnis` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(120) NOT NULL,
  `prenom`      VARCHAR(120) NOT NULL,
  `email`       VARCHAR(190) NOT NULL,
  `telephone`   VARCHAR(40)  NULL,
  `promotion`   VARCHAR(40)  NULL,                  -- année / promo
  `filiere`     VARCHAR(190) NULL,
  `ville`       VARCHAR(120) NULL,
  `pays`        VARCHAR(120) NULL,
  `consentement_email` TINYINT(1) NOT NULL DEFAULT 1, -- opt-in mailing
  `source`      ENUM('saisie','import_csv') NOT NULL DEFAULT 'saisie',
  `cree_le`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_alumni_email` (`email`),           -- évite les doublons à l'import
  KEY `idx_alumni_promo` (`promotion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  10. NEWSLETTER (inscriptions e-mail simples)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `newsletter`;
CREATE TABLE `newsletter` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `actif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `ip`         VARBINARY(16) NULL,
  `cree_le`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_news_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  11. DONS
--     La confirmation d'un don en ligne provient TOUJOURS du webhook
--     serveur-à-serveur de Dohone (jamais d'une capture d'écran du donateur).
--     Les virements bancaires sont rapprochés à la main depuis le relevé
--     de la Fondation, via une référence unique.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `dons`;
CREATE TABLE `dons` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference`       VARCHAR(60)  NOT NULL,          -- référence unique FDM (générée)
  `donateur_nom`    VARCHAR(190) NULL,
  `donateur_email`  VARCHAR(190) NULL,
  `donateur_tel`    VARCHAR(40)  NULL,
  `montant`         DECIMAL(12,2) NOT NULL,
  `devise`          VARCHAR(8)   NOT NULL DEFAULT 'XAF',
  `moyen`           ENUM('dohone','virement') NOT NULL DEFAULT 'dohone',
  `canal`           VARCHAR(40)  NULL,              -- ex. OrangeMoney, MoMo (renvoyé par Dohone)
  `statut`          ENUM('initie','confirme','echoue','annule') NOT NULL DEFAULT 'initie',
  `dohone_transaction` VARCHAR(120) NULL,           -- ID de transaction côté Dohone
  `dohone_payload`  TEXT         NULL,              -- notification brute (audit)
  `message`         VARCHAR(255) NULL,
  `confirme_le`     DATETIME     NULL DEFAULT NULL,
  `rapproche_par`   INT UNSIGNED NULL,              -- admin ayant validé un virement
  `cree_le`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_don_reference` (`reference`),
  KEY `idx_don_statut` (`statut`),
  KEY `idx_don_moyen` (`moyen`),
  CONSTRAINT `fk_don_rappro` FOREIGN KEY (`rapproche_par`)
      REFERENCES `administrateurs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  12. MEDIAS (images décoratives du site gérées depuis l'admin)
--      Chaque image occupe un « emplacement » identifié par une clé
--      (ex. accueil_hero, banniere_contact). Le front remplace alors
--      le visuel correspondant par l'image téléversée.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `medias`;
CREATE TABLE `medias` (
  `id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cle`     VARCHAR(60)  NOT NULL,        -- identifiant d'emplacement
  `fichier` VARCHAR(255) NOT NULL,        -- fichier dans /uploads/site
  `maj_le`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_media_cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  COMPTE(S) ADMINISTRATEUR DE DÉPART
--  Le mot de passe ci-dessous est l'empreinte bcrypt de : ChangeMoi!2026
--  (générée avec password_hash). À CHANGER après la première connexion.
--  Gouvernance : prévoir AU MOINS 2 administrateurs (pas de point unique
--  de défaillance). Créez le second compte depuis l'interface d'admin.
-- =====================================================================
INSERT INTO `administrateurs` (`nom`, `email`, `mot_de_passe`, `role`, `actif`)
VALUES
  ('Administrateur FDM', 'admin@fondation-dm.org',
   '$2y$10$ZE1.wZoKyM4l3C5zLZMLUOWgW/uHFDHQPCTqdFi9A./mp3P8Jo3lq', 'admin', 1);

-- Fin du script.
