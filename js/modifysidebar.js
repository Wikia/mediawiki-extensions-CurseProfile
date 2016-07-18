$(function(){

    if (mw.config.get('wgCanonicalNamespace') == "UserProfile") {
        var user = mw.config.get('wgTitle');
        var inject = "<li id=\"t-contributions\"><a href=\"/Special:Contributions/"+user+"\" title=\""+mw.msg('tooltip-t-contributions')+"\">"+mw.msg('contributions-title',user)+"</a></li>"
        + "<li id=\"t-log\"><a href=\"/Special:Log/"+user+"\">"+mw.msg('log')+"</a></li>"
        + "<li id=\"t-blockip\"><a href=\"/Special:Block/"+user+"\">"+mw.msg('block')+"</a></li>"
        + "<li id=\"t-emailuser\"><a href=\"/Special:EmailUser/"+user+"\" title=\""+mw.msg('tooltip-t-emailuser')+"\">"+mw.msg('emailuser')+"</a></li>"
        + "<li id=\"t-userrights\"><a href=\"/Special:UserRights/"+user+"\">"+mw.msg('userrights')+"</a></li>";

        $("#p-tb ul").find('li').first().after(inject);
    }
});

/*

*/
