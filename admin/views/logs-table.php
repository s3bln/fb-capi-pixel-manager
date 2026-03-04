<?php
/**
 * Partial view: log table content.
 * Used both on initial page load (tab-logs.php) and via AJAX refresh.
 *
 * Expected variables: $logs, $success (count), $errors (count), $total (count).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Compute counts if not already provided (direct include context).
if ( ! isset( $total ) ) {
    $total   = count( $logs );
    $success = count( array_filter( $logs, fn( $l ) => ( $l['status'] ?? '' ) === 'success' ) );
    $errors  = $total - $success;
}
?>

<?php if ( empty( $logs ) ) : ?>
    <div class="fbc-alert info"><?php echo fbc_t( '📭 No logs recorded yet.' ); ?></div>
<?php else : ?>

    <div style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">
        <span class="fbc-badge success" style="font-size:13px;padding:6px 14px;">✅ <?php echo $success; ?> <?php echo fbc_t( 'successful' ); ?></span>
        <span class="fbc-badge error"   style="font-size:13px;padding:6px 14px;">❌ <?php echo $errors; ?> <?php echo fbc_t( 'errors' ); ?></span>
        <span style="font-size:13px;padding:6px 14px;background:#f3f4f6;border-radius:8px;color:#6b7280;">📊 <?php echo $total; ?> total</span>
    </div>

    <div style="margin-bottom:12px;">
        <select id="fbc-log-filter" onchange="fbcFilterLogs(this.value)"
                style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;">
            <option value="all"><?php echo fbc_t( 'All events' ); ?></option>
            <option value="PageView">PageView</option>
            <option value="ViewContent">ViewContent</option>
            <option value="AddToCart">AddToCart</option>
            <option value="InitiateCheckout">InitiateCheckout</option>
            <option value="AddPaymentInfo">AddPaymentInfo</option>
            <option value="Purchase">Purchase</option>
            <option value="error"><?php echo fbc_t( '❌ Errors only' ); ?></option>
        </select>
    </div>

    <div style="max-height:500px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:10px;">
        <table class="fbc-logs-table" id="fbc-logs-table">
            <thead>
                <tr>
                    <th><?php echo fbc_t( 'Event' ); ?></th>
                    <th>Event ID</th>
                    <th><?php echo fbc_t( 'Status' ); ?></th>
                    <th><?php echo fbc_t( 'Response' ); ?></th>
                    <th><?php echo fbc_t( 'Date' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( array_slice( $logs, 0, 50 ) as $log ) :
                $is_success = ( $log['status'] ?? '' ) === 'success';
            ?>
                <tr data-event="<?php echo esc_attr( $log['event'] ?? '' ); ?>"
                    data-status="<?php echo esc_attr( $log['status'] ?? '' ); ?>">
                    <td>
                        <span class="fbc-badge event">
                            <?php echo esc_html( $log['event'] ?? '' ); ?>
                        </span>
                    </td>
                    <td style="font-family:monospace;font-size:11px;color:#6b7280;">
                        <?php echo esc_html( substr( $log['event_id'] ?? '', 0, 24 ) ); ?>…
                    </td>
                    <td>
                        <span class="fbc-badge <?php echo $is_success ? 'success' : 'error'; ?>">
                            <?php echo $is_success ? '✅ OK' : fbc_t( '❌ Error' ); ?>
                        </span>
                    </td>
                    <td style="font-size:11px;max-width:260px;">
                        <?php if ( ! $is_success && ! empty( $log['response'] ) ) : ?>
                            <span style="color:#dc2626;word-break:break-word;">
                                <?php echo esc_html( substr( $log['response'], 0, 120 ) ); ?>
                            </span>
                        <?php else : ?>
                            <span style="color:#d1d5db;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:12px;">
                        <?php echo esc_html( $log['time'] ?? '' ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
