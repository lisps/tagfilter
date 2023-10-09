<?php

use dokuwiki\Cache\Cache;

class helper_plugin_tagfilter_syntax extends DokuWiki_Plugin
{
    /**
     *
     * @param array $opt
     * with amongst others:
     *      array 'tagfilterFlags' with:
     *          string[] 'excludeNs',
     *          string[] 'withTags' (optional),
     *          string[] 'excludeTags (optional)
     *      array 'tagFilters' with three arrays with same keys::
     *          key=>string 'label'
     *          key=>string 'tagExpression'
     *          key=>array 'selectedTags'
     *      - string 'ns'
     * @return array
     *  with
     *      - array $tagFilters with four arrays with same key for each tagExpression:
     *          key=>string 'label'
     *          key=>string 'tagExpression'
     *          key=>array 'selectedTags'
     *          key=>array 'pagesPerMatchedTag' with
     *              tag=>array of pageids of pages having the tag
     *      - array $pageids ids of related pages
     *
     */
    public function getTagPageRelations($opt)
    {
        /* @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');

        $flags = $opt['tagfilterFlags'];

        $tagFilters = $opt['tagFilters'];
        foreach ($tagFilters['tagExpression'] as $key => $tagExpression) { //build tag->pages relation
            $tagFilters['pagesPerMatchedTags'][$key] = $Htagfilter->getPagesByMatchedTags($tagExpression, $opt['ns']);
        }

        //extract all pageids
        $allPageids = [];
        foreach ($tagFilters['pagesPerMatchedTags'] as $pagesPerMatchedTag) {
            if (!is_array($pagesPerMatchedTag)) {
                continue;
            }
            foreach ($pagesPerMatchedTag as $tag => $pageidsPerTag) {
                if (!empty($flags['withTags']) && !in_array($tag, $flags['withTags'])) {
                    continue;
                }
                if (!empty($flags['excludeTags']) && in_array($tag, $flags['excludeTags'])) {
                    continue;
                }
                $allPageids = array_merge($allPageids, $pageidsPerTag);
            }
        }

        $allPageids = array_filter($allPageids, function ($val) use ($opt) {
            //Template nicht anzeigen
            if (strpos($val, '_template') !== false) {
                return false;
            }

            foreach ($opt['tagfilterFlags']['excludeNs'] as $excludeNs) {
                if (strpos($val, $excludeNs) === 0) {
                    return false;
                }
            }
            return true;
        });

        $allPageids = array_unique($allPageids); //TODO cache this

        //cache $pageids and $tagFilters for all users
        return [
            $tagFilters,
            $allPageids
        ];
    }

    /**
     * Prepare array with data for each page suitable for displaying with the pagelist plugin
     *
     * @param array $pageids pages to list
     * @param array $flags with
     *      - array 'tagcolumn' (optional)
     *          - string tagexpr
     *      - array 'tagimagecolumn'
     *          - string tagexpr
     *          - string namespace of images
     *      - bool 'rsort' whether reverse sort
     * @return array[] with
     *      - array with
     *          - string 'title'
     *          - string 'id' page id
     *          - string 'tmp_id'
     *          - for each tagcolumn: string '<tagexpr as column key>' html of cell
     *          - for each tagimagecolumn: string '<tagexpr as column key>' html of cell
     */
    public function prepareList($pageids, $flags)
    {
        global $ID;
        global $INFO;

        /* @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');

        if (!isset($flags['tagcolumn'])) {
            $flags['tagcolumn'] = [];
        }


        $pages = [];
        $_uniqueid = 0;
        foreach ($pageids as $page) {

            $depends = ['files' => [
                $INFO['filepath'],
                wikiFN($page)
            ]];
            $cache_key = implode('_', ['plugin_tagfilter', $ID, $page, $flags['sortbypageid']]);
            $cache = new Cache($cache_key, '.tpcache');
            if (!$cache->useCache($depends)) {
                $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);

                $cache_page = [
                    'title' => $title ?: $page,
                    'id' => $page,
                    'tmp_id' => $flags['sortbypageid']
                        ? $page
                        : ($title ?: (noNS($page) ?: $page)),
                ];

                foreach ($flags['tagcolumn'] as $tagcolumn) {
                    $cache_page[hsc($tagcolumn)] = $Htagfilter->td($page, hsc($tagcolumn));
                }
                foreach ($flags['tagimagecolumn'] as $tagimagecolumn) {
                    $cache_page[hsc($tagimagecolumn[0]) . ' '] = $Htagfilter->getTagImageColumn($page, $tagimagecolumn[0], $tagimagecolumn[1]);
                }
                $cache->storeCache(serialize($cache_page));
            } else {
                $cache_page = unserialize($cache->retrieveCache());
            }

            //create unique key
            $tmp_id = $cache_page['tmp_id'];
            if (isset($pages[$tmp_id])) {
                $tmp_id .= '_' . $_uniqueid++;
            }

            $pages[$tmp_id] = $cache_page;
        }


        if ($flags['rsort']) {
            krsort($pages, SORT_NATURAL | SORT_FLAG_CASE);
        } else {
            ksort($pages, SORT_NATURAL | SORT_FLAG_CASE);
        }
        return $pages;
    }


    /**
     * Generated list of the give page data
     *
     * @param array $pages for format @see prepareList()
     * @param array $flags tagfilter flags with at least:
     *      - array 'tagcolumn' (optional)
     *          - string tagexpr
     *      - array 'tagimagecolumn'
     *          - string tagexpr
     *          - string namespace of images
     * @param array $pagelistflags all flags set by user
     * @return false|string
     */
    public function renderList($pages, $flags, $pagelistflags)
    {
        if (!isset($flags['tagcolumn'])) {
            $flags['tagcolumn'] = [];
        }


        // let Pagelist Plugin do the work for us
        /* @var helper_plugin_pagelist $Hpagelist */
        if (plugin_isdisabled('pagelist')
            || (!$Hpagelist = plugin_load('helper', 'pagelist'))) {
            msg($this->getLang('missing_pagelistplugin'), -1);
            return false;
        }

        foreach ($flags['tagcolumn'] as $tagcolumn) {
            $Hpagelist->addColumn('tagfilter', hsc($tagcolumn));
        }
        foreach ($flags['tagimagecolumn'] as $tagimagecolumn) {
            $Hpagelist->addColumn('tagfilter', hsc($tagimagecolumn[0] . ' '));
        }

        unset($flags['tagcolumn']);  //TODO unset is not needed because pagelistflags are separate array?
        $Hpagelist->setFlags($pagelistflags);
        $Hpagelist->startList();

        foreach ($pages as $page) {
            $Hpagelist->addPage($page);
        }

        return $Hpagelist->finishList();
    }


    /**
     * parseFlags checks for tagfilter flags and returns them as true/false
     *
     * @param array $flags array with (all optional):
     *      multi, chosen, tagimage, pagesearch, cacheage, nocache, rsort, nolabels, noneonclear, tagimagecolumn,
     *      tagcolumn, excludeNs, withTags, excludeTags, images, count, tagintersect, sortbypageid, include
     * @return array tagfilter flags with:
     *      multi, chosen, tagimage, pagesearch, pagesearchlabel, cache, rsort, labels, noneonclear, tagimagecolumn,
     *      tagcolumn (optional), excludeNs, withTags, excludeTags, images, count, tagintersect, sortbypageid, include
     */
    public function parseFlags($flags)
    {
        $conf = [
            'multi' => false,
            'chosen' => false,
            'tagimage' => false,
            'pagesearch' => false,
            'pagesearchlabel' => 'Seiten',
            'cache' => false,
            'rsort' => false,
            'labels' => true,
            'noneonclear' => false,
            'tagimagecolumn' => [],
            'excludeNs' => [],
            'withTags' => [],
            'excludeTags' => [],
            'images' => false,
            'count' => false,
            'tagintersect' => false,
            'sortbypageid' => false,
            'include' => [],
        ];
        if (!is_array($flags)) {
            return $conf;
        }

        foreach ($flags as $flag) {
            list($flag, $value) = array_pad(explode('=', $flag, 2), 2, '');
            $flag = trim($flag);
            $value = trim($value);
            switch ($flag) {
                case 'multi':
                    $conf['multi'] = true;
                    break;
                case 'chosen':
                    $conf['chosen'] = true;
                    break;
                case 'tagimage':
                    $conf['tagimage'] = true;
                    break;
                case 'pagesearch':
                    $conf['pagesearch'] = true;
                    if ($value != '') {
                        $conf['pagesearchlabel'] = hsc($value);
                    }
                    break;
                case 'cacheage':
                    $conf['cache'] = intval($value);
                    break;
                case 'nocache':
                    $conf['cache'] = null;
                    break;
                case 'tagcolumn':
                    $conf['tagcolumn'][] = $value;
                    break;
                case 'tagimagecolumn':
                    $conf['tagimagecolumn'][] = explode('=', $value, 2);
                    break;
                case 'rsort':
                    $conf['rsort'] = true;
                    break;
                case 'nolabels':
                    $conf['labels'] = false;
                    break;
                case 'noneonclear':
                    $conf['noneonclear'] = true;
                    break;
                case 'excludeNs':
                    $conf['excludeNs'] = explode(',', $value, 2); //TODO really maximum of two namespaces?
                    break;
                case 'withTags':
                    $conf['withTags'] = explode(',', $value, 2); //TODO really maximum of two tags?
                    break;
                case 'excludeTags':
                    $conf['excludeTags'] = explode(',', $value, 2); //TODO really maximum of two tags?
                    break;
                case 'images':
                    $conf['images'] = true;
                    break;
                case 'count':
                    $conf['count'] = true;
                    break;
                case 'tagintersect':
                    $conf['tagintersect'] = true;
                    break;
                case 'sortbypageid':
                    $conf['sortbypageid'] = true;
                    break;
                case 'include':
                    $conf['include'] = explode(';', $value);
                    break;
            }
        }

        return $conf;
    }


    /**
     * This function just lists documents (for RSS namespace export)
     *
     * @param array $data Reference to the result data structure
     * @param string $base Base usually $conf['datadir']
     * @param string $file current file or directory relative to $base
     * @param string $type Type either 'd' for directory or 'f' for file
     * @param int $lvl Current recursion depth
     * @param array $opts option array as given to search() with:
     *      string[] 'excludeNs'
     * @return bool if this directory should be traversed (true) or not (false)
     *              return value is ignored for files
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public function search_all_pages(&$data, $base, $file, $type, $lvl, $opts)
    {
        global $conf;

        //we do nothing with directories
        if ($type == 'd') {
            return true;
        }

        //only search txt files
        if (substr($file, -4) == '.txt') {
            foreach ($opts['excludeNs'] as $excludeNs) {
                if (strpos($file, str_replace(':', '/', $excludeNs)) === 0) {
                    return true;
                }
            }

            //check ACL
            $data[] = $conf['datadir'] . '/' . $file;
        }
        return false;
    }
}
