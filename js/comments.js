'use strict';
(function($) {

	/**
	 * All functionality for comment boards
	 */
	var commentBoard = {
		replyForm: null,
		editForm: null,
		editedComment: null,

		init: function() {
			// attach events
			$('.reply-count').on('click', commentBoard.loadReplies);
			$('.comments')
				.on('click', '.add-comment .submit', commentBoard.preventDoublePost)
				.on('click', 'a.newreply', commentBoard.newReply)
				.on('click', 'a.edit', commentBoard.editComment)
				.on('click', 'form.edit .submit', commentBoard.submitCommentEdit)
				.on('click', 'form.edit .cancel', commentBoard.cancelCommentEdit)
				.on('click', 'a.remove', commentBoard.removeComment)
				.on('click', 'a.restore', commentBoard.restoreComment)
				.on('click', 'a.purge', commentBoard.purgeComment)
				.on('click', 'a.report', commentBoard.reportComment)
				.on('keydown', '.commentdisplay .entryform textarea', commentBoard.ctrlEnter);

			// Auto-size comment field
			autosize($('.commentdisplay .entryform textarea'));

			// Dynamic relative timestamps
			$('time.timeago').timeago();

			// Avoid cached "disabled" attributes
			$('.comments button.submit').attr('disabled', false);
		},

		preventDoublePost: function(e) {
			e.preventDefault();
			$(this).attr('disabled', true).closest('form').submit();
		},

		loadReplies: function(e, callback) {
			var $this = $(this);
			$this.attr('disabled', 'true');
			(new mw.Api()).post({
				action: 'comment',
				do: 'getReplies',
				user_id: $('.curseprofile').data('user_id'),
				comment_id: $this.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				if (resp.html) {
					var $replies = $(resp.html);
					$replies.find('time.timeago').timeago();
					$this.closest('.replyset').html($replies);
					$this.attr('disabled', false);
					$this.detach();
					if (typeof callback !== 'undefined') {
						callback();
					}
				}
			}).fail(function(code, resp) {
				$this.attr('disabled', false);
				console.debug(resp);
			});
			// if the button is left disabled when detached, the browser will remember and keep it disabled on page reloads
			// thus it needs to be re-enabled before we get rid of it
		},

		newReply: function(e) {
			var $replySet, $replyHolder, $textarea, $this = $(this).closest('.commentdisplay'),
				placeReplyBox = function() {
					if (commentBoard.replyForm === null) {
						commentBoard.replyForm = $('.add-comment').clone().removeClass('hidden add-comment').addClass('add-reply');
						// Update placeholder to use the reply-specific one
						$textarea = commentBoard.replyForm.find('textarea');
						autosize($textarea.prop('placeholder', $textarea.data('replyplaceholder')));
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

			// if the replies now exist after attempts to load them, we can put the reply box below them
			if ($replySet.length !== 0) {
				placeReplyBox();
			}
		},

		overlay: '<div class="overlay"><span class="fa fa-spinner fa-2x fa-pulse"></span></div>',

		editComment: function(e) {
			var $this = $(this), $comment = $this.closest('.commentdisplay');
			e.preventDefault();

			// obscure comment with translucent throbber
			$comment.append(commentBoard.overlay);

			// clone and alter new comment form to function as an edit form
			if (commentBoard.editForm === null) {
				commentBoard.editForm = $('.add-comment').clone().removeClass('hidden add-comment').addClass('edit-comment');
				autosize(commentBoard.editForm.find('textarea'));
				// Update the form to behave as an edit instead of a reply
				commentBoard.editForm.find('form').addClass('edit');
				commentBoard.editForm.find('button').text(mw.message('save').text())
					.after('<input type="hidden" name="comment_id" value="" />')
					.before('<button class="cancel"></button>').prev().text(mw.message('cancel').text());
			} else {
				// cancel any pending edits
				if (commentBoard.editedComment) {
					commentBoard.cancelCommentEdit();
				}
				commentBoard.editForm.detach();
			}
			commentBoard.editForm.find('input[name=comment_id]').attr('value', $comment.data('id'));

			// use API to download raw comment text
			(new mw.Api()).post({
				action: 'comment',
				do: 'getRaw',
				comment_id: $comment.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				if (resp.text) {
					// insert edit form into DOM to replace throbber
					commentBoard.editForm.append($comment.find('.replyset'));
					$comment.hide().after(commentBoard.editForm);

					// insert raw comment text in to edit form
					commentBoard.editForm.find('textarea').val(resp.text).trigger('autosize:update');
				}
			});

			commentBoard.editedComment = $comment;
		},

		cancelCommentEdit: function(e) {
			var $comment = commentBoard.editedComment;
			if (e && e.preventDefault) {
				e.preventDefault();
			}

			if (!$comment) {
				return;
			}

			// remove edit form and show old comment content
			$comment.append(commentBoard.editForm.find('.replyset')).find('div.overlay').detach();
			$comment.show();
			commentBoard.editForm.detach().find('div.overlay').detach();

			// mark that we don't have a pending edit anymore
			commentBoard.editedComment = null;
		},

		submitCommentEdit: function(e) {
			var $this = $(this), $comment = commentBoard.editedComment, api = new mw.Api();
			e.preventDefault();

			// overlay throbber
			commentBoard.editForm.append(commentBoard.overlay);

			// use API to post new comment text
			api.post({
				action: 'comment',
				do: 'edit',
				comment_id: $comment.data('id'),
				text: commentBoard.editForm.find('textarea').val(),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				if (resp.result === 'success') {
					// replace the text of the old comment object
					$comment.find('.commentbody').html(resp.parsedContent);
					// end the editing context
					commentBoard.cancelCommentEdit();
				}
			});
		},

		removeComment: function(e) {
			var $this = $(this), $comment = $this.closest('.commentdisplay');
			e.preventDefault();
			if ( !window.confirm( mw.message('remove-prompt', $comment.find('a.commentUser').first().text()).text() ) ) {
				return;
			}
			$this.hide();
			(new mw.Api()).post({
				action: 'comment',
				do: 'remove',
				comment_id: $comment.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				if (resp.html) {
					$comment.addClass('deleted').find('.icon').hide();
					$this.removeClass('remove').addClass('restore').show()
						.find('.fa').removeClass('fa-trash').addClass('fa-undo');
				}
			}).fail(function(code, resp) {
				$this.show();
				console.dir(resp);
			});
		},

		restoreComment: function(e) {
			var $this = $(this), $comment = $this.closest('.commentdisplay');
			e.preventDefault();
			$this.hide();
			(new mw.Api()).post({
				action: 'comment',
				do: 'restore',
				comment_id: $comment.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				if (resp.result) {
					$comment.removeClass('deleted');
					$this.removeClass('restore').addClass('remove').show()
						.find('.fa').removeClass('fa-undo').addClass('fa-trash');
				}
			}).fail(function(code, resp) {
				$this.show();
				console.dir(resp);
			});
		},

		purgeComment: function(e) {
			var $this = $(this), $comment = $this.closest('.commentdisplay');
			e.preventDefault();
			if (!window.confirm(mw.message('purge-prompt', $comment.find('a.commentUser').first().text()).text())) {
				return;
			}
			$this.hide();
			(new mw.Api()).post({
				action: 'comment',
				do: 'purge',
				comment_id: $comment.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				if (resp.result === 'success') {
					$comment.slideUp();
				} else {
					$this.show();
					console.dir(resp);
				}
			}).fail(function(code, resp) {
				$this.show();
				console.dir(resp);
			});
		},

		reportComment: function(e) {
			var $this = $(this), $comment = $this.closest('.commentdisplay');
			e.preventDefault();
			if (!window.confirm(mw.message('report-prompt', $comment.find('a.commentUser').first().text()).text())) {
				return;
			}
			$this.hide();
			(new mw.Api()).post({
				action: 'comment',
				do: 'report',
				comment_id: $comment.data('id'),
				format: 'json',
				formatversion: 2,
				token: mw.user.tokens.get('csrfToken')
			}).done(function(resp) {
				// TODO what happens after a comment is reported?
				window.alert( mw.message('report-thanks').text() );
			}).fail(function(code, resp) {
				$this.show();
				console.dir(resp);
			});
		},
		
		ctrlEnter: function(e) {
			if (e.keyCode === 13 && (e.ctrlKey || e.metaKey)) {
				$(this).siblings('.submit').eq(0).click();
			}
		}
	};

	$(document).ready(commentBoard.init);
})(jQuery);
