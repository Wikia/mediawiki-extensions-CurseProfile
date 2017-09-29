$(function(){
    if (mw.config.get('wgCanonicalNamespace') == "UserProfile") {
        var user = mw.config.get('wgTitle');
        var inject = "<li id=\"t-contributions\"><a href=\"/Special:Contributions/"+user+"\" title=\""+mw.message('tooltip-t-contributions').escaped()+"\">"+mw.message('contributions-title',user).escaped()+"</a></li>"
        + "<li id=\"t-log\"><a href=\"/Special:Log/"+user+"\">"+mw.message('log').escaped()+"</a></li>"
        + "<li id=\"t-blockip\"><a href=\"/Special:Block/"+user+"\">"+mw.message('block').escaped()+"</a></li>"
		+ "<li id=\"t-emailuser\"><a href=\"/Special:EmailUser/"+user+"\" title=\""+mw.message('tooltip-t-emailuser').escaped()+"\">"+mw.message('emailuser').escaped()+"</a></li>";

		$("#p-tb ul").find('li').first().after(inject);
    }
});

/*

*/
