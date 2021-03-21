<?php
declare( strict_types=1 );

use Phalcon\Forms\Element\Numeric;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Form;
use Phalcon\Validation\Validator\Between as BetweenValidator;
use Phalcon\Validation\Validator\Url as UrlValidator;

class ScanController extends ControllerBase {

	/**
	 * @param int|null $id
	 */
	public function showAction( $id = null ) {
		if ( empty( $id ) ) {
			$response = new Phalcon\Http\Response();
			$response->redirect( 'scan' );
			$response->send();
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
		$this->view->form = new Form();

		$url = new Text( 'url', [ 'placeholder' => 'www.url.com' ] );
		$url->addValidator( new UrlValidator( [ 'message' => 'Crawler target must be a valid url' ] ) );
		$this->view->form->add( $url );

		$max_pages = new Numeric( 'max_pages', [
			'min'         => 1,
			'step'        => 1,
			'placeholder' => 'Page limit',
		] );
		$max_pages->addValidator( new BetweenValidator( [
			'message' => 'Page limit must be a sane value',
			'minimum' => 1,
			'maximum' => 100,
		] ) );
		$this->view->form->add( $max_pages );

		if ( $this->view->form->isValid( $_POST ) ) {
			$crawler = new Crawler( $this->view->form->getValue( 'url' ), $this->view->form->getValue( 'max_pages' ) );
			$crawler->crawl();
		}
	}
}

