<?php
/**
 * GitHub-based updater for TripCosmos Agents.
 *
 * Pulls plugin updates from GitHub releases of the repo:
 * https://github.com/tripcosmosco/agents
 *
 * Release tags must follow `vX.Y.Z` (e.g. `v1.0.1`), and each release must
 * attach a plugin zip asset (built automatically by the Release workflow).
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_GitHub_Updater {

	const GH_OWNER  = 'tripcosmosco';
	const GH_REPO   = 'agents';
	const CACHE_KEY = 'tc_agents_gh_release';

	/**
	 * Whether the current request is a plugin upgrade from this updater.
	 *
	 * @var bool
	 */
	private static $is_upgrade = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$instance = new self();

		add_filter( 'pre_set_site_transient_update_plugins', array( $instance, 'inject_update' ) );
		add_filter( 'plugins_api', array( $instance, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $instance, 'flag_upgrade' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $instance, 'fix_source_dir' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $instance, 'clear_cache' ), 10, 2 );
		add_filter(
			'plugin_action_links_' . plugin_basename( TC_AGENTS_FILE ),
			array( $instance, 'action_links' )
		);
		add_action( 'admin_notices', array( $instance, 'manual_check_notice' ) );

		// In-plugin AJAX actions for General Settings GitHub update manager
		add_action( 'wp_ajax_tc_agents_check_github_update', array( __CLASS__, 'ajax_check_update' ) );
		add_action( 'wp_ajax_tc_agents_perform_github_update', array( __CLASS__, 'ajax_perform_update' ) );
	}

	/**
	 * Fetch the latest release payload from the GitHub API (cached 6 hours).
	 *
	 * @param bool $force Bypass the cache.
	 * @return array|null Release array or null on failure.
	 */
	public static function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'TripCosmos-Agents-Updater; ' . home_url( '/' ),
		);
		$token = class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'github_token', '' ) : get_option( 'tc_agents_github_token', '' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}

		$request = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GH_OWNER, self::GH_REPO ),
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			set_transient( self::CACHE_KEY, null, 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			set_transient( self::CACHE_KEY, null, 15 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Normalise a GitHub tag (`v1.2.3`) to a version string (`1.2.3`).
	 *
	 * @param string $tag Raw tag.
	 * @return string Version string.
	 */
	public static function tag_to_version( $tag ) {
		return ltrim( (string) $tag, 'vV' );
	}

	/**
	 * Find the plugin zip asset download URL from a release payload.
	 *
	 * @param array $release Release payload.
	 * @return string|null Zip URL or null if none found.
	 */
	public static function get_zip_url( $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( strtolower( $asset['name'] ), -4 ) ) {
					return $asset['browser_download_url'];
				}
			}
		}

		return $release['zipball_url'] ?? null;
	}

	/**
	 * Inject the GitHub release as a plugin update into the update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient ) || ! isset( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_release();

		if ( ! $release ) {
			return $transient;
		}

		$remote_version = self::tag_to_version( $release['tag_name'] );

		if ( empty( $remote_version ) || version_compare( $remote_version, TC_AGENTS_VERSION, '<=' ) ) {
			return $transient;
		}

		$zip_url = self::get_zip_url( $release );

		if ( ! $zip_url ) {
			return $transient;
		}

		$basename = plugin_basename( TC_AGENTS_FILE );

		$transient->response[ $basename ] = (object) array(
			'slug'            => dirname( $basename ),
			'plugin'          => $basename,
			'new_version'     => $remote_version,
			'url'             => sprintf( 'https://github.com/%s/%s/releases', self::GH_OWNER, self::GH_REPO ),
			'package'         => $zip_url,
			'tested'          => '',
			'requires_php'    => '8.0',
			'compatibility'   => new stdClass(),
		);

		unset( $transient->no_update[ $basename ] );

		return $transient;
	}

	/**
	 * Serve release notes in the plugin "View details" modal.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action Requested action.
	 * @param object             $args   Plugin API args.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( plugin_basename( TC_AGENTS_FILE ) ) !== $args->slug ) {
			return $result;
		}

		$release = self::get_release();

		if ( ! $release ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'TripCosmos Agents';
		$info->slug          = $args->slug;
		$info->version       = self::tag_to_version( $release['tag_name'] );
		$info->download_link = self::get_zip_url( $release );
		$info->homepage      = sprintf( 'https://github.com/%s/%s', self::GH_OWNER, self::GH_REPO );
		$info->author        = 'TripCosmos Team';
		$info->sections      = array(
			'description'  => 'Unified multi-provider AI conversational agent layer for TripCosmos.co.',
			'changelog'    => ! empty( $release['body'] )
				? '<pre>' . esc_html( $release['body'] ) . '</pre>'
				: '<p>No changelog provided for this release.</p>',
			'installation' => 'Updates are pulled automatically from GitHub releases, or trigger them manually from the Plugins screen.',
		);

		return $info;
	}

	/**
	 * Flag the request when the plugin zip is being downloaded so we can
	 * rewrite the extracted source folder afterwards.
	 *
	 * @param bool        $reply     Whether to bail without proceeding.
	 * @param string      $package   Package URL.
	 * @param WP_Upgrader $upgrader  Upgrader instance.
	 * @return bool
	 */
	public function flag_upgrade( $reply, $package, $upgrader ) {
		self::$is_upgrade = false;

		if ( is_string( $package ) && false !== strpos( $package, self::GH_REPO ) ) {
			self::$is_upgrade = true;
		}

		if ( ! self::$is_upgrade && $upgrader && isset( $upgrader->skin ) ) {
			$skin = $upgrader->skin;
			if ( ! empty( $skin->plugin ) && plugin_basename( TC_AGENTS_FILE ) === $skin->plugin ) {
				self::$is_upgrade = true;
			} elseif ( ! empty( $skin->plugin_info['plugin'] ) && plugin_basename( TC_AGENTS_FILE ) === $skin->plugin_info['plugin'] ) {
				self::$is_upgrade = true;
			}
		}

		return $reply;
	}

	/**
	 * Force the extracted update folder to match the current plugin directory
	 * name, so the upgrade replaces this installation in place (regardless of
	 * how the release zip was structured).
	 *
	 * @param string      $source        Extracted source path.
	 * @param string      $remote_source Full remote source path.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @return string|WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader ) {
		global $wp_filesystem;

		if ( ! self::$is_upgrade ) {
			return $source;
		}

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$target = trailingslashit( $remote_source ) . basename( dirname( TC_AGENTS_FILE ) );

		if ( untrailingslashit( $source ) === untrailingslashit( $target ) ) {
			return $source;
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $target, true ) ) {
			return $target;
		}

		return new WP_Error(
			'tc_agents_rename_failed',
			__( 'Unable to rename the extracted plugin folder during the GitHub update.', 'tripcosmos-agents' )
		);
	}

	/**
	 * Clear the cached release payload once any plugin upgrade finishes.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options  Upgrade options.
	 * @return void
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_transient( self::CACHE_KEY );
			self::$is_upgrade = false;
		}
	}

	/**
	 * Add helper action links on the Plugins screen row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = wp_nonce_url(
			add_query_arg( 'tc-check-update', '1', admin_url( 'plugins.php' ) ),
			'tc_check_update'
		);

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'tripcosmos-agents' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Show a result notice after a manual update check.
	 *
	 * @return void
	 */
	public function manual_check_notice() {
		if ( empty( $_GET['tc-check-update'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) || ! check_admin_referer( 'tc_check_update' ) ) {
			return;
		}

		$screen = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( $_SERVER['SCRIPT_NAME'] ) : '';

		if ( 'plugins.php' !== $screen ) {
			return;
		}

		delete_transient( self::CACHE_KEY );
		$release = self::get_release( true );

		if ( ! $release ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'TripCosmos Agents: could not reach GitHub to check for updates.', 'tripcosmos-agents' )
			);
			return;
		}

		$remote_version = self::tag_to_version( $release['tag_name'] );

		if ( version_compare( $remote_version, TC_AGENTS_VERSION, '>' ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <strong>%s</strong> %s</p></div>',
				esc_html__( 'TripCosmos Agents update available:', 'tripcosmos-agents' ),
				esc_html( $remote_version ),
				esc_html__( '— refresh this page to see the update link.', 'tripcosmos-agents' )
			);
		} else {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'TripCosmos Agents is up to date.', 'tripcosmos-agents' )
			);
		}
	}

	/**
	 * AJAX endpoint: Check for updates from GitHub.
	 *
	 * @return void
	 */
	public static function ajax_check_update() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to update plugins.', 'tripcosmos-agents' ) ) );
		}

		delete_transient( self::CACHE_KEY );
		$release = self::get_release( true );

		if ( ! $release ) {
			wp_send_json_error( array(
				'message' => __( 'Could not connect to GitHub API or no releases found. If your repository is private or rate-limited, ensure a GitHub Token is configured in General Settings.', 'tripcosmos-agents' ),
			) );
		}

		$remote_version = self::tag_to_version( $release['tag_name'] );
		$has_update     = version_compare( $remote_version, TC_AGENTS_VERSION, '>' );
		$zip_url        = self::get_zip_url( $release );

		wp_send_json_success( array(
			'current_version' => TC_AGENTS_VERSION,
			'latest_version'  => $remote_version,
			'has_update'      => $has_update,
			'tag_name'        => $release['tag_name'],
			'published_at'    => ! empty( $release['published_at'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $release['published_at'] ) ) : '',
			'zip_url'         => $zip_url,
			'changelog'       => ! empty( $release['body'] ) ? esc_html( $release['body'] ) : '',
			'html_url'        => $release['html_url'] ?? sprintf( 'https://github.com/%s/%s/releases', self::GH_OWNER, self::GH_REPO ),
			'message'         => $has_update
				? sprintf( __( 'A new update (%s) is ready to install from GitHub!', 'tripcosmos-agents' ), $remote_version )
				: sprintf( __( 'You are running the latest version (%s).', 'tripcosmos-agents' ), TC_AGENTS_VERSION ),
		) );
	}

	/**
	 * AJAX endpoint: Perform direct update from GitHub release package.
	 *
	 * @return void
	 */
	public static function ajax_perform_update() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to update plugins.', 'tripcosmos-agents' ) ) );
		}

		$release = self::get_release( true );
		if ( ! $release ) {
			wp_send_json_error( array( 'message' => __( 'Could not retrieve the latest release from GitHub.', 'tripcosmos-agents' ) ) );
		}

		$zip_url = self::get_zip_url( $release );
		if ( ! $zip_url ) {
			wp_send_json_error( array( 'message' => __( 'No release zip archive found on GitHub.', 'tripcosmos-agents' ) ) );
		}

		$remote_version = self::tag_to_version( $release['tag_name'] );

		// Load WordPress upgrade and filesystem infrastructure
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Initialize global $wp_filesystem for folder manipulation
		WP_Filesystem();

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$plugin   = plugin_basename( TC_AGENTS_FILE );

		// Hook the folder renaming filter and flag upgrade active
		$instance = new self();
		self::$is_upgrade = true;
		add_filter( 'upgrader_source_selection', array( $instance, 'fix_source_dir' ), 10, 3 );

		// Inject into update transient so Plugin_Upgrader knows where to download
		$transient = get_site_transient( 'update_plugins' );
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$transient = $instance->inject_update( $transient );
		set_site_transient( 'update_plugins', $transient );

		$result = $upgrader->upgrade( $plugin );

		delete_transient( self::CACHE_KEY );
		self::$is_upgrade = false;

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} elseif ( false === $result ) {
			$error_message = __( 'Plugin update failed.', 'tripcosmos-agents' );
			if ( method_exists( $skin, 'get_errors' ) ) {
				$errs = $skin->get_errors();
				if ( is_wp_error( $errs ) ) {
					$error_message = $errs->get_error_message();
				}
			} elseif ( method_exists( $skin, 'get_error_messages' ) ) {
				$msgs = $skin->get_error_messages();
				if ( ! empty( $msgs ) ) {
					$error_message = implode( ' ', (array) $msgs );
				}
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		if ( ! is_plugin_active( $plugin ) ) {
			activate_plugin( $plugin );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Successfully updated to %s! Reloading page...', 'tripcosmos-agents' ), $remote_version ),
			'version' => $remote_version,
		) );
	}
}

