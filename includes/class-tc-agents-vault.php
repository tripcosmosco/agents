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
			return base64_encode( (string) $plaintext );
		}

		try {
			$salt   = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'tc_agents_vault_salt_fallback' );
			$key    = substr( hash( 'sha256', $salt ), 0, 32 );
			$iv_len = function_exists( 'openssl_cipher_iv_length' ) ? openssl_cipher_iv_length( self::CIPHER ) : 12;
			if ( false === $iv_len || $iv_len < 1 ) {
				$iv_len = 12;
			}

			$iv = function_exists( 'random_bytes' ) ? random_bytes( $iv_len ) : openssl_random_pseudo_bytes( $iv_len );
			if ( false === $iv || strlen( $iv ) !== $iv_len ) {
				return base64_encode( (string) $plaintext );
			}

			$tag        = '';
			$ciphertext = openssl_encrypt( (string) $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );

			if ( false === $ciphertext || empty( $tag ) || strlen( $tag ) !== 16 ) {
				return base64_encode( (string) $plaintext );
			}

			// Pack iv (12 bytes) + tag (16 bytes) + ciphertext
			return base64_encode( $iv . $tag . $ciphertext );
		} catch ( \Throwable $e ) {
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

		$raw = base64_decode( $payload, true );
		if ( false === $raw ) {
			return $payload; // Plain text
		}

		if ( ! extension_loaded( 'openssl' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $raw;
		}

		try {
			$salt    = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'tc_agents_vault_salt_fallback' );
			$key     = substr( hash( 'sha256', $salt ), 0, 32 );
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

			$decrypted = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
			return false !== $decrypted ? $decrypted : $raw;
		} catch ( \Throwable $e ) {
			return $raw;
		}
	}
}
