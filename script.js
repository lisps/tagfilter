/* DOKUWIKI:include_once script/chosen/chosen.jquery.js */

/**
 * DokuWiki Plugin tagfilter (JavaScript Component) 
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  lisps
 */

function getSelectByFormId(id){
	return jQuery('select.tagdd_select_'+id);
}

function tagfilter_cleanform(id){
	//elements = getElementsByClass('tagdd_select',document.getElementById('tagdd_'+id),'select');
	$elements = getSelectByFormId(id);
	for(i=0;i<$elements.length;i++){
		for(k=0;k<$elements[i].options.length;k++){
			$elements[i].options[k].selected=false;	
		}
			
	}
    $elements[i-1].onchange();  
	jQuery('select.tagdd_select').trigger("liszt:updated");	
	
}

function tagfilter_submit(id,ns,flags)
{
	form = new Array();
	pagesearch= new Array();
	$elements = getSelectByFormId(id);
	
	//document.getElementById('tagfilter_ergebnis_'+id).innerHTML = '';
	document.getElementById('tagfilter_ergebnis_'+id).className += " loading";
	count = 0;
	for(i=0;i<$elements.length;i++){
		e = $elements[i];
		if(e.selectedIndex != -1)
			form[i] = new Array();
		for(k=0;k<e.options.length;k++){
			if(e.options[k].selected && e.options[k].value != ''){
				if(e.id == '__tagfilter_page_'+id) {
					pagesearch.push(e.options[k].value);	
				}
				else {
					form[i].push(e.options[k].value);
					count++;
				}
			}
		}
	}
	
	if(count == 0){
		form[0] = new Array();
		for(i=0;i<$elements.length;i++){
			e = $elements[i];
			if(e.id == '__tagfilter_page_'+id) continue; //do not sent the pagenames
			for(k=0;k<e.options.length;k++){
				form[0].push(e.options[k].value);
			}
		}
	}
	
	
	
	tagfiltersent(id,JSON.stringify(form),ns,flags,pagesearch);

}


//send ajax data    
function tagfiltersent(id,form,ns,flags,pagesearch)
{
	jQuery.post(
		DOKU_BASE+'lib/plugins/tagfilter/ajax.php',
		{
			form:form,
			id:id,
			ns:JSON.stringify(ns),
			flags:JSON.stringify(flags),
			pagesearch:JSON.stringify(pagesearch)
		},
		tagfilterdone	
	);
}

    
function tagfilterdone(data,textStatus,jqXHR)
{
    if(data) {
		ret = JSON.parse(data);
		div = document.getElementById("tagfilter_ergebnis_"+ret.id);	
		div.innerHTML = ret.text;
		document.getElementById('tagfilter_ergebnis_'+ret.id).className = " tagfilter";
		return true;
    }
}

jQuery().ready(function(){
	var clean_r = [];
	if(JSINFO['tagfilter']){
		jQuery(JSINFO['tagfilter']).each(function(k,tf_elmt){
			
			
			var $tf_dd = jQuery('#tagdd_'+tf_elmt.key +' [data-label="'+tf_elmt.label+'"]');
			
			
			if($tf_dd){
				if(jQuery.inArray(tf_elmt.key,clean_r) === -1){
					tagfilter_cleanform(tf_elmt.key);
					clean_r.push(tf_elmt.key);
				}
				$tf_dd.val(tf_elmt.values);
			}
		});
	}
});

/**
 * put the selected tags into the url for later use (browser history)
 * 
 */
jQuery(window).on('beforeunload',function(e){
	var $tagfilter_r = jQuery('form[data-plugin="tagfilter"]');
	//tagfilter found?
	if($tagfilter_r.length){
		var tf_params = [];
		$tagfilter_r.each(function(i,tagfilter){
			var $tagfilter = jQuery(tagfilter);
			//search for each dropdown field inside a tagfilter
			$tagfilter.find('select').each(function(i,dd){
				var $dd = jQuery(dd);
				var value = $dd.val();
				var type = jQuery.type(value);
				if(!value)return;
				
				//add selected fields to tf_params
				if(type === 'string') {
					tf_params.push(encodeURIComponent('tf' + $tagfilter.attr('data-idx')+'_'+$dd.attr('data-label'))+'[]='+encodeURIComponent(value));
				}
				if(type === 'array') {
					jQuery(value).each(function(i,v){
						tf_params.push(encodeURIComponent('tf' + $tagfilter.attr('data-idx')+'_'+$dd.attr('data-label'))+'[]='+encodeURIComponent(v));
					});
				}
			});
			
		});
		//something selected?
		//if(tf_params.length){
			var url = document.location.href.split('?');

			var url_params = url[1].split('&');
			var old_params = [];
			jQuery(url_params).each(function(k,v){
				if(tagfilter_strpos(v,'tf',0) !== 0) //hack but should almost work ;)
					old_params.push(v);
			});
			
			old_params = old_params.concat(tf_params);
			window.history.replaceState({},'tagfilter',url[0]+'?'+old_params.join('&'));
		//}
		//return true;
		//window.history.replaceState();
	} else {
	}
});

/**
 * copied from http://phpjs.org/functions/strpos/ 
 */
function tagfilter_strpos (haystack, needle, offset) {
  // http://kevin.vanzonneveld.net
  // +   original by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
  // +   improved by: Onno Marsman
  // +   bugfixed by: Daniel Esteban
  // +   improved by: Brett Zamir (http://brett-zamir.me)
  // *     example 1: strpos('Kevin van Zonneveld', 'e', 5);
  // *     returns 1: 14
  var i = (haystack + '').indexOf(needle, (offset || 0));
  return i === -1 ? false : i;
}
 
