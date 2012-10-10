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
class VBulletin4Parser extends AbstractParser
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
     * @see http://www.php.net/manual/en/class.domelement.php#86803
     * @param mixed $elem
     */
    protected function _getInnerHtml($elem)
    {
        $innerHtml = '';

        foreach ($elem->childNodes as $child) {
            $tmp_doc = new DOMDocument();
            $tmp_doc->appendChild($tmp_doc->importNode($child,true));
            $innerHtml .= $tmp_doc->saveHTML();
        }

        return $innerHtml;
    }

    /**
     *
     * @param <type> $obj
     * @param <type> $xpath
     * @param <type> $context
     * @return <type>
     */
    protected function _getFirstMatch($obj, $xpath, $context)
    {
        $node = null;
        foreach ($obj->query($xpath, $context) as $node) { break; }
        return $node;
    }

    /**
     *
     * @param string $s
     * @return string
     */
    protected function _sanitize($s)
    {
        return trim(html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
    }

    /**
     *
     * @see    http://pivotallabs.com/users/alex/blog/articles/427-xpath-css-class-matching
     * @param  string $className
     * @return string
     */
    protected function _containsClassName($className)
    {
        return "contains(concat(' ',normalize-space(@class),' '),' " . $className . " ')";
    }

    public function getData($url)
    {
        $this->url = $url;
        $channelInfo = new ChannelInfo();

        $s = $this->getUrl($url);

        $doc = new DOMDocument();
        $shutUp = libxml_use_internal_errors(true);
        $doc->loadHTML($s);
        libxml_use_internal_errors($shutUp);
        $xpath = new DOMXPath($doc);

        $channelInfo->title = parse_url($url, PHP_URL_HOST) . ' posts';
        $channelInfo->description = 'Latest posts from ' . parse_url($url, PHP_URL_HOST) . '.';

        $elem = $xpath->query('/html[@lang]');
        $lang = 'en-US';

        if ($elem->length > 0) {
            $lang = $elem->item(0)->getAttribute('lang');
        }

        $channelInfo->language = $lang;

        $entries = array();

        foreach ($xpath->query('id("searchbits")/li') as $node) {
            $entry = new StreamEntry();

            $parts = explode('_', $node->getAttribute('id'));

            if (!isset($parts[1])) {
                continue;
            }

            $entry->id = $parts[1];

            $entry->title = $this->_sanitize(
                $this->_getInnerHtml(
                    $this->_getFirstMatch(
                        $xpath,
                        'id("thread_title_' . $entry->id . '")',
                        $node)));

            $entry->desc = $this->_sanitize(
                $this->_getFirstMatch(
                    $xpath,
                    'id("thread_title_' . $entry->id . '")',
                    $node)
                ->getAttribute('title'));

            $entry->threadUrl = 'http://www.html.de/'
                    . $this->_sanitize(
                        $this->_getFirstMatch(
                            $xpath,
                            'id("thread_title_' . $entry->id . '")',
                            $node
                        )->getAttribute('href'));

            $entry->author = $this->_sanitize(
                $this->_getInnerHtml(
                    $this->_getFirstMatch(
                        $xpath,
                        './/div[@class="threadmeta"]/div[@class="author"]//a['
                            . $this->_containsClassName('username') . ']',
                        $node
                    )
                )
            );

            $entry->subforum = $this->_sanitize($this->_getInnerHtml($this->_getFirstMatch($xpath,
                    './/div[' . $this->_containsClassName('threadpostedin') . ']//a[1]', $node)));

            $entry->posts = $this->_sanitize(
                $this->_getInnerHtml(
                    $this->_getFirstMatch(
                        $xpath,
                        './/ul[' . $this->_containsClassName('threadstats') . '][1]/li[1]/a[1]',
                        $node)));
            #$parts = explode(':', $entry->posts);
            #$entry->posts = trim($parts[1]);

            $entry->hits = $this->_sanitize(
                $this->_getInnerHtml(
                    $this->_getFirstMatch(
                        $xpath,
                        './/ul[' . $this->_containsClassName('threadstats') . '][1]/li[2]',
                        $node)));
            $parts = explode(':', $entry->hits);
            $entry->hits = trim($parts[1]);

            $entry->lastPostAuthor = $this->_sanitize(
                $this->_getInnerHtml(
                    $this->_getFirstMatch(
                        $xpath,
                        './/dl['.$this->_containsClassName('threadlastpost').']/dd[2]/a[1]',
                        $node)));

            $entry->lastPostUrl = 'http://www.html.de/' . $this->_sanitize(
                    $this->_getFirstMatch(
                        $xpath,
                        './/dl['.$this->_containsClassName('threadlastpost').']/dd[2]/a[2]',
                        $node)->getAttribute('href'));

            preg_match('/\d+$/', $entry->lastPostUrl, $matches);
            $entry->lastPostId = $matches[0];

            $entry->lastPostDate = $this->_sanitize(strip_tags(
                $this->_getInnerHtml(
                    $this->_getFirstMatch(
                        $xpath,
                        './/dl['.$this->_containsClassName('threadlastpost').']/dd[1]',
                        $node))));

            $entry->lastPostDate = str_replace('Letzter Beitrag:', '', $entry->lastPostDate);
            $entry->lastPostDate = str_replace('Heute', date('d-m-Y'), $entry->lastPostDate);
            $entry->lastPostDate = str_replace('Gestern', date('d-m-Y', time() - 86400), $entry->lastPostDate);
            $entry->lastPostDate = trim(preg_replace('/\s{2,}/', ' ', $entry->lastPostDate));
            $entry->lastPostDate = strtotime($entry->lastPostDate) + 60*60; // Sommerzeit

            $entries[] = $entry;
        }

        $ne = array();

        foreach ($entries as $entry) {
            $ne[] = $this->convert($entry);
        }

        return array($channelInfo, $ne);
    }

    public function getDataHorizontal($threadUrl)
    {
        $this->url = $threadUrl;

        $commentItems = array();
        $content = $this->getUrl($threadUrl);

        $save = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML($content);

        $xpath = new DOMXPath($doc);

        foreach ($xpath->query('//*[@id="postlist"]/li['.$this->_containsClassName('postbit').']') as $postbit) {
            $ci = new CommentItem();

            foreach ($xpath->query('./div[@class="header"]/div[@class="datetime"]', $postbit) as $tmp) {
                $ci->pubDate = DateTime::createFromFormat('d.m.Y, H:i', trim($tmp->nodeValue));
            }

            foreach ($xpath->query('./div[@class="header"]/span[@class="username"]', $postbit) as $tmp) {
                $ci->dcCreator = trim($tmp->nodeValue);
            }

            // Title

            $nodes = $xpath->query('./div[@class="title"]', $postbit);

            if ($nodes->length === 1) {
                $ci->title = trim($nodes->item(0)->nodeValue);
            } else {
                $ci->title = '';
            }

            $nodes = $xpath->query('./div[@class="content"]', $postbit);

            if ($nodes->length === 1) {
                $ci->description = trim($nodes->item(0)->nodeValue);
                $ci->contentEncoded = trim($nodes->item(0)->nodeValue);
            } else {
                $ci->description = '';
                $ci->contentEncoded = '';
            }

            $commentItems[] = $ci;
        }

        libxml_use_internal_errors($save);

        $channelInfo = new ChannelInfo();

        $channelInfo->title = 'html.de - Neueste Kommentare zu ' . $threadUrl;
        $channelInfo->description = 'Das große deutsche HTML-Forum.';
        $channelInfo->language = 'de-DE';

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
            'type' => 'vbulletin4',
            'url' => substr($item->link, 0, -5) . '-print.html')
        );

        $item->slashComments = $entry->posts;

        return $item;
    }
}
