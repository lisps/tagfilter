<?php

use dokuwiki\Remote\AccessDeniedException;

class remote_plugin_tagfilter extends DokuWiki_Remote_Plugin
{
    public function _getMethods()
    {
        return [/*'getTagsById'=>array(
                'args'=>array('id'),
                'return'=>'array'
            )*/
        ];
    }

    /**
     * @throws AccessDeniedException
     */
    public function getTagsByPage($id)
    {
        if (auth_quickaclcheck($id) < AUTH_READ) {
            throw new AccessDeniedException('You are not allowed to read this file', 111);
        }

        /** @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter', false);
        if (!$Htagfilter) {
            /*Exeption*/
            throw new AccessDeniedException('problem with helper plugin', 99999);
        }
        return $Htagfilter->getTagsByPageID($id);
    }

    /**
     * @throws AccessDeniedException
     */
    public function getPagesByTags($tags, $ns = '')
    {

        /** @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter', false);
        if (!$Htagfilter) {
            /*Exeption*/
            throw new AccessDeniedException('problem with helper plugin', 99999);
        }

        $pages = $Htagfilter->getPagesByTags($ns, $tags);

        //$pages_cleaned = array_intersect_key($pages, array_flip('id','title'));
        $pages_r = [];

        foreach ($pages as $page) {
            $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
            $pages_r[] = [
                'title' => $title ?: $page,
                'id' => $page,
                'tags' => $Htagfilter->getTagsByPageID($page)
            ];
        }

        return $pages_r;
    }

    /**
     * @throws AccessDeniedException
     */
    public function getPagesByRegExpTags($tags, $ns = '')
    {
        /** @var helper_plugin_tagfilter $Htagfilter */
        $Htagfilter = $this->loadHelper('tagfilter', false);
        if (!$Htagfilter) {
            /*Exeption*/
            throw new AccessDeniedException('problem with helper plugin', 99999);
        }


        $tags_labels = $Htagfilter->getTagsByRegExp($tags, $ns);
        $tags_r = array_keys($tags_labels);
        $pages = $Htagfilter->getPagesByTags($ns, implode(' ', $tags_r));

        //$pages_cleaned = array_intersect_key($pages, array_flip('id','title'));
        $pages_r = [];

        foreach ($pages as $page) {
            $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
            $pages_r[] = [
                'title' => $title ?: $page,
                'id' => $page,
                'tags' => $Htagfilter->getTagsByPageID($page)
            ];
        }

        return $pages_r;
    }


}
