<?php

class Crawler {
	protected $base_url;
	protected $current_url;
	protected $base_domain;
	protected $https;
	protected $output_method;
	protected $max_pages;
	protected $crawled_internal_urls = array();
	protected $found_internal_urls = array();
	protected $external_urls = array();
	protected $images = array();

	public function __construct( $base_url, $output_method = 'database', $max_pages = 5 ) {
		$this->setBaseUrl( $base_url );
		$this->setOutputMethod( $output_method );
		$this->setMaxPages( $max_pages );

		// Initialize set of found urls to the provided url
		array_push( $this->found_internal_urls, $this->getBaseUrl() );
	}

	/**
	 * @param string $base_url
	 */
	public function setBaseUrl( string $base_url ) : void {
		// TODO:  normalize base url better?
		// TODO:  do an initial curl request to verify URL is legit and follow redirects for base_url (to normalize correct http/s, www)
		// TODO:  respect robots.txt in scan?
		// Sanity check so we can parse the url
		if ( false === strpos( $base_url, 'http' ) ) {
			$base_url = 'https://' . $base_url;
		}
		$this->base_url    = $base_url;
		$this->current_url = $base_url;
		$parsed_url        = parse_url( $base_url );
		$this->base_domain = $parsed_url['host'];
		if ( ! empty( $parsed_url['scheme'] ) ) {
			$this->https = $parsed_url['scheme'];
		} else {
			// Default to SSL on.
			$this->https = 'https';
		}
	}

	/**
	 * @param string $output_method
	 *
	 * @throws \Exception
	 */
	public function setOutputMethod( $output_method ) : void {
		if ( ! in_array( $output_method, [ 'database', 'html' ] ) ) {
			throw new Exception( 'Invalid output method.' );
		}
		$this->output_method = $output_method;
	}

	/**
	 * @param int $max_pages
	 */
	public function setMaxPages( int $max_pages ) {
		$this->max_pages = $max_pages;
	}

	/**
	 * @return string
	 */
	public function getBaseUrl() : string {
		return $this->base_url;
	}

	public function crawl() {
		while ( $this->getTotalCrawled() <= $this->getMaxPages() && $this->getTotalFound() > 0 ) {
			$this->current_url = $this->_popPage();

			// TODO: Convert to curl and log time
			$dom = new DOMDocument( '1.0' );
			@$dom->loadHTMLFile( $this->current_url );

			$this->_scanLinks( $dom );
			$this->_scanImages( $dom );
		}

		// TODO: Redirect, display table, etc
		echo '<pre>';
		var_dump( $this->crawled_internal_urls, $this->found_internal_urls, $this->external_urls, $this->images );
		//$avg_page_load, $avg_word_count, $avg_title_length
	}

	/**
	 * @return int
	 */
	public function getTotalCrawled() : int {
		return count( $this->crawled_internal_urls );
	}

	/**
	 * @return int
	 */
	public function getMaxPages() : int {
		return $this->max_pages;
	}

	public function getTotalFound() {
		return count( $this->found_internal_urls );
	}

	/**
	 * @return string
	 */
	private function _popPage() : string {
		$url = array_shift( $this->found_internal_urls );

		$this->crawled_internal_urls [] = $url;

		return $url;
	}

	/**
	 * @param \DOMDocument $dom
	 */
	private function _scanLinks( DOMDocument $dom ) : void {
		$anchors = $dom->getElementsByTagName( 'a' );
		foreach ( $anchors as $element ) {
			$href = $element->getAttribute( 'href' );
			$this->_discoverUrl( $href );

			// TODO: Log results
		}
	}

	/**
	 * Parse a url and add it to the lists if relevant.
	 *
	 * @param string $href
	 */
	private function _discoverUrl( string $href ) : void {
		$href = $this->_normalizeUrl( $href );

		//TODO: Optimize lookup.  Hashtable?
		if ( $this->isSameDomain( $href ) ) {
			if ( ! in_array( $href, $this->found_internal_urls ) && ! in_array( $href, $this->crawled_internal_urls ) ) {
				$this->found_internal_urls[] = $href;
			}
		} else {
			if ( ! in_array( $href, $this->external_urls ) ) {
				$this->external_urls[] = $href;
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	private function _normalizeUrl( string $url ) : string {
		$url = trim( $url );
		if ( empty( $url ) || '#' === $url || false !== strpos( $url, 'javascript:' ) ) {
			return $this->current_url;
		}

		// Normalize http/s to whatever base url was set as
		$normalized_url = $this->https . '://';

		$parsed = parse_url( $url );
		// If url is relative, process link relative to current url
		if ( empty( $parsed['host'] ) ) {
			if ( 0 === strpos( $parsed['path'], '/' ) ) {
				// Relative to root
				$normalized_url .= $this->base_domain;
			} else {
				// Relative to current page
				// Drop everything after the last /
				$normalized_url .= preg_replace( '#/[^/]*$#', '', $this->current_url );
			}
		} else {
			$normalized_url .= $parsed['host'];
		}

		if ( ! empty( $parsed['path'] ) ) {
			$normalized_url .= $parsed['path'];
		}

		// Drop any trailing slashes to treat www.foo.com and www.foo.com/ as the same
		$normalized_url = rtrim( $normalized_url, '/' );

		// If there's a query string, re-append it
		if ( ! empty( $parsed['query'] ) ) {
			$normalized_url .= '?' . $parsed['query'];
		}

		return $normalized_url;
	}

	/**
	 * Checks if a URL is an internal link or an external link.
	 *
	 * @param string $href
	 *
	 * @return bool
	 */
	public function isSameDomain( string $href ) : bool {
		$parsed = parse_url( $href );

		return empty( $parsed['host'] ) || $parsed['host'] === $this->base_domain;
	}

	private function _scanImages( DOMDocument $dom ) : void {
		$images = $dom->getElementsByTagName( 'img' );
		foreach ( $images as $image ) {
			$src = $this->_normalizeUrl( $image->getAttribute( 'src' ) );
			if ( empty( $this->images[ $src ] ) ) {
				$this->images[ $src ] = 1;
			} else {
				$this->images[ $src ]++;
			}

			// TODO: Log results
		}
	}

	/**
	 * @return string
	 */
	public function getOutputMethod() : string {
		return $this->output_method;
	}
}