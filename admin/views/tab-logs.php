<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-logs" class="fbc-panel">

    <!-- ── Logs settings ─────────────────────────────────────────────────── -->
    <div class="fbc-card">
        <div class="fbc-card-title">⚙️ Paramètres des logs</div>

        <div class="fbc-toggle-row">
            <div class="fbc-toggle-info">
                <strong>Activer les logs</strong>
                <span>Enregistrer les événements envoyés via la Conversions API</span>
            </div>
            <label class="fbc-switch">
                <input type="checkbox" name="logs_enabled"
                       <?php checked( $options['logs_enabled'] ?? 1 ); ?> />
                <span class="fbc-slider"></span>
            </label>
        </div>

        <div class="fbc-field" style="margin-top:14px;">
            <label>Rétention des logs</label>
            <select name="logs_retention_days" class="fbc-select">
                <?php
                $retention_options = [
                    1  => '1 jour',
                    3  => '3 jours',
                    7  => '7 jours',
                    14 => '14 jours',
                    30 => '30 jours',
                    60 => '60 jours',
                    90 => '90 jours',
                    0  => '♾️ Illimité (non recommandé)',
                ];
                $current_retention = (int) ( $options['logs_retention_days'] ?? 30 );
                foreach ( $retention_options as $days => $label ) :
                ?>
                    <option value="<?php echo esc_attr( $days ); ?>"
                            <?php selected( $current_retention, $days ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="padding:14px 0 0;">
            <strong style="font-size:13px;color:#111827;display:block;margin-bottom:10px;">
                Événements à logger
            </strong>
            <span style="font-size:11px;color:#9ca3af;display:block;margin-bottom:8px;">
                Sélectionnez les événements dont vous souhaitez enregistrer les logs
            </span>
            <div class="fbc-logs-events-grid">
                <?php
                $log_events_config = [
                    'PageView'         => '👁️ PageView',
                    'ViewContent'      => '📄 ViewContent',
                    'AddToCart'        => '🛒 AddToCart',
                    'InitiateCheckout' => '💳 InitiateCheckout',
                    'AddPaymentInfo'   => '💰 AddPaymentInfo',
                    'Purchase'         => '✅ Purchase',
                ];
                $logs_events = $options['logs_events'] ?? [];
                foreach ( $log_events_config as $ev_key => $ev_label ) :
                    $ev_checked = ! empty( $logs_events[ $ev_key ] );
                ?>
                    <label class="fbc-logs-event-item">
                        <input type="checkbox"
                               name="logs_events[<?php echo esc_attr( $ev_key ); ?>]"
                               value="1"
                               <?php checked( $ev_checked ); ?> />
                        <?php echo esc_html( $ev_label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="fbc-actions">
            <button type="submit" name="fb_capi_save_logs_settings" class="fbc-btn fbc-btn-primary">
                💾 Enregistrer les paramètres
            </button>
        </div>
    </div>

    <!-- ── Log viewer ─────────────────────────────────────────────────────── -->
    <div class="fbc-card">
        <div class="fbc-card-title" style="display:flex;align-items:center;justify-content:space-between;">
            <span>📋 Visualisation des logs</span>
            <button type="button" id="fbc-refresh-btn" onclick="fbcRefreshLogs()" class="fbc-btn fbc-btn-outline" style="font-size:12px;padding:6px 14px;">
                <span id="fbc-refresh-icon" style="display:inline-block;transition:transform 0.5s;">🔄</span>
                Rafraîchir
            </button>
        </div>

        <div id="fbc-logs-container">
            <?php include FB_CAPI_DIR . 'admin/views/logs-table.php'; ?>
        </div>
    </div>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_clear_logs" class="fbc-btn fbc-btn-danger"
                onclick="return confirm('Effacer tous les logs ?');">
            🗑️ Effacer les logs
        </button>
    </div>

</div>
