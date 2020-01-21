  function CurseProfile($) {
	'use strict';
	this.init = function () {

		$('button.linksub').click(function () {
			window.location = $(this).data('href');
		});

		$('.userinfo').each(function () {
			$(this)
				.on('click', 'a.profileedit', function (element) {
					var fieldParent = $(this).parents("div[data-field]");
					profile.editField(element, $(fieldParent).attr('data-field'), $(fieldParent).attr('id'));
				})
				.on('click', 'a.socialedit', function (element) {
					var fieldParent = $(this).parents("div[data-field]");
					profile.editSocialField(element, $(fieldParent).attr('data-field'), $(fieldParent).attr('id'));
				})
				.on('click', 'button.cancel', function (element) {
					var fieldParent = $(this).parents("form[data-field]");
					profile.cancelEdit(element, $(fieldParent).attr('data-field'), $(fieldParent).attr('id'));
				})
				.on('click', 'button.save', function (element) {
					var fieldParent = $(this).parents("form[data-field]");
					profile.saveField(element, $(fieldParent).attr('data-field'), $(fieldParent).attr('id'));
				})
				.on('click', 'button.saveGroup', function (element) {
					var fieldParent = $(this).parents("form[data-field]");
					profile.saveField(element, $(fieldParent).attr('data-field'), $(fieldParent).attr('data-block-id'));
				});
		});
		friendship.init();

		$('.profile-icon-tooltip button').on('click touch', function(event) {
			var $temp = $("<input>");
			$("body").append($temp);
			$temp.val($(this).data('profile-text')).select();
			document.execCommand("copy");
			$temp.remove();
		});

		$('.profile-icon').on('click touch', function(event) {
			$('.profile-icon-tooltip').hide();
			$(this).siblings('.profile-icon-tooltip').show();
			$(this).siblings('.profile-icon-tooltip').css('display', 'grid');
		});

		$(document).on('click touch', function(event) {
			if (!$(event.target).parents().addBack().is('.profile-icon')) {
				$('.profile-icon-tooltip').hide();
			}
		});

		$('.profile-icon-tooltip').on('click touch', function(event) {
			event.stopPropagation();
		});

		
	};

	/**
	 * All friending-related ajax functions
	 */
	var friendship = {
		init: function () {
			$(document).on('click', '.friendship-action', friendship.sendAction);
			$('#senddirectreq').on('click', friendship.sendDirectReq);
		},

		sendAction: function (e) {
			var $this = $(this),
				$container = $this.closest('.friendship-container'),
				$buttons = $container.find('.friendship-action');
			e.preventDefault();
			$buttons.attr('disabled', true);
			// confirmation for friend removal
			if ($this.data('confirm') && !window.confirm($this.data('confirm'))) {
				$buttons.attr('disabled', false);
				return;
			}
			(new mw.Api()).post({
				action: 'friend',
				do: $this.data('action'),
				user_id: $this.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function (resp) {
				$buttons.attr('disabled', false);
				if (resp.remove) {
					var $list = $this.closest('ul.friends');
					if ($list.length === 0) {
						$container.detach();
					} else {
						if ($list.find('li').length > 1) {
							$this.closest('li').hide();
						} else {
							// remove the entire list and the preceeding H2
							$list.hide().prev().hide();
						}
					}
				} else {
					$container.html(resp.html);
				}
			}).fail(function (code, resp) {
				$buttons.attr('disabled', false);
			});
		},

		sendDirectReq: function (e) {
			var $this = $(this);
			$('.directreqresult').slideUp().detach(); // remove existing messages
			$this.attr('disabled', true);
			(new mw.Api()).post({
				action: 'friend',
				do: 'directreq',
				name: $('#directfriendreq').val(),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function (resp) {
				$('<div/>').addClass('successbox').addClass('directreqresult').text(resp.html).hide().insertAfter('#senddirectreq').slideDown();
				$this.attr('disabled', false);
			}).fail(function (message, resp) {
				$('<div/>').addClass('errorbox').addClass('directreqresult').text(resp.error.info).hide().insertAfter('#senddirectreq').slideDown();
				$this.attr('disabled', false);
			});
		}
	},

		profile = {
			editForms: null,
			overlay: $('<div class="overlay"><span class="fa fa-spinner fa-2x fa-pulse"></span></div>'),
			removeUnfinished: function(){
				if (profile.editForms) {
					$("#" + profile.editForms.attr('id').replace("block-", "")).show();
					profile.editForms.remove();
					profile.editForms = null;
				}
			},
			editField: function (e, field, blockId) {
				var $this = $(this),
					$profile = $('.curseprofile'),
					$block = $('#' + blockId);
				e.preventDefault();

				// remove any currently active edit forms since they will all get screwed up anyway
				profile.removeUnfinished();

				// obscure comment with translucent throbber
				$block.append(profile.overlay);

				//Create new form to function as an edit form.
				profile.editForms = $('<div>').addClass('entryform').attr('id', 'block-' + blockId);
				profile.editForms.append('<form data-field="' + field + '"><textarea class="autoresizeme" maxlength="5000"></textarea><button class="cancel"></button><button class="save"></button></form>');
				profile.editForms.find('button.cancel').text(mw.message('cancel').text());
				profile.editForms.find('button.save').text(mw.message('save').text());
				autosize(profile.editForms.find('textarea'));

				//Use API to download raw text.
				(new mw.Api()).post({
					action: 'profile',
					do: 'getRawField',
					field: field,
					user_id: $profile.data('user_id'),
					format: 'json',
					formatversion: 2,
					token: mw.user.tokens.get('csrfToken')
				}).done(function (resp) {
					if (resp[field] !== undefined && resp[field] !== null) {
						//Insert edit form into DOM to replace throbber.
						$block.hide().after(profile.editForms);
						//Insert raw comment text in to edit form.
						profile.editForms.find('textarea').val(resp[field]).trigger('autosize:update');
						autosize($('.autoresizeme'));
					} else {
						profile.cancelEdit();
					}
				});

			},

			editSocialField: function (e, getfields, blockId) {
				// Here we have a really similar, but not the same as editField function. FUN.
				var $this = $(this),
					$profile = $('.curseprofile'),
					$block = $('#' + blockId);
				e.preventDefault();



				var fields = getfields.split(" ");

				// remove any currently active edit forms since they will all get screwed up anyway
				profile.removeUnfinished();

				// obscure comment with translucent throbber
				$block.append(profile.overlay);

				var editFormInner = '<form data-field="' + getfields + '" data-block-id="'+blockId+'">';
					for (var x in fields) {
						var title = mw.message('profile-' + fields[x] ).text();
						var placeholder = mw.message(fields[x].replace('link-','') + 'linkplaceholder' ).text();
						editFormInner += '<strong>' + title +':</strong> <input type="text" name="edit-'+fields[x]+'" placeholder="'+placeholder+'" class="sociallink"/><br />';
					}
				editFormInner += '<button class="cancel"></button><button class="saveGroup"></button></form>'

				//Create new form to function as an edit form.
				profile.editForms = $('<div>').addClass('entryform').attr('id', 'block-' + blockId);
				profile.editForms.append(editFormInner);
				profile.editForms.find('button.cancel').text(mw.message('cancel').text());
				profile.editForms.find('button.saveGroup').text(mw.message('save').text());

				for (var x in fields) {
				//Use API to download raw text.
					(new mw.Api()).post({
						action: 'profile',
						do: 'getRawField',
						field: fields[x],
						user_id: $profile.data('user_id'),
						format: 'json',
						formatversion: 2,
						token: mw.user.tokens.get('csrfToken')
					}).done(function (resp) {
						var x = this, field = fields[x];
						if (resp[field] !== undefined && resp[field] !== null) {
							//Insert edit form into DOM to replace throbber.

							$block.hide().after(profile.editForms);

							//Insert raw comment text in to edit form.
							$("input[name=\"edit-" + field + "\"]").val(resp[field]);
						} else {
							profile.cancelEdit();
						}
					}.bind(x));
				}

			},

			cancelEdit: function (e, fields, blockId) {

				var fields = fields.split(" "); // handle both multi-field and single field.
				if (fields.length == 1) {
					var $block = $('#profile-' + fields[0])
				} else {
					var $block = $('#'+blockId);
				}

				e.preventDefault();

				//Remove edit form and show old comment content.
				profile.overlay.detach();
				profile.removeUnfinished();
				$block.show();
			},

			saveField: function (e, fields, blockId) {
				var $this = $(this), $profile = $('.curseprofile'), api = new mw.Api();
				e.preventDefault();

				var fields = fields.split(" "); // handle both multi-field and single field.
				// overlay throbber
				profile.editForms.append(profile.overlay);

				if (fields.length == 1) {
					var field = fields[0];
					var $block = $('#profile-' + field);

					api.post({
						action: 'profile',
						do: 'editField',
						field: field,
						user_id: $profile.data('user_id'),
						text: profile.editForms.find('textarea').val(),
						format: 'json',
						formatversion: 2,
						token: mw.user.tokens.get('csrfToken')
					}).done(function (resp) {
						if (resp.result === 'success') {
							// replace the text of the old comment object
							$block.html(resp.parsedContent);
							// end the editing context
							profile.cancelEdit(e, field);
						} else if (resp.result === 'failure') {
							alert(mw.message(resp.errormsg).text());
							profile.cancelEdit(e, field);
						} else {
							profile.cancelEdit(e, field);
						}
					});
				} else {
					/*
						This is a social edit.
					*/
					var $block = $('#'+blockId);

					var data = {};
					for (var x in fields) {
						var field = fields[x];
						var value = $("input[name=\"edit-" + field + "\"]").val();
						data[field] = value;
					}

					data = JSON.stringify(data);
					// use API to post new comment text
					api.post({
						action: 'profile',
						do: 'editSocialFields',
						data: data,
						user_id: $profile.data('user_id'),
						format: 'json',
						formatversion: 2,
						token: mw.user.tokens.get('csrfToken')
					}).done(function (resp) {
						if (resp.result === 'success') {
							// replace the text of the old comment object
							$block.replaceWith(resp.parsedContent);
							// end the editing context
							profile.cancelEdit(e, field);
						} else if (resp.result === 'failure') {
							alert(mw.message(resp.errormsg).text());
							profile.cancelEdit(e, field);
						} else {
							profile.cancelEdit(e, field);
						}
					});

				}

			}
	};
}

var CP = new CurseProfile(jQuery);
jQuery(document).ready(CP.init);
