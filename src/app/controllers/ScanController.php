<?php
declare( strict_types=1 );

class ScanController extends ControllerBase {

	/**
	 * @param int|null $id
	 */
	public function showAction( $id = null ) {
		if ( empty( $id ) ) {
			// TODO: Is this intended to be a redirect instead?  hmm.
			return $this->indexAction();
		}

		$condition = 'scan_id  = ' . $id;

		$this->view->cols = array(
			'load_time'      => 'Load Time',
			'status_code'    => 'Status Code',
			'internal_links' => 'Unique Internal Links',
			'external_links' => 'Unique External Links',
			'images'         => 'Unique Images',
			'word_count'     => 'Word Count',
			'title_length'   => 'Title Length',
		);

		$this->view->scan          = Scan::findFirst( $id );
		$this->view->pages         = Page::find( $condition );
		$this->view->pages_crawled = count( $this->view->pages );

		$this->view->average_load_time    = round( Page::average( [
			'column'     => 'load_time',
			'conditions' => $condition,
		] ), 3 );
		$this->view->average_word_count   = round( Page::average( [
			'column'     => 'word_count',
			'conditions' => $condition,
		] ), 0 );
		$this->view->average_title_length = round( Page::average( [
			'column'     => 'title_length',
			'conditions' => $condition,
		] ), 0 );
	}

	public function indexAction() {
		// Show a table of all scans performed, with links to their inners.
		$this->view->scans = Scan::find();
	}

	public function newAction() {
		$crawler = new Crawler( 'www.agencyanalytics.com' );
		$crawler->crawl();;
	}
}

