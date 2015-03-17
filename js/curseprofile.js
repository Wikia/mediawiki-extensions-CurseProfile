function CurseProfile($) {
	'use strict';
	this.init = function() {
		$('button.linksub').click(function() {
			window.location = $(this).data('href');
		});
		$('a.profilepurge').click(profile.purgeAboutMe);
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
		purgeAboutMe: function(e) {
			var $this = $(this), $profile = $this.closest('.curseprofile');
			e.preventDefault();
			if ( !window.confirm( mw.message('purgeaboutme-prompt').text() ) ) {
				return;
			}
			$this.hide();
			(new mw.Api()).post({
				action: 'profile',
				do: 'purgeAboutMe',
				userId: $profile.data('userid'),
				token: mw.user.tokens.get('editToken')
			}).done(function(resp) {
				$('.aboutme').slideUp();
			}).fail(function(code, resp) {
				$this.show();
				console.dir(resp);
			});
		},
	};
}

var CP = new CurseProfile(jQuery);
jQuery(document).ready(CP.init);
