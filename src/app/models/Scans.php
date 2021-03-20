<?php

class Scans extends \Phalcon\Mvc\Model
{

    /**
     *
     * @var integer
     */
    public $id;

    /**
     *
     * @var string
     */
    public $url;

    /**
     *
     * @var string
     */
    public $timestamp;

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
     * Initialize method for model.
     */
    public function initialize()
    {
        $this->setSchema("agency_analytics_crawler");
        $this->setSource("scans");
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed $parameters
     * @return Scans[]|Scans|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find($parameters = null): \Phalcon\Mvc\Model\ResultsetInterface
    {
        return parent::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed $parameters
     * @return Scans|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst($parameters = null)
    {
        return parent::findFirst($parameters);
    }

}
