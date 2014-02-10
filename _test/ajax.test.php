<?php
/**
 * @group plugin_tagfilter
 * @group plugins
 */
class plugin_tagfilter_ajax_test extends DokuWikiTest {

    protected $pluginsEnabled = array(
        'tag','tagfilter','pagelist'
    );
    public function setup() {
        parent::setup();
        $this->_createPages();
    }

    
    public function test_ajax_request_notags() {
        global $INPUT;
        global $lang;
        
        $INPUT->set('id', 0);
        $INPUT->set('form',json_encode(array()));
        $INPUT->set('ns',json_encode('test:plugin_tagfilter:tags'));
        $INPUT->set('flags',json_encode(array()));
        $INPUT->set('pagesearch',json_encode(array()));
        
        ob_start();
        $data = 'plugin_tagfilter';
        trigger_event('AJAX_CALL_UNKNOWN',$data);
        $response = ob_get_contents();
        ob_end_clean();
        
        if(isset($lang['nothingfound'])) {
            $this->assertEquals('{"id":0,"text":"<i>Nothing was found.<\/i>"}', $response);
        }
        else {
            $this->assertEquals('{"id":0,"text":"<i><\/i>"}', $response);
        }
    }
    
    public function test_ajax_request_alltags() {
        global $INPUT;
    
        $INPUT->set('id', 0);
        $INPUT->set('form','[["cat1:blorg","cat2:a","cat3:1","cat2:b","cat3:2"]]');
        $INPUT->set('ns',json_encode('test:plugin_tagfilter:tags'));
        $INPUT->set('flags',json_encode(array()));
        $INPUT->set('pagesearch',json_encode(array()));
    
        ob_start();
        $data = 'plugin_tagfilter';
        trigger_event('AJAX_CALL_UNKNOWN',$data);
        $response = ob_get_contents();
        var_dump($response); 
        $response = json_decode($response);
        var_dump($response);
        $response = (array)$response;
        var_dump($response);

        $this->assertContains('id=test:plugin_tagfilter:tags:tagpage1', $response['text']);
        $this->assertContains('id=test:plugin_tagfilter:tags:tagpage2', $response['text']);

    }
    
    public function test_ajax_request_onepage() {
        global $INPUT;
    
        $INPUT->set('id', 0);
        $INPUT->set('form','[["cat1:blorg"],["cat3:2"]]');
        $INPUT->set('ns',json_encode('test:plugin_tagfilter:tags'));
        $INPUT->set('flags',json_encode(array()));
        $INPUT->set('pagesearch',json_encode(array()));
    
        ob_start();
        $data = 'plugin_tagfilter';
        trigger_event('AJAX_CALL_UNKNOWN',$data);
        $response = (array)json_decode(ob_get_contents());
        ob_end_clean();
        
        $this->assertFalse(strpos($response->text, 'id=test:plugin_tagfilter:tags:tagpage1') !== false);
        $this->assertContains('id=test:plugin_tagfilter:tags:tagpage2', $response['text']);
    
    }
    
    public function test_ajax_request_pagesearch() {
        global $INPUT;
    
        $INPUT->set('id', 0);
        $INPUT->set('form','[["cat1:blorg"]]');
        $INPUT->set('ns',json_encode('test:plugin_tagfilter:tags'));
        $INPUT->set('flags',json_encode(array()));
        $INPUT->set('pagesearch',json_encode(array('test:plugin_tagfilter:tags:tagpage2')));
    
        ob_start();
        $data = 'plugin_tagfilter';
        trigger_event('AJAX_CALL_UNKNOWN',$data);
        $response = (array)json_decode(ob_get_contents());
        ob_end_clean();
    
        $this->assertFalse(strpos($response->text, 'id=test:plugin_tagfilter:tags:tagpage1') !== false);
        $this->assertContains('id=test:plugin_tagfilter:tags:tagpage2', $response['text']);
    
    }

    protected function _createPages() {
        saveWikiText('test:plugin_tagfilter:tags:tagpage1', '==== Tagpage1 ===='.DOKU_LF.'{{tag>cat1:blorg cat2:a cat3:1}}', 'test');
        saveWikiText('test:plugin_tagfilter:tags:tagpage2', '==== Tagpage2 ===='.DOKU_LF.'{{tag>cat1:blorg cat2:b cat3:2}}', 'test');
        saveWikiText('test:plugin_tagfilter:start',  '{{tagfilter>test:plugin_tagfilter:tags?T1=cat1:.*|T2=cat2:.*|T3=cat3:.*}}', 'test');
        saveWikiText('test:plugin_tagfilter:start2', '{{tagfilter>test:plugin_tagfilter:tags?T1=cat1:.*|T2=cat2:.*|T3=cat3:.*&pagesearch}}', 'test');
        saveWikiText('test:plugin_tagfilter:start3', '{{tagfilter>test:plugin_tagfilter:tags?T1=cat1:.*|T2=cat2:.*|T3=cat3:.*&multi}}', 'test');
        
        idx_addPage('test:plugin_tagfilter:tags:tagpage1',false);
        idx_addPage('test:plugin_tagfilter:tags:tagpage2',false);
    }
    
    
    

}
