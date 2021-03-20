<?php
function crawl_page( $url, $depth = 5, $same_domain = true, $max_pages = 5 ) {
	static $seen = array();
	static $internal_urls = array();
	static $external_urls = array();
	static $images_found = array();
	static $base_host;
	if ( empty( $base_host ) ) {
		$domain    = parse_url( $url );
		$base_host = $domain['host'];
	}

	// Consider agencyanalytics.com and agencyanalytics.com/ to be the same url.
	$url = rtrim( $url, '/\\' );

	if ( isset( $seen[ $url ] ) || $depth === 0 ) {
		return;
	}

	if ( count( $seen ) >= $max_pages ) {
		echo '<pre>';
		var_dump( $seen, $internal_urls, $external_urls, $images_found);
		//$avg_page_load, $avg_word_count, $avg_title_length
		exit;
	}

	$seen[ $url ] = true;

	$dom = new DOMDocument( '1.0' );
	@$dom->loadHTMLFile( $url );

	$images = $dom->getElementsByTagName( 'img' );
	foreach ( $images as $image ) {
		$src = $image->getAttribute( 'src' );
		if ( empty( $images_found[ $src ] ) ) {
			$images_found[ $src ] = 1;
		} else {
			$images_found[ $src ]++;
		}
	}

	$anchors = $dom->getElementsByTagName( 'a' );
	foreach ( $anchors as $element ) {
		$href = $element->getAttribute( 'href' );
		if ( 0 !== strpos( $href, 'http' ) ) {
			$path = '/' . ltrim( $href, '/' );
			$parts = parse_url( $url );
			$href = $parts['scheme'] . '://';
			if ( isset( $parts['user'] ) && isset( $parts['pass'] ) ) {
				$href .= $parts['user'] . ':' . $parts['pass'] . '@';
			}
			$href .= $parts['host'];
			if ( isset( $parts['port'] ) ) {
				$href .= ':' . $parts['port'];
			}
			if ( ! empty( $parts['path'] ) ) {
				// Windows hacks.
				$href .= str_replace( "\\", "/", dirname( $parts['path'], 1 ) );
			}
			$href .= $path;
		}

		$href_parts = parse_url($href);

		if ( $href_parts['host'] === $base_host ) {
			if ( empty( $internal_urls[ $href ] ) ) {
				$internal_urls[ $href ] = 1;
			} else {
				$internal_urls[ $href ]++;
			}
		} else {
			if ( empty( $external_urls[ $href ] ) ) {
				$external_urls[ $href ] = 1;
			} else {
				$external_urls[ $href ]++;
			}
		}

		if ( $same_domain && $href_parts['host'] === $base_host ) {
			crawl_page( $href, $depth - 1 );
		}
	}
}

//crawl_page( "https://agencyanalytics.com/feature/active-campaign-dashboard", 2 );
crawl_page( "http://agencyanalytics.com", 2 );