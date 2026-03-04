<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-settings" class="fbc-panel active">

    <div class="fbc-card">
        <div class="fbc-card-title">🔑 Configuration API</div>

        <div class="fbc-field">
            <label>
                Pixel ID
                <span class="hint">Trouvable dans le Gestionnaire d'événements Meta</span>
            </label>
            <input type="text" name="pixel_id"
                   value="<?php echo esc_attr( $options['pixel_id'] ); ?>"
                   placeholder="Ex : 1234567890123456" />
        </div>

        <div class="fbc-field">
            <label>
                Access Token
                <span class="hint">Token CAPI généré dans les paramètres du Pixel</span>
            </label>
            <input type="password" name="access_token" value=""
                   placeholder="<?php echo ! empty( $options['access_token'] ) ? '••••••••• (déjà configuré)' : 'EAAxxxxxxx…'; ?>" />
        </div>

        <div class="fbc-field">
            <label>
                Webhook Secret
                <span class="hint">Clé HMAC pour vérifier les webhooks SureCart</span>
            </label>
            <input type="password" name="webhook_secret" value=""
                   placeholder="<?php echo ! empty( $options['webhook_secret'] ) ? '••••••••• (déjà configuré)' : 'Collez votre secret ici'; ?>" />
        </div>

        <div class="fbc-field">
            <label>
                Code événement test
                <span class="hint">Optionnel — pour tester dans Meta Events Manager</span>
            </label>
            <input type="text" name="test_event_code"
                   value="<?php echo esc_attr( $options['test_event_code'] ); ?>"
                   placeholder="TEST12345" />
        </div>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title">🛒 Plateforme e-commerce</div>

        <?php
        $sc_active        = defined( 'SURECART_VERSION' ) || class_exists( 'SureCart' );
        $wc_active        = class_exists( 'WooCommerce' );
        $saved_platform   = $options['platform'] ?? 'none';

        $platforms = [
            'none'        => [
                'label'     => 'Aucune',
                'desc'      => 'Seul PageView est géré automatiquement',
                'available' => true,
            ],
            'surecart'    => [
                'label'     => 'SureCart',
                'desc'      => 'Webhook Purchase + sélecteurs web components',
                'available' => $sc_active,
            ],
            'woocommerce' => [
                'label'     => 'WooCommerce',
                'desc'      => 'Hook woocommerce_thankyou + sélecteurs DOM standards',
                'available' => $wc_active,
            ],
        ];

        // Si la plateforme sauvegardée n'est plus disponible, afficher "Aucune" comme sélectionnée.
        $ui_platform = ( isset( $platforms[ $saved_platform ] ) && $platforms[ $saved_platform ]['available'] )
            ? $saved_platform
            : 'none';
        ?>

        <div class="fbc-platform-list">
            <?php foreach ( $platforms as $value => $p ) :
                $unavailable = ! $p['available'];
            ?>
            <label class="fbc-platform-option<?php echo $unavailable ? ' fbc-platform-unavailable' : ''; ?>">
                <input type="radio" name="platform"
                       value="<?php echo esc_attr( $value ); ?>"
                       <?php checked( $ui_platform, $value ); ?>
                       <?php echo $unavailable ? 'disabled' : ''; ?> />
                <div class="fbc-platform-info">
                    <span class="fbc-platform-name"><?php echo esc_html( $p['label'] ); ?></span>
                    <span class="fbc-platform-desc"><?php echo esc_html( $p['desc'] ); ?></span>
                </div>
                <?php if ( $value !== 'none' ) : ?>
                <span class="fbc-platform-badge <?php echo $p['available'] ? 'detected' : 'not-detected'; ?>">
                    <?php echo $p['available'] ? '✓ Détecté' : '✗ Non installé'; ?>
                </span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title">📡 Canaux d'envoi</div>

        <div class="fbc-toggle-row">
            <div class="fbc-toggle-info">
                <strong>Pixel JavaScript (navigateur)</strong>
                <span>Envoie les événements via le navigateur du visiteur</span>
            </div>
            <label class="fbc-switch">
                <input type="checkbox" name="enable_pixel_js" <?php checked( $options['enable_pixel_js'] ); ?> />
                <span class="fbc-slider"></span>
            </label>
        </div>

        <div class="fbc-toggle-row">
            <div class="fbc-toggle-info">
                <strong>Conversions API (serveur)</strong>
                <span>Envoie les événements côté serveur pour un meilleur tracking</span>
            </div>
            <label class="fbc-switch">
                <input type="checkbox" name="enable_capi" <?php checked( $options['enable_capi'] ); ?> />
                <span class="fbc-slider"></span>
            </label>
        </div>
    </div>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_save" class="fbc-btn fbc-btn-primary">💾 Enregistrer</button>
    </div>

</div>
