<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="fbc-app">

    <!-- ═══ HEADER ═══ -->
    <div class="fbc-header">
        <div>
            <h1><span>📊</span> FB CAPI & Pixel Manager</h1>
            <p>Gestionnaire Facebook Pixel &amp; Conversions API pour SureCart</p>
        </div>
        <div class="fbc-status-badge">
            <?php echo $is_configured ? '🟢 Connecté' : '🔴 Non configuré'; ?>
        </div>
    </div>

    <?php echo $notice; ?>

    <!-- ═══ STATS ═══ -->
    <div class="fbc-stats">
        <div class="fbc-stat">
            <div class="fbc-stat-icon purple">📡</div>
            <div>
                <span class="fbc-stat-num"><?php echo $total_events; ?></span>
                <span class="fbc-stat-label">Total événements</span>
            </div>
        </div>
        <div class="fbc-stat">
            <div class="fbc-stat-icon orange">⚠️</div>
            <div>
                <span class="fbc-stat-num"><?php echo $error_events; ?></span>
                <span class="fbc-stat-label">Erreurs</span>
            </div>
        </div>
        <div class="fbc-stat">
            <div class="fbc-stat-icon green">🎯</div>
            <div>
                <span class="fbc-stat-num"><?php echo $active_events; ?>/6</span>
                <span class="fbc-stat-label">Événements actifs</span>
            </div>
        </div>
    </div>

    <!-- ═══ PROGRESS ═══ -->
    <div class="fbc-progress">
        <div class="fbc-progress-header">
            <span>✅ Taux de réussite</span>
            <small><?php echo $success_events; ?> réussis sur <?php echo $total_events; ?></small>
        </div>
        <div class="fbc-progress-bar">
            <div class="fbc-progress-fill" style="width: <?php echo $success_rate; ?>%"></div>
        </div>
    </div>

    <!-- ═══ TABS ═══ -->
    <div class="fbc-tabs">
        <button class="fbc-tab active" data-tab="settings" onclick="fbcTab('settings', this)">⚙️ Réglages</button>
        <button class="fbc-tab" data-tab="events"   onclick="fbcTab('events', this)">🎯 Événements</button>
        <button class="fbc-tab" data-tab="logs"     onclick="fbcTab('logs', this)">📋 Logs</button>
        <button class="fbc-tab" data-tab="test"     onclick="fbcTab('test', this)">🧪 Test</button>
    </div>

    <form method="post">
        <?php wp_nonce_field( 'fb_capi_nonce' ); ?>

        <?php include FB_CAPI_DIR . 'admin/views/tab-settings.php'; ?>
        <?php include FB_CAPI_DIR . 'admin/views/tab-events.php'; ?>
        <?php include FB_CAPI_DIR . 'admin/views/tab-logs.php'; ?>
        <?php include FB_CAPI_DIR . 'admin/views/tab-test.php'; ?>
    </form>

</div>

<!-- Restore active tab after form save -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var active = '<?php echo esc_js( $active_tab ); ?>';
    if (active && active !== 'settings') {
        var btn = document.querySelector('[data-tab="' + active + '"]');
        if (btn) btn.click();
    }
});
</script>
