<?php
declare( strict_types=1 );

class ScanController extends ControllerBase {

	public function indexAction() {

	}

	/**
	 * @param int|null $id
	 */
	public function showAction( $id = null, $page = null ) {
		var_dump( $id, $page );
	}

	public function newAction() {
		$crawler = new Crawler( 'www.agencyanalytics.com' );
		$crawler->crawl();;
	}
}

