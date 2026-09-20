<?php
/**
 * Knowledge Base Chunker for TripCosmos Agents.
 *
 * Splits trek descriptions, itineraries, and policy documents on paragraph
 * boundaries into overlapping chunks so answers are never clipped midway.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agent_Chunker {

	/**
	 * Target character length per chunk.
	 *
	 * @var int
	 */
	private $target_chars;

	/**
	 * Overlap characters between consecutive chunks.
	 *
	 * @var int
	 */
	private $overlap_chars;

	/**
	 * Constructor.
	 *
	 * @param int $target_chars  Target chunk size (default: 1000).
	 * @param int $overlap_chars Overlap window (default: 150).
	 */
	public function __construct( $target_chars = 1000, $overlap_chars = 150 ) {
		$this->target_chars  = (int) $target_chars;
		$this->overlap_chars = (int) $overlap_chars;
	}

	/**
	 * Split text into semantic overlapping chunks.
	 *
	 * @param string $text Raw input text.
	 * @return array<int, string>
	 */
	public function split( $text ) {
		$stripped = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $text ) : strip_tags( (string) $text );
		$text     = trim( preg_replace( '/\r\n?/', "\n", $stripped ) ?? '' );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? '';

		if ( '' === $text ) {
			return array();
		}

		if ( self::strlen( $text ) <= $this->target_chars ) {
			return array( $text );
		}

		$paragraphs = preg_split( '/\n\n+/', $text ) ?: array();
		$chunks     = array();
		$buffer     = '';

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( '' === $paragraph ) {
				continue;
			}

			if ( self::strlen( $paragraph ) > $this->target_chars ) {
				if ( '' !== $buffer ) {
					$chunks[] = $buffer;
					$buffer   = '';
				}
				foreach ( $this->split_long_paragraph( $paragraph ) as $piece ) {
					$chunks[] = $piece;
				}
				continue;
			}

			if ( self::strlen( $buffer ) + self::strlen( $paragraph ) + 2 > $this->target_chars ) {
				$chunks[] = $buffer;
				$buffer   = $this->tail( $buffer );
			}

			$buffer .= ( '' === $buffer ? '' : "\n\n" ) . $paragraph;
		}

		if ( '' !== trim( $buffer ) ) {
			$chunks[] = $buffer;
		}

		return array_values( array_filter( array_map( 'trim', $chunks ) ) );
	}

	/**
	 * Split oversized paragraphs by sentence boundaries.
	 *
	 * @param string $paragraph
	 * @return array<int, string>
	 */
	private function split_long_paragraph( $paragraph ) {
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $paragraph ) ?: array( $paragraph );
		$out       = array();
		$buffer    = '';

		foreach ( $sentences as $sentence ) {
			if ( self::strlen( $buffer ) + self::strlen( $sentence ) > $this->target_chars && '' !== $buffer ) {
				$out[]  = $buffer;
				$buffer = $this->tail( $buffer );
			}
			$buffer .= ( '' === $buffer ? '' : ' ' ) . $sentence;
		}

		if ( '' !== trim( $buffer ) ) {
			$out[] = $buffer;
		}

		return $out;
	}

	/**
	 * Extract overlap tail from buffer.
	 *
	 * @param string $text
	 * @return string
	 */
	private function tail( $text ) {
		return $this->overlap_chars > 0 ? self::substr( $text, -$this->overlap_chars ) : '';
	}

	/**
	 * Safe multibyte string length.
	 *
	 * @param string $str
	 * @return int
	 */
	private static function strlen( $str ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $str ) : strlen( (string) $str );
	}

	/**
	 * Safe multibyte substring.
	 *
	 * @param string   $str
	 * @param int      $start
	 * @param int|null $length
	 * @return string
	 */
	private static function substr( $str, $start, $length = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( (string) $str, $start, $length );
		}
		return null !== $length ? substr( (string) $str, $start, $length ) : substr( (string) $str, $start );
	}

	/**
	 * Estimate token count from character length (~4 chars per token).
	 *
	 * @param string $text
	 * @return int
	 */
	public static function estimate_tokens( $text ) {
		return (int) ceil( self::strlen( (string) $text ) / 4 );
	}
}
