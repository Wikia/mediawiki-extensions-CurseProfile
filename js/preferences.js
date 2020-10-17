$(function(){
	var api = new mw.Api();
	var favwikiInput = $('.profile-favwiki-hidden input');
	var favwikiDisplay = $("#mw-input-wpprofile-favwiki-display input");
	var prefForm = $("#mw-prefs-form");
	var wikiresponse = {};

	prefForm.on('submit', function() {
		if (favwikiDisplay.val().length == 0 || favwikiDisplay.val() == "") {
			favwikiInput.val(''); // unset this on submit if no wiki is chosen.
		}
	});

	if (favwikiInput.val().length) {
		// Auto Fill "Favorite Wiki" with name from the actual stored value.
		api.get({
			action: 'profile',
			do: 'getWiki',
			hash: favwikiInput.val()
		}).done(function(data) {
			if (data.result == "success") {
				var label = (typeof data.data.wiki_name_display !== 'undefined')
					? data.data.wiki_name_display
					: data.data.wiki_name;
				favwikiDisplay.val(label);
			}
		});
	}

	favwikiDisplay.autocomplete({
		source: function(req, response) {
			api.get({
				action: 'profile',
				do: 'getWikisByString',
				search: req.term
			}).done(function(data) {
				if (data.result == "success") {
					var fill = [];
					var wikis = data.data;
					for (var i = 0; i < wikis.length; i++) {
						var res = wikis[i];
						var label = (typeof res.wiki_name_display !== 'undefined') ? res.wiki_name_display : res.wiki_name;
						fill.push(label);
						wikiresponse[label] = res.md5_key; // push into the store object
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
		close: function(event, ui) {
			var selected = $(event.target).val();
			if (typeof wikiresponse[selected] !== 'undefined' && wikiresponse[selected] !== "") {
				favwikiInput.val(wikiresponse[selected]);
			}
		}
	});
});