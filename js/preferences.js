$(function(){
    var api = new mw.Api();
    var favwiki = $("#mw-input-wpprofile-favwiki");
    var favwikiDisplay = $("#mw-input-wpprofile-favwiki-display");
    var wikiresponse = {};
    if (favwikiDisplay.length) {
        // Auto Fill "Favorite Wiki" with name from the actual stored value.
        api.get({
            action: 'profile',
            do: 'getWiki',
            hash: favwiki.val()
        }).done(function(data) {
            if (data.result == "success") {
                favwikiDisplay.val(data.data.wiki_name);
            }
        });
        favwikiDisplay.autocomplete({
            source: function(req, response) {
                api.get({
                    action: 'profile',
                    do: 'getWikisByString',
                    search: req.term
                }).done(function(data) {
                    if (data.result == "success") {
                        var fill = [];
                        var results = data.data;
                        for (hash in results) {
                            var res = results[hash];
                            fill.push({ label: res.wiki_name  });
                            wikiresponse[res.wiki_name] = hash; // push into the store object
                        }
                        response(fill);
                    } else {
                        response([]);
                    }
                });
            },
            close: function( event, ui ) {
                var selected = $(event.target).val();
                if (typeof wikiresponse[selected] !== 'undefined') {
                    favwiki.val(wikiresponse[selected]);
                }
            }
        });
    }
});