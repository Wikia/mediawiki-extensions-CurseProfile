function CurseProfile($) {
	'use strict';
	this.init = function() {
		self.user_id = $('.curseprofile').data('userid');
		$('time.timeago').timeago(); // enable dynamic relative times on recent activity and comments
		$('button.linksub').click(function() {
			window.location = $(this).data('href');
		});
		commentBoard.init();
		friendship.init();
		window.commentBoard = commentBoard;
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
		editForm: null,
		editedComment: null,

		init: function() {
			$('.reply-count').on('click', commentBoard.loadReplies);
			$('.comments')
				.on('click', 'a.newreply', commentBoard.newReply)
				.on('click', 'a.edit', commentBoard.editComment)
				.on('click', 'form.edit .submit', commentBoard.submitCommentEdit)
				.on('click', 'form.edit .cancel', commentBoard.cancelCommentEdit)
				.on('click', 'a.remove', commentBoard.removeComment);
			$('.commentdisplay .entryform textarea').autosize(); // grow as more text is entered
		},

		loadReplies: function(e, callback) {
			var $this = $(this);
			$this.attr('disabled', 'true');
			self.ajax('CommentDisplay::repliesTo', [self.user_id, $this.data('id')])
				.done(function(r){
					var $r = $(r);
					$r.find('time.timeago').timeago();
					$this.closest('.replyset').html($r);
					$this.attr('disabled', false);
					$this.detach();
					if (typeof callback !== 'undefined') {
						callback();
					}
				})
				.fail(function(code, resp) {
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
						commentBoard.replyForm = $('.add-comment').clone().removeClass('hidden');
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

			// if the replies now exist after attempts to load them, we can put the reply box below them
			if ($replySet.length !== 0) {
				placeReplyBox();
			}
		},

		editComment: function(e) {
			var $this = $(this), $comment = $this.closest('.commentdisplay');
			e.preventDefault();

			// obscure comment with translucent throbber
			$comment.append('<div class="overlay"></div>');

			// clone and alter new comment form to function as an edit form
			if (commentBoard.editForm === null) {
				commentBoard.editForm = $('.add-comment').clone().removeClass('hidden');
				// Update the form to behave as an edit instead of a reply
				commentBoard.editForm.find('form').addClass('edit');
				commentBoard.editForm.find('button').addClass('submit')
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
				comment_id: $comment.data('id')
			}).done(function(resp) {
				if (resp.text) {
					// insert raw comment text in to edit form
					commentBoard.editForm.find('textarea').val(resp.text);

					// insert edit form into DOM to replace throbber
					commentBoard.editForm.append($comment.find('.replyset'));
					$comment.hide().after(commentBoard.editForm);
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
			commentBoard.editForm.append('<div class="overlay"></div>');

			// use API to post new comment text
			api.post({
				action: 'comment',
				do: 'edit',
				comment_id: $comment.data('id'),
				text: commentBoard.editForm.find('textarea').val(),
				token: mw.user.tokens.get('editToken')
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
			$this.hide();
			(new mw.Api()).post({
				action: 'comment',
				do: 'remove',
				comment_id: $comment.data('id'),
				token: mw.user.tokens.get('editToken')
			}).done(function(resp) {
				if (resp.html) {
					$comment.slideUp();
				}
			}).fail(function(code, resp) {
				$this.show();
				console.dir(resp);
			});
		}
	},

	/**
	 * All friending-related ajax functions
	 */
	friendship = {
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
	};
}

var CP = new CurseProfile(jQuery);
jQuery(document).ready(CP.init);
