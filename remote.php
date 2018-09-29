<?php
class remote_plugin_tagfilter extends DokuWiki_Remote_Plugin {
    public function _getMethods() {
        return array(
            /*'getTagsById'=>array(
                'args'=>array('id'),
                'return'=>'array'
            )*/
        );
    }
    
    public function getTagsByPage($id) {
        if(auth_quickaclcheck($id) < AUTH_READ){
            throw new RemoteAccessDeniedException('You are not allowed to read this file', 111);
        }
        
        $Htagfilter = $this->loadHelper('tagfilter',false);
        if(!$Htagfilter) {
            /*Exeption*/
            throw new RemoteAccessDeniedException('problem with helper plugin', 99999);
        } 
        return $Htagfilter->getTagsBySiteID($id);
    }
    
    public function getPagesByTags($tags,$ns='') {  

        $Htagfilter = $this->loadHelper('tagfilter',false);
        if(!$Htagfilter) {
            /*Exeption*/
            throw new RemoteAccessDeniedException('problem with helper plugin', 99999);
        } 

        $pages =  $Htagfilter->getPagesByTags($ns,$tags);

        //$pages_cleaned = array_intersect_key($pages, array_flip('id','title'));
        $pages_r = array();
        
        foreach($pages as $page){
            $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
            $pages_r[] = array(
                'title' => $title?$title:$page,
                'id' => $page,
                'tags' => $Htagfilter->getTagsBySiteID($page)
                );
        }
        
        return $pages_r;
    }
	
	public function getPagesByRegExpTags($tags,$ns='') {  

        $Htagfilter = $this->loadHelper('tagfilter',false);
        if(!$Htagfilter) {
            /*Exeption*/
            throw new RemoteAccessDeniedException('problem with helper plugin', 99999);
        } 


		$tags_r = $Htagfilter->getTagsByRegExp($tags,$ns);
		$tags_r = array_keys($tags_r);
        $pages =  $Htagfilter->getPagesByTags($ns,implode(' ',$tags_r));

        //$pages_cleaned = array_intersect_key($pages, array_flip('id','title'));
        $pages_r = array();
        
        foreach($pages as $page){
            $title = p_get_metadata($page, 'title', METADATA_DONT_RENDER);
            $pages_r[] = array(
                'title' => $title?$title:$page,
                'id' => $page,
                'tags' => $Htagfilter->getTagsBySiteID($page)
                );
        }
        
        return $pages_r;
    }
    
    
    
}
