<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Minimal translation helper.
 * English is the default language. French strings are applied when lang = 'fr'.
 *
 * Usage: fbc_t('English string')  — returns French equivalent if lang=fr, else the string as-is.
 * For HTML blocks: FB_Capi_Lang::is_fr()  — branch directly in the view.
 */
class FB_Capi_Lang {

    /** All French translations keyed by their English equivalent. */
    private static array $fr = [

        // ── page-main.php ─────────────────────────────────────────────────────
        'Facebook Pixel & Conversions API Manager for SureCart & WooCommerce'
            => 'Gestionnaire Facebook Pixel & Conversions API pour SureCart & WooCommerce',
        '🟢 Connected'          => '🟢 Connecté',
        '🔴 Not configured'     => '🔴 Non configuré',
        'Total events'          => 'Total événements',
        'Errors'                => 'Erreurs',
        'Active events'         => 'Événements actifs',
        '✅ Success rate'       => '✅ Taux de réussite',
        'successful out of'     => 'réussis sur',
        '⚙️ Settings'           => '⚙️ Réglages',
        '🎯 Events'             => '🎯 Événements',

        // ── tab-settings.php ──────────────────────────────────────────────────
        '🔑 API Configuration'  => '🔑 Configuration API',
        'Found in Meta Events Manager'
            => 'Trouvable dans le Gestionnaire d\'événements Meta',
        'CAPI token generated in Pixel settings'
            => 'Token CAPI généré dans les paramètres du Pixel',
        '••••••••• (already configured)' => '••••••••• (déjà configuré)',
        'HMAC key for SureCart webhook verification'
            => 'Clé HMAC pour vérifier les webhooks SureCart',
        'Paste your secret here'    => 'Collez votre secret ici',
        'Test event code'           => 'Code événement test',
        'Optional — to test in Meta Events Manager'
            => 'Optionnel — pour tester dans Meta Events Manager',
        '🛒 E-commerce Platform'    => '🛒 Plateforme e-commerce',
        'None'                      => 'Aucune',
        'Only PageView is handled automatically'
            => 'Seul PageView est géré automatiquement',
        'Purchase webhook + web component selectors'
            => 'Webhook Purchase + sélecteurs web components',
        'woocommerce_thankyou hook + standard DOM selectors'
            => 'Hook woocommerce_thankyou + sélecteurs DOM standards',
        '✓ Detected'            => '✓ Détecté',
        '✗ Not installed'       => '✗ Non installé',
        '📡 Tracking channels'  => '📡 Canaux d\'envoi',
        'JavaScript Pixel (browser)' => 'Pixel JavaScript (navigateur)',
        'Sends events via the visitor\'s browser'
            => 'Envoie les événements via le navigateur du visiteur',
        'Conversions API (server)'  => 'Conversions API (serveur)',
        'Sends events server-side for better tracking'
            => 'Envoie les événements côté serveur pour un meilleur tracking',
        '💾 Save'               => '💾 Enregistrer',
        '🌐 Language'           => '🌐 Langue',
        'Interface language'    => 'Langue de l\'interface',

        // ── tab-events.php ────────────────────────────────────────────────────
        '🎯 Events to track'    => '🎯 Événements à tracker',
        'Fires on every page load'
            => 'Se déclenche à chaque chargement de page',
        'When a visitor views a WooCommerce product page'
            => 'Quand un visiteur consulte une page produit WooCommerce',
        'When a visitor views a SureCart product page'
            => 'Quand un visiteur consulte une page produit SureCart',
        'When a product is added to the cart'
            => 'Quand un produit est ajouté au panier',
        'When the WooCommerce checkout page is loaded'
            => 'Quand la page checkout WooCommerce est chargée',
        'When the SureCart payment form is displayed'
            => 'Quand le formulaire de paiement SureCart s\'affiche',
        'When a purchase is completed (woocommerce_thankyou hook)'
            => 'Quand un achat est complété (hook woocommerce_thankyou)',
        'When a purchase is completed (via SureCart webhook)'
            => 'Quand un achat est complété (via webhook SureCart)',
        'When payment information is entered'
            => 'Quand les informations de paiement sont saisies',
        '🔗 WooCommerce Integration' => '🔗 Intégration WooCommerce',
        '🔗 SureCart Webhook for Purchase' => '🔗 Webhook SureCart pour Purchase',
        'Add this URL in SureCart → Settings → Webhooks → Event <strong>order.paid</strong>:'
            => 'Ajoutez cette URL dans SureCart → Settings → Webhooks → Événement <strong>order.paid</strong> :',

        // ── tab-logs.php ──────────────────────────────────────────────────────
        '⚙️ Log Settings'       => '⚙️ Paramètres des logs',
        'Enable logs'           => 'Activer les logs',
        'Record events sent via the Conversions API'
            => 'Enregistrer les événements envoyés via la Conversions API',
        'Log retention'         => 'Rétention des logs',
        '1 day'                 => '1 jour',
        '3 days'                => '3 jours',
        '7 days'                => '7 jours',
        '14 days'               => '14 jours',
        '30 days'               => '30 jours',
        '60 days'               => '60 jours',
        '90 days'               => '90 jours',
        '♾️ Unlimited (not recommended)' => '♾️ Illimité (non recommandé)',
        'Events to log'         => 'Événements à logger',
        'Select events to log'
            => 'Sélectionnez les événements dont vous souhaitez enregistrer les logs',
        '💾 Save settings'      => '💾 Enregistrer les paramètres',
        '📋 Log viewer'         => '📋 Visualisation des logs',
        'Refresh'               => 'Rafraîchir',
        '🗑️ Clear logs'         => '🗑️ Effacer les logs',
        'Clear all logs?'       => 'Effacer tous les logs ?',

        // ── tab-test.php ──────────────────────────────────────────────────────
        '🧪 Send a test event'  => '🧪 Envoyer un événement test',
        'Click the button to send a test PageView via the Conversions API.'
            => 'Cliquez sur le bouton pour envoyer un PageView test via la Conversions API.',
        '🚀 Send CAPI Test'     => '🚀 Envoyer un test CAPI',
        '📖 Configuration Guide' => '📖 Guide de configuration',

        // ── logs-table.php ────────────────────────────────────────────────────
        '📭 No logs recorded yet.' => '📭 Aucun log enregistré pour le moment.',
        'successful'            => 'réussis',
        'errors'                => 'erreurs',
        'All events'            => 'Tous les événements',
        '❌ Errors only'        => '❌ Erreurs uniquement',
        'Event'                 => 'Événement',
        'Status'                => 'Statut',
        'Response'              => 'Réponse',
        'Date'                  => 'Date',
        '❌ Error'              => '❌ Erreur',

        // ── class-admin.php notices ───────────────────────────────────────────
        '✅ Settings saved successfully!' => '✅ Réglages enregistrés avec succès !',
        '✅ Log settings saved!'          => '✅ Paramètres des logs enregistrés !',
        '🗑️ Logs cleared successfully!'  => '🗑️ Logs effacés avec succès !',

        // ── class-capi.php ────────────────────────────────────────────────────
        'Pixel ID or Access Token missing.' => 'Pixel ID ou Access Token manquant.',
        '✅ Test sent! Event ID: %s'         => '✅ Test envoyé ! Event ID : %s',
        'HTTP Error %s'                      => 'Erreur HTTP %s',
    ];

    /**
     * Return the translation of $s in the current language.
     * Reads lang from FB_Capi_Options every call (negligible cost — options are statically cached).
     */
    public static function t( string $s ): string {
        $lang = FB_Capi_Options::get()['lang'] ?? 'en';
        if ( $lang === 'fr' && isset( self::$fr[ $s ] ) ) {
            return self::$fr[ $s ];
        }
        return $s;
    }

    /** Returns true when the current language is French. */
    public static function is_fr(): bool {
        return ( FB_Capi_Options::get()['lang'] ?? 'en' ) === 'fr';
    }
}

/** Global shortcut — use fbc_t('string') in views instead of FB_Capi_Lang::t('string'). */
function fbc_t( string $s ): string {
    return FB_Capi_Lang::t( $s );
}
