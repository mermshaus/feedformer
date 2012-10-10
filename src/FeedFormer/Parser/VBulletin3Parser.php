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
use FeedFormer\Item as CRItem;
use FeedFormer\StreamEntry;

use FeedFormer\Parser\AbstractParser;

/**
 *
 */
class VBulletin3Parser extends AbstractParser
{
    /**
     *
     * @var AppContext
     */
    protected $appContext;

    protected $url;

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
     *
     * @return unknown
     */
    public function getData($url)
    {
        $this->url = $url;
        $channelInfo = new ChannelInfo();

        $s = $this->getUrl($url);

        $s = mb_convert_encoding($s, 'UTF-8', mb_detect_encoding($s, 'UTF-8,ISO-8859-1'));

        $save = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($s);

        libxml_use_internal_errors($save);

        $xp = new DOMXPath($doc);

        $channelInfo->title = parse_url($url, PHP_URL_HOST) . ' posts';
        $channelInfo->description = 'Latest posts from ' . parse_url($url, PHP_URL_HOST) . '.';

        $elem = $xp->query('/html[@lang]');
        $lang = 'en-US';

        if ($elem->length > 0) {
            $lang = $elem->item(0)->getAttribute('lang');
        }

        $channelInfo->language = $lang;

        $entries = array();
        $matches = array();

        preg_match('#<table[^>]*? id="threadslist">(.*?)</table>#s', $s, $matches);

        $data = $matches[1];

        preg_match_all('#<tr>\s*<td class="alt1" id="td_threadstatusicon[^>]*?>(.*?)</tr>#s',
                $data, $matches);

        foreach ($matches[1] as $row) {
            $entries[] = $this->_parseRow($row);
        }

        $ne = array();

        foreach ($entries as $entry) {
            $ne[] = $this->convert($entry);
        }

        return array($channelInfo, $ne);
    }

    /**
     *
     * @param string $s
     * @return StreamEntry
     */
    private function _parseRow($s)
    {
        //echo $s . "\n\n\n------------------\n\n\n";

        $entry = new StreamEntry();
        $matches = array();

        preg_match_all('#<td[^>]*?>(?:.*?)</td>#s', $s, $matches);

        // has to be [0][0] and [0][1]
        // or:       [0][1] and [0][2]

        if (strpos($matches[0][0], '<td class="alt2"') !== 0) {
            $this->_parseColTopic($matches[0][0], $entry);
            $this->_parseColLastPost($matches[0][1], $entry);
            $this->_parseColSubForum($matches[0][4], $entry);
        } else {
            $this->_parseColTopic($matches[0][1], $entry);
            $this->_parseColLastPost($matches[0][2], $entry);
            $this->_parseColSubForum($matches[0][5], $entry);
        }

        return $entry;
    }

    /**
     *
     * @param string $s
     * @param StreamEntry $entry
     */
    private function _parseColSubForum($s, StreamEntry $entry)
    {
        $entry->subforum = strip_tags($s);
    }

    /**
     *
     * @param string $s
     * @param StreamEntry $entry
     */
    private function _parseColTopic($s, StreamEntry $entry)
    {
        $matches = array();
        preg_match('#title="(.*?)">#s', $s, $matches);
        $entry->desc = $matches[1];

        $title_matches = array();
        preg_match('#<div>(.*?)</div>#s', $s, $matches);
        preg_match('#<a href="([^"]*)"[^>]*? id="thread_title_(\d*)">(.*?)</a>#s', $matches[1],
                $title_matches);

        $entry->threadUrl = 'http://' . parse_url($this->url,PHP_URL_HOST) . dirname(parse_url($this->url,PHP_URL_PATH)) . '/' . $title_matches[1];
        $entry->id = $title_matches[2];
        $entry->title = html_entity_decode($title_matches[3]);

        preg_match('#\'_self\'\)">(.*?)</span>#s', $s, $matches);

        if (!isset($matches[1])) {
            // Wahrscheinlich Nutzername entfernt
            $entry->author = 'n/a';
        } else {
            $entry->author = $matches[1];
        }
    }

    /**
     *
     * @param string $s
     * @param StreamEntry $entry
     */
    private function _parseColLastPost($s, StreamEntry $entry)
    {
        $matches = array();
        preg_match('#title="Antworten: ([\d.]*), Hits: ([\d.]*)"#s', $s, $matches);

        $entry->posts = str_replace('.', '', $matches[1]);
        $entry->hits = str_replace('.', '', $matches[2]);

        preg_match('#>\s*(.*?)<br />#s', $s, $matches);

        $entry->lastPostDate = trim(preg_replace('#<[^>]*?>#s', '', $matches[1]));
        $entry->lastPostDate = str_replace('Heute', date('d-m-Y'), $entry->lastPostDate);
        $entry->lastPostDate = str_replace('Gestern', date('d-m-Y', time() - 86400), $entry->lastPostDate);
        $entry->lastPostDate = strtotime($entry->lastPostDate) - 2 * 60 * 60;

        preg_match('#rel="nofollow">(.*?)</a>#s', $s, $matches);
        $entry->lastPostAuthor = trim($matches[1]);

        preg_match('#\#post(\d*)"><img#s', $s, $matches);
        $entry->lastPostId = trim($matches[1]);
        $entry->lastPostUrl = $entry->threadUrl . '#post' . $entry->lastPostId;
    }

    /**
     *
     * @param string $threadUrl
     * @return array Array of CommentItem objects.
     */
    public function getDataHorizontal($threadUrl)
    {
        $this->url = $threadUrl;
        $commentItems = array();
        $content = $this->getUrl($threadUrl);

        $save = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($content);

        $xpath = new DOMXPath($doc);

        foreach ($xpath->query('//body/table[@class="tborder"]') as $node) {
            $ci = new CommentItem();

            foreach ($xpath->query('.//td[@class="page"]', $node) as $node2) {
                $i = 0;
                foreach ($xpath->query('./table//td', $node2) as $node3) {
                    if ($i === 0) {
                        $ci->dcCreator = trim($node3->nodeValue);
                    } elseif ($i === 1) {
                        $ci->pubDate = DateTime::createFromFormat('d.m.Y H:i', trim($node3->nodeValue));
                    }
                    $i++;
                }

                $nodes = $xpath->query('./div', $node2);

                if ($nodes->length > 1) {
                    $ci->title = trim($nodes->item(0)->nodeValue);
                } else {
                    $ci->title = '';
                }

                $ci->description = trim($nodes->item($nodes->length - 1)->nodeValue);
                $ci->contentEncoded = trim($nodes->item($nodes->length - 1)->nodeValue);
            }

            $commentItems[] = $ci;
        }

        libxml_use_internal_errors($save);

        $channelInfo = new ChannelInfo();

        $channelInfo->title = 'Newest comments from ' . $threadUrl;
        $channelInfo->description = 'Description for ' . $threadUrl;
        $channelInfo->language = 'en-US';

        return array($channelInfo, $commentItems);
    }

    /**
     *
     * @param StreamEntry $entry
     * @return CRItem
     */
    private function convert(StreamEntry $entry)
    {
        $item = new CRItem();
        $item->title = $entry->title;
        $item->link = $entry->threadUrl;
        $item->comments = $entry->threadUrl . '/comments';
        if ($entry->threadDate === null) {
            $entry->threadDate = '1970-01-01 12:00:00';
        } else {
            $entry->threadDate = date('Y-m-d H:i:s', $entry->threadDate);
        }
        $item->pubDate = DateTime::createFromFormat('Y-m-d H:i:s', $entry->threadDate, new DateTimeZone('UTC'));

        if ($entry->lastPostDate === null) {
            $entry->lastPostDate = '1970-01-01 12:00:00';
        } else {
            $entry->lastPostDate = date('Y-m-d H:i:s', $entry->lastPostDate);
        }
        $item->pubDate = DateTime::createFromFormat('Y-m-d H:i:s', $entry->lastPostDate, new DateTimeZone('UTC'));

        $item->dcCreator = $entry->author;


        $matches = array();
        if (1 === preg_match('/\/([0-9]+)[^\/]*$/', $entry->threadUrl, $matches)) {
            $item->guid = parse_url($entry->threadUrl, PHP_URL_HOST) . '::thread::' . $matches[1];
        } else {
            $item->guid = $entry->threadUrl;
        }

        $item->description = $entry->desc;
        $item->contentEncoded = $entry->desc;

        $item->categories = array($entry->subforum);

        $item->wfwCommentRss = $this->appContext->url('comments', array(
            'type' => 'vbulletin3',
            'url' => substr($item->link, 0, -5) . '-print.html?pp=40')
        );

        $item->slashComments = $entry->posts;

        return $item;
    }
}
