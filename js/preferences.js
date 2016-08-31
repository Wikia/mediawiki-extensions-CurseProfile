$(function(){
    var api = new mw.Api();
    var favwiki = $("#mw-input-wpprofile-favwiki");
    var favwikiDisplayOrig = $("#mw-input-wpprofile-favwiki-display");
        favwikiDisplayOrig.after('<input id="fakewikiDisplay" size="45" class="ui-autocomplete-input">');
        favwikiDisplayOrig.hide();
    var prefForm = $("#mw-prefs-form");
    var favwikiDisplay = $("#fakewikiDisplay");
    var wikiresponse = {};

    prefForm.submit(function(e){
        if (favwikiDisplay.val().length == 0 || favwikiDisplay.val() == "") {
            favwiki.val(''); // unset this on submit if no wiki is chosen.
        }
    });

    if (favwikiDisplay.length) {
        // Auto Fill "Favorite Wiki" with name from the actual stored value.
        api.get({
            action: 'profile',
            do: 'getWiki',
            hash: favwiki.val()
        }).done(function(data) {
            if (data.result == "success") {
                console.log(data);
                var label = (typeof data.data.wiki_name_display !== 'undefined') ? data.data.wiki_name_display : data.data.wiki_name;
                favwikiDisplay.change().val(label);
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
                            console.log(res);
                            var label = (typeof res.wiki_name_display !== 'undefined') ? res.wiki_name_display : res.wiki_name;
                            fill.push({ label: label });
                            wikiresponse[label] = hash; // push into the store object
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