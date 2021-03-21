<?php

class Crawler {
	/**
	 * @var \Scan
	 */
	protected $scan;
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
		// Follow redirects to get canonical url
		$base_url          = $this->getEffectiveUrl( $base_url );
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
	 * @param string $base_url
	 *
	 * @return string
	 * @throws \Phalcon\Exception
	 */
	public function getEffectiveUrl( string $base_url ) : string {
		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $base_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

		curl_exec( $ch );
		if ( curl_errno( $ch ) ) {
			throw new \Phalcon\Exception( curl_error( $ch ) );
		}
		$effective_url = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
		curl_close( $ch );

		return rtrim( $effective_url, '/' );
	}

	/**
	 * @param string $output_method
	 *
	 * @throws \Exception
	 */
	public function setOutputMethod( $output_method ) : void {
		if ( ! in_array( $output_method, [ 'database', 'html' ] ) ) {
			throw new \Phalcon\Exception( 'Invalid output method.' );
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
		$this->scan            = new Scan();
		$this->scan->timestamp = date( 'Y-m-d H:i:s' );
		$this->scan->base_url  = $this->base_url;
		// Save to get initial ID
		$this->scan->save();
		while ( $this->getTotalCrawled() <= $this->getMaxPages() && $this->getTotalFound() > 0 ) {
			$this->current_url = $this->_popPage();

			list( $result, $load_time, $status_code ) = $this->getData( $this->current_url );

			$dom = new DOMDocument( '1.0' );
			@$dom->loadHTML( $result );

			$link_data  = $this->_scanLinks( $dom );
			$image_data = $this->_scanImages( $dom );

			$page                 = new Page();
			$page->scan_id        = $this->scan->id;
			$page->url            = $this->current_url;
			$page->load_time      = $load_time;
			$page->status_code    = $status_code;
			$page->internal_links = count( $link_data['internal'] );
			$page->external_links = count( $link_data['external'] );
			$page->images         = count( $image_data );
			$page->word_count     = $this->countWords( $result );
			$page->title_length   = $this->countTitle( $dom );
			$page->save();
		}

		$this->scan->pages_scanned  = count( $this->crawled_internal_urls );
		$this->scan->internal_links = count( $this->found_internal_urls ) + count( $this->crawled_internal_urls );
		$this->scan->external_links = count( $this->external_urls );
		$this->scan->images         = count( $this->images );
		$this->scan->save();

		$response = new Phalcon\Http\Response();
		$response->redirect( 'scan/show/' . $this->scan->id );
		$response->send();
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
	 * @param string $url
	 *
	 * @return array
	 */
	private function getData( string $url ) : array {
		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

		$result      = curl_exec( $ch );
		$load_time   = curl_getinfo( $ch, CURLINFO_TOTAL_TIME );
		$status_code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );

		return array( $result, $load_time, $status_code );
	}

	/**
	 * @param \DOMDocument $dom
	 *
	 * @return array
	 */
	private function _scanLinks( DOMDocument $dom ) : array {
		$anchors         = $dom->getElementsByTagName( 'a' );
		$this_page_links = array(
			'internal' => array(),
			'external' => array(),
		);

		foreach ( $anchors as $element ) {
			$href = $this->_normalizeUrl( $element->getAttribute( 'href' ) );

			// TODO:  respect robots.txt in scan?
			// TODO: Optimize lookup.  Hashtable?
			if ( $this->isSameDomain( $href ) ) {
				if ( empty( $this_page_links['internal'][ $href ] ) ) {
					$this_page_links['internal'][ $href ] = 1;
				} else {
					$this_page_links['internal'][ $href ]++;
				}
				if ( ! in_array( $href, $this->found_internal_urls ) && ! in_array( $href, $this->crawled_internal_urls ) ) {
					$this->found_internal_urls[] = $href;
				}
			} else {
				if ( empty( $this_page_links['external'][ $href ] ) ) {
					$this_page_links['external'][ $href ] = 1;
				} else {
					$this_page_links['external'][ $href ]++;
				}
				if ( ! in_array( $href, $this->external_urls ) ) {
					$this->external_urls[] = $href;
				}
			}
		}

		return $this_page_links;
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	private function _normalizeUrl( string $url ) : string {
		$url = trim( $url );
		if ( empty( $url ) || 0 === strpos( $url, '#' ) || false !== strpos( $url, 'javascript:' ) ) {
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

	private function _scanImages( DOMDocument $dom ) : array {
		$images           = $dom->getElementsByTagName( 'img' );
		$this_page_images = array();
		foreach ( $images as $image ) {
			$src = $this->_normalizeUrl( $image->getAttribute( 'src' ) );
			if ( empty( $this->images[ $src ] ) ) {
				$this->images[ $src ] = 1;
			} else {
				$this->images[ $src ]++;
			}

			if ( empty( $this_page_images[ $src ] ) ) {
				$this_page_images[ $src ] = 1;
			} else {
				$this_page_images[ $src ]++;
			}
		}

		return $this_page_images;
	}

	/**
	 * @param string $result
	 *
	 * @return int
	 */
	private function countWords( string $result ) : int {
		return str_word_count( strip_tags( $result ) );
	}

	/**
	 * @param \DOMDocument $dom
	 *
	 * @return int
	 */
	private function countTitle( DOMDocument $dom ) : int {
		return str_word_count( $dom->getElementsByTagName( 'title' )->item( 0 )->textContent );
	}

	/**
	 * @return string
	 */
	public function getOutputMethod() : string {
		return $this->output_method;
	}
}