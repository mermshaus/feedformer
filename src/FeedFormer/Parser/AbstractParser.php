<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer\Parser;

use Buzz\Browser;

/**
 *
 */
abstract class AbstractParser
{
    /**
     *
     * @var Browser
     */
    private $_httpClient = null;

    public function __construct()
    {
        $this->_httpClient = new Browser();
    }

    protected function getUrl($url)
    {
        return $this->_httpClient->get($url)->getContent();

    }

    abstract public function getData($url);

    abstract public function getDataHorizontal($threadUrl);
}
