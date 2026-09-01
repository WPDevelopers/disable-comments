/**
 * Dismissing the post-delete review prompt.
 *
 * Separate from the settings-page bundle because the prompt is not: it renders
 * on every screen get_own_screen_ids() covers, which includes the Tools page
 * and the -network variants. The dismiss handlers used to live in
 * disable-comments-settings-scripts.js, which is only enqueued on the settings
 * screen — so on the other screens nothing was listening and the notice came
 * back on the next page load however you dismissed it.
 *
 * Deliberately depends on nothing but jQuery. The nonce comes from the prompt's
 * own data attribute and `ajaxurl` is defined on every admin screen, so this
 * needs no localized object and can load anywhere the notice can.
 */
jQuery(function ($) {
	var prompt = $('#disable_comments_review_prompt');

	if (!prompt.length) {
		return;
	}

	var dismissed = false;

	var dismiss = function () {
		// The three entry points below can all fire for one dismissal — the
		// core X sits inside the notice this handler is bound to — and the
		// meta write is the same either way, so send it once.
		if (dismissed) {
			return;
		}

		dismissed = true;

		$.post(ajaxurl, {
			action: 'disable_comments_dismiss_review',
			nonce: prompt.data('nonce')
		});
	};

	prompt.on('click', '[data-dc-review="dismiss"]', function () {
		dismiss();
		prompt.fadeOut();
	});

	// Someone who clicks through to leave a review has answered the ask;
	// showing it to them again would be the annoying part.
	prompt.on('click', '[data-dc-review="leave"]', dismiss);

	// The core dismiss button is rendered by WordPress after this runs.
	prompt.on('click', '.notice-dismiss', dismiss);
});
