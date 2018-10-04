<?php
// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_tagfilter_syntax extends DokuWiki_Plugin 
{
    function getTagPageRelations($opt) {
        /* @var $Htagfilter helper_plugin_tagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');
    
        $flags = $opt['tfFlags'];
    
        $tagselect_r = $opt['tagselect_r'];
        foreach($tagselect_r['tag_expr'] as $key=>$tag_expr){ //build tag->pages relation
            $tagselect_r['tagPages'][$key] = $Htagfilter->getRelationsByTagRegExp($tag_expr,$opt['ns']);
        }
    
        //extract all pageids
        $pageids = array();
        foreach($tagselect_r['tagPages'] as $select_r){
            if(!is_array($select_r)) continue;
            foreach($select_r as $tag => $pageid_r){
                if(!empty($flags['withTags']) && !in_array($tag,$flags['withTags'])) continue;
                if(!empty($flags['excludeTags']) && in_array($tag,$flags['excludeTags'])) continue;
                $pageids = array_merge($pageids,$pageid_r);
            }
        }
    
        //Template nicht anzeigen
        $pageids = array_filter($pageids,function($val) use($opt){
            if(strpos($val,'_template')!==false) return false;
            	
            foreach($opt['tfFlags']['excludeNs'] as $excludeNs) {
                if(strpos($val, $excludeNs) === 0) return false;
            }
            return true;
        });
    
            $pageids = array_unique($pageids); //TODO cache this
    
            //cache $pageids and $tagselect_r for all users
            return array(
                $tagselect_r,
                $pageids
    
            );
    }
    
    public function prepareList($pageids, $flags) {
        global $ID;
        global $INFO;
    
        /* @var $Htagfilter helper_plugin_tagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');
    
        if(!isset($flags['tagcolumn']))$flags['tagcolumn'] = array();
    
    
    
        $pages = array();
        $_uniqueid = 0;
        foreach($pageids as $page){
            	
            $depends = array('files'=>array(
                $INFO['filepath'],
                wikiFN($page)
            ));
            $cache_key = 'plugin_tagfilter_'.$ID . '_' . $page;
            $cache = new cache($cache_key, '.tpcache');
            if(!$cache->useCache($depends)) {
                $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
    
                $cache_page = array(
                    'title' => $title?$title:$page,
                    'id' => $page,
                    'tmp_id' => $title?$title:noNS($page)?noNS($page):$page,
                );
    
                foreach($flags['tagcolumn'] as $tagcolumn){
                    $cache_page[hsc($tagcolumn)] = $Htagfilter->td($page,hsc($tagcolumn));
                }
                foreach($flags['tagimagecolumn'] as $tagimagecolumn){
                    $cache_page[hsc($tagimagecolumn[0]).' '] = $Htagfilter->getTagImageColumn($page,$tagimagecolumn[0], $tagimagecolumn[1]);
                }
                $cache->storeCache(serialize($cache_page));
            } else {
                $cache_page = unserialize($cache->retrieveCache());
            }
            	
            $tmp_id = $cache_page['tmp_id'];
            if(isset($pages[$tmp_id])) {
                $tmp_id .= '_'.$_uniqueid++;
            }
            	
            $pages[$tmp_id] = $cache_page;
        }
    
    
        if($flags['rsort']) {
            krsort($pages,SORT_NATURAL|SORT_FLAG_CASE);
        } else {
            ksort($pages,SORT_NATURAL|SORT_FLAG_CASE);
        }
        return $pages;
    }
    
    
    function renderList($pages, $flags,$pagelistflags) {
        /* @var $Htagfilter helper_plugin_tagfilter */
        $Htagfilter = $this->loadHelper('tagfilter');
    
        if(!isset($flags['tagcolumn']))$flags['tagcolumn'] = array();
    
    
    
        // let Pagelist Plugin do the work for us
        if (plugin_isdisabled('pagelist')
            || (!$Hpagelist = plugin_load('helper', 'pagelist'))) {
                msg($this->getLang('missing_pagelistplugin'), -1);
                return false;
            }
    
            foreach($flags['tagcolumn'] as $tagcolumn) {
                $Hpagelist->addColumn('tagfilter',hsc($tagcolumn));
            }
            foreach($flags['tagimagecolumn'] as $tagimagecolumn) {
                $Hpagelist->addColumn('tagfilter', hsc($tagimagecolumn[0] . ' '));
            }
            	
            unset($flags['tagcolumn']);
            $Hpagelist->setFlags($pagelistflags);
            $Hpagelist->startList();
    
            foreach ($pages as $page) {
                $Hpagelist->addPage($page);
            }
    
    
            return $Hpagelist->finishList();
    }
    
    
    /*
     * parseFlags checks for tagfilter flags and returns them as true/false
     * @param $flags array
     * @return array tagfilter flags
     */
    function parseFlags($flags){
        if(!is_array($flags)) return false;
        $conf = array(
            'multi' => false,
            'chosen' => false,
            'tagimage' => false,
            'pagesearch' => false,
            'pagesearchlabel' => 'Seiten',
            'cache' => false,
            'rsort' => false,
            'labels' => true,
            'noneonclear' => false,
            'tagimagecolumn' => array(),
            'excludeNs' => array(),
            'withTags' => array(),
            'excludeTags' => array(),
            'images' => false,
        );
    
        foreach($flags as $k=>$flag) {
            list($flag,$value) = explode('=',$flag,2);
            $flag = trim($flag);
            $value = trim($value);
            switch($flag) {
                case 'multi':
                    $conf['multi'] = true;
                    break;
                case 'chosen':
                    $conf['chosen'] = true;
                    break;
                case 'tagimage':
                    $conf['tagimage']= true;
                    break;
                case 'pagesearch':
                    $conf['pagesearch']= true;
                    if($value != ''){
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
                    $conf['tagimagecolumn'][] = explode('=', $value,2);
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
                    $conf['excludeNs'] = explode(',', $value,2);
                    break;
                case 'withTags':
                    $conf['withTags'] = explode(',', $value,2);
                    break;
                case 'excludeTags':
                    $conf['excludeTags'] = explode(',', $value,2);
                    break;
                case 'images':
                    $conf['images'] = true;
                    break;
            }
        }
    
        return $conf;
    }
    
    
    /**
     * This function just lists documents (for RSS namespace export)
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    function search_all_pages(&$data,$base,$file,$type,$lvl,$opts){
        global $conf;
    
        //we do nothing with directories
        if($type == 'd') return true;
    
        //only search txt files
        if(substr($file,-4) == '.txt'){
            foreach($opts['excludeNs'] as $excludeNs) {
                if(strpos($file, str_replace(':','/',$excludeNs)) === 0) return true;
            }
    
            //check ACL
            $data[] = $conf['datadir'].'/'.$file;
        }
        return false;
    }
}