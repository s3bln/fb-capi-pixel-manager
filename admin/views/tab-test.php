<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-test" class="fbc-panel">

    <div class="fbc-alert info">
        ⚡ Le test envoie un événement <strong>PageView</strong> via la Conversions API.
        Vérifiez ensuite dans <strong>Meta → Gestionnaire d'événements → Événements de test</strong>.
    </div>

    <?php if ( $test_result ) : ?>
        <div class="fbc-alert <?php echo $test_result['status'] === 'success' ? 'success' : 'warning'; ?>">
            <?php echo esc_html( $test_result['message'] ); ?>
        </div>
        <?php if ( ! empty( $test_result['response'] ) ) : ?>
            <div class="fbc-card">
                <div class="fbc-card-title">📄 Réponse API</div>
                <pre class="fbc-pre"><?php echo esc_html( $test_result['response'] ); ?></pre>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="fbc-card">
        <div class="fbc-card-title">🧪 Envoyer un événement test</div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">
            Cliquez sur le bouton pour envoyer un PageView test via la Conversions API.
        </p>
        <button type="submit" name="fb_capi_test" class="fbc-btn fbc-btn-primary">
            🚀 Envoyer un test CAPI
        </button>
    </div>

    <div class="fbc-card">
        <div class="fbc-card-title">📖 Guide de configuration</div>
        <ol style="font-size:13px;color:#374151;line-height:2.2;padding-left:20px;">
            <li>Renseignez votre <strong>Pixel ID</strong> et <strong>Access Token</strong> dans l'onglet Réglages</li>
            <li>Configurez votre <strong>Webhook Secret</strong> (copiez-le depuis SureCart)</li>
            <li>Activez les événements souhaités dans l'onglet Événements</li>
            <li>Ajoutez un <strong>code test</strong> pour voir les events dans Meta Events Manager</li>
            <li>Envoyez un test depuis cette page</li>
            <li>Vérifiez dans <strong>Meta → Événements de test</strong></li>
            <li>Configurez le webhook SureCart pour le <strong>Purchase</strong></li>
        </ol>
    </div>

</div>
