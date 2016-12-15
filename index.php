<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

use FeedFormer\Application;

use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/vendor/autoload.php';

$feedFormer = new Application();
$response = $feedFormer->dispatch(Request::createFromGlobals());
$response->send();
