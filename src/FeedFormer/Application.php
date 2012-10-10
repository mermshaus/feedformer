<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer;

use Exception;

use FeedFormer\AppContext;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 */
class Application
{
    private function sourceBlob(Request $request)
    {
        $response = new Response();

        $args = array(
            'action' => (string) $request->query->get('action'),
            'type'   => (string) $request->query->get('type'),
            'url'    => (string) $request->query->get('url')
        );

        $appContext = new AppContext();
        $parser = null;
        $horizontal = false;
        $invalid = false;

        if ($args['action'] === 'posts') {
            if ($args['type'] === 'vbulletin3') {
                $parser = new Parser\VBulletin3Parser($appContext);
            } elseif ($args['type'] === 'vbulletin4') {
                $parser = new Parser\VBulletin4Parser($appContext);
            } elseif ($args['type'] === 'clasen') {
                $parser = new Parser\ClasenParser($appContext);
            }
        } elseif ($args['action'] === 'comments') {
            if ($args['type'] === 'vbulletin3') {
                $parser = new Parser\VBulletin3Parser($appContext);
            } elseif ($args['type'] === 'vbulletin4') {
                $parser = new Parser\VBulletin4Parser($appContext);
            } elseif ($args['type'] === 'clasen') {
                $parser = new Parser\ClasenParser($appContext);
            }

            $horizontal = true;
        } else {
            $response->setContent('invalid');
            $invalid = true;
        }



        // create a log channel
        //$log = new Logger('access');
        //$log->pushHandler(new StreamHandler(__DIR__.'/data/access.log', Logger::INFO));


        if (!$invalid) {
            $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');
            if (!$horizontal) {
                //$log->addInfo('Posts ' . $args['url']);
                list($channelInfo, $items) = $parser->getData($args['url']);
                $generator = new AtomFeedGenerator($appContext);
            } else {
                //$log->addInfo('Comments ' . $args['url']);
                list($channelInfo, $items) = $parser->getDataHorizontal($args['url']);
                $generator = new AtomFeedGeneratorHorizontal();
            }
            $response->setContent($generator->f($channelInfo, $items));
        }

        return $response;
    }

    /**
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch(Request $request)
    {
        $response = null;

        try {
            $response = $this->sourceBlob($request);
        } catch (Exception $e) {
            $response = new Response();
            $response->setStatusCode(500);
            $response->setContent($e->getMessage());
        }

        $response->prepare($request);

        return $response;
    }
}
