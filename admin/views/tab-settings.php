<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-settings" class="fbc-panel active">

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🔑 API Configuration' ); ?></div>

        <div class="fbc-field">
            <label>
                Pixel ID
                <span class="hint"><?php echo fbc_t( 'Found in Meta Events Manager' ); ?></span>
            </label>
            <input type="text" name="pixel_id"
                   value="<?php echo esc_attr( $options['pixel_id'] ); ?>"
                   placeholder="Ex : 1234567890123456" />
        </div>

        <div class="fbc-field">
            <label>
                Access Token
                <span class="hint"><?php echo fbc_t( 'CAPI token generated in Pixel settings' ); ?></span>
            </label>
            <input type="password" name="access_token" value=""
                   placeholder="<?php echo ! empty( $options['access_token'] ) ? fbc_t( '••••••••• (already configured)' ) : 'EAAxxxxxxx…'; ?>" />
        </div>

        <div class="fbc-field">
            <label>
                Webhook Secret
                <span class="hint"><?php echo fbc_t( 'HMAC key for SureCart webhook verification' ); ?></span>
            </label>
            <input type="password" name="webhook_secret" value=""
                   placeholder="<?php echo ! empty( $options['webhook_secret'] ) ? fbc_t( '••••••••• (already configured)' ) : fbc_t( 'Paste your secret here' ); ?>" />
        </div>

        <div class="fbc-field">
            <label>
                <?php echo fbc_t( 'Test event code' ); ?>
                <span class="hint"><?php echo fbc_t( 'Optional — to test in Meta Events Manager' ); ?></span>
            </label>
            <input type="text" name="test_event_code"
                   value="<?php echo esc_attr( $options['test_event_code'] ); ?>"
                   placeholder="TEST12345" />
        </div>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🛒 E-commerce Platform' ); ?></div>

        <?php
        $sc_active        = defined( 'SURECART_VERSION' ) || class_exists( 'SureCart' );
        $wc_active        = class_exists( 'WooCommerce' );
        $saved_platform   = $options['platform'] ?? 'none';

        $platforms = [
            'none'        => [
                'label'     => fbc_t( 'None' ),
                'desc'      => fbc_t( 'Only PageView is handled automatically' ),
                'available' => true,
            ],
            'surecart'    => [
                'label'     => 'SureCart',
                'desc'      => fbc_t( 'Purchase webhook + web component selectors' ),
                'available' => $sc_active,
            ],
            'woocommerce' => [
                'label'     => 'WooCommerce',
                'desc'      => fbc_t( 'woocommerce_thankyou hook + standard DOM selectors' ),
                'available' => $wc_active,
            ],
        ];

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
                    <?php echo $p['available'] ? fbc_t( '✓ Detected' ) : fbc_t( '✗ Not installed' ); ?>
                </span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '📡 Tracking channels' ); ?></div>

        <div class="fbc-toggle-row">
            <div class="fbc-toggle-info">
                <strong><?php echo fbc_t( 'JavaScript Pixel (browser)' ); ?></strong>
                <span><?php echo fbc_t( 'Sends events via the visitor\'s browser' ); ?></span>
            </div>
            <label class="fbc-switch">
                <input type="checkbox" name="enable_pixel_js" <?php checked( $options['enable_pixel_js'] ); ?> />
                <span class="fbc-slider"></span>
            </label>
        </div>

        <div class="fbc-toggle-row">
            <div class="fbc-toggle-info">
                <strong><?php echo fbc_t( 'Conversions API (server)' ); ?></strong>
                <span><?php echo fbc_t( 'Sends events server-side for better tracking' ); ?></span>
            </div>
            <label class="fbc-switch">
                <input type="checkbox" name="enable_capi" <?php checked( $options['enable_capi'] ); ?> />
                <span class="fbc-slider"></span>
            </label>
        </div>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🌐 Language' ); ?></div>

        <div class="fbc-field">
            <label><?php echo fbc_t( 'Interface language' ); ?></label>
            <select name="lang" class="fbc-select">
                <option value="en" <?php selected( $options['lang'] ?? 'en', 'en' ); ?>>English</option>
                <option value="fr" <?php selected( $options['lang'] ?? 'en', 'fr' ); ?>>Français</option>
            </select>
        </div>
    </div>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_save" class="fbc-btn fbc-btn-primary"><?php echo fbc_t( '💾 Save' ); ?></button>
    </div>

</div>
