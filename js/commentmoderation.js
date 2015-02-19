'use strict';
(function($) {
	function init() {
		$(document)
			.on('click', '.moderation-actions .actions .del', del)
			.on('click', '.moderation-actions .actions .dis', dis)
			.on('click', '.moderation-actions .confirm a', confirm)
			.on('click', cancel);
	}

	function del(e) {
		var $this = $(this);
		cancel();
		$this.closest('.moderation-actions').addClass('do-confirm')
			.find('.confirm a').removeClass('dis').addClass('del').text('Confirm Delete');
	}

	function dis(e) {
		var $this = $(this);
		cancel();
		$this.closest('.moderation-actions').addClass('do-confirm')
			.find('.confirm a').removeClass('del').addClass('dis').text('Confirm Dismiss');
	}

	function confirm() {
		var $this = $(this), action;
		if ($this.hasClass('del')) {
			action = 'delete'
		} else if ($this.hasClass('dis')) {
			action = 'dismiss';
		} else {
			return;
		}

		// do ajax call
		console.debug('comments API call to '+action);
		var success = true;

		$this.closest('.moderation-actions').removeClass('do-confirm');
		if (success) {
			$this.closest('.report-item').slideUp();
		}
	}

	function cancel(e) {
		// only run if e is empty (called manually) or if the click was outside of a moderation-actions element
		if (!e || !$(e.target).closest('.moderation-actions').length) {
			$('.moderation-actions').removeClass('do-confirm');
		}
	}

	$(document).ready(init);
})(jQuery);
