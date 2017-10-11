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
				.on('click', 'button.cancel', function (element) {
					var fieldParent = $(this).parents("form[data-field]");
					profile.cancelEdit(element, $(fieldParent).attr('data-field'));
				})
				.on('click', 'button.save', function (element) {
					var fieldParent = $(this).parents("form[data-field]");
					profile.saveField(element, $(fieldParent).attr('data-field'));
				});
		});
		friendship.init();
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
				global_id: $this.data('id'),
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

			editField: function (e, field, blockId) {
				var $this = $(this),
					$profile = $('.curseprofile'),
					$block = $('#' + blockId);
				e.preventDefault();

				// remove any currently active edit forms since they will all get screwed up anyway
				if (profile.editForms) {
					$("#" + profile.editForms.attr('id').replace("block-", "")).show();
					profile.editForms.remove();
					profile.editForms = null;
				}

				console.log('Making edit box for ' + field);
				// obscure comment with translucent throbber
				$block.append(profile.overlay);

				//Create new form to function as an edit form.
				profile.editForms = $('<div>').addClass('entryform').attr('id', 'block-' + blockId);
				profile.editForms.append('<form data-field="' + field + '"><textarea maxlength="5000"></textarea><button class="cancel"></button><button class="save"></button></form>');
				profile.editForms.find('button.cancel').text(mw.message('cancel').text());
				profile.editForms.find('button.save').text(mw.message('save').text());
				autosize(profile.editForms.find('textarea'));

				//Use API to download raw text.
				(new mw.Api()).post({
					action: 'profile',
					do: 'getRawField',
					field: field,
					userId: $profile.data('userid'),
					format: 'json',
					formatversion: 2,
					token: mw.user.tokens.get('csrfToken')
				}).done(function (resp) {
					if (resp[field] !== undefined && resp[field] !== null) {
						//Insert edit form into DOM to replace throbber.

						$block.hide().after(profile.editForms);

						//Insert raw comment text in to edit form.
						profile.editForms.find('textarea').val(resp[field]).trigger('autosize:update');
					} else {
						profile.cancelEdit();
					}
				});

			},

			cancelEdit: function (e, field) {
				var $block = $('#profile-' + field);
				if (e && e.preventDefault) {
					e.preventDefault();
				}

				//Remove edit form and show old comment content.
				profile.overlay.detach();
				for (var x in profile.editForms) {
					profile.editForms.remove();
				}
				$block.show();
			},

			saveField: function (e, field) {
				var $this = $(this), $block = $('#profile-' + field), $profile = $('.curseprofile'), $editPencil = $('#profile-' + field + ' a.profileedit'), api = new mw.Api();
				e.preventDefault();



				// overlay throbber
				profile.editForms.append(profile.overlay);

				// use API to post new comment text
				api.post({
					action: 'profile',
					do: 'editField',
					field: field,
					userId: $profile.data('userid'),
					text: profile.editForms.find('textarea').val(),
					format: 'json',
					formatversion: 2,
					token: mw.user.tokens.get('csrfToken')
				}).done(function (resp) {
					if (resp.result === 'success') {
						// replace the text of the old comment object
						$editPencil.detach();
						$block.html(resp.parsedContent);
						$block.prepend($editPencil);
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
		};
}

var CP = new CurseProfile(jQuery);
jQuery(document).ready(CP.init);
