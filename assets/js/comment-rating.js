/**
 * Comment Rating Plugin JavaScript
 * Handles AJAX voting with smooth animations and user feedback
 */

(function ($) {
    'use strict';

    $(document).ready(function () {


        /**
         * Move voting buttons next to reply link (to the left)
         */
        function positionVotingButtons() {
            $('.cr-voting-wrapper').each(function () {
                const $wrapper = $(this);
                // Find the comment container
                const $comment = $wrapper.closest('article, .comment, li[id^="comment-"]');
                // Look for reply link in common locations
                const $replyLink = $comment.find('.comment-reply-link, .reply a, a.comment-reply-link').first();

                if ($replyLink.length) {
                    // Move voting wrapper before (to the left of) reply link
                    $replyLink.before($wrapper);
                } else {
                    // Fallback: look for .reply container
                    const $replyContainer = $comment.find('.reply, .comment-metadata .reply').first();
                    if ($replyContainer.length) {
                        $replyContainer.prepend($wrapper);
                    }
                }
            });
        }

        // Position buttons on load
        positionVotingButtons();


        /**
         * Handle vote button clicks
         */
        $(document).on('click', '.cr-vote-btn', function (e) {
            e.preventDefault();

            const $button = $(this);
            const $wrapper = $button.closest('.cr-voting-wrapper');
            const $voteCount = $wrapper.find('.cr-vote-count');
            const commentId = $wrapper.data('comment-id');
            const voteType = parseInt($button.data('vote'));

            // Prevent multiple clicks
            if ($wrapper.hasClass('cr-loading')) {
                return;
            }

            // Add loading state
            $wrapper.addClass('cr-loading');

            // Send AJAX request
            $.ajax({
                url: crAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cr_vote',
                    comment_id: commentId,
                    vote_type: voteType,
                    nonce: crAjax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;
                        const newTotal = data.votes.total;
                        const userVote = data.user_vote;

                        // Update vote count with animation
                        $voteCount.addClass('cr-updated');
                        $voteCount.text(newTotal);

                        setTimeout(function () {
                            $voteCount.removeClass('cr-updated');
                        }, 300);

                        // Update button states
                        $wrapper.find('.cr-vote-btn').removeClass('cr-active');

                        if (userVote === 1) {
                            $wrapper.find('.cr-upvote').addClass('cr-active');
                        } else if (userVote === -1) {
                            $wrapper.find('.cr-downvote').addClass('cr-active');
                        }

                        // Update wrapper class for count color
                        $wrapper.removeClass('cr-positive cr-negative');
                        if (newTotal > 0) {
                            $wrapper.addClass('cr-positive');
                        } else if (newTotal < 0) {
                            $wrapper.addClass('cr-negative');
                        }

                        // Visual feedback
                        $button.css('transform', 'scale(1.2)');
                        setTimeout(function () {
                            $button.css('transform', '');
                        }, 200);

                    } else {
                        // Show error message
                        showNotification($wrapper, crAjax.errorMessage, 'error');
                    }
                },
                error: function () {
                    // Show error message
                    showNotification($wrapper, crAjax.errorMessage, 'error');
                },
                complete: function () {
                    // Remove loading state
                    $wrapper.removeClass('cr-loading');
                }
            });
        });

        /**
         * Show notification message
         */
        function showNotification($wrapper, message, type) {
            const $notification = $('<div class="cr-notification cr-notification-' + type + '">' + message + '</div>');

            $wrapper.append($notification);

            setTimeout(function () {
                $notification.addClass('cr-show');
            }, 10);

            setTimeout(function () {
                $notification.removeClass('cr-show');
                setTimeout(function () {
                    $notification.remove();
                }, 300);
            }, 3000);
        }

        /**
         * Initialize vote count colors on page load
         */
        $('.cr-voting-wrapper').each(function () {
            const $wrapper = $(this);
            const $voteCount = $wrapper.find('.cr-vote-count');
            const count = parseInt($voteCount.text());

            if (count > 0) {
                $wrapper.addClass('cr-positive');
            } else if (count < 0) {
                $wrapper.addClass('cr-negative');
            }
        });

        /**
         * Keyboard accessibility
         */
        $(document).on('keydown', '.cr-vote-btn', function (e) {
            // Allow Enter or Space to trigger vote
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });

    });

})(jQuery);
