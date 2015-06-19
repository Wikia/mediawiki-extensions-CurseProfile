function CurseProfile($) {
	'use strict';
	this.init = function() {
		$('button.linksub').click(function() {
			window.location = $(this).data('href');
		});

		if ($('.userinfo a.profileedit').length > 0) {
			$('.userinfo')
				.on('click', 'a.profileedit', profile.editAboutMe)
				.on('click', 'button.cancel', profile.cancelEdit)
				.on('click', 'button.save', profile.saveAboutMe);
		}
		friendship.init();
	};

	/**
	 * All friending-related ajax functions
	 */
	var friendship = {
		init: function() {
			$(document).on('click', '.friendship-action', friendship.sendAction);
			$('#senddirectreq').on('click', friendship.sendDirectReq);
		},

		sendAction: function(e) {
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
				curse_id: $this.data('id'),
				token: mw.user.tokens.get('editToken')
			}).done(function(resp) {
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
			}).fail(function(code, resp) {
				$buttons.attr('disabled', false);
			});
		},

		sendDirectReq: function(e) {
			var $this = $(this);
			$('.directreqresult').slideUp().detach(); // remove existing messages
			$this.attr('disabled', true);
			(new mw.Api()).post({
				action: 'friend',
				do: 'directreq',
				name: $('#directfriendreq').val(),
				token: mw.user.tokens.get('editToken')
			}).done(function(resp) {
				$('<div/>').addClass('successbox').addClass('directreqresult').text(resp.html).hide().insertAfter('#senddirectreq').slideDown();
				$this.attr('disabled', false);
			}).fail(function(message, resp) {
				$('<div/>').addClass('errorbox').addClass('directreqresult').text(resp.error.info).hide().insertAfter('#senddirectreq').slideDown();
				$this.attr('disabled', false);
			});
		}
	},

	profile = {
		editForm: null,
		overlay: $('<div class="overlay"><span class="fa fa-spinner fa-2x fa-pulse"></span></div>'),

		editAboutMe: function(e) {
			var $this = $(this), $profile = $('.curseprofile'), $block = $('.aboutme');
			e.preventDefault();

			// obscure comment with translucent throbber
			$block.append(profile.overlay);

			// create new form to function as an edit form
			if (profile.editForm === null) {
				profile.editForm = $('<div>').addClass('entryform');
				profile.editForm.append('<form><textarea maxlength="5000"></textarea><button class="cancel"></button><button class="save"></button></form>');
				profile.editForm.find('button.cancel').text(mw.message('cancel').text());
				profile.editForm.find('button.save').text(mw.message('save').text());
				profile.editForm.find('textarea').autosize();
			}

			// use API to download raw comment text
			(new mw.Api()).post({
				action: 'profile',
				do: 'getRawAboutMe',
				userId: $profile.data('userid')
			}).done(function(resp) {
				if (resp.text !== null) {
					// insert edit form into DOM to replace throbber
					$block.hide().after(profile.editForm);

					// insert raw comment text in to edit form
					profile.editForm.find('textarea').val(resp.text).trigger('autosize.resize');
				} else {
					profile.cancelEdit();
				}
			});
		},

		cancelEdit: function(e) {
			var $block = $('.aboutme');
			if (e && e.preventDefault) {
				e.preventDefault();
			}

			// remove edit form and show old comment content
			profile.overlay.detach();
			profile.editForm.detach();
			$block.show();
		},

		saveAboutMe: function(e) {
			var $this = $(this), $block = $('.aboutme'), $profile = $('.curseprofile'), $editPencil = $('.aboutme a.profileedit'), api = new mw.Api();
			e.preventDefault();

			// overlay throbber
			profile.editForm.append(profile.overlay);

			// use API to post new comment text
			api.post({
				action: 'profile',
				do: 'editAboutMe',
				userId: $profile.data('userid'),
				text: profile.editForm.find('textarea').val(),
				token: mw.user.tokens.get('editToken')
			}).done(function(resp) {
				if (resp.result === 'success') {
					// replace the text of the old comment object
					$editPencil.detach();
					$block.html(resp.parsedContent);
					$block.prepend($editPencil);
					// end the editing context
					profile.cancelEdit();
				}
			});
		},
	};
}

var CP = new CurseProfile(jQuery);
jQuery(document).ready(CP.init);
