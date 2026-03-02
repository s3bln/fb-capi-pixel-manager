<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-events" class="fbc-panel">

    <div class="fbc-card">
        <div class="fbc-card-title">🎯 Événements à tracker</div>

        <?php
        $events = [
            'enable_pageview'         => [
                'label' => 'PageView',
                'desc'  => 'Se déclenche à chaque chargement de page',
            ],
            'enable_viewcontent'      => [
                'label' => 'ViewContent',
                'desc'  => 'Quand un visiteur consulte une page produit SureCart',
            ],
            'enable_addtocart'        => [
                'label' => 'AddToCart',
                'desc'  => 'Quand un produit est ajouté au panier',
            ],
            'enable_initiatecheckout' => [
                'label' => 'InitiateCheckout',
                'desc'  => 'Quand le formulaire de paiement s\'affiche',
            ],
            'enable_purchase'         => [
                'label' => 'Purchase',
                'desc'  => 'Quand un achat est complété (via webhook SureCart)',
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

    <div class="fbc-card">
        <div class="fbc-card-title">🔗 Webhook SureCart pour Purchase</div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
            Ajoutez cette URL dans SureCart → Settings → Webhooks → Événement <strong>order.paid</strong> :
        </p>
        <div class="fbc-code"><?php echo esc_url( rest_url( 'fb-capi/v1/surecart-webhook' ) ); ?></div>
    </div>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_save" class="fbc-btn fbc-btn-primary">💾 Enregistrer</button>
    </div>

</div>
