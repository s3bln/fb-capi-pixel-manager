<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-test" class="fbc-panel">

    <div class="fbc-alert info">
        ⚡
        <?php if ( FB_Capi_Lang::is_fr() ) : ?>
            Le test envoie un événement <strong>PageView</strong> via la Conversions API.
            Vérifiez ensuite dans <strong>Meta → Gestionnaire d'événements → Événements de test</strong>.
        <?php else : ?>
            The test sends a <strong>PageView</strong> event via the Conversions API.
            Then check in <strong>Meta → Events Manager → Test Events</strong>.
        <?php endif; ?>
    </div>

    <?php if ( $test_result ) : ?>
        <div class="fbc-alert <?php echo $test_result['status'] === 'success' ? 'success' : 'warning'; ?>">
            <?php echo esc_html( $test_result['message'] ); ?>
        </div>
        <?php if ( ! empty( $test_result['response'] ) ) : ?>
            <div class="fbc-card">
                <div class="fbc-card-title">📄 API Response</div>
                <pre class="fbc-pre"><?php echo esc_html( $test_result['response'] ); ?></pre>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '🧪 Send a test event' ); ?></div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">
            <?php echo fbc_t( 'Click the button to send a test PageView via the Conversions API.' ); ?>
        </p>
        <button type="submit" name="fb_capi_test" class="fbc-btn fbc-btn-primary">
            <?php echo fbc_t( '🚀 Send CAPI Test' ); ?>
        </button>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '📖 Configuration Guide' ); ?></div>
        <ol style="font-size:13px;color:#374151;line-height:2.2;padding-left:20px;">
            <?php if ( FB_Capi_Lang::is_fr() ) : ?>
                <li>Renseignez votre <strong>Pixel ID</strong> et <strong>Access Token</strong> dans l'onglet Réglages</li>
                <li>Configurez votre <strong>Webhook Secret</strong> (copiez-le depuis SureCart)</li>
                <li>Activez les événements souhaités dans l'onglet Événements</li>
                <li>Ajoutez un <strong>code test</strong> pour voir les events dans Meta Events Manager</li>
                <li>Envoyez un test depuis cette page</li>
                <li>Vérifiez dans <strong>Meta → Événements de test</strong></li>
                <li>Configurez le webhook SureCart pour le <strong>Purchase</strong></li>
            <?php else : ?>
                <li>Enter your <strong>Pixel ID</strong> and <strong>Access Token</strong> in the Settings tab</li>
                <li>Configure your <strong>Webhook Secret</strong> (copy it from SureCart)</li>
                <li>Enable the desired events in the Events tab</li>
                <li>Add a <strong>test code</strong> to see events in Meta Events Manager</li>
                <li>Send a test from this page</li>
                <li>Check in <strong>Meta → Test Events</strong></li>
                <li>Configure the SureCart webhook for <strong>Purchase</strong></li>
            <?php endif; ?>
        </ol>
    </div>

</div>
