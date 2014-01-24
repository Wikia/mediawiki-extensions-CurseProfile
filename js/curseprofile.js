function CurseProfile($) {
	'use strict';
	this.init = function() {
		self.user_id = $('.curseprofile').data('userid');
		$('time.timeago').timeago(); // enable dynamic relative times on recent activity and comments
		commentBoard.init();
	};

	this.ajax = function(method, params) {
		return $.ajax({
			type: 'GET',
			url: mw.util.wikiScript(),
			data: {
				action: 'ajax',
				rs: 'CurseProfile\\'+method,
				rsargs: params
			},
			dataType: 'html',
		});
	};

	/**
	 * Because the 'this' reference shifts around due to jQuery callbacks and such,
	 * the variable 'self' will be used as a convenient shortcut back to the CurseProfile instance.
	 */
	var self = this,

	/**
	 * All functionality for comment boards
	 */
	commentBoard = {
		page: 1,
		replyForm: null,

		init: function() {
			$('.reply-count').on('click', commentBoard.loadReplies);
			$('.comments').on('click', 'a.newreply', commentBoard.newReply);
		},

		loadReplies: function(e, callback) {
			var $this = $(this);
			$this.attr('disabled', 'true');
			self.ajax('CommentDisplay::repliesTo', [self.user_id, $this.data('id')])
				.done(function(r){
					var $r = $(r);
					$r.find('time.timeago').timeago();
					$this.closest('.commentdisplay').append($r);
					$this.attr('disabled', false);
					$this.detach();
					if (typeof callback !== 'undefined') {
						callback();
					}
				})
				.fail(function(r) {
					$this.attr('disabled', false);
					console.debug(r);
				});
			// if the button is left disabled when detached, the browser will remember and keep it disabled on page reloads
			// thus it needs to be re-enabled before we get rid of it
		},

		newReply: function(e) {
			var $replySet, $replyHolder, $textarea, $this = $(this).closest('.commentdisplay'),
				placeReplyBox = function() {
					if (commentBoard.replyForm === null) {
						commentBoard.replyForm = $('.add-comment').clone();
						// Update placeholder to use the reply-specific one
						$textarea = commentBoard.replyForm.find('textarea');
						$textarea.prop('placeholder', $textarea.data('replyplaceholder'));
					} else {
						commentBoard.replyForm.detach();
						$textarea = commentBoard.replyForm.find('textarea');
					}

					// append to the .replyset
					commentBoard.replyForm.appendTo($replySet);
					// set which this will be a reply to
					commentBoard.replyForm.find('[name=inreplyto]').attr('value', $replyHolder.data('id'));
					$textarea.focus();
				};
			e.preventDefault();

			// Get top level comment
			$replyHolder = $this.parents('.commentdisplay');
			if ($replyHolder.length === 0) {
				$replyHolder = $this;
			}

			// check for .replyset below
			$replySet = $replyHolder.find('.replyset');
			// create or load a new one if it doesn't exist
			if ($replySet.length === 0) {
				// check for .reply-count button
				if ($replyHolder.find('.reply-count').length === 0) {
					// create a replyset if replies do not exist
					$replySet = $('<div class="replyset"></div>');
					$replyHolder.append($replySet);
				} else {
					// load replies if they exist
					$replyHolder.find('.reply-count').trigger('click', function() {
						$replySet = $replyHolder.find('.replyset');
						placeReplyBox();
					});
				}
			}

			if ($replySet.length !== 0) {
				placeReplyBox();
			}
		}
	};
}

var CP = new CurseProfile(jQuery);
jQuery(document).ready(CP.init);
