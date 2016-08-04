$(function(){
    var api = new mw.Api();
    var favwiki = $("#mw-input-wpprofile-favwiki");
    var favwikiDisplayOrig = $("#mw-input-wpprofile-favwiki-display");
        favwikiDisplayOrig.after('<input id="fakewikiDisplay" size="45" class="ui-autocomplete-input">');
        favwikiDisplayOrig.hide();
    var favwikiDisplay = $("#fakewikiDisplay");
    var wikiresponse = {};
    if (favwikiDisplay.length) {
        // Auto Fill "Favorite Wiki" with name from the actual stored value.
        api.get({
            action: 'profile',
            do: 'getWiki',
            hash: favwiki.val()
        }).done(function(data) {
            if (data.result == "success") {
                favwikiDisplay.change().val(data.data.wiki_name);
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
                            fill.push({ label: res.wiki_name_display  });
                            wikiresponse[res.wiki_name_display] = hash; // push into the store object
                        }
                        if (fill.length) {
                            response(fill);
                        } else {
                            response([{label:'No results match "'+req.term+'"', value: ''}]);
                        }
                    } else {
                        response([]);
                    }
                });
            },
            close: function( event, ui ) {
                var selected = $(event.target).val();
                if (typeof wikiresponse[selected] !== 'undefined' && wikiresponse[selected] !== "") {
                    favwiki.val(wikiresponse[selected]);
                }
            }
        });
    }
});