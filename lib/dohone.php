<?php
/**
 * Intégration du paiement Dohone.
 *
 * Principe d'intégrité (rappelé dans le cahier des charges) :
 *   - Un don en ligne n'est jamais confirmé sur la foi d'une capture
 *     d'écran du donateur.
 *   - La confirmation provient UNIQUEMENT de la notification
 *     serveur-à-serveur (webhook « notify ») envoyée par Dohone, dont
 *     on vérifie la signature, le montant et l'unicité (idempotence).
 *
 * NOTE : le détail exact des champs et du calcul de signature peut
 * varier selon la version de l'API et le compte marchand. Le modèle de
 * signature est donc configurable (clé 'signature_modele' de config.php).
 * Adaptez-le à la documentation de VOTRE compte Dohone.
 */

/**
 * Construit les paramètres à transmettre à Dohone pour démarrer un
 * paiement (à utiliser côté client pour rediriger vers la page Dohone,
 * ou pour un appel API selon votre intégration).
 */
function dohone_parametres_paiement(array $don): array
{
    $d = fdm_config()['dohone'];
    return [
        'marchand'   => $d['marchand'],
        'montant'    => number_format((float)$don['montant'], 0, '.', ''),
        'devise'     => $d['devise'],
        'reference'  => $don['reference'],      // notre référence unique
        'url_retour' => $d['url_retour'],
        'url_notify' => $d['url_notify'],
        'client_nom'   => $don['donateur_nom']   ?? '',
        'client_email' => $don['donateur_email'] ?? '',
        'client_tel'   => $don['donateur_tel']   ?? '',
    ];
}

/**
 * Calcule la signature attendue à partir du modèle configuré et des
 * valeurs reçues. Les occurrences {champ} du modèle sont remplacées par
 * la valeur reçue correspondante ; {secret} par le secret partagé.
 */
function dohone_signature_attendue(array $recu): string
{
    $d = fdm_config()['dohone'];
    $modele = $d['signature_modele'];

    $chaine = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use ($recu, $d) {
        $cle = $m[1];
        if ($cle === 'secret') { return (string)$d['secret']; }
        return (string)($recu[$cle] ?? '');
    }, $modele);

    return strtolower(md5($chaine));
}

/**
 * Vérifie la notification reçue de Dohone.
 * Retourne un tableau : ['valide' => bool, 'raison' => string].
 *
 * $recu          : les paramètres reçus (GET/POST) de la notification.
 * $signatureNom  : nom du champ contenant la signature (défaut 'hash').
 */
function dohone_verifier_notification(array $recu, string $signatureNom = 'hash'): array
{
    $d = fdm_config()['dohone'];
    $fournie = strtolower((string)($recu[$signatureNom] ?? ''));

    if ($fournie === '') {
        if (!empty($d['signature_obligatoire'])) {
            return ['valide' => false, 'raison' => 'Signature absente.'];
        }
        return ['valide' => true, 'raison' => 'Signature non vérifiée (désactivée).'];
    }

    $attendue = dohone_signature_attendue($recu);
    if (!hash_equals($attendue, $fournie)) {
        return ['valide' => false, 'raison' => 'Signature invalide.'];
    }
    return ['valide' => true, 'raison' => 'OK'];
}
