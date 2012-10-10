<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

use FeedFormer\Application;

use Symfony\Component\HttpFoundation\Request;

error_reporting(-1);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

$feedFormer = new Application();
$response = $feedFormer->dispatch(Request::createFromGlobals());
$response->send();
