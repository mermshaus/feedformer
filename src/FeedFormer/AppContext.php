<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer;

class AppContext
{
    public function getVersion()
    {
        return '0.1.0';
    }

    public function url($route, array $args)
    {
        $url = 'http://' . $_SERVER['SERVER_NAME'];
        $url .= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $args = array_merge(array('action' => $route), $args);

        return $url . '?' . http_build_query($args);
    }
}
