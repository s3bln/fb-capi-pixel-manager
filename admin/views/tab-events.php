<?php if ( ! defined( 'ABSPATH' ) ) exit;

$platform = $options['platform'] ?? 'surecart';
?>

<div id="panel-events" class="fbc-panel">

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🎯 Events to track' ); ?></div>

        <?php
        $woo = ( $platform === 'woocommerce' );
        $events = [
            'enable_pageview'         => [
                'label' => 'PageView',
                'desc'  => fbc_t( 'Fires on every page load' ),
            ],
            'enable_viewcontent'      => [
                'label' => 'ViewContent',
                'desc'  => $woo
                    ? fbc_t( 'When a visitor views a WooCommerce product page' )
                    : fbc_t( 'When a visitor views a SureCart product page' ),
            ],
            'enable_addtocart'        => [
                'label' => 'AddToCart',
                'desc'  => fbc_t( 'When a product is added to the cart' ),
            ],
            'enable_initiatecheckout' => [
                'label' => 'InitiateCheckout',
                'desc'  => $woo
                    ? fbc_t( 'When the WooCommerce checkout page is loaded' )
                    : fbc_t( 'When the SureCart payment form is displayed' ),
            ],
            'enable_purchase'         => [
                'label' => 'Purchase',
                'desc'  => $woo
                    ? fbc_t( 'When a purchase is completed (woocommerce_thankyou hook)' )
                    : fbc_t( 'When a purchase is completed (via SureCart webhook)' ),
            ],
            'enable_addpaymentinfo'   => [
                'label' => 'AddPaymentInfo',
                'desc'  => fbc_t( 'When payment information is entered' ),
            ],
        ];
        foreach ( $events as $key => $event ) :
        ?>
            <div class="fbc-toggle-row">
                <div class="fbc-toggle-info">
                    <strong><?php echo esc_html( $event['label'] ); ?></strong>
                    <span><?php echo esc_html( $event['desc'] ); ?></span>
                </div>
                <label class="fbc-switch">
                    <input type="checkbox" name="<?php echo esc_attr( $key ); ?>"
                           <?php checked( $options[ $key ] ); ?> />
                    <span class="fbc-slider"></span>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ( $platform === 'woocommerce' ) : ?>
    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🔗 WooCommerce Integration' ); ?></div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
            <?php if ( FB_Capi_Lang::is_fr() ) : ?>
                En mode WooCommerce, <strong>aucun webhook n'est nécessaire</strong>. Le plugin utilise
                le hook natif <code>woocommerce_thankyou</code> pour envoyer l'événement Purchase
                côté serveur dès que la page de confirmation de commande est affichée.
            <?php else : ?>
                In WooCommerce mode, <strong>no webhook is required</strong>. The plugin uses
                the native <code>woocommerce_thankyou</code> hook to send the Purchase event
                server-side as soon as the order confirmation page is displayed.
            <?php endif; ?>
        </p>
        <p style="font-size:13px;color:#6b7280;margin:0;">
            <?php if ( FB_Capi_Lang::is_fr() ) : ?>
                Les données transmises à Meta incluent automatiquement la valeur, la devise,
                les IDs produits, la quantité et les informations de facturation (advanced matching).
            <?php else : ?>
                Data sent to Meta automatically includes value, currency, product IDs,
                quantity and billing information (advanced matching).
            <?php endif; ?>
        </p>
    </div>
    <?php else : ?>
    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🔗 SureCart Webhook for Purchase' ); ?></div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
            <?php echo fbc_t( 'Add this URL in SureCart → Settings → Webhooks → Event <strong>order.paid</strong>:' ); ?>
        </p>
        <div class="fbc-code"><?php echo esc_url( rest_url( 'fb-capi/v1/surecart-webhook' ) ); ?></div>
    </div>
    <?php endif; ?>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_save" class="fbc-btn fbc-btn-primary"><?php echo fbc_t( '💾 Save' ); ?></button>
    </div>

</div>
