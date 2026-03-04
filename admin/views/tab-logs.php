<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="panel-logs" class="fbc-panel">

    <!-- ── Logs settings ─────────────────────────────────────────────────── -->
    <div class="fbc-card">
        <div class="fbc-card-title"><?php echo fbc_t( '⚙️ Log Settings' ); ?></div>

        <div class="fbc-toggle-row">
            <div class="fbc-toggle-info">
                <strong><?php echo fbc_t( 'Enable logs' ); ?></strong>
                <span><?php echo fbc_t( 'Record events sent via the Conversions API' ); ?></span>
            </div>
            <label class="fbc-switch">
                <input type="checkbox" name="logs_enabled"
                       <?php checked( $options['logs_enabled'] ?? 1 ); ?> />
                <span class="fbc-slider"></span>
            </label>
        </div>

        <div class="fbc-field" style="margin-top:14px;">
            <label><?php echo fbc_t( 'Log retention' ); ?></label>
            <select name="logs_retention_days" class="fbc-select">
                <?php
                $retention_options = [
                    1  => fbc_t( '1 day' ),
                    3  => fbc_t( '3 days' ),
                    7  => fbc_t( '7 days' ),
                    14 => fbc_t( '14 days' ),
                    30 => fbc_t( '30 days' ),
                    60 => fbc_t( '60 days' ),
                    90 => fbc_t( '90 days' ),
                    0  => fbc_t( '♾️ Unlimited (not recommended)' ),
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
                <?php echo fbc_t( 'Events to log' ); ?>
            </strong>
            <span style="font-size:11px;color:#9ca3af;display:block;margin-bottom:8px;">
                <?php echo fbc_t( 'Select events to log' ); ?>
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
                <?php echo fbc_t( '💾 Save settings' ); ?>
            </button>
        </div>
    </div>

    <!-- ── Log viewer ─────────────────────────────────────────────────────── -->
    <div class="fbc-card">
        <div class="fbc-card-title" style="display:flex;align-items:center;justify-content:space-between;">
            <span><?php echo fbc_t( '📋 Log viewer' ); ?></span>
            <button type="button" id="fbc-refresh-btn" onclick="fbcRefreshLogs()" class="fbc-btn fbc-btn-outline" style="font-size:12px;padding:6px 14px;">
                <span id="fbc-refresh-icon" style="display:inline-block;transition:transform 0.5s;">🔄</span>
                <?php echo fbc_t( 'Refresh' ); ?>
            </button>
        </div>

        <div id="fbc-logs-container">
            <?php include FB_CAPI_DIR . 'admin/views/logs-table.php'; ?>
        </div>
    </div>

    <div class="fbc-actions">
        <button type="submit" name="fb_capi_clear_logs" class="fbc-btn fbc-btn-danger"
                onclick="return confirm('<?php echo esc_js( fbc_t( 'Clear all logs?' ) ); ?>');">
            <?php echo fbc_t( '🗑️ Clear logs' ); ?>
        </button>
    </div>

</div>
