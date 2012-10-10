<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer;

/**
 *
 *
 * Please note: All dates have to be Unix timestamps, all strings have to be
 * clean UTF-8 with all HTML entities resolved
 */
class StreamEntry
{
    /* These values refer to the thread */

    /**
     * Thread title
     *
     * @var string
     */
    public $title = '';

    /**
     * Thread id
     *
     * @var string
     */
    public $id;
    public $desc;
    public $author;
    public $threadUrl;
    public $threadDate;
    public $posts;
    public $hits;

    /* These values refer to the last post */
    public $lastPostAuthor;
    public $lastPostDate;
    public $lastPostId;
    public $lastPostUrl;

    /**
     * Board origin
     *
     * Do not set this in parsers. It is set on application level
     *
     * @var string
     */
    public $origin;

    /**
     * Name of the subforum in which the entry has been posted
     *
     * (Added on 2010-Feb-16, not yet widely implemented)
     *
     * @var string
     */
    public $subforum = '';
}
