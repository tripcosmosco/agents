<?php
/**
 * Cryptographic Vault for Secure Credential Storage.
 * Inspired by VM Sales OS Vault architecture.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Vault {

	private const OPTION = 'tc_agents_vault';
	private const CIPHER = 'aes-256-gcm';

	/**
	 * Store an encrypted secret in the vault.
	 *
	 * @param string $key
	 * @param string $value
	 */
	public static function put( $key, $value ) {
		$vault = get_option( self::OPTION, array() );
		if ( ! is_array( $vault ) ) {
			$vault = array();
		}

		if ( '' === $value || null === $value ) {
			unset( $vault[ $key ] );
		} else {
			$vault[ $key ] = self::encrypt( (string) $value );
		}

		update_option( self::OPTION, $vault, false );
	}

	/**
	 * Alias for put().
	 *
	 * @param string $key
	 * @param string $value
	 */
	public static function set( $key, $value ) {
		self::put( $key, $value );
	}

	/**
	 * Retrieve a decrypted secret from the vault.
	 * Priority: wp-config.php constants > Vault storage > Legacy option.
	 *
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	public static function get( $key, $default = '' ) {
		// 1. wp-config.php constant override always wins (security best practice)
		$constant = 'TC_AGENTS_' . strtoupper( str_replace( array( '.', '-' ), '_', $key ) );
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}

		// 2. Vault encrypted store
		$vault = get_option( self::OPTION, array() );
		if ( is_array( $vault ) && ! empty( $vault[ $key ] ) ) {
			$decrypted = self::decrypt( $vault[ $key ] );
			if ( false !== $decrypted ) {
				return $decrypted;
			}
		}

		// 3. Fallback to legacy option if migrating from older version
		$legacy = get_option( 'tc_agents_' . $key, '' );
		if ( ! empty( $legacy ) ) {
			// Auto-upgrade into encrypted vault
			self::put( $key, $legacy );
			return $legacy;
		}

		return $default;
	}

	/**
	 * Check if secret exists in vault.
	 */
	public static function has( $key ) {
		return '' !== self::get( $key );
	}

	/**
	 * Provide a masked preview for admin settings display (e.g. ••••••••a1b2).
	 */
	public static function hint( $key ) {
		$val = self::get( $key );
		if ( empty( $val ) ) {
			return '';
		}
		if ( strlen( $val ) <= 6 ) {
			return '••••••••';
		}
		return '••••••••' . substr( $val, -4 );
	}

	/**
	 * Encrypt a string using AES-256-GCM (with safe fallbacks).
	 */
	private static function encrypt( $plaintext ) {
		if ( ! extension_loaded( 'openssl' ) || ! function_exists( 'openssl_encrypt' ) ) {
			self::log_insecure_fallback( 'openssl_unavailable' );
			return base64_encode( (string) $plaintext );
		}

		try {
			$key    = self::keys()[0];
			$iv_len = function_exists( 'openssl_cipher_iv_length' ) ? openssl_cipher_iv_length( self::CIPHER ) : 12;
			if ( false === $iv_len || $iv_len < 1 ) {
				$iv_len = 12;
			}

			$iv = function_exists( 'random_bytes' ) ? random_bytes( $iv_len ) : openssl_random_pseudo_bytes( $iv_len );
			if ( false === $iv || strlen( $iv ) !== $iv_len ) {
				self::log_insecure_fallback( 'iv_generation_failed' );
				return base64_encode( (string) $plaintext );
			}

			$tag        = '';
			$ciphertext = openssl_encrypt( (string) $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );

			if ( false === $ciphertext || empty( $tag ) || strlen( $tag ) !== 16 ) {
				self::log_insecure_fallback( 'encrypt_failed' );
				return base64_encode( (string) $plaintext );
			}

			// Pack iv (12 bytes) + tag (16 bytes) + ciphertext
			return 'v2:' . base64_encode( $iv . $tag . $ciphertext );
		} catch ( \Throwable $e ) {
			self::log_insecure_fallback( 'exception:' . $e->getMessage() );
			return base64_encode( (string) $plaintext );
		}
	}

	/**
	 * Decrypt an AES-256-GCM encrypted string (with safe fallback for legacy or plain values).
	 */
	private static function decrypt( $payload ) {
		if ( empty( $payload ) || ! is_string( $payload ) ) {
			return '';
		}

		// v2 values are always real ciphertext: a failed decrypt means the key changed. Return false
		// (never the raw bytes) so callers don't send garbage to an API as if it were a key.
		if ( 0 === strpos( $payload, 'v2:' ) ) {
			$raw = base64_decode( substr( $payload, 3 ), true );
			if ( false === $raw || strlen( $raw ) < 29 || ! function_exists( 'openssl_decrypt' ) ) {
				return false;
			}
			foreach ( self::keys() as $key ) {
				$out = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
				if ( false !== $out ) {
					return $out;
				}
			}
			if ( class_exists( 'TC_Agents_Logger' ) ) {
				TC_Agents_Logger::log( 'vault_decrypt_failed', 'error', array( 'reason' => 'Key changed (salts rotated or site cloned). Re-enter this API key.' ) );
			}
			return false;
		}

		$raw = base64_decode( $payload, true );
		if ( false === $raw ) {
			return $payload; // Plain text
		}

		if ( ! extension_loaded( 'openssl' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $raw;
		}

		try {
			
			$iv_len  = function_exists( 'openssl_cipher_iv_length' ) ? openssl_cipher_iv_length( self::CIPHER ) : 12;
			if ( false === $iv_len || $iv_len < 1 ) {
				$iv_len = 12;
			}
			$tag_len = 16;

			if ( strlen( $raw ) < ( $iv_len + $tag_len ) ) {
				return $raw;
			}

			$iv         = substr( $raw, 0, $iv_len );
			$tag        = substr( $raw, $iv_len, $tag_len );
			$ciphertext = substr( $raw, $iv_len + $tag_len );

			if ( strlen( $tag ) !== $tag_len ) {
				return $raw;
			}

			// Try the pinned key (TC_AGENTS_VAULT_KEY) first, then the salt-derived key used by older installs.
			foreach ( self::keys() as $key ) {
				$decrypted = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
				if ( false !== $decrypted ) {
					return $decrypted;
				}
			}
			return $raw;
		} catch ( \Throwable $e ) {
			return $raw;
		}
	}

	/**
	 * Candidate encryption keys, preferred first. Define TC_AGENTS_VAULT_KEY in wp-config.php to
	 * pin the key so secrets survive salt rotation or a site clone; the salt-derived key stays
	 * as a decrypt fallback for values stored by earlier versions.
	 *
	 * @return string[]
	 */
	private static function keys() {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'tc_agents_vault_salt_fallback' );
		$keys = array();
		if ( defined( 'TC_AGENTS_VAULT_KEY' ) && '' !== (string) TC_AGENTS_VAULT_KEY ) {
			$keys[] = substr( hash( 'sha256', (string) TC_AGENTS_VAULT_KEY ), 0, 32 );
		}
		$keys[] = substr( hash( 'sha256', $salt ), 0, 32 );
		return $keys;
	}

	/**
	 * Whether secrets are really encrypted (false means the base64 fallback is in use).
	 */
	public static function is_secure() {
		return extension_loaded( 'openssl' ) && function_exists( 'openssl_encrypt' ) && in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Loudly record that a secret was just stored via the reversible base64 fallback instead of
	 * real encryption, so this is never a silent failure. Throttled to once per hour per reason
	 * so a broken environment doesn't spam the log on every save.
	 *
	 * @param string $reason
	 */
	private static function log_insecure_fallback( $reason ) {
		$throttle_key = 'tc_agents_vault_fallback_' . md5( $reason );
		if ( false !== get_transient( $throttle_key ) ) {
			return;
		}
		set_transient( $throttle_key, 1, HOUR_IN_SECONDS );

		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log(
				'vault_base64_fallback_used',
				'error',
				array(
					'reason'  => $reason,
					'message' => 'A secret was just stored base64-encoded instead of AES-256-GCM encrypted. This is NOT real encryption and is trivially reversible. Fix the underlying cause (missing/broken OpenSSL) and re-save all API keys.',
				)
			);
		}
		// Also flip a persistent flag so the admin notice can warn even outside admin_notices timing.
		update_option( 'tc_agents_vault_insecure_fallback_seen', time(), false );
	}

	/**
	 * Re-encrypt every stored secret with the preferred key. Runs once after TC_AGENTS_VAULT_KEY is
	 * added (or changed) so existing secrets move to it without being re-entered.
	 *
	 * @return array{moved:int,failed:string[]}
	 */
	public static function rekey() {
		$vault  = get_option( self::OPTION, array() );
		$moved  = 0;
		$failed = array();
		if ( ! is_array( $vault ) ) {
			return array( 'moved' => 0, 'failed' => array() );
		}
		foreach ( $vault as $k => $payload ) {
			$plain = self::decrypt( $payload );
			if ( false === $plain || '' === $plain ) {
				$failed[] = (string) $k;
				continue;
			}
			$vault[ $k ] = self::encrypt( $plain );
			$moved++;
		}
		update_option( self::OPTION, $vault, false );
		return array( 'moved' => $moved, 'failed' => $failed );
	}

	/**
	 * Admin hook: rekey when the pinned key's fingerprint differs from the last one used.
	 */
	public static function maybe_rekey() {
		if ( ! defined( 'TC_AGENTS_VAULT_KEY' ) || '' === (string) TC_AGENTS_VAULT_KEY || ! self::is_secure() ) {
			return;
		}
		$fp = substr( hash( 'sha256', 'fp|' . TC_AGENTS_VAULT_KEY ), 0, 16 );
		if ( get_option( 'tc_agents_vault_key_fp' ) === $fp ) {
			return;
		}
		$res = self::rekey();
		update_option( 'tc_agents_vault_key_fp', $fp, false );
		if ( class_exists( 'TC_Agents_Logger' ) ) {
			TC_Agents_Logger::log( 'vault_rekeyed', $res['failed'] ? 'warning' : 'info', $res );
		}
	}
}
