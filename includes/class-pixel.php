<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles all front-end pixel and JavaScript event injection.
 */
class FB_Capi_Pixel {

    public function __construct() {
        // Priority 2 : defines FB_CAPI_EVENT_ID before send_server_pageview (priority 5)
        // and inject_js_events (priority 50) — but runs in footer, not head,
        // so it never blocks HTML parsing (better LCP / TBT scores).
        add_action( 'wp_footer', [ $this, 'inject_pixel' ],          2  );
        add_action( 'wp_footer', [ $this, 'send_server_pageview' ],   5  );
        add_action( 'wp_footer', [ $this, 'inject_js_events' ],      50 );
    }

    // ── wp_footer (priority 2) : Pixel base code + PageView ──────────────────

    public function inject_pixel(): void {
        if ( is_admin() ) return;

        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_pixel_js'] ) || empty( $options['pixel_id'] ) ) return;

        // Define the shared event_id only when the pixel JS is actually injected,
        // so send_server_pageview() can reuse the same ID for deduplication.
        $event_id = 'fb_' . bin2hex( random_bytes( 16 ) );
        if ( ! defined( 'FB_CAPI_EVENT_ID' ) ) {
            define( 'FB_CAPI_EVENT_ID', $event_id );
        }

        $pixel_id = esc_js( $options['pixel_id'] );
        $event_id = esc_js( $event_id );
        ?>
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window,document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo $pixel_id; ?>');
        <?php if ( ! empty( $options['enable_pageview'] ) ) : ?>
        fbq('track', 'PageView', {}, {eventID: '<?php echo $event_id; ?>'});
        <?php endif; ?>
        // Filtre anti-doublon : bloque les fbq('track') sans eventID (ex : pixel natif SureCart).
        // Correction : après chargement du SDK, window.fbq.callMethod est défini sur notre wrapper —
        // on l'utilise directement au lieu du stub d'origine qui ne le possède pas.
        (function(){
            var realFbq = fbq;
            function hasFbId(args) {
                for (var i = 2; i < args.length; i++) {
                    if (args[i] && typeof args[i] === 'object' && args[i].eventID) return true;
                }
                return false;
            }
            window.fbq = function() {
                var args = [].slice.call(arguments);
                if (args[0] !== 'track' || hasFbId(args)) {
                    if (typeof window.fbq.callMethod === 'function') {
                        window.fbq.callMethod.apply(window.fbq, args);
                    } else {
                        realFbq.apply(window, args);
                    }
                }
            };
            window.fbq.queue   = realFbq.queue;
            window.fbq.loaded  = realFbq.loaded;
            window.fbq.version = realFbq.version;
            window.fbq.push    = window.fbq;
            window._fbq        = window.fbq;
        })();
        </script>
        <?php
    }

    // ── wp_footer (priority 5) : server-side PageView ─────────────────────────

    public function send_server_pageview(): void {
        static $sent = false;
        if ( $sent ) return;
        $sent = true;

        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_capi'] ) || empty( $options['enable_pageview'] ) ) return;
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) return;

        // Ignore les requêtes de préchargement navigateur (prefetch/prerender).
        // Chrome/Edge envoient Sec-Purpose: prefetch — pas une vraie visite utilisateur.
        $sec_purpose = strtolower( $_SERVER['HTTP_SEC_PURPOSE'] ?? '' );
        $purpose     = strtolower( $_SERVER['HTTP_PURPOSE']     ?? '' );
        if ( strpos( $sec_purpose, 'prefetch' ) !== false || strpos( $purpose, 'prefetch' ) !== false ) return;

        // WooCommerce frontend AJAX (cart fragments, etc.) — non capturé par wp_doing_ajax().
        if ( isset( $_GET['wc-ajax'] ) ) return;

        // Requête XHR/fetch (jQuery $.ajax, fetch API) — pas une navigation réelle.
        if ( strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' ) === 'xmlhttprequest' ) return;

        // Re-use the same event_id defined in inject_pixel() for deduplication.
        $event_id = defined( 'FB_CAPI_EVENT_ID' )
            ? FB_CAPI_EVENT_ID
            : ( 'fb_' . bin2hex( random_bytes( 16 ) ) );

        // PHP-FPM: flush the response to the browser first, then send blocking (real log).
        // Other environments: non-blocking fallback (no latency, but no real response in logs).
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            add_action( 'shutdown', static function () use ( $event_id ) {
                fastcgi_finish_request();
                ( new FB_Capi_Sender() )->send( 'PageView', $event_id, [], '', '', true );
            } );
        } else {
            ( new FB_Capi_Sender() )->send( 'PageView', $event_id, [], '', '', false );
        }
    }

    // ── wp_footer (priority 50) : dynamic JS events ───────────────────────────

    public function inject_js_events(): void {
        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_pixel_js'] ) || empty( $options['pixel_id'] ) ) return;
        if ( is_admin() ) return;

        $platform   = $options['platform'] ?? 'none';
        $enable_vc  = ! empty( $options['enable_viewcontent'] )      && $platform !== 'none';
        $enable_atc = ! empty( $options['enable_addtocart'] )        && $platform !== 'none';
        $enable_ic  = ! empty( $options['enable_initiatecheckout'] ) && $platform !== 'none';

        // Évite rest_url() + wp_create_nonce() si aucun événement e-commerce n'est actif.
        if ( ! $enable_vc && ! $enable_atc && ! $enable_ic ) return;

        $capi_url   = esc_url( rest_url( 'fb-capi/v1/event' ) );
        $nonce      = wp_create_nonce( 'wp_rest' );
        ?>
        <script>
        (function () {
            function genId() {
                return 'fb_' + Array.from(crypto.getRandomValues(new Uint8Array(16)))
                    .map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
            }

            function sendServer(name, id, data) {
                fetch('<?php echo $capi_url; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce':   '<?php echo $nonce; ?>',
                    },
                    body: JSON.stringify({
                        event_name:  name,
                        event_id:    id,
                        custom_data: data || {},
                        source_url:  location.href,
                    }),
                    keepalive: true,
                }).catch(function () {});
            }

                <?php if ( $enable_vc ) : ?>
            // ── ViewContent ──────────────────────────────────────────────────
            (function () {
                <?php if ( $platform === 'woocommerce' ) : ?>
                var cartForm = document.querySelector('body.single-product form.cart');
                if (!cartForm) return;
                var pInput = cartForm.querySelector('input[name="product_id"]') ||
                             cartForm.querySelector('input[name="add-to-cart"]');
                var contentId = pInput ? pInput.value : '';
                <?php else : // surecart ?>
                var scEl = document.querySelector('sc-product, sc-product-item-list, .sc-product-page, [data-sc-product]');
                if (!scEl) return;
                var contentId = scEl.getAttribute('product-id') || scEl.getAttribute('data-sc-product-id') || '';
                <?php endif; ?>
                var vid = genId();
                var vData = { content_type: 'product' };
                if (contentId) vData.content_ids = [contentId];
                fbq('track', 'ViewContent', vData, { eventID: vid });
                sendServer('ViewContent', vid, vData);
            })();
            <?php endif; ?>

            <?php if ( $enable_atc ) : ?>
            // ── AddToCart ────────────────────────────────────────────────────
            document.addEventListener('click', function (e) {
                <?php if ( $platform === 'woocommerce' ) : ?>
                var btn = e.target.closest('.single_add_to_cart_button, .add_to_cart_button');
                if (!btn) return;
                var form = btn.closest('form.cart');
                var contentId = '';
                if (form) {
                    var pInput = form.querySelector('input[name="product_id"]') ||
                                 form.querySelector('input[name="add-to-cart"]');
                    if (pInput) contentId = pInput.value;
                }
                <?php else : // surecart ?>
                var btn = e.target.closest(
                    '.sc-add-to-cart, [data-sc-add-to-cart], .surecart-add-to-cart, sc-product-buy-button, .sc-buy-button, button[type="submit"]'
                );
                if (!btn) return;
                var inSC = btn.closest(
                    '.sc-product-page, [data-sc-product], .surecart-product, sc-product, sc-checkout, .sc-checkout-form'
                );
                if (!inSC) return;
                var contentId = '';
                <?php endif; ?>
                var aid = genId();
                var aData = { content_type: 'product' };
                if (contentId) aData.content_ids = [contentId];
                fbq('track', 'AddToCart', aData, { eventID: aid });
                sendServer('AddToCart', aid, aData);
            });
            <?php endif; ?>

            <?php if ( $enable_ic ) : ?>
            // ── InitiateCheckout ─────────────────────────────────────────────
            <?php if ( $platform === 'woocommerce' ) : ?>
            (function () {
                if (document.querySelector('body.woocommerce-checkout, .woocommerce-checkout form.checkout')) {
                    var iid = genId();
                    fbq('track', 'InitiateCheckout', {}, { eventID: iid });
                    sendServer('InitiateCheckout', iid);
                }
            })();
            <?php else : // surecart — async web components need observer + timers ?>
            var icDone = false;
            var icObs  = null;
            var icTimers = [];

            function fireIC() {
                if (icDone) return;
                icDone = true;
                if (icObs) { icObs.disconnect(); icObs = null; }
                icTimers.forEach(clearTimeout);
                var iid = genId();
                fbq('track', 'InitiateCheckout', {}, { eventID: iid });
                sendServer('InitiateCheckout', iid);
            }
            function checkIC() {
                if (icDone) return;
                if (document.querySelector('sc-checkout, sc-checkout-form, .sc-checkout-form, sc-order-confirmation, [data-sc-checkout]')) {
                    fireIC(); return;
                }
                var path = location.pathname.toLowerCase();
                if (path.indexOf('checkout') !== -1 || path.indexOf('commande') !== -1 || path.indexOf('paiement') !== -1) {
                    if (document.querySelector('[class*="surecart"], [class*="sc-"], sc-line-items, sc-order-summary, sc-payment')) {
                        fireIC(); return;
                    }
                    fireIC();
                }
            }
            checkIC();
            if (!icDone) {
                icObs = new MutationObserver(function () { checkIC(); });
                icObs.observe(document.body, { childList: true, subtree: true });
                icTimers = [500, 1000, 2000, 3500, 5000].map(function (t) {
                    return setTimeout(checkIC, t);
                });
            }
            <?php endif; ?>
            <?php endif; ?>

        })();
        </script>
        <?php
    }
}
