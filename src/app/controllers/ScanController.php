<?php
declare( strict_types=1 );

class ScanController extends ControllerBase {

	/**
	 * @param int|null $id
	 */
	public function showAction( $id = null ) {
		if ( empty( $id ) ) {
			return $this->indexAction();
		}

		$this->renderScanSummaryHtmlTable( $id );
	}

	public function indexAction() {
		// Show a table of all scans performed, with links to their inners.
		$this->renderScansHtmlTable();
	}

	/**
	 * TODO: extract out to view
	 *
	 * @param $scan_id
	 */
	public function renderScanSummaryHtmlTable( $scan_id ) {
		$scan  = Scan::findFirst( $scan_id );
		$pages = Page::find( "scan_id = {$scan_id}" );

		$total_pages_crawled = count( $pages );

		$total_load_time = $total_word_count = $total_title_length = 0;

		printf( '<h2>Scan %d (%s)</h2>', $scan->id, $scan->timestamp );
		printf( '<p><strong>Pages Crawled</strong>: %d</p>', $total_pages_crawled );
		printf( '<p><strong>Unique Internal Links</strong>: %d</p>', $scan->internal_links );
		printf( '<p><strong>Unique External Links</strong>: %d</p>', $scan->external_links );
		printf( '<p><strong>Unique Images</strong>: %d</p>', $scan->images );

		$labels     = array(
			'load_time'      => 'Load Time',
			'status_code'    => 'Status Code',
			'internal_links' => 'Unique Internal Links',
			'external_links' => 'Unique External Links',
			'images'         => 'Unique Images',
			'word_count'     => 'Word Count',
			'title_length'   => 'Title Length',
		);
		$page_table = '<table><tr><th>Page URL</th><th>';
		$page_table .= implode( '</th><th>', $labels );
		$page_table .= '</th></tr>';
		/** @var Page $page */
		foreach ( $pages as $page ) {
			// This should really be a method of the model, doing a database query instead of a loop add
			$total_load_time    += $page->load_time;
			$total_word_count   += $page->word_count;
			$total_title_length += $page->title_length;

			$page_table .= "<tr><th><a href='{$page->url}'>{$page->url}</a></th><td>";
			$page_table .= implode( '</td><td>', $page->toArray( array_keys( $labels ) ) );
			$page_table .= '</td></tr>';
		}
		$page_table .= '</table>';

		printf( '<p><strong>Average Page Load</strong>: %s</p>', round( $total_load_time / $total_pages_crawled, 2 ) );
		printf( '<p><strong>Average Word Count</strong>: %s</p>', round( $total_word_count / $total_pages_crawled, 2 ) );
		printf( '<p><strong>Average Title Length</strong>: %s</p>', round( $total_title_length / $total_pages_crawled, 2 ) );

		echo $page_table;
	}

	/**
	 * TODO: extract out to view
	 */
	public function renderScansHtmlTable() {
		$scans = Scan::find();

		$scan_table = '<table><tr><th>Scan Start URL</th><th>Date</th><th>Pages Scanned</th><th>Unique Internal Links</th><th>Unique External Links</th><th>Unique Images</th></tr>';
		foreach ( $scans as $scan ) {
			$scan_table .= '<tr>';
			$scan_table .= "<th><a href='show/{$scan->id}'>{$scan->base_url}</a></th>";
			$scan_table .= '<td>' . date( 'D M j', strtotime( $scan->timestamp ) ) . '</td>';
			$scan_table .= "<td>{$scan->pages_scanned}</td>";
			$scan_table .= "<td>{$scan->internal_links}</td>";
			$scan_table .= "<td>{$scan->external_links}</td>";
			$scan_table .= "<td>{$scan->images}</td>";

			$scan_table .= '</tr>';
		}
		$scan_table .= '</table>';

		echo $scan_table;
	}

	public function newAction() {
		$crawler = new Crawler( 'www.agencyanalytics.com' );
		$crawler->crawl();;
	}
}

