<?php
/**
 * TripCosmos Progressive Web App (PWA) Core Engine
 *
 * Implements high-performance PWA manifest, service worker registration,
 * smart offline fallback for Varanasi travelers, and install prompts.
 *
 * @package TripCosmos_Agents
 * @subpackage PWA
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_PWA {

	const CACHE_VERSION = 'tc-pwa-v1.0.1';

	/**
	 * Initialize PWA subsystem hooks
	 */
	public static function init() {
		// Frontend head tags: manifest, theme-color, apple-touch
		add_action( 'wp_head', array( __CLASS__, 'output_head_tags' ), 2 );

		// Frontend footer: service worker registration & install prompt
		add_action( 'wp_footer', array( __CLASS__, 'output_footer_script' ), 99 );

		// Virtual routes & fallback handler for /manifest.webmanifest, /sw.js, /offline.html
		add_action( 'parse_request', array( __CLASS__, 'handle_virtual_routes' ) );

		// Auto-provision physical static files to ABSPATH if missing or updated
		add_action( 'admin_init', array( __CLASS__, 'maybe_sync_static_files' ) );

		// Filter for query vars
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
	}

	/**
	 * Register custom query vars
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'tc_pwa_route';
		return $vars;
	}

	/**
	 * Serve virtual PWA endpoints if physical files are bypassed
	 */
	public static function handle_virtual_routes( $wp ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = parse_url( $uri, PHP_URL_PATH );

		if ( $path === '/manifest.webmanifest' || $path === '/manifest.json' ) {
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
			header( 'Access-Control-Allow-Origin: *' );
			echo self::get_manifest_json();
			exit;
		}

		if ( $path === '/sw.js' ) {
			header( 'Content-Type: application/javascript; charset=utf-8' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
			header( 'Service-Worker-Allowed: /' );
			header( 'Access-Control-Allow-Origin: *' );
			echo self::get_service_worker_js();
			exit;
		}

		if ( $path === '/offline.html' ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Cache-Control: public, max-age=3600' );
			echo self::get_offline_html();
			exit;
		}
	}

	/**
	 * Output PWA meta tags and link tags into wp_head
	 */
	public static function output_head_tags() {
		$theme_color = '#FD4621'; // Togo primary vermilion
		$bg_color    = '#0B0F19'; // Luxury dark night sky
		$icon_180    = 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-180x180.jpg';
		$icon_192    = 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg';
		$icon_512    = 'https://tripcosmos.co/wp-content/uploads/2026/07/tripcosmos-logo.jpg';
		?>
		<!-- TripCosmos PWA Support -->
		<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.webmanifest' ) ); ?>">
		<meta name="theme-color" content="<?php echo esc_attr( $theme_color ); ?>">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="apple-mobile-web-app-title" content="TripCosmos">
		<meta name="application-name" content="TripCosmos">
		<meta name="msapplication-TileColor" content="<?php echo esc_attr( $bg_color ); ?>">
		<meta name="msapplication-TileImage" content="<?php echo esc_url( $icon_180 ); ?>">
		<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icon_180 ); ?>">
		<link rel="icon" type="image/jpeg" sizes="192x192" href="<?php echo esc_url( $icon_192 ); ?>">
		<link rel="icon" type="image/jpeg" sizes="512x512" href="<?php echo esc_url( $icon_512 ); ?>">
		<?php
	}

	/**
	 * Output Service Worker registration & lightweight Install App prompt in footer
	 */
	public static function output_footer_script() {
		// Never interrupt ad visitors or tour package buyers with a PWA app prompt
		if (
			is_singular('togo_trip') ||
			(function_exists('is_checkout') && is_checkout()) ||
			(function_exists('is_cart') && is_cart()) ||
			isset($_GET['gclid']) ||
			isset($_GET['wbraid']) ||
			isset($_GET['gbraid']) ||
			isset($_GET['utm_source']) ||
			isset($_GET['utm_campaign'])
		) {
			return;
		}
		?>
		<!-- TripCosmos PWA Registration & Install Engine -->
		<div id="tc-pwa-install-prompt" class="tc-pwa-prompt" style="display:none;" aria-live="polite">
			<div class="tc-pwa-prompt-inner">
				<div class="tc-pwa-prompt-icon">
					<img src="https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-180x180.jpg" alt="TripCosmos" width="48" height="48" />
				</div>
				<div class="tc-pwa-prompt-text">
					<strong>TripCosmos App</strong>
					<span>Install for instant offline tour radar &amp; 24x7 Varanasi desk</span>
				</div>
				<div class="tc-pwa-prompt-actions">
					<button type="button" id="tc-pwa-btn-install" class="tc-pwa-btn tc-pwa-btn-primary">Install</button>
					<button type="button" id="tc-pwa-btn-dismiss" class="tc-pwa-btn tc-pwa-btn-close" aria-label="Dismiss">&times;</button>
				</div>
			</div>
		</div>

		<style id="tc-pwa-styles">
			.tc-pwa-prompt {
				position: fixed;
				bottom: 24px;
				left: 50%;
				transform: translateX(-50%) translateY(120%);
				z-index: 999999;
				width: 92%;
				max-width: 440px;
				background: linear-gradient(135deg, rgba(11, 15, 25, 0.98), rgba(20, 27, 45, 0.98));
				border: 1px solid rgba(253, 70, 33, 0.4);
				border-radius: 16px;
				box-shadow: 0 16px 40px rgba(0, 0, 0, 0.6), 0 0 24px rgba(253, 70, 33, 0.2);
				backdrop-filter: blur(12px);
				-webkit-backdrop-filter: blur(12px);
				color: #ffffff;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
				transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease;
				opacity: 0;
			}
			.tc-pwa-prompt.tc-show {
				transform: translateX(-50%) translateY(0);
				opacity: 1;
			}
			.tc-pwa-prompt-inner {
				display: flex;
				align-items: center;
				padding: 12px 14px;
				gap: 12px;
			}
			.tc-pwa-prompt-icon img {
				border-radius: 10px;
				display: block;
				border: 1px solid rgba(255, 255, 255, 0.15);
			}
			.tc-pwa-prompt-text {
				flex: 1;
				min-width: 0;
			}
			.tc-pwa-prompt-text strong {
				display: block;
				font-size: 14px;
				font-weight: 700;
				color: #ffffff;
				letter-spacing: -0.2px;
			}
			.tc-pwa-prompt-text span {
				display: block;
				font-size: 11px;
				color: rgba(255, 255, 255, 0.7);
				line-height: 1.3;
				margin-top: 2px;
			}
			.tc-pwa-prompt-actions {
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.tc-pwa-btn {
				border: none;
				outline: none;
				cursor: pointer;
				transition: all 0.2s ease;
			}
			.tc-pwa-btn-primary {
				background: linear-gradient(135deg, #FD4621, #E03513);
				color: #ffffff;
				font-size: 12px;
				font-weight: 700;
				padding: 8px 14px;
				border-radius: 20px;
				box-shadow: 0 4px 12px rgba(253, 70, 33, 0.35);
			}
			.tc-pwa-btn-primary:hover {
				transform: scale(1.04);
				background: #FD4621;
			}
			.tc-pwa-btn-close {
				background: transparent;
				color: rgba(255, 255, 255, 0.5);
				font-size: 20px;
				line-height: 1;
				padding: 4px 6px;
				border-radius: 50%;
			}
			.tc-pwa-btn-close:hover {
				color: #ffffff;
			}
			@media (max-width: 480px) {
				.tc-pwa-prompt {
					bottom: 16px;
					width: calc(100% - 24px);
				}
			}
		</style>

		<script id="tc-pwa-init">
		(function() {
			if (!('serviceWorker' in navigator)) return;

			// Register Service Worker
			window.addEventListener('load', function() {
				navigator.serviceWorker.register('/sw.js', { scope: '/' })
					.then(function(reg) {
						if (reg.waiting) {
							console.log('[TripCosmos PWA] Update available.');
						}
						reg.addEventListener('updatefound', function() {
							var installingWorker = reg.installing;
							if (!installingWorker) return;
							installingWorker.addEventListener('statechange', function() {
								if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
									console.log('[TripCosmos PWA] New version ready.');
								}
							});
						});
					})
					.catch(function(err) {
						console.warn('[TripCosmos PWA] Registration error:', err);
					});
			});

			// Handle Smart Install Banner
			var deferredPrompt = null;
			var promptEl = document.getElementById('tc-pwa-install-prompt');
			var btnInstall = document.getElementById('tc-pwa-btn-install');
			var btnDismiss = document.getElementById('tc-pwa-btn-dismiss');

			var DISMISSED_KEY = 'tc_pwa_dismissed_until';
			var now = Date.now();
			var dismissedUntil = parseInt(localStorage.getItem(DISMISSED_KEY) || '0', 10);

			window.addEventListener('beforeinstallprompt', function(e) {
				e.preventDefault();
				deferredPrompt = e;

				// Don't show if user dismissed within last 7 days
				if (now < dismissedUntil) return;

				if (promptEl) {
					promptEl.style.display = 'block';
					setTimeout(function() {
						promptEl.classList.add('tc-show');
					}, 1200);
				}
			});

			if (btnInstall) {
				btnInstall.addEventListener('click', function() {
					if (!deferredPrompt) return;
					promptEl.classList.remove('tc-show');
					setTimeout(function() { promptEl.style.display = 'none'; }, 400);
					deferredPrompt.prompt();
					deferredPrompt.userChoice.then(function(choice) {
						if (choice.outcome === 'accepted') {
							console.log('[TripCosmos PWA] User accepted install prompt');
						}
						deferredPrompt = null;
					});
				});
			}

			if (btnDismiss) {
				btnDismiss.addEventListener('click', function() {
					if (promptEl) {
						promptEl.classList.remove('tc-show');
						setTimeout(function() { promptEl.style.display = 'none'; }, 400);
					}
					// Dismiss for 7 days
					localStorage.setItem(DISMISSED_KEY, String(Date.now() + (7 * 86400 * 1000)));
				});
			}

			window.addEventListener('appinstalled', function() {
				console.log('[TripCosmos PWA] App installed successfully');
				if (promptEl) {
					promptEl.classList.remove('tc-show');
					setTimeout(function() { promptEl.style.display = 'none'; }, 400);
				}
				localStorage.setItem(DISMISSED_KEY, String(Date.now() + (30 * 86400 * 1000)));
			});
		})();
		</script>
		<?php
	}

	/**
	 * Build Web App Manifest JSON representation
	 */
	public static function get_manifest_json() {
		$manifest = array(
			'name'             => 'TripCosmos - Luxury Tours & Spiritual Journeys Varanasi',
			'short_name'       => 'TripCosmos',
			'description'      => 'Book verified Varanasi tours, private Ganga sunrise boat rides, temple darshan, and North India luxury journeys with 24x7 local support.',
			'start_url'        => '/?pwa=1',
			'id'               => '/?pwa=1',
			'scope'            => '/',
			'display'          => 'standalone',
			'display_override' => array( 'window-controls-overlay', 'standalone', 'minimal-ui' ),
			'orientation'      => 'any',
			'background_color' => '#0B0F19',
			'theme_color'      => '#FD4621',
			'lang'             => 'en-IN',
			'categories'       => array( 'travel', 'lifestyle', 'navigation' ),
			'icons'            => array(
				array(
					'src'     => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-180x180.jpg',
					'sizes'   => '180x180',
					'type'    => 'image/jpeg',
					'purpose' => 'any',
				),
				array(
					'src'     => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
					'sizes'   => '192x192',
					'type'    => 'image/jpeg',
					'purpose' => 'any',
				),
				array(
					'src'     => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
					'sizes'   => '192x192',
					'type'    => 'image/jpeg',
					'purpose' => 'maskable',
				),
				array(
					'src'     => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-270x270.jpg',
					'sizes'   => '270x270',
					'type'    => 'image/jpeg',
					'purpose' => 'any',
				),
				array(
					'src'     => 'https://tripcosmos.co/wp-content/uploads/2026/07/tripcosmos-logo.jpg',
					'sizes'   => '512x512',
					'type'    => 'image/jpeg',
					'purpose' => 'any',
				),
				array(
					'src'     => 'https://tripcosmos.co/wp-content/uploads/2026/07/tripcosmos-logo.jpg',
					'sizes'   => '512x512',
					'type'    => 'image/jpeg',
					'purpose' => 'maskable',
				),
			),
			'shortcuts'        => array(
				array(
					'name'        => 'Ganga Boat Rides',
					'short_name'  => 'Boat Rides',
					'description' => 'Book private sunrise or evening Ganga Aarti boat rides in Varanasi',
					'url'         => '/?s=boat#booking',
					'icons'       => array(
						array(
							'src'   => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
							'sizes' => '192x192',
						),
					),
				),
				array(
					'name'        => 'Temple Darshan Tours',
					'short_name'  => 'Temple Tours',
					'description' => 'Guided Kashi Vishwanath, Ayodhya & Prayagraj pilgrimage tours',
					'url'         => '/?s=temple',
					'icons'       => array(
						array(
							'src'   => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
							'sizes' => '192x192',
						),
					),
				),
				array(
					'name'        => '24x7 Emergency Desk',
					'short_name'  => 'Emergency',
					'description' => 'Direct WhatsApp hotline with Varanasi field manager',
					'url'         => 'https://wa.me/919336116210?text=Hello%20TripCosmos%2C%20I%20need%20emergency%20assistance%20in%20Varanasi',
					'icons'       => array(
						array(
							'src'   => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
							'sizes' => '192x192',
						),
					),
				),
				array(
					'name'        => 'My Account & Bookings',
					'short_name'  => 'My Bookings',
					'description' => 'View itinerary, driver details, and vouchers',
					'url'         => '/my-account/',
					'icons'       => array(
						array(
							'src'   => 'https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
							'sizes' => '192x192',
						),
					),
				),
			),
		);

		return wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Build Service Worker JS content
	 */
	public static function get_service_worker_js() {
		$ver = self::CACHE_VERSION;
		return <<<JS
/* =========================================================================
   TripCosmos PWA Service Worker (Varanasi Pilgrimage & Luxury Tours)
   Version: {$ver}
   ========================================================================= */

const CACHE_NAME = '{$ver}';
const OFFLINE_FALLBACK_URL = '/offline.html';

// Critical core assets to pre-cache on install
const PRECACHE_ASSETS = [
  '/',
  OFFLINE_FALLBACK_URL,
  '/wp-content/uploads/2026/07/cropped-tripcosmos-logo-180x180.jpg',
  '/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
  '/manifest.webmanifest'
];

// Install: Pre-cache core shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS).catch((err) => {
        console.warn('[TripCosmos SW] Precache warning:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate: Purge obsolete caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Strategy
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Skip non-GET and external domains (except specific CDN assets)
  if (req.method !== 'GET') return;

  // Never cache admin, login, checkout, cart, or internal REST APIs
  if (
    url.pathname.includes('/wp-admin') ||
    url.pathname.includes('/wp-login.php') ||
    url.pathname.includes('/cart') ||
    url.pathname.includes('/checkout') ||
    url.pathname.includes('/wc-api') ||
    url.pathname.includes('/wc-ajax') ||
    url.pathname.includes('/tc-agents/v1')
  ) {
    return;
  }

  // 1. HTML Navigation Requests -> Network First, Fallback to Cache, then Offline Page
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((networkRes) => {
          // If valid response, clone into cache
          if (networkRes && networkRes.status === 200) {
            const copy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return networkRes;
        })
        .catch(async () => {
          // Check if page was previously cached
          const cachedPage = await caches.match(req);
          if (cachedPage) return cachedPage;

          // Otherwise serve branded offline page
          const fallback = await caches.match(OFFLINE_FALLBACK_URL);
          return fallback || new Response('You are offline. Please check your connection.', {
            headers: { 'Content-Type': 'text/plain' }
          });
        })
    );
    return;
  }

  // 2. Static Assets (CSS, JS, Fonts, Images) -> Stale-While-Revalidate
  const isStatic =
    url.pathname.match(/\\.(css|js|woff2?|ttf|otf|eot|svg|png|jpe?g|webp|ico)(\\?.*)?$/i) &&
    url.origin === self.location.origin;

  if (isStatic) {
    event.respondWith(
      caches.match(req).then((cachedRes) => {
        const fetchPromise = fetch(req)
          .then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              const copy = networkRes.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
            }
            return networkRes;
          })
          .catch(() => cachedRes);

        return cachedRes || fetchPromise;
      })
    );
    return;
  }
});
JS;
	}

	/**
	 * Build Offline Fallback HTML page
	 */
	public static function get_offline_html() {
		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <title>Exploring Offline | TripCosmos Varanasi</title>
  <meta name="theme-color" content="#0B0F19" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background-color: #0B0F19;
      color: #F8FAFC;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
      text-align: center;
    }
    .tc-card {
      background: linear-gradient(180deg, rgba(20, 27, 45, 0.95), rgba(11, 15, 25, 0.95));
      border: 1px solid rgba(253, 70, 33, 0.35);
      border-radius: 20px;
      padding: 32px 24px;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6), 0 0 30px rgba(253, 70, 33, 0.15);
    }
    .tc-logo-wrap {
      width: 76px;
      height: 76px;
      margin: 0 auto 20px;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(253, 70, 33, 0.3);
      border: 2px solid rgba(253, 70, 33, 0.6);
    }
    .tc-logo-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
    h1 {
      font-size: 22px;
      font-weight: 800;
      color: #FFFFFF;
      letter-spacing: -0.3px;
      margin-bottom: 8px;
    }
    .tc-subtitle {
      font-size: 13px;
      color: rgba(255, 255, 255, 0.7);
      line-height: 1.5;
      margin-bottom: 24px;
    }
    .tc-info-box {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 16px;
      text-align: left;
      margin-bottom: 24px;
      font-size: 13px;
    }
    .tc-info-box strong { color: #FD4621; display: block; margin-bottom: 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
    .tc-info-box p { color: rgba(255, 255, 255, 0.85); line-height: 1.4; margin-bottom: 6px; }
    .tc-info-box p:last-child { margin-bottom: 0; }
    .tc-btn-group { display: flex; flex-direction: column; gap: 12px; }
    .tc-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 14px 20px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 700;
      font-size: 14px;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
    }
    .tc-btn-primary {
      background: linear-gradient(135deg, #FD4621, #E03513);
      color: #FFFFFF;
      box-shadow: 0 4px 16px rgba(253, 70, 33, 0.4);
    }
    .tc-btn-secondary {
      background: rgba(255, 255, 255, 0.08);
      color: #FFFFFF;
      border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .tc-btn:hover { transform: translateY(-2px); }
  </style>
</head>
<body>
  <div class="tc-card">
    <div class="tc-logo-wrap">
      <img src="https://tripcosmos.co/wp-content/uploads/2026/07/cropped-tripcosmos-logo-180x180.jpg" alt="TripCosmos" />
    </div>
    <h1>You are Exploring Offline</h1>
    <p class="tc-subtitle">Connection dropped along the Ghats or mid-Ganga river ride? Your essential emergency support numbers are saved right here:</p>

    <div class="tc-info-box">
      <strong>Varanasi Emergency &amp; Tour Desk</strong>
      <p>📞 24x7 Hotline: <a href="tel:+919336116210" style="color:#FFF;text-decoration:underline;">+91 93361 16210</a></p>
      <p>💬 WhatsApp: <a href="https://wa.me/919336116210" style="color:#FFF;text-decoration:underline;">Chat on WhatsApp</a></p>
      <p>📍 Office: Garhwa Ghat Rd, Shivala Ghats, Varanasi</p>
    </div>

    <div class="tc-info-box">
      <strong>Varanasi Aarti Schedule</strong>
      <p>🌅 Morning Subah-e-Banaras (Assi Ghat): 5:00 AM</p>
      <p>🪔 Evening Ganga Aarti (Dashashwamedh): 6:30 PM</p>
    </div>

    <div class="tc-btn-group">
      <button class="tc-btn tc-btn-primary" onclick="window.location.reload();">🔄 Try Reconnecting</button>
      <a class="tc-btn tc-btn-secondary" href="tel:+919336116210">📞 Call Tour Manager</a>
    </div>
  </div>
</body>
</html>
HTML;
	}

	/**
	 * Sync static manifest, sw.js, and offline.html into ABSPATH
	 */
	public static function maybe_sync_static_files() {
		$target_files = array(
			ABSPATH . 'manifest.webmanifest' => self::get_manifest_json(),
			ABSPATH . 'manifest.json'        => self::get_manifest_json(),
			ABSPATH . 'sw.js'                => self::get_service_worker_js(),
			ABSPATH . 'offline.html'         => self::get_offline_html(),
		);

		foreach ( $target_files as $file_path => $content ) {
			if ( ! file_exists( $file_path ) || file_get_contents( $file_path ) !== $content ) {
				@file_put_contents( $file_path, $content );
			}
		}
	}
}
