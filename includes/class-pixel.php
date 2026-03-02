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
        // Anti-duplicate filter: blocks fbq('track') calls without an eventID.
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
                if (args[0] !== 'track' || hasFbId(args)) return realFbq.apply(window, args);
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
        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_capi'] ) || empty( $options['enable_pageview'] ) ) return;
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) return;

        // Re-use the same event_id defined in inject_pixel() for deduplication.
        $event_id = defined( 'FB_CAPI_EVENT_ID' )
            ? FB_CAPI_EVENT_ID
            : ( 'fb_' . bin2hex( random_bytes( 16 ) ) );

        ( new FB_Capi_Sender() )->send( 'PageView', $event_id );
    }

    // ── wp_footer (priority 50) : dynamic JS events ───────────────────────────

    public function inject_js_events(): void {
        $options = FB_Capi_Options::get();
        if ( empty( $options['enable_pixel_js'] ) || empty( $options['pixel_id'] ) ) return;
        if ( is_admin() ) return;

        $enable_vc  = ! empty( $options['enable_viewcontent'] );
        $enable_atc = ! empty( $options['enable_addtocart'] );
        $enable_ic  = ! empty( $options['enable_initiatecheckout'] );
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
                var selectors = '.sc-product-page, [data-sc-product], .surecart-product, sc-product, sc-product-item-list';
                if (document.querySelector(selectors)) {
                    var vid = genId();
                    fbq('track', 'ViewContent', {}, { eventID: vid });
                    sendServer('ViewContent', vid);
                }
            })();
            <?php endif; ?>

            <?php if ( $enable_atc ) : ?>
            // ── AddToCart ────────────────────────────────────────────────────
            document.addEventListener('click', function (e) {
                var btn = e.target.closest(
                    '.sc-add-to-cart, [data-sc-add-to-cart], .surecart-add-to-cart, sc-product-buy-button, .sc-buy-button, button[type="submit"]'
                );
                if (!btn) return;
                var inSC = btn.closest(
                    '.sc-product-page, [data-sc-product], .surecart-product, sc-product, sc-checkout, .sc-checkout-form'
                );
                if (!inSC) return;
                var aid = genId();
                fbq('track', 'AddToCart', {}, { eventID: aid });
                sendServer('AddToCart', aid);
            });
            <?php endif; ?>

            <?php if ( $enable_ic ) : ?>
            // ── InitiateCheckout ─────────────────────────────────────────────
            var icDone = false;
            var icObs  = null;
            var icTimers = [];

            function fireIC() {
                if (icDone) return;
                icDone = true;
                // Cleanup: stop observer and pending timers immediately.
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
                    fireIC(); // ultimate fallback
                }
            }
            checkIC();
            // Only install the observer and timers if IC hasn't already fired.
            if (!icDone) {
                icObs = new MutationObserver(function () { checkIC(); });
                icObs.observe(document.body, { childList: true, subtree: true });
                icTimers = [500, 1000, 2000, 3500, 5000].map(function (t) {
                    return setTimeout(checkIC, t);
                });
            }
            <?php endif; ?>

        })();
        </script>
        <?php
    }
}
