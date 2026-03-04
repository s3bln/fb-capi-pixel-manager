<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles all server-side communication with the Facebook Conversions API
 * and manages the log file.
 */
class FB_Capi_Sender {

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Send an event to Facebook CAPI.
     *
     * @param string $event_name  Standard FB event name (e.g. 'Purchase').
     * @param string $event_id    Unique event ID shared with the browser pixel for deduplication.
     * @param array  $custom_data Optional event-specific data (value, currency, …).
     * @param string $source_url  Page URL where the event occurred.
     * @param string $email       Raw customer email — will be hashed before sending.
     */
    public function send(
        string $event_name,
        string $event_id,
        array  $custom_data   = [],
        string $source_url    = '',
        string $email         = '',
        bool   $blocking      = true,
        array  $raw_user_data = []
    ): void {
        $options = FB_Capi_Options::get();

        if ( empty( $options['pixel_id'] ) || empty( $options['access_token'] ) ) {
            error_log( '[FB CAPI] ❌ send: pixel_id ou access_token manquant' );
            return;
        }

        error_log( '[FB CAPI] 📡 send: ' . $event_name . ' / ' . $event_id );

        $user_data = [
            'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        if ( ! empty( $email ) ) {
            $user_data['em'] = [ hash( 'sha256', strtolower( trim( $email ) ) ) ];
        }

        // Advanced matching: normalize + hash additional user fields.
        $field_rules = [
            'phone'      => [ 'key' => 'ph',      'fn' => fn( $v ) => preg_replace( '/[^\d+]/', '', $v ) ],
            'first_name' => [ 'key' => 'fn',      'fn' => fn( $v ) => strtolower( trim( $v ) ) ],
            'last_name'  => [ 'key' => 'ln',      'fn' => fn( $v ) => strtolower( trim( $v ) ) ],
            'city'       => [ 'key' => 'ct',      'fn' => fn( $v ) => strtolower( trim( $v ) ) ],
            'state'      => [ 'key' => 'st',      'fn' => fn( $v ) => strtolower( trim( $v ) ) ],
            'zip'        => [ 'key' => 'zp',      'fn' => fn( $v ) => preg_replace( '/\s+/', '', $v ) ],
            'country'    => [ 'key' => 'country', 'fn' => fn( $v ) => strtolower( trim( $v ) ) ],
        ];
        foreach ( $field_rules as $input_key => $rule ) {
            if ( empty( $raw_user_data[ $input_key ] ) ) continue;
            $normalized = ( $rule['fn'] )( (string) $raw_user_data[ $input_key ] );
            if ( $normalized !== '' ) {
                $user_data[ $rule['key'] ] = [ hash( 'sha256', $normalized ) ];
            }
        }

        if ( ! empty( $_COOKIE['_fbp'] ) ) {
            $user_data['fbp'] = sanitize_text_field( $_COOKIE['_fbp'] );
        }
        if ( ! empty( $_COOKIE['_fbc'] ) ) {
            $user_data['fbc'] = sanitize_text_field( $_COOKIE['_fbc'] );
        }

        $event = [
            'event_name'       => $event_name,
            'event_time'       => time(),
            'event_id'         => $event_id,
            'action_source'    => 'website',
            'event_source_url' => ! empty( $source_url )
                ? $source_url
                : home_url( $_SERVER['REQUEST_URI'] ?? '/' ),
            'user_data'        => $user_data,
        ];

        if ( ! empty( $custom_data ) ) {
            $event['custom_data'] = $custom_data;
        }

        $payload = [ 'data' => [ $event ] ];
        if ( ! empty( $options['test_event_code'] ) ) {
            $payload['test_event_code'] = $options['test_event_code'];
        }

        $response = wp_remote_post( $this->api_url( $options ), [
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
            'timeout'  => 15,
            'blocking' => $blocking,
        ] );

        // Non-blocking: WordPress n'attend pas la réponse de Facebook.
        // On peut quand même détecter un échec local (DNS, SSL, connexion refusée).
        if ( ! $blocking ) {
            if ( is_wp_error( $response ) ) {
                $this->write_log( $event_name, $event_id, 'error', '[async] ' . $response->get_error_message(), $options );
            } else {
                $this->write_log( $event_name, $event_id, 'success', '(async)', $options );
            }
            return;
        }

        if ( is_wp_error( $response ) ) {
            $status        = 'error';
            $response_text = $response->get_error_message();
            error_log( '[FB CAPI] ❌ WP_Error: ' . $response_text );
        } else {
            $response_text = wp_remote_retrieve_body( $response );
            $decoded       = json_decode( $response_text, true );
            $status        = isset( $decoded['error'] ) ? 'error' : 'success';
            error_log( '[FB CAPI] ✅ Réponse: ' . $response_text );
        }

        $this->write_log( $event_name, $event_id, $status, $response_text, $options );
    }

    /**
     * Send a test PageView event and return the result for display.
     */
    public function send_test(): array {
        $options = FB_Capi_Options::get();

        if ( empty( $options['pixel_id'] ) || empty( $options['access_token'] ) ) {
            return [
                'status'   => 'error',
                'message'  => 'Pixel ID ou Access Token manquant.',
                'response' => '',
            ];
        }

        $event_id = 'test_' . bin2hex( random_bytes( 8 ) );
        $payload  = [
            'data' => [[
                'event_name'       => 'PageView',
                'event_time'       => time(),
                'event_id'         => $event_id,
                'action_source'    => 'website',
                'event_source_url' => home_url(),
                'user_data'        => [
                    'client_ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Test',
                ],
            ]],
        ];
        if ( ! empty( $options['test_event_code'] ) ) {
            $payload['test_event_code'] = $options['test_event_code'];
        }

        $response = wp_remote_post( $this->api_url( $options ), [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'status'   => 'error',
                'message'  => $response->get_error_message(),
                'response' => '',
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        return [
            'status'   => ( $code === 200 ) ? 'success' : 'error',
            'message'  => ( $code === 200 )
                ? "✅ Test envoyé ! Event ID: {$event_id}"
                : "Erreur HTTP {$code}",
            'response' => $body,
        ];
    }

    /**
     * Read log entries from the log file, newest first, filtered by retention.
     *
     * @return array  Each entry: [ time, status, event, event_id, response ]
     */
    public function read_logs(): array {
        $options  = FB_Capi_Options::get();
        $log_file = $this->log_file_path();
        $logs     = [];

        if ( ! file_exists( $log_file ) ) {
            return $logs;
        }

        $lines          = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        $retention_days = (int) ( $options['logs_retention_days'] ?? 30 );
        $cutoff         = ( $retention_days > 0 ) ? time() - ( $retention_days * 86400 ) : 0;

        foreach ( (array) $lines as $line ) {
            // Format: [dd/mm/YYYY HH:ii:ss] STATUS | EventName | ID: xxx | response
            if ( ! preg_match(
                '/^\[(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2})\]\s+(SUCCESS|ERROR)\s+\|\s+(\S+)\s+\|\s+ID:\s+(\S+)\s+\|\s*(.*)$/',
                $line, $m
            ) ) {
                continue;
            }

            $log_time = \DateTime::createFromFormat( 'd/m/Y H:i:s', $m[1] );
            if ( ! $log_time ) continue;
            if ( $cutoff > 0 && $log_time->getTimestamp() < $cutoff ) continue;

            $logs[] = [
                'time'     => $log_time->format( 'Y-m-d H:i:s' ),
                'status'   => strtolower( $m[2] ),
                'event'    => $m[3],
                'event_id' => $m[4],
                'response' => $m[5],
            ];
        }

        return array_reverse( $logs ); // newest first
    }

    /**
     * Erase the log file content.
     */
    public function clear_logs(): void {
        $log_file = $this->log_file_path();
        if ( file_exists( $log_file ) ) {
            file_put_contents( $log_file, '', LOCK_EX );
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function api_url( array $options ): string {
        return sprintf(
            'https://graph.facebook.com/%s/%s/events?access_token=%s',
            FB_CAPI_GRAPH_VERSION,
            $options['pixel_id'],
            $options['access_token']
        );
    }

    private function log_file_path(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/fb-capi-logs/capi-log.txt';
    }

    private function write_log(
        string $event_name,
        string $event_id,
        string $status,
        string $response_text,
        array  $options
    ): void {
        if ( empty( $options['logs_enabled'] ) ) return;

        // Per-event filter
        $logs_events = $options['logs_events'] ?? [];
        if ( is_array( $logs_events ) && ! empty( $logs_events ) ) {
            if ( empty( $logs_events[ $event_name ] ) ) return;
        }

        $upload_dir = wp_upload_dir();
        $log_dir    = $upload_dir['basedir'] . '/fb-capi-logs';
        $log_file   = $log_dir . '/capi-log.txt';

        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
            file_put_contents( $log_dir . '/index.php', '<?php // Silence' );
        }

        $line = sprintf(
            "[%s] %s | %s | ID: %s | %s\n",
            current_time( 'd/m/Y H:i:s' ),
            strtoupper( $status ),
            $event_name,
            $event_id,
            mb_substr( $response_text, 0, 300 )
        );

        file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
    }
}
