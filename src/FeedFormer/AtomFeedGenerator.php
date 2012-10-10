<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer;

use DateTime;
use DateTimeZone;
use DOMDocument;
use DOMElement;

use FeedFormer\AppContext;
use FeedFormer\ChannelInfo;
use FeedFormer\Item as CRItem;

class AtomFeedGenerator
{
    /**
     *
     * @var DOMDocument
     */
    private $document;

    protected $appContext;

    public function __construct(AppContext $appContext)
    {
        $this->appContext = $appContext;
    }

    private function generateChannelElement(ChannelInfo $channelInfo)
    {
        $doc = $this->document;

        $e = function ($title, $value) use ($doc) {
            $elem = $doc->createElement($title);
            $text = $doc->createTextNode($value);
            $elem->appendChild($text);
            return $elem;
        };

        /* @var $channel DOMElement */
        $channel = $doc->createElement('channel');

        $channel->appendChild($e('title', $channelInfo->title));

        $node = $this->document->createElement(
            'atom:link'
        );
        $node->setAttribute('href', 'http://example.org/feed');
        $node->setAttribute('rel', 'self');
        $node->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($node);

        $channel->appendChild($e('link', 'http://example.org/'));
        $channel->appendChild($e('description', $channelInfo->description));

        $date = new DateTime('now', new DateTimeZone('UTC'));
        $channel->appendChild($e('lastBuildDate', $date->format(DateTime::RFC2822)));

        $channel->appendChild($e('language', $channelInfo->language));

        $channel->appendChild($e('sy:updatePeriod', 'hourly'));

        $channel->appendChild($e('sy:updateFrequency', '1'));

        $channel->appendChild($e('generator', 'FeedFormer ' . $this->appContext->getVersion()));

        return $channel;
    }

    private function generateItemElement(CRItem $data)
    {
        $doc = $this->document;

        $e = function ($title, $value) use ($doc) {
            $elem = $doc->createElement($title);
            $text = $doc->createTextNode($value);
            $elem->appendChild($text);
            return $elem;
        };

        $c = function ($title, $value) use ($doc) {
            $cdata = $doc->createCDATASection($value);
            $temp = $doc->createElement($title);
            $temp->appendChild($cdata);
            return $temp;
        };

        $item = $doc->createElement('item');

        $item->appendChild($e('title', $data->title));
        $item->appendChild($e('link', $data->link));
        $item->appendChild($e('comments', $data->comments));
        $item->appendChild($e('pubDate', $data->pubDate->format(DateTime::RFC2822)));
        $item->appendChild($e('dc:creator', $data->dcCreator));

        $temp = $e('guid', $data->guid);
        $temp->setAttribute('isPermaLink', 'false');
        $item->appendChild($temp);

        foreach ($data->categories as $category) {
            $item->appendChild($c('category', $category));
        }

        $item->appendChild($c('description', $data->description));
        $item->appendChild($c('content:encoded', $data->contentEncoded));
        $item->appendChild($e('wfw:commentRss', $data->wfwCommentRss));
        $item->appendChild($e('slash:comments', $data->slashComments));

        return $item;
    }

    public function f(ChannelInfo $channelInfo, array $items)
    {
        $namespaces = array(
            'content' => 'http://purl.org/rss/1.0/modules/content/',
            'wfw'     => 'http://wellformedweb.org/CommentAPI/',
            'dc'      => 'http://purl.org/dc/elements/1.1/',
            'atom'    => 'http://www.w3.org/2005/Atom',
            'sy'      => 'http://purl.org/rss/1.0/modules/syndication/',
            'slash'   => 'http://purl.org/rss/1.0/modules/slash/'
        );

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $this->document = $doc;

        $rss = $doc->createElement('rss');

        $rss->setAttribute('version', '2.0');

        foreach ($namespaces as $prefix => $uri) {
            $rss->setAttribute('xmlns:' . $prefix, $uri);
        }

        $doc->appendChild($rss);

        $channel = $this->generateChannelElement($channelInfo);

        $rss->appendChild($channel);

        foreach ($items as $data) {
            $channel->appendChild($this->generateItemElement($data));
        }

        return $doc->saveXML();
    }
}
