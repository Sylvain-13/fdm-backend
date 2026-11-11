<?php
/**
 * CONFIGURATION ACTIVE du site.
 * Valeurs par défaut prêtes pour WampServer (http://localhost/fdm).
 * Adaptez-les à votre environnement, puis renseignez les secrets
 * (SMTP, Dohone) avant la mise en production.
 *
 * Repère complet des options : voir config.sample.php
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'fdm',
        'user'    => 'root',   // WampServer : root / mot de passe vide
        'pass'    => 'root',
        'charset' => 'utf8mb4',
        'socket'  => '',
    ],
    'site' => [
        'nom'         => 'Fondation Denis Maugenest',
        'base_url'    => 'http://localhost/fdm',
        'timezone'    => 'Africa/Douala',
        'email_admin' => 'contact@fondation-dm.org',
    ],
    'uploads' => [
        'dir'        => __DIR__ . '/../uploads',
        'url'        => '/fdm/uploads',
        'max_octets' => 4 * 1024 * 1024,
        'types'      => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],
    'securite' => [
        'session_nom'   => 'FDMADMIN',
        'jours_2fa'     => 30,
        'code_longueur' => 6,
        'code_minutes'  => 15,
        'code_essais'   => 5,
    ],
    'mail' => [
        // 'log' en local (codes 2FA écrits dans logs/mail.log) ;
        // passez à 'smtp' en production et renseignez la section smtp.
        'transport'  => 'log',
        'from_email' => 'no-reply@fondation-dm.org',
        'from_nom'   => 'Fondation Denis Maugenest',
        'smtp' => [
            'hote'     => 'smtp.zoho.com',
            'port'     => 587,
            'securite' => 'tls',
            'user'     => 'no-reply@fondation-dm.org',
            'pass'     => 'MOT_DE_PASSE_SMTP',
        ],
    ],
    'dohone' => [
        'marchand'              => 'VOTRE_ID_MARCHAND_DOHONE',
        'secret'                => 'VOTRE_SECRET_DOHONE',
        'devise'                => 'XAF',
        'url_retour'            => 'http://localhost/fdm/api/don_retour.php',
        'url_notify'            => 'http://localhost/fdm/api/don_webhook.php',
        'signature_modele'      => '{rN}{mt}{idPaiement}{secret}',
        'signature_obligatoire' => true,
    ],
];
