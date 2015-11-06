'use strict';
(function($) {
	function init() {
		$(document)
			.on('click', '.moderation-actions .actions .del', {action: 'delete'}, takeAction)
			.on('click', '.moderation-actions .actions .dis', {action: 'dismiss'}, takeAction)
			.on('click', '.moderation-actions .confirm a', confirm)
			.on('click', cancel);
	}

	function takeAction(e) {
		var $this = $(this);
		cancel();
		$this.closest('.moderation-actions').addClass('do-confirm')
			.find('.confirm a')
			.removeClass('del dis')
			.addClass( e.data.action.slice(0,3) )
			.text( mw.message('report-confirm'+e.data.action).text() );
	}

	function confirm() {
		var $this = $(this), reportKey = $this.closest('.report-item').data('key'), action;
		if ($this.hasClass('del')) {
			action = 'delete'
		} else if ($this.hasClass('dis')) {
			action = 'dismiss';
		} else {
			return;
		}

		// do ajax call
		(new mw.Api()).post({
			action: 'comment',
			do: 'resolveReport',
			reportKey: reportKey,
			withAction: action,
			token: mw.user.tokens.get('editToken')
		}).done(function(resp) {
			var success = resp.result === 'success' || resp.result === 'queued';

			cancel();
			if (success) {
				$this.closest('.report-item').slideUp();
			} else {
				window.alert('Error submitting moderation action. Check the JS console for debug info.');
				console.dir(resp);
			}
		}).fail(function(code, resp) {
			console.dir(resp);
			cancel();
		});

		$this.closest('.moderation-actions').addClass('working');
	}

	function cancel(e) {
		// only run if e is empty (called manually) or if the click was outside of a moderation-actions element
		if (!e || !$(e.target).closest('.moderation-actions').length) {
			$('.moderation-actions').removeClass('do-confirm working');
		}
	}

	$(document).ready(init);
})(jQuery);
