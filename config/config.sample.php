<?php
/**
 * =====================================================================
 *  CONFIGURATION — Fondation Denis Maugenest
 * =====================================================================
 *  1. Copiez ce fichier sous le nom  config.php  (dans le même dossier).
 *  2. Renseignez vos valeurs réelles.
 *  3. NE VERSIONNEZ JAMAIS config.php (il contient des secrets).
 *
 *  Sur WampServer en local, les valeurs par défaut de la base
 *  (hôte 127.0.0.1, utilisateur root, mot de passe vide) fonctionnent
 *  telles quelles. En production, créez un utilisateur MySQL dédié.
 * =====================================================================
 */

return [

    // ---- Base de données MySQL -------------------------------------
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'fdm',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
        // Laissez vide sur WampServer. En production/Linux, vous pouvez
        // utiliser un socket Unix : 'socket' => '/run/mysqld/mysqld.sock'
        'socket'  => '',
    ],

    // ---- Site -------------------------------------------------------
    'site' => [
        'nom'       => 'Fondation Denis Maugenest',
        // URL publique de base (sans slash final). En local WampServer :
        'base_url'  => 'http://localhost/fdm',
        // Fuseau horaire (Cameroun = Afrique/Douala, UTC+1)
        'timezone'  => 'Africa/Douala',
        // E-mail qui reçoit les notifications internes (nouveau don,
        // nouveau message de contact, nouvelle candidature bénévole…)
        'email_admin' => 'contact@fondation-dm.org',
    ],

    // ---- Téléversement des images ----------------------------------
    'uploads' => [
        'dir'        => __DIR__ . '/../uploads', // chemin disque
        'url'        => '/fdm/uploads',                 // chemin web
        'max_octets' => 4 * 1024 * 1024,                // 4 Mo par image
        'types'      => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

    // ---- Sessions / sécurité ---------------------------------------
    'securite' => [
        'session_nom'   => 'FDMADMIN',
        // Double authentification (2FA) par e-mail : un code est demandé
        // lorsque la dernière vérification remonte à plus de N jours.
        'jours_2fa'     => 30,
        'code_longueur' => 6,
        'code_minutes'  => 15,   // durée de validité du code
        'code_essais'   => 5,    // tentatives maximum par code
    ],

    // ---- Envoi d'e-mails -------------------------------------------
    //  transport :
    //   'smtp' -> envoi réel via un serveur SMTP (recommandé en prod)
    //   'mail' -> fonction mail() de PHP (nécessite un MTA local)
    //   'log'  -> aucun envoi : le message est écrit dans logs/mail.log
    //             (pratique pour développer en local sans SMTP)
    'mail' => [
        'transport' => 'log',
        'from_email'=> 'no-reply@fondation-dm.org',
        'from_nom'  => 'Fondation Denis Maugenest',
        'smtp' => [
            'hote'     => 'smtp.zoho.com', // ou smtp.hostinger.com
            'port'     => 587,
            'securite' => 'tls',           // 'tls' (587) ou 'ssl' (465)
            'user'     => 'no-reply@fondation-dm.org',
            'pass'     => 'MOT_DE_PASSE_SMTP',
        ],
    ],

    // ---- Paiement Dohone -------------------------------------------
    //  Renseignez ces valeurs depuis votre compte marchand Dohone.
    //  La confirmation d'un don provient TOUJOURS du webhook (notify)
    //  serveur-à-serveur : jamais d'une capture d'écran du donateur.
    'dohone' => [
        'marchand'   => 'VOTRE_ID_MARCHAND_DOHONE',
        // Clé/secret partagé servant à vérifier la signature des
        // notifications. À obtenir auprès de Dohone.
        'secret'     => 'VOTRE_SECRET_DOHONE',
        'devise'     => 'XAF',
        // URL vers laquelle Dohone renvoie le navigateur du donateur
        'url_retour' => 'http://localhost/fdm/api/don_retour.php',
        // URL (notify) que Dohone appelle en serveur-à-serveur
        'url_notify' => 'http://localhost/fdm/api/don_webhook.php',
        // Modèle de calcul de la signature de notification.
        // Les champs entre {} sont remplacés par les valeurs reçues,
        // puis on applique md5() sur la chaîne obtenue.
        // ADAPTEZ cet ordre à la documentation de VOTRE compte Dohone.
        'signature_modele' => '{rN}{mt}{idPaiement}{secret}',
        // true = refuser toute notification dont la signature est absente
        // ou invalide (comportement recommandé en production).
        'signature_obligatoire' => true,
    ],
];
