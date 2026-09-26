<?php
/**
 * Plain-text email lift into a branded HTML shell.
 *
 * @package ZekoBusinessDirectory
 */

namespace ZBE\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Wrap a plain-text email body in a branded HTML shell when the shared
 * Zeko Core email designer is available. Falls back to the original plain
 * text otherwise.
 *
 * @return array{body: string, html: bool} The email body and whether it is HTML.
 * @param string $body     Plain-text body.
 * @param string $brand    Brand name shown in the header.
 * @param string $tagline  Optional one-line header tagline.
 * @param string $heading  Optional card heading (default empty).
 */
function zbe_wrap_email( string $body, string $brand, string $tagline = '', string $heading = '' ): array {
	if ( ! class_exists( '\Zeko_Core_Emails' ) ) {
		return array( $body, false );
	}

	$emails  = \Zeko_Core_Emails::get_instance();
	$heading = '' !== $heading ? $emails->h2( $heading ) : '';
	$html    = $emails->wrap(
		$heading . $emails->plain_to_html( $body ),
		array(
			'brand_name' => $brand,
			'tagline'    => $tagline,
		)
	);

	return array( $html, true );
}
