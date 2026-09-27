<?php
/**
 * GitHub-based Two-Way Code Synchronizer & Updater for TripCosmos Agents.
 *
 * Supports bidirectional synchronization:
 * 1. Checks plugin versions between WordPress and GitHub (finds which code is latest).
 * 2. Pulls & updates plugin in WordPress when GitHub has a newer version or release.
 * 3. Pushes plugin code from WordPress to GitHub when local code is updated / ahead.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_GitHub_Updater {

	const CACHE_KEY = 'tc_agents_gh_release';

	/**
	 * Whether the current request is a plugin upgrade from this updater.
	 *
	 * @var bool
	 */
	private static $is_upgrade = false;

	/**
	 * Get configured repository (owner and name).
	 *
	 * @return array { owner:string, repo:string, slug:string }
	 */
	public static function get_repo() {
		$raw = (string) get_option( 'tc_agents_github_repo', 'tripcosmosco/TripCosmos.co-Agentd' );
		$raw = trim( $raw );
		if ( empty( $raw ) ) {
			$raw = 'tripcosmosco/TripCosmos.co-Agentd';
		}
		if ( strpos( $raw, '/' ) === false ) {
			$raw = 'tripcosmosco/' . $raw;
		}
		$parts = explode( '/', $raw, 2 );
		$owner = trim( $parts[0] );
		$repo  = trim( $parts[1] );
		return array(
			'owner' => $owner,
			'repo'  => $repo,
			'slug'  => $owner . '/' . $repo,
		);
	}

	/**
	 * Get configured git branch (default: main).
	 *
	 * @return string
	 */
	public static function get_branch() {
		$branch = (string) get_option( 'tc_agents_github_branch', 'main' );
		return ! empty( trim( $branch ) ) ? trim( $branch ) : 'main';
	}

	/**
	 * Get GitHub Personal Access Token from Vault or option.
	 *
	 * @return string
	 */
	public static function get_token() {
		return class_exists( 'TC_Agents_Vault' ) ? TC_Agents_Vault::get( 'github_token', '' ) : get_option( 'tc_agents_github_token', '' );
	}

	/**
	 * Standard GitHub API headers.
	 *
	 * @return array
	 */
	public static function get_headers() {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'TripCosmos-Agents-Updater; ' . home_url( '/' ),
		);
		$token = self::get_token();
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $token );
		}
		return $headers;
	}

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
		add_filter( 'upgrader_pre_download', array( $instance, 'verify_package' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $instance, 'fix_source_dir' ), 10, 3 );
		add_filter( 'http_request_args', array( $instance, 'attach_token_to_download' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $instance, 'clear_cache' ), 10, 2 );
		add_filter(
			'plugin_action_links_' . plugin_basename( TC_AGENTS_FILE ),
			array( $instance, 'action_links' )
		);
		add_action( 'admin_notices', array( $instance, 'manual_check_notice' ) );

		// In-plugin AJAX actions for General Settings GitHub update & two-way sync manager
		add_action( 'wp_ajax_tc_agents_check_github_update', array( __CLASS__, 'ajax_check_update' ) );
		add_action( 'wp_ajax_tc_agents_perform_github_update', array( __CLASS__, 'ajax_perform_update' ) );
		add_action( 'wp_ajax_tc_agents_check_github_sync', array( __CLASS__, 'ajax_check_sync' ) );
		add_action( 'wp_ajax_tc_agents_push_github', array( __CLASS__, 'ajax_push_to_github' ) );
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

		$repo_info = self::get_repo();
		$headers   = self::get_headers();

		$request = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', $repo_info['owner'], $repo_info['repo'] ),
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
	 * Find the plugin zip asset download URL from a release payload or branch zipball.
	 *
	 * @param array|null $release Release payload.
	 * @return string|null Zip URL or null if none found.
	 */
	public static function get_zip_url( $release = null ) {
		$url = null;
		if ( $release && ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && '.zip' === substr( strtolower( $asset['name'] ), -4 ) ) {
					$url = $asset['browser_download_url'];
					break;
				}
			}
		}
		if ( ! $url && $release ) {
			$url = $release['zipball_url'] ?? null;
		}

		// Fallback to direct branch zipball if no releases exist
		if ( ! $url ) {
			$repo_info = self::get_repo();
			$branch    = self::get_branch();
			$url       = sprintf( 'https://api.github.com/repos/%s/%s/zipball/%s', $repo_info['owner'], $repo_info['repo'], rawurlencode( $branch ) );
		}

		// Only ever install a package served over HTTPS from GitHub.
		if ( $url ) {
			$parts = wp_parse_url( $url );
			$host  = strtolower( $parts['host'] ?? '' );
			$ok    = 'https' === ( $parts['scheme'] ?? '' ) && in_array( $host, array( 'github.com', 'api.github.com', 'codeload.github.com', 'objects.githubusercontent.com' ), true );
			if ( ! $ok ) {
				return null;
			}
		}

		return $url;
	}

	/**
	 * Attach GitHub token header when WordPress downloads packages from GitHub.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public function attach_token_to_download( $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( in_array( $host, array( 'github.com', 'api.github.com', 'codeload.github.com' ), true ) ) {
			$token = self::get_token();
			if ( ! empty( $token ) ) {
				$args['headers']['Authorization'] = 'Bearer ' . trim( $token );
				$args['headers']['Accept']        = 'application/vnd.github+json';
			}
		}
		return $args;
	}

	/**
	 * Check sync status between WordPress and GitHub repository.
	 *
	 * @return array
	 */
	public static function check_sync() {
		$repo_info = self::get_repo();
		$owner     = $repo_info['owner'];
		$repo      = $repo_info['repo'];
		$branch    = self::get_branch();
		$token     = self::get_token();

		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'error'   => 'token_missing',
				'message' => __( 'GitHub Access Token is missing. Please save a token with repository write access in settings below.', 'tripcosmos-agents' ),
				'repo'    => $repo_info['slug'],
				'branch'  => $branch,
			);
		}

		$headers = self::get_headers();

		// 1. Check latest commit on branch
		$commit_url = sprintf( 'https://api.github.com/repos/%s/%s/commits/%s', $owner, $repo, rawurlencode( $branch ) );
		$commit_res = wp_remote_get( $commit_url, array( 'timeout' => 15, 'headers' => $headers ) );

		if ( is_wp_error( $commit_res ) ) {
			return array(
				'success' => false,
				'error'   => 'network_error',
				'message' => $commit_res->get_error_message(),
				'repo'    => $repo_info['slug'],
				'branch'  => $branch,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $commit_res );
		$body = json_decode( (string) wp_remote_retrieve_body( $commit_res ), true );

		if ( 403 === $code ) {
			return array(
				'success' => false,
				'error'   => 'auth_403',
				'message' => __( 'GitHub returned 403 Forbidden. Your Personal Access Token does not have permission to read/write this repository. Ensure it has "Contents: Read and write" permission (or Classic token with "repo" scope).', 'tripcosmos-agents' ),
				'repo'    => $repo_info['slug'],
				'branch'  => $branch,
			);
		}

		if ( 404 === $code ) {
			return array(
				'success' => false,
				'error'   => 'not_found_404',
				'message' => sprintf( __( 'Repository "%s/%s" or branch "%s" was not found on GitHub.', 'tripcosmos-agents' ), $owner, $repo, $branch ),
				'repo'    => $repo_info['slug'],
				'branch'  => $branch,
			);
		}

		$latest_commit = null;
		if ( ! empty( $body['sha'] ) ) {
			$latest_commit = array(
				'sha'     => substr( $body['sha'], 0, 7 ),
				'full_sha'=> $body['sha'],
				'message' => $body['commit']['message'] ?? '',
				'date'    => ! empty( $body['commit']['committer']['date'] ) ? date_i18n( 'M j, Y H:i T', strtotime( $body['commit']['committer']['date'] ) ) : '',
				'author'  => $body['commit']['author']['name'] ?? '',
				'url'     => $body['html_url'] ?? '',
			);
		}

		// 2. Check remote plugin file version on GitHub
		$remote_version = null;
		$file_url = sprintf( 'https://api.github.com/repos/%s/%s/contents/tripcosmos-agents.php?ref=%s', $owner, $repo, rawurlencode( $branch ) );
		$file_res = wp_remote_get( $file_url, array( 'timeout' => 15, 'headers' => $headers ) );
		if ( ! is_wp_error( $file_res ) && 200 === (int) wp_remote_retrieve_response_code( $file_res ) ) {
			$file_data = json_decode( (string) wp_remote_retrieve_body( $file_res ), true );
			if ( ! empty( $file_data['content'] ) ) {
				$raw_php = base64_decode( $file_data['content'] );
				if ( preg_match( '/\*\s*Version:\s*([0-9\.]+)/i', $raw_php, $vm ) ) {
					$remote_version = $vm[1];
				}
			}
		}

		// Fallback to release tag if file not found
		if ( ! $remote_version ) {
			$release = self::get_release( true );
			if ( $release && ! empty( $release['tag_name'] ) ) {
				$remote_version = self::tag_to_version( $release['tag_name'] );
			}
		}

		$local_version = defined( 'TC_AGENTS_VERSION' ) ? TC_AGENTS_VERSION : '1.9.0';
		$status        = 'synced';
		$message       = '';

		if ( ! $remote_version ) {
			$status  = 'wp_ahead';
			$message = __( 'Remote repository does not contain a plugin version yet. Your WordPress code is ahead and ready to push!', 'tripcosmos-agents' );
		} elseif ( version_compare( $local_version, $remote_version, '>' ) ) {
			$status  = 'wp_ahead';
			$message = sprintf( __( 'Your WordPress plugin (v%s) is NEWER than GitHub (v%s). You can push your code to GitHub!', 'tripcosmos-agents' ), $local_version, $remote_version );
		} elseif ( version_compare( $remote_version, $local_version, '>' ) ) {
			$status  = 'gh_ahead';
			$message = sprintf( __( 'GitHub has a NEWER version (v%s) than your WordPress installation (v%s). You can update from GitHub!', 'tripcosmos-agents' ), $remote_version, $local_version );
		} else {
			// Versions match
			$status  = 'synced';
			$message = sprintf( __( 'WordPress and GitHub are in sync at version v%s.', 'tripcosmos-agents' ), $local_version );
		}

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'github_sync_check',
				'info',
				array(
					'sync_status'    => $status,
					'local_version'  => $local_version,
					'remote_version' => $remote_version ?: 'None',
					'repo'           => $repo_info['slug'],
					'branch'         => $branch,
				),
				'',
				'system'
			);
		}

		return array(
			'success'        => true,
			'local_version'  => $local_version,
			'remote_version' => $remote_version ?: 'None',
			'sync_status'    => $status,
			'message'        => $message,
			'latest_commit'  => $latest_commit,
			'repo'           => $repo_info['slug'],
			'branch'         => $branch,
		);
	}

	/**
	 * Push current WordPress plugin code to GitHub.
	 *
	 * @param string $custom_msg Commit message.
	 * @return array|WP_Error
	 */
	public static function push_to_github( $custom_msg = '' ) {
		$repo_info = self::get_repo();
		$owner     = $repo_info['owner'];
		$repo      = $repo_info['repo'];
		$branch    = self::get_branch();
		$token     = self::get_token();

		if ( empty( $token ) ) {
			return new WP_Error( 'no_token', __( 'GitHub Personal Access Token is required to push to GitHub.', 'tripcosmos-agents' ) );
		}

		$plugin_dir = untrailingslashit( TC_AGENTS_PATH );
		if ( ! is_dir( $plugin_dir ) ) {
			return new WP_Error( 'dir_missing', __( 'Plugin directory does not exist.', 'tripcosmos-agents' ) );
		}

		$version = defined( 'TC_AGENTS_VERSION' ) ? TC_AGENTS_VERSION : '1.9.0';
		$msg     = ! empty( $custom_msg ) ? sanitize_text_field( $custom_msg ) : sprintf( 'Release v%s: sync from WordPress site [skip ci]', $version );

		// Ensure .gitignore exists in plugin dir
		$gi_path = $plugin_dir . '/.gitignore';
		if ( ! file_exists( $gi_path ) ) {
			@file_put_contents( $gi_path, "*~\n*.bak\n*.tmp\n*.log\n.DS_Store\n" );
		}

		$remote_url = sprintf( 'https://%s:%s@github.com/%s/%s.git', urlencode( $owner ), urlencode( $token ), urlencode( $owner ), urlencode( $repo ) );

		$cmds = array(
			'cd ' . escapeshellarg( $plugin_dir ),
			'git init -b ' . escapeshellarg( $branch ) . ' 2>&1',
			'git config user.name "TripCosmos WordPress"',
			'git config user.email "admin@tripcosmos.co"',
			'git remote remove origin 2>/dev/null || true',
			'git remote add origin ' . escapeshellarg( $remote_url ),
			'git add -A',
			'git commit -m ' . escapeshellarg( $msg ) . ' 2>&1 || true',
			'git push -u origin ' . escapeshellarg( $branch ) . ' 2>&1',
		);

		$cmd_str = implode( ' && ', $cmds );
		$output  = array();
		$code    = 0;
		@exec( $cmd_str, $output, $code );

		$out_str   = implode( "\n", $output );
		$clean_out = str_replace( $token, '••••••••', $out_str );

		if ( 0 !== $code ) {
			if ( strpos( $clean_out, '403' ) !== false || strpos( $clean_out, 'Write access to repository not granted' ) !== false ) {
				$err_obj = new WP_Error(
					'git_push_403',
					__( 'GitHub rejected write access: Your Personal Access Token needs "Contents: Read and write" permission (or Classic token with "repo" scope).', 'tripcosmos-agents' ),
					array( 'output' => $clean_out )
				);
			} else {
				$err_obj = new WP_Error( 'git_push_failed', __( 'Git push failed: ', 'tripcosmos-agents' ) . $clean_out, array( 'output' => $clean_out ) );
			}
			if ( class_exists( 'TC_Agents_Logger' ) ) {
				TC_Agents_Logger::log(
					'github_push_error',
					'error',
					array(
						'repo'    => "$owner/$repo",
						'branch'  => $branch,
						'message' => $err_obj->get_error_message(),
						'output'  => $clean_out,
					),
					'',
					'system'
				);
			}
			return $err_obj;
		}

		$sha = '';
		@exec( 'cd ' . escapeshellarg( $plugin_dir ) . ' && git rev-parse --short HEAD', $sha_out );
		if ( ! empty( $sha_out[0] ) ) {
			$sha = trim( $sha_out[0] );
		}

		delete_transient( self::CACHE_KEY );

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'github_push_success',
				'info',
				array(
					'commit'  => $sha,
					'repo'    => "$owner/$repo",
					'branch'  => $branch,
					'message' => $msg,
				),
				'',
				'system'
			);
		}

		return array(
			'success'    => true,
			'commit_sha' => $sha,
			'repo'       => "$owner/$repo",
			'branch'     => $branch,
			'url'        => sprintf( 'https://github.com/%s/%s/commit/%s', $owner, $repo, $sha ),
			'message'    => sprintf( __( 'Successfully pushed latest plugin code (commit %s) to GitHub branch "%s"!', 'tripcosmos-agents' ), $sha, $branch ),
		);
	}

	/**
	 * Inject the GitHub release or branch package as a plugin update into transient.
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

		$repo_info = self::get_repo();
		$basename  = plugin_basename( TC_AGENTS_FILE );

		$transient->response[ $basename ] = (object) array(
			'slug'            => dirname( $basename ),
			'plugin'          => $basename,
			'new_version'     => $remote_version,
			'url'             => sprintf( 'https://github.com/%s', $repo_info['slug'] ),
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

		$basename = plugin_basename( TC_AGENTS_FILE );

		if ( empty( $args->slug ) || $args->slug !== dirname( $basename ) ) {
			return $result;
		}

		$release = self::get_release();

		if ( ! $release ) {
			return $result;
		}

		$remote_version = self::tag_to_version( $release['tag_name'] );
		$repo_info      = self::get_repo();

		$info = (object) array(
			'name'          => 'TripCosmos Agents',
			'slug'          => dirname( $basename ),
			'version'       => $remote_version,
			'author'        => '<a href="https://tripcosmos.co">TripCosmos Team</a>',
			'homepage'      => sprintf( 'https://github.com/%s', $repo_info['slug'] ),
			'download_link' => self::get_zip_url( $release ),
			'sections'      => array(
				'description' => __( 'Unified multi-provider AI conversational agent layer for TripCosmos.co.', 'tripcosmos-agents' ),
				'changelog'   => ! empty( $release['body'] )
					? esc_html( $release['body'] )
					: __( 'No changelog provided for this release.', 'tripcosmos-agents' ),
			),
			'banners'       => array(),
		);

		return $info;
	}

	/**
	 * Verify package checksum if published.
	 */
	public function verify_package( $reply, $package, $upgrader = null ) {
		if ( false !== $reply || ! self::$is_upgrade || ! is_string( $package ) || false === strpos( $package, 'github' ) ) {
			return $reply;
		}

		$release  = self::get_release();
		$expected = $release ? self::get_expected_sha256( $release, $package ) : null;

		if ( ! $expected ) {
			return $reply;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = download_url( $package );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! hash_equals( $expected, strtolower( (string) hash_file( 'sha256', $file ) ) ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'tc_checksum_mismatch', __( 'Update package failed SHA-256 verification and was not installed.', 'tripcosmos-agents' ) );
		}
		return $file;
	}

	public static function get_expected_sha256( $release, $package ) {
		$zip_name = basename( (string) wp_parse_url( $package, PHP_URL_PATH ) );
		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			$name = (string) ( $asset['name'] ?? '' );
			if ( $name !== $zip_name . '.sha256' && 'SHA256SUMS' !== $name ) {
				continue;
			}
			$url = (string) ( $asset['browser_download_url'] ?? '' );
			$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
			if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				continue;
			}
			foreach ( preg_split( '/\R/', (string) wp_remote_retrieve_body( $res ) ) as $line ) {
				if ( preg_match( '/^([a-f0-9]{64})(?:\s+\*?(.+))?$/i', trim( $line ), $m ) ) {
					if ( '.sha256' === substr( $name, -7 ) || ( isset( $m[2] ) && basename( trim( $m[2] ) ) === $zip_name ) ) {
						return strtolower( $m[1] );
					}
				}
			}
		}
		return null;
	}

	public function flag_upgrade( $reply, $package, $upgrader ) {
		self::$is_upgrade = false;

		$repo_info = self::get_repo();
		if ( is_string( $package ) && false !== strpos( $package, $repo_info['repo'] ) ) {
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

	public function clear_cache( $upgrader, $options ) {
		if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_transient( self::CACHE_KEY );
			self::$is_upgrade = false;
		}
	}

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

	public function manual_check_notice() {
		if ( empty( $_GET['tc-check-update'] ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! current_user_can( 'update_plugins' ) || empty( $nonce ) || ! wp_verify_nonce( $nonce, 'tc_check_update' ) ) {
			return;
		}

		$screen = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( $_SERVER['SCRIPT_NAME'] ) : '';
		if ( 'plugins.php' !== $screen ) {
			return;
		}

		delete_transient( self::CACHE_KEY );
		$sync = self::check_sync();

		if ( empty( $sync['success'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $sync['message'] ?? __( 'TripCosmos Agents: could not reach GitHub.', 'tripcosmos-agents' ) )
			);
			return;
		}

		if ( 'gh_ahead' === $sync['sync_status'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <strong>v%s</strong> %s</p></div>',
				esc_html__( 'TripCosmos Agents update available from GitHub:', 'tripcosmos-agents' ),
				esc_html( $sync['remote_version'] ),
				esc_html__( '— visit TripCosmos Settings to install.', 'tripcosmos-agents' )
			);
		} elseif ( 'wp_ahead' === $sync['sync_status'] ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s (v%s &gt; v%s).</p></div>',
				esc_html__( 'Your WordPress plugin code is ahead of GitHub', 'tripcosmos-agents' ),
				esc_html( $sync['local_version'] ),
				esc_html( $sync['remote_version'] )
			);
		} else {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				esc_html__( 'TripCosmos Agents is in sync with GitHub.', 'tripcosmos-agents' )
			);
		}
	}

	/**
	 * AJAX endpoint: Check two-way sync comparison with GitHub.
	 */
	public static function ajax_check_sync() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tripcosmos-agents' ) ) );
		}

		delete_transient( self::CACHE_KEY );
		$res = self::check_sync();

		if ( empty( $res['success'] ) ) {
			wp_send_json_error( $res );
		}

		wp_send_json_success( $res );
	}

	/**
	 * AJAX endpoint: Check for updates (legacy BC).
	 */
	public static function ajax_check_update() {
		self::ajax_check_sync();
	}

	/**
	 * AJAX endpoint: Push WordPress plugin code to GitHub.
	 */
	public static function ajax_push_to_github() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tripcosmos-agents' ) ) );
		}

		$msg = sanitize_text_field( wp_unslash( $_POST['commit_message'] ?? '' ) );
		$res = self::push_to_github( $msg );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array(
				'message' => $res->get_error_message(),
				'data'    => $res->get_error_data(),
			) );
		}

		wp_send_json_success( $res );
	}

	/**
	 * AJAX endpoint: Perform direct update from GitHub package.
	 */
	public static function ajax_perform_update() {
		check_ajax_referer( 'tc_agents_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to update plugins.', 'tripcosmos-agents' ) ) );
		}

		$release = self::get_release( true );
		$zip_url = self::get_zip_url( $release );

		if ( ! $zip_url ) {
			wp_send_json_error( array( 'message' => __( 'No release zip or branch archive found on GitHub.', 'tripcosmos-agents' ) ) );
		}

		$remote_version = $release ? self::tag_to_version( $release['tag_name'] ) : 'latest';

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		WP_Filesystem();

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$plugin   = plugin_basename( TC_AGENTS_FILE );

		$instance = new self();
		self::$is_upgrade = true;
		add_filter( 'upgrader_source_selection', array( $instance, 'fix_source_dir' ), 10, 3 );

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
