<?php if ( ! defined( 'ABSPATH' ) ) exit;

$platform = $options['platform'] ?? 'surecart';
?>

<div id="panel-events" class="fbc-panel">

    <div class="fbc-card">
        <div class="fbc-card-title">🎯 Événements à tracker</div>

        <?php
        $woo = ( $platform === 'woocommerce' );
        $events = [
            'enable_pageview'         => [
                'label' => 'PageView',
                'desc'  => 'Se déclenche à chaque chargement de page',
            ],
            'enable_viewcontent'      => [
                'label' => 'ViewContent',
                'desc'  => $woo
                    ? 'Quand un visiteur consulte une page produit WooCommerce'
                    : 'Quand un visiteur consulte une page produit SureCart',
            ],
            'enable_addtocart'        => [
                'label' => 'AddToCart',
                'desc'  => 'Quand un produit est ajouté au panier',
            ],
            'enable_initiatecheckout' => [
                'label' => 'InitiateCheckout',
                'desc'  => $woo
                    ? 'Quand la page checkout WooCommerce est chargée'
                    : 'Quand le formulaire de paiement SureCart s\'affiche',
            ],
            'enable_purchase'         => [
                'label' => 'Purchase',
                'desc'  => $woo
                    ? 'Quand un achat est complété (hook woocommerce_thankyou)'
                    : 'Quand un achat est complété (via webhook SureCart)',
            ],
            'enable_addpaymentinfo'   => [
                'label' => 'AddPaymentInfo',
                'desc'  => 'Quand les informations de paiement sont saisies',
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
        <div class="fbc-card-title">🔗 Intégration WooCommerce</div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
            En mode WooCommerce, <strong>aucun webhook n'est nécessaire</strong>. Le plugin utilise
            le hook natif <code>woocommerce_thankyou</code> pour envoyer l'événement Purchase
            côté serveur dès que la page de confirmation de commande est affichée.
        </p>
        <p style="font-size:13px;color:#6b7280;margin:0;">
            Les données transmises à Meta incluent automatiquement la valeur, la devise,
            les IDs produits, la quantité et les informations de facturation (advanced matching).
        </p>
    </div>
    <?php else : ?>
    <div class="fbc-card">
        <div class="fbc-card-title">🔗 Webhook SureCart pour Purchase</div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
            Ajoutez cette URL dans SureCart → Settings → Webhooks → Événement <strong>order.paid</strong> :
        </p>
        <div class="fbc-code"><?php echo esc_url( rest_url( 'fb-capi/v1/surecart-webhook' ) ); ?></div>
    </div>
    <?php endif; ?>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_save" class="fbc-btn fbc-btn-primary">💾 Enregistrer</button>
    </div>

</div>
