<?php

use dokuwiki\Utf8\PhpString;

/**
 * DokuWiki Plugin tagfilter (Helper Component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */
class helper_plugin_tagfilter extends DokuWiki_Plugin
{
    /**
     *
     * @var helper_plugin_tag
     */
    protected $taghelper;

    /**
     * Constructor gets default preferences and language strings
     */
    public function __construct()
    {
        $this->taghelper = $this->loadHelper('tag');
    }

    public function getMethods()
    {
        $result = [];
        $result[] = [
            'name' => 'getTagsByRegExp',
            'desc' => 'returns tags for given Regular Expression',
            'params' => [
                'tags (required)' => 'string',
                'namespace (optional)' => 'string',],
            'return' => ['tags' => 'array'],
        ];
        $result[] = [
            'name' => 'getTagsByNamespace',
            'desc' => 'returns tags for given namespace',
            'params' => [
                'namespace' => 'string',],
            'return' => ['tags' => 'array'],
        ];
        $result[] = [
            'name' => 'getTagsByPageID',
            'desc' => 'returns tags for given pageID',
            'params' => [
                'pageID' => 'string',],
            'return' => ['tags' => 'array'],
        ];

        return $result;
    }

    /**
     * Search in Tagindex for tags that matches the tag pattern and are in requested namespace
     *
     * @param string $tagExpression regexp pattern of wanted tags e.g. "status:.*"
     * @param string $ns list only pages from this namespace
     * @param bool $aclSafe if true, add only tags that are on readable pages
     * @return string[]|false with tag=>label pairs
     *
     */
    public function getTagsByRegExp($tagExpression, $ns = '', $aclSafe = false)
    {
        if (!$this->taghelper) {
            return false;
        }

        $tags = $this->getIndex('subject', '_w');

        $matchedTag_label = [];
        foreach ($tags as $tag) {
            if ($this->matchesTagExpression($tagExpression, $tag) && $this->isTagInNamespace($tag, $ns, $aclSafe)) {
                $matchedTag_label[$tag] = $this->getTagLabel($tag);
            }
        }
        asort($matchedTag_label);  //TODO update next release to dokuwiki builtin sort
        return $matchedTag_label;
    }


    /**
     * Test if tag matches with requested pattern
     *
     * @param string $tagExpression regexp pattern of wanted tags e.g. "status:.*"
     * @param string $tag
     * @return bool
     */
    public function matchesTagExpression($tagExpression, $tag)
    {
        return (bool)@preg_match('/^' . $tagExpression . '$/i', $tag);
    }

    /**
     * Returns latest part of tag as label
     *
     * @param string $tag
     * @return string
     */
    public function getTagLabel($tag)
    {
        $label = strrchr($tag, ':');
        $label = $label != '' ? $label : $tag;
        return PhpString::ucwords(str_replace('_', ' ', trim($label, ':')));
    }


    /**
     * Returns all tags used in given namespace
     *
     * @param string $ns list only tags used on pages from this namespace
     * @param bool $aclSafe if true, checks if user has read permission for the pages containing the tags
     * @return array|false|int[]|string[]
     */
    public function getTagsByNamespace($ns = '', $aclSafe = true)
    {
        if (!$this->taghelper) {
            return false;
        }

        return array_keys($this->getTagsByRegExp('.*', $ns, $aclSafe));
    }

    /**
     * Checks if current user can read the given pageid
     *
     * @param string $pageid
     * @return bool
     */
    public function canRead($pageid)
    {
        return auth_quickaclcheck($pageid) >= AUTH_READ;
    }

    /**
     * Returns all tags for the given pageid
     *
     * @param string $pageID
     * @return array|mixed
     */
    public function getTagsByPageID($pageID)
    {
        $meta = p_get_metadata($pageID, 'subject');
        if ($meta === null) {
            $meta = [];
        }
        return $meta;
    }

    /**
     * Returns true if tags are equal
     *
     * @param string $tag1 tag being searched
     * @param string $tag2 tag from index
     * @return bool whether equal tags
     */
    public function tagCompare($tag1, $tag2)
    {
        return $tag1 == $tag2;
    }


    /**
     * Checks if tag is used in the namespace, eventually can consider read permission as well
     *
     * @param string $tag
     * @param string $ns list pages from this namespace
     * @param bool $aclSafe if true, uses tag from a page only if user has read permissions
     * @return bool
     */
    protected function isTagInNamespace($tag, $ns, $aclSafe = true)
    {
        if ($ns == '') {
            return true;
        }
        if (!$this->taghelper) {
            return false;
        }

        $indexer = idx_get_indexer();
        $pages = $indexer->lookupKey('subject', $tag, [$this, 'tagCompare']);

        foreach ($pages as $page) {
            if ($this->taghelper->isVisible($page, $ns)) {
                if (!$aclSafe) {
                    return true;
                }
                if (auth_quickaclcheck($page) >= AUTH_READ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns entire index file as array
     *
     * from inc/indexer.php
     *
     * @param string $idx
     * @param string $suffix
     * @return array|false
     */
    protected function getIndex($idx, $suffix)
    {
        global $conf;
        $fn = $conf['indexdir'] . '/' . $idx . $suffix . '.idx';
        if (!@file_exists($fn)) {
            return [];
        }
        return file($fn, FILE_IGNORE_NEW_LINES);
    }

    /** @var string */
    protected $ps_ns = '';
    /** @var array */
    protected $ps_pages_id = [];
    /** @var array */
    protected $ps_pages = [];

    /**
     * @param string $tag space separated tags
     * @param string $ns list only pages from this namespace
     * @return array
     */
    public function getPagesByTag($tag, $ns = '')
    {
        $tags = explode(' ', $tag);
        $this->startPageSearch($ns);
        foreach ($tags as $t) {
            if ($t[0] == '+') {
                $this->addAndTag(substr($t, 1));
            } elseif ($t[0] == '-') {
                $this->addSubTag(substr($t, 1));
            } else {
                $this->addOrTag($t);
            }
        }
        return $this->getPages();
    }

    /**
     * @param string $ns
     */
    protected function startPageSearch($ns = '')
    {
        $this->ps_ns = $ns;
        $this->ps_pages_id = [];
        $this->ps_pages = [];
    }

    /**
     * @param string $tagExpression regexp pattern of wanted tags e.g. "status:.*"
     */
    protected function addAndTag($tagExpression)
    {
        $tags = $this->getTagsByRegExp($tagExpression, $this->ps_ns);
        $pages = [];
        foreach ($tags as $t => $v) {
            $Hpages = $this->taghelper->getTopic($this->ps_ns, null, $t);
            foreach ($Hpages as $p) {
                $pages[] = $p['id'];
                if (!isset($this->ps_pages[$p['id']])) {
                    $this->ps_pages[$p['id']] = $p;
                }
            }

        }
        $pages = array_unique($pages);
        $this->ps_pages_id = array_intersect($this->ps_pages_id, $pages);
    }

    /**
     * @param string $tagExpression regexp pattern of wanted tags e.g. "status:.*"
     */
    protected function addSubTag($tagExpression)
    {
        $tags = $this->getTagsByRegExp($tagExpression, $this->ps_ns);
        $pages = array();
        foreach ($tags as $t => $v) {
            $Hpages = $this->taghelper->getTopic($this->ps_ns, '', $t);
            foreach ($Hpages as $p) {
                $pages[] = $p['id'];
            }
        }
        $pages = array_unique($pages);
        $this->ps_pages_id = array_diff($this->ps_pages_id, $pages);
    }

    /**
     * @param string $tagExpression regexp pattern of wanted tags e.g. "status:.*"
     * @return void
     */
    protected function addOrTag($tagExpression)
    {
        $tags = $this->getTagsByRegExp($tagExpression, $this->ps_ns);
        $pages = array();
        foreach ($tags as $t => $v) {
            $Hpages = $this->taghelper->getTopic($this->ps_ns, '', $t);
            foreach ($Hpages as $p) {
                $pages[] = $p['id'];
                if (!isset($this->ps_pages[$p['id']])) {
                    $this->ps_pages[$p['id']] = $p;
                }
            }
        }
        $pages = array_unique($pages);
        $this->ps_pages_id = array_merge($this->ps_pages_id, $pages);
        $this->ps_pages_id = array_unique($this->ps_pages_id);
    }

    /**
     * @return array
     */
    protected function getPages()
    {
        $ret = [];
        foreach ($this->ps_pages_id as $id) {
            $ret[] = $this->ps_pages[$id];
        }
        return $ret;
    }

    /**
     * @param string $tag
     * @return false|string
     */
    public function getImageLinkByTag($tag)
    {
        $id = $this->getConf('nsTagImage') . ':' . str_replace([' ', ':'], '_', $tag);
        $src = $id . '.jpg';
        if (!@file_exists(mediaFN($src))) {
            $src = $id . '.png';
            if (!@file_exists(mediaFN($src))) {
                $src = $id . '.jpeg';
                if (!@file_exists(mediaFN($src))) {
                    $src = false;
                }
            }
        }
        if ($src !== false) {
            return ml($src);
        }
        return false;
    }

    /**
     * Generate html for in a cell of the column of the tags as images
     *
     * @param string $id pageid
     * @param string $col tagexpression: regexp pattern of wanted tags e.g. "status:.*"
     * @param string $ns namespace with images
     * @return string html of tagimage(s) in cell
     */
    public function getTagImageColumn($id, $col, $ns)
    {
        if (!isset($this->tagsPerPage[$id])) {
            $this->tagsPerPage[$id] = $this->getTagsByPageID($id);
        }
        $foundTags = [];
        foreach ($this->tagsPerPage[$id] as $tag) {
            if ($this->matchesTagExpression($col, $tag)) {
                $foundTags[] = hsc($this->getTagLabel($tag));
            }
        }
        $images = [];
        foreach ($foundTags as $foundTag) {
            $imageid = $ns . ':' . substr($foundTag, strrpos($foundTag, ':'));

            $src = $imageid . '.jpg';
            if (!@file_exists(mediaFN($src))) {
                $src = $imageid . '.png';
                if (!@file_exists(mediaFN($src))) {
                    $src = $imageid . '.jpeg';
                    if (!@file_exists(mediaFN($src))) {
                        $src = $imageid . '.gif';
                        if (!@file_exists(mediaFN($src))) {
                            $src = false;
                        }
                    }
                }
            }
            if ($src !== false) {
                $images[] = '<img src="' . ml($src) . ' " class="media" style="height:max-width:200px;"/>';
            }
        }

        return implode("<br>", $images);

    }

    /**
     * return all pages defined by tag_list_r in a specific namespace
     *
     * @param string $ns the namespace to look in
     * @param array $tag_list_r an array containing strings with tags seperated by ' '
     *
     */
    public function getAllPages($ns, $tag_list_r)
    {
        $pages = array();
        $pages[''] = '';

        $tag_list = implode(' ', $tag_list_r);

        $page_r = $this->getPagesByTags($ns, $tag_list);

        foreach ($page_r as $page) {
            $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
            $title = $title ?: $page;
            $pages[$page] = strip_tags($title);  //FIXME hsc() doesent work with chosen
        }

        asort($pages);
        return $pages;
    }

    /**
     * Returns page title, otherwise pageid
     *
     * @param string $pageid
     * @return string
     */
    public function getPageTitle($pageid)
    {
        $title = p_get_metadata($pageid, 'title', METADATA_DONT_RENDER);
        $title = $title ?: $pageid;
        return strip_tags($title);
    }

    /**
     * Gets the pages defined by tag_list
     *
     * partially copied from tag->helper with less checks (on cache) and no meta lookups
     * @param string $ns the namespace to look in
     * @param string $tag_list the tags separated by ' '
     *
     * @return array array of page ids
     */
    public function getPagesByTags($ns, $tag_list)
    {
        $tags = $this->taghelper->parseTagList($tag_list, true);
        $matchedPages = $this->taghelper->getIndexedPagesMatchingTagQuery($tags);

        $filteredPages = [];
        foreach ($matchedPages as $matchedPage) {
            // filter by namespace, root namespace is identified with a dot // root namespace is specified, discard all pages who lay outside the root namespace
            if (($ns == '.' && getNS($matchedPage) === false) || strpos(':' . getNS($matchedPage) . ':', ':' . $ns . ':') === 0 || $ns === '') {
                if (auth_quickaclcheck($matchedPage) >= AUTH_READ) {
                    $filteredPages[] = $matchedPage;
                }
            }
        }
        return $filteredPages;
    }

    /**
     * @param $tag
     * @return string
     */
    public function getTagCategory($tag)
    {
        $label = strstr($tag, ':', true);
        $label = $label != '' ? $label : $tag;
        return PhpString::ucwords(str_replace('_', ' ', trim($label, ':')));
    }

    /**
     * Used by pagelist plugin for filling the cell of the table header
     *
     * @param string $column column name is a tagexpression
     * @return string
     */
    public function th($column = '')
    {
        if (strpos($column, '*')) {
            return $this->getTagCategory($column);
        } else {
            return $this->getTagLabel($column);
        }
    }

    /** @var array[] with pageid => array with tags */
    protected $tagsPerPage = [];

    /**
     * Used by pagelist plugin for filling the cells of the table
     * and in listing by the tagfilter
     *
     * @param string $id page id of row
     * @param string $column column name is a tagexpression: regexp pattern of wanted tags e.g. "status:.*". Supported since 2022 in pagelist plugin
     * @return string
     */
    public function td($id, $column = null)
    {
        if($column === null) {
            return '';
        }
        if (!isset($this->tagsPerPage[$id])) {
            $this->tagsPerPage[$id] = $this->getTagsByPageID($id);
        }
        $foundTags = [];
        foreach ($this->tagsPerPage[$id] as $tag) {
            if ($this->matchesTagExpression($column, $tag)) {
                $foundTags[] = hsc($this->getTagLabel($tag));
            }
        }
        return implode("<br>", $foundTags);
    }


    /**
     * Returns per tag the pages where these are used as array with: tag=>array with pages
     * The tags matches the tag regexp pattern and only shown if it is used at pages in requested namespace, these pages
     * are listed in an array per tag
     *
     * Does not check ACL
     *
     * @param string $tags tag expression e.g. "status:.*"
     * @param string $ns list only pages from this namespace
     * @return array [tag]=>array pages where tag is used
     */
    public function getPagesByMatchedTags($tags, $ns = '')
    {
        if (!$this->taghelper) return [];

        $tags = $this->taghelper->parseTagList($tags, false); //array

        $indexer = idx_get_indexer();
        $indexTags = array_keys($indexer->histogram(1, 0, 3, 'subject'));

        $matchedTags = [];
        foreach ($indexTags as $tag) {
            foreach ($tags as $tagExpr) {
                if ($this->matchesTagExpression($tagExpr, $tag))
                    $matchedTags[] = $tag;
            }
        }
        $matchedTags = array_unique($matchedTags);

        $matchedPages = [];
        foreach ($matchedTags as $tag) {
            $pages = $this->taghelper->getIndexedPagesMatchingTagQuery([$tag]);

            // keep only if in requested ns
            $matchedPages[$tag] = array_filter($pages, function ($pageid) use ($ns) {
                return $ns === '' || strpos(':' . getNS($pageid) . ':', ':' . $ns . ':') === 0;
            });
        }

        //clean empty tags, because not in requested namespace
        $matchedPages = array_filter($matchedPages);
        ksort($matchedPages);

        return $matchedPages;
    }
}

