<?php
/** Configuration : variables d'environnement (Railway) sinon valeurs locales. */
function env($k, $d = null) { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }

return [
    'db' => [
        'host'    => env('MYSQLHOST',     env('DB_HOST', '127.0.0.1')),
        'port'    => (int) env('MYSQLPORT', env('DB_PORT', '3306')),
        'name'    => env('MYSQLDATABASE', env('DB_NAME', 'railway')),
        'user'    => env('MYSQLUSER',     env('DB_USER', 'root')),
        'pass'    => env('MYSQLPASSWORD', env('DB_PASS', 'vserDwzzqlGzVQWPGaMAgJAiixXynUNW')),
        'charset' => 'utf8mb4',
        'socket'  => '',
    ],
    'site' => [
        'nom'         => 'Fondation Denis Maugenest',
        'base_url'    => env('APP_BASE_URL', ''),
        'timezone'    => 'Africa/Douala',
        'email_admin' => env('EMAIL_ADMIN', 'contact@fondation-dm.org'),
    ],
    'uploads' => [
        'dir'        => __DIR__ . '/../uploads',
        'url'        => env('UPLOADS_URL', '/uploads'),
        'max_octets' => 4 * 1024 * 1024,
        'types'      => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],
    'securite' => [
        'session_nom' => 'FDMADMIN',
        'jours_2fa' => 30, 'code_longueur' => 6, 'code_minutes' => 15, 'code_essais' => 5,
    ],
    'mail' => [
        'transport'  => env('MAIL_TRANSPORT', 'log'),
        'from_email' => env('MAIL_FROM', 'no-reply@fondation-dm.org'),
        'from_nom'   => 'Fondation Denis Maugenest',
        'smtp' => [
            'hote'     => env('SMTP_HOTE', 'smtp.zoho.com'),
            'port'     => (int) env('SMTP_PORT', '587'),
            'securite' => env('SMTP_SECURITE', 'tls'),
            'user'     => env('SMTP_USER', ''),
            'pass'     => env('SMTP_PASS', ''),
        ],
    ],
    'dohone' => [
        'marchand'              => env('DOHONE_MARCHAND', 'TEST'),
        'secret'                => env('DOHONE_SECRET', 'TEST'),
        'devise'                => 'XAF',
        'url_retour'            => env('APP_BASE_URL', '') . '/api/don_retour.php',
        'url_notify'            => env('APP_BASE_URL', '') . '/api/don_webhook.php',
        'signature_modele'      => '{rN}{mt}{idPaiement}{secret}',
        'signature_obligatoire' => true,
    ],
];