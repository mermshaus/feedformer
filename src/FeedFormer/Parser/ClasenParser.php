<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer\Parser;

use DateTime;
use DateTimeZone;
use DOMDocument;
use DOMXPath;

use FeedFormer\AppContext;
use FeedFormer\ChannelInfo;
use FeedFormer\CommentItem;
use FeedFormer\FeedFormerException;
use FeedFormer\Item as CRItem;

use FeedFormer\Parser\AbstractParser;

/**
 *
 */
class ClasenParser extends AbstractParser
{
    /**
     *
     * @var AppContext
     */
    protected $appContext;

    /**
     *
     * @param AppContext $appContext
     */
    public function __construct(AppContext $appContext)
    {
        parent::__construct();

        $this->appContext = $appContext;
    }

    /**
     *
     * @param string $url
     * @return array
     */
    public function getData($url)
    {
        $s = $this->getUrl($url);

        $channelInfo = new ChannelInfo();
        $channelInfo->title = 'ClasenParser output';
        $channelInfo->description = 'Can we fill this with meaningful content?';
        $channelInfo->language = 'en';

        $doc = new DOMDocument();
        $doc->loadXML($s);

        $entries = array();

        if ($doc->hasChildNodes()) {
            $domThreads = $doc->childNodes->item(0);
            $threads = array();

            for ($i = 0; $i < $domThreads->childNodes->length; $i++) {
                $child = $domThreads->childNodes->item($i);

                if ($child->nodeName == 'thread') {
                    $threads[] = $domThreads->childNodes->item($i);
                }
            }

            foreach ($threads as $thread) {
                $entry = new CRItem();

                $entry->guid = $thread->attributes->getNamedItem('id')->nodeValue;

                for ($i = 0; $i < $thread->childNodes->length; $i++) {
                    $child = $thread->childNodes->item($i);
                    if ($child->nodeName == 'title') {
                        //$entry->title = htmlspecialchars_decode($child->nodeValue);
                        $entry->title = $child->nodeValue;
                    } elseif ($child->nodeName == 'owner') {
                        $entry->dcCreator = $child->nodeValue;
                    } elseif ($child->nodeName == 'created') {
                        $entry->pubDate = DateTime::createFromFormat(
                            'U',
                            strtotime(substr(str_replace('.', '-', $child->nodeValue), 0, strlen($child->nodeValue) - 3)),
                            new DateTimeZone('UTC')
                        );
                    } elseif ($child->nodeName == 'lastposter') {
                        #$entry->lastPostAuthor = $child->nodeValue;
                    } elseif ($child->nodeName == 'lastpost') {
                        #$entry->lastPostDate = strtotime(substr(str_replace('.', '-', $child->nodeValue), 0, strlen($child->nodeValue) - 3));
                    } elseif ($child->nodeName == 'intro') {
                        $entry->description = htmlspecialchars_decode($child->nodeValue);
                    } elseif ($child->nodeName == 'replies') {
                        $entry->slashComments = $child->nodeValue;
                    } elseif ($child->nodeName == 'views') {
                        #$entry->hits = $child->nodeValue;
                    } elseif ($child->nodeName == 'url') {
                        $entry->link = $child->nodeValue;
                    }
                }

                $entry->categories = array();

                $entries[] = $entry;
            }
        }

        return array($channelInfo, $entries);
    }

    /**
     *
     * @param string $threadUrl
     */
    public function getDataHorizontal($threadUrl)
    {
        throw new FeedFormerException('Not implemented');
    }
}
