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
	 * Encrypt a string using AES-256-GCM.
	 */
	private static function encrypt( $plaintext ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return base64_encode( $plaintext ); // Fallback if OpenSSL missing
		}

		$key       = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		$iv_len    = openssl_cipher_iv_length( self::CIPHER );
		$iv        = openssl_random_pseudo_bytes( $iv_len );
		$tag       = '';
		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext ) {
			return '';
		}

		// Pack iv + tag + ciphertext into base64 payload
		return base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt an AES-256-GCM encrypted string.
	 */
	private static function decrypt( $payload ) {
		$raw = base64_decode( $payload, true );
		if ( false === $raw ) {
			return $payload; // Might be legacy plain text
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			return $raw;
		}

		$key    = substr( hash( 'sha256', wp_salt( 'auth' ) ), 0, 32 );
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$tag_len = 16;

		if ( strlen( $raw ) < ( $iv_len + $tag_len ) ) {
			return $payload;
		}

		$iv         = substr( $raw, 0, $iv_len );
		$tag        = substr( $raw, $iv_len, $tag_len );
		$ciphertext = substr( $raw, $iv_len + $tag_len );

		$decrypted = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
		return false !== $decrypted ? $decrypted : $payload;
	}
}
