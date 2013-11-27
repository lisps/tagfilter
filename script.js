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
 
