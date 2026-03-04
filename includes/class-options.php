<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manages plugin options: defaults, sanitization schema, and helpers.
 */
class FB_Capi_Options {

    public static function defaults(): array {
        return [
            'pixel_id'                => '',
            'access_token'            => '',
            'test_event_code'         => '',
            'webhook_secret'          => '',
            'enable_pixel_js'         => 0,
            'enable_capi'             => 0,
            'enable_pageview'         => 0,
            'enable_viewcontent'      => 0,
            'enable_addtocart'        => 0,
            'enable_initiatecheckout' => 0,
            'enable_purchase'         => 0,
            'enable_addpaymentinfo'   => 0,
            'logs_enabled'            => 1,
            'logs_events'             => [],
            'logs_retention_days'     => 30,
            'platform'                => 'none',
        ];
    }

    public static function schema(): array {
        return [
            'pixel_id'                => [ 'type' => 'numeric_string', 'max_length' => 20 ],
            'access_token'            => [ 'type' => 'token_string',   'max_length' => 300 ],
            'test_event_code'         => [ 'type' => 'alnum_string',   'max_length' => 20 ],
            'webhook_secret'          => [ 'type' => 'token_string',   'max_length' => 200 ],
            'enable_pixel_js'         => [ 'type' => 'boolean' ],
            'enable_capi'             => [ 'type' => 'boolean' ],
            'enable_pageview'         => [ 'type' => 'boolean' ],
            'enable_viewcontent'      => [ 'type' => 'boolean' ],
            'enable_addtocart'        => [ 'type' => 'boolean' ],
            'enable_initiatecheckout' => [ 'type' => 'boolean' ],
            'enable_purchase'         => [ 'type' => 'boolean' ],
            'enable_addpaymentinfo'   => [ 'type' => 'boolean' ],
            'logs_enabled'            => [ 'type' => 'boolean' ],
            'logs_events'             => [ 'type' => 'array', 'allowed' => [
                'PageView', 'ViewContent', 'AddToCart',
                'InitiateCheckout', 'AddPaymentInfo', 'Purchase',
            ]],
            'logs_retention_days'     => [ 'type' => 'select', 'allowed' => [ 0, 1, 3, 7, 14, 30, 60, 90 ] ],
            'platform'                => [ 'type' => 'select', 'allowed' => [ 'none', 'surecart', 'woocommerce' ] ],
        ];
    }

    /**
     * Sanitize a single option value against its schema rule.
     */
    public static function sanitize_value( string $key, $value ) {
        $schema   = self::schema();
        $defaults = self::defaults();

        if ( ! isset( $schema[ $key ] ) ) {
            return $defaults[ $key ] ?? '';
        }

        $rule = $schema[ $key ];

        switch ( $rule['type'] ) {
            case 'numeric_string':
                $value = sanitize_text_field( $value );
                if ( ! preg_match( '/^\d{0,' . $rule['max_length'] . '}$/', $value ) ) {
                    return $defaults[ $key ];
                }
                return $value;

            case 'alnum_string':
                $value = sanitize_text_field( $value );
                if ( strlen( $value ) > $rule['max_length'] ) {
                    return $defaults[ $key ];
                }
                if ( ! preg_match( '/^[a-zA-Z0-9_\-]*$/', $value ) ) {
                    return $defaults[ $key ];
                }
                return $value;

            case 'token_string':
                $value = sanitize_text_field( $value );
                if ( strlen( $value ) > $rule['max_length'] ) {
                    return $defaults[ $key ];
                }
                // Base64url chars + pipe (app tokens) + equals/slash/plus (standard base64).
                // Facebook CAPI tokens are base64-like and can contain these characters.
                if ( ! preg_match( '/^[a-zA-Z0-9_\-\|\.\/\+=]*$/', $value ) ) {
                    return $defaults[ $key ];
                }
                return $value;

            case 'boolean':
                return (int) (bool) $value;

            case 'array':
                if ( ! is_array( $value ) ) return $defaults[ $key ];
                return array_values( array_intersect( $value, $rule['allowed'] ) );

            case 'select':
                if ( is_numeric( $value ) ) $value = (int) $value;
                return in_array( $value, $rule['allowed'], true ) ? $value : $defaults[ $key ];

            default:
                return $defaults[ $key ];
        }
    }

    /**
     * Sanitize all options at once, preserving sensitive fields when submitted empty.
     */
    public static function sanitize_all( array $raw_input ): array {
        $defaults    = self::defaults();
        $old_options = get_option( 'fb_capi_options', $defaults );
        $sanitized   = [];

        foreach ( $defaults as $key => $default_value ) {
            $raw_value         = $raw_input[ $key ] ?? $default_value;
            $sanitized[ $key ] = self::sanitize_value( $key, $raw_value );
        }

        // Preserve existing secret fields when form submits them empty (masked inputs).
        foreach ( [ 'access_token', 'webhook_secret' ] as $secret_key ) {
            if ( empty( $sanitized[ $secret_key ] ) && ! empty( $old_options[ $secret_key ] ) ) {
                $sanitized[ $secret_key ] = $old_options[ $secret_key ];
            }
        }

        // logs_events is managed exclusively via fb_capi_save_logs_settings.
        // Never overwrite it from the main settings form (its checkboxes use
        // array keys as names, which array_intersect cannot match correctly).
        $sanitized['logs_events'] = $old_options['logs_events'] ?? [];

        return $sanitized;
    }

    /** Cache — invalidé après chaque save via invalidate_cache(). */
    private static ?array $cache = null;

    /**
     * Shorthand: get current options merged with defaults.
     * Mis en cache — vider le cache via invalidate_cache() après update_option().
     */
    public static function get(): array {
        if ( self::$cache === null ) {
            self::$cache = wp_parse_args( get_option( 'fb_capi_options', [] ), self::defaults() );
        }
        return self::$cache;
    }

    /** Vide le cache après une mise à jour des options. */
    public static function invalidate_cache(): void {
        self::$cache = null;
    }
}
