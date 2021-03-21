<?php

use Phalcon\Mvc\ModelInterface;

class Page extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var integer
     */
    public $scan_id;

	/**
	 *
	 * @var string
	 */
	public $url;

    /**
     *
     * @var double
     */
    public $load_time;

    /**
     *
     * @var string
     */
    public $status_code;

    /**
     *
     * @var integer
     */
    public $internal_links;

    /**
     *
     * @var integer
     */
    public $external_links;

	/**
	 *
	 * @var integer
	 */
	public $images;

    /**
     *
     * @var integer
     */
    public $word_count;

    /**
     *
     * @var integer
     */
    public $title_length;

	/**
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("agency_analytics_crawler");
        $this->setSource("pages");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     *
     * @return Page[]|Page|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     *
     * @return Page|\Phalcon\Mvc\Model\ResultInterface
     */
	public static function findFirst($parameters = null): ?ModelInterface
    {
        return parent::findFirst($parameters);
    }

}
