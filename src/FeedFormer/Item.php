<?php
/**
 * FeedFormer (http://www.ermshaus.org)
 *
 * @license MIT License
 */

namespace FeedFormer;

use DateTime;

class Item
{
    /**
     *
     * @var string
     */
    public $title;

    /**
     *
     * @var string
     */
    public $link;

    /**
     *
     * @var string
     */
    public $comments;

    /**
     *
     * @var DateTime
     */
    public $pubDate;
    public $dcCreator;
    public $guid;
    public $description;
    public $contentEncoded;
    public $categories;
    public $wfwCommentRss;
    public $slashComments;
}
