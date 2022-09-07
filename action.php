<?php
/**
 * DokuWiki Plugin tagfilter (Action Component)
 *
 * inserts a button into the toolbar
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */

class action_plugin_tagfilter extends DokuWiki_Action_Plugin
{
    /**
     * Register the eventhandlers
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array());
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, '_addparams');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, '_ajax_call');
    }

    public function _addparams($event, $param)
    {
        global $JSINFO;
        global $INPUT;
        // filter for ft* in GET
        $f = function ($value) {
            return strpos($value, "tf") === 0;
        };
        $get_tagfilter = array_filter(array_keys($_GET), $f);

        //filter for ft<key>_<label> and add it to JSINFO to select it via JavaScript
        foreach ($get_tagfilter as $param) {
            $ret = preg_match('/^tf(\d+)\_([\w\:äöü\-\/#]+)/', $param, $matches);

            if ($ret && is_numeric($matches[1]) && $matches[2]) {
                $JSINFO['tagfilter'][] = [
                    'key' => $matches[1],
                    'label' => $matches[2],
                    'values' => $INPUT->str($param) ? [$INPUT->str($param)] : $INPUT->arr($param)
                ];
            }
        }

    }

    /**
     * Inserts the toolbar button
     */
    public function insert_button($event, $param)
    {
        $event->data[] = [
            'type' => 'format',
            'title' => 'Tagfilter plugin',
            'icon' => '../../plugins/tagfilter/tagfilter.png',
            'sample' => '<namespace>? <Label>=<TagAusdruck>=<Tags Selected>|... &<pagelistoptions (&multi&nouser&chosen)>',
            'open' => '{{tagfilter>',
            'close' => '}}',
            'insert' => '',
        ];
    }

    /**
     * ajax Request Handler
     */
    public function _ajax_call($event, $param)
    {
        if ($event->data !== 'plugin_tagfilter') {
            return;
        }
        //no other ajax call handlers needed
        $event->stopPropagation();
        $event->preventDefault();

        global $INPUT;
        global $lang;

        //Variables
        $tagfilterFormId = $INPUT->int('id');
        $form = $this->getJSONdecodedUrlParameter('form');
        $ns = $this->getJSONdecodedUrlParameter('ns');
        $flags = (array)$this->getJSONdecodedUrlParameter('flags');
        //print_r($flags);
        $tagfilterFlags = isset($flags[1]) ? (array)$flags[1] : [];  //TODO parse?
        $pagelistFlags = isset($flags[0]) ? (array)$flags[0] : [];
        //print_r($tagfilterFlags);
        $pagesearch = $this->getJSONdecodedUrlParameter('pagesearch');

        //load tagfilter plugin
        /** @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter', false);

        //load tag plugin
        /** @var helper_plugin_tag $Htag */
        if (is_null($Htag = $this->loadHelper('tag', false))) {
            $this->sendResponse(sprintf($Htagfilter->getLang('missing_plugin'), 'tag'));
            return;
        }
        //load tag plugin
        /** @var helper_plugin_pagelist $Hpagelist */
        if (is_null($Hpagelist = $this->loadHelper('pagelist', false))) {
            $this->sendResponse(sprintf($Htagfilter->getLang('missing_plugin'), 'pagelist'));
            return;
        }

        $form = array_filter($form);//remove empty entries

        $tag_list_r = [];

        foreach ($form as $item) { //rearrange entries for tag plugin lookup
            $tag_list_r[] = implode(' ', array_filter($item));
        }

        $tag_list_r = array_filter($tag_list_r);

        //lookup the pages from the taglist
        //partially copied from tag->helper with less checks and no meta lookups
        $page_names = [];
        foreach ($tag_list_r as $key => $tag_list) {
            $tags_parsed = $Htag->parseTagList($tag_list, true);
            $pages_lookup = $Htag->getIndexedPagesMatchingTagQuery($tags_parsed);
            foreach ($pages_lookup as $page_lookup) {
                // filter by namespace, root namespace is identified with a dot
                // root namespace is specified, discard all pages who lay outside the root namespace
                if (($ns == '.' && getNS($page_lookup) === false)
                    || ($ns && strpos(':' . getNS($page_lookup) . ':', ':' . $ns . ':') === 0)
                    || $ns === '') {
                    if (auth_quickaclcheck($page_lookup) >= AUTH_READ) {
                        $page_names[$key][] = $page_lookup;
                    }
                }
            }
        }


        //get the first item
        if (!is_array($pages_intersect = array_shift($page_names))) {
            $pages_intersect = [];
        }

        //find the intersections
        foreach ($page_names as $names) {
            $pages_intersect = array_intersect($pages_intersect, $names);
        }

        //intersections with the pagesearch
        if (is_array($pagesearch) && !empty($pagesearch)) {
            $pages_intersect = array_intersect($pages_intersect, $pagesearch);
        }

        //no matching pages
        if (count($pages_intersect) === 0) {
            $this->sendResponse('<i>' . $lang['nothingfound'] . '</i>');
            return;
        }

        $pages_intersect = array_filter($pages_intersect, [$this, 'filterHide_Template']);

        $pages = [];
        foreach ($pages_intersect as $page) {
            $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
            $pages[$title ?: $page] = [
                'title' => $title ?: $page,
                'id' => $page
            ];
        }
        if (is_array($pagelistFlags) && in_array('rsort', $pagelistFlags)) {
            krsort($pages, SORT_STRING | SORT_FLAG_CASE);
        } else {
            ksort($pages, SORT_STRING | SORT_FLAG_CASE);
        }

        if (!isset($tagfilterFlags['tagcolumn'])) {
            $tagfilterFlags['tagcolumn'] = [];
        }
        foreach ($tagfilterFlags['tagcolumn'] as $tagcolumn) {
            $Hpagelist->addColumn('tagfilter', hsc($tagcolumn));
        }
        $Hpagelist->setFlags($pagelistFlags);
        $Hpagelist->startList();

        foreach ($pages as $page) {
            $Hpagelist->addPage($page);
        }
        $text = $Hpagelist->finishList();

        $this->sendResponse($text);
    }

    private function getJSONdecodedUrlParameter($name)
    {
        global $INPUT;

        $param = null;
        if ($INPUT->has($name)) {
            $param = $INPUT->param($name);
            $param = json_decode($param);
        }
        return $param;
    }

    /**
     * @param string $text
     */
    private function sendResponse($text)
    {
        global $INPUT;
        $tagfilter_id = $INPUT->int('id');
        echo json_encode(['id' => $tagfilter_id, 'text' => $text]);
    }

    /**
     * Filter function
     * show only pages different to _template and the have to exist
     *
     * @param string $val
     * @return bool
     */
    private function filterHide_Template($val)
    {
        return strpos($val, "_template") === false && @file_exists(wikiFN($val));
    }

}





