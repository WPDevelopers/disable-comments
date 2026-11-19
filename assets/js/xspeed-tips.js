/**
 * xSpeed Cache tips on the Disable Comments settings screen.
 *
 * The save and delete tips are printed hidden and shown once: the save tip
 * right after `disableComments:saved`, the delete tip on the load that
 * follows `disableComments:deleted` (that event reloads the page).
 * Showing one records it as seen, so it does not come back. The X on any tip
 * hides it for good.
 */
(function ($) {
	'use strict';

	var cfg = window.disableCommentsXSpeedTips;
	if (!cfg) {
		return;
	}

	function remember(tip) {
		$.post(cfg.ajaxUrl, {
			action: cfg.action,
			nonce: cfg.nonce,
			tip: tip
		});
	}

	function reveal(tip) {
		var $tip = $('[data-dc-xspeed-tip="' + tip + '"]');
		if (!$tip.length || $tip.data('dcShown')) {
			return $();
		}
		$tip.data('dcShown', true).prop('hidden', false);
		remember(tip);
		return $tip;
	}

	$(document).on('click', '[data-dc-xspeed-dismiss]', function () {
		var tip = $(this).attr('data-dc-xspeed-dismiss');
		$('[data-dc-xspeed-tip="' + tip + '"]').prop('hidden', true);
		remember(tip);
	});

	// "Scan my site free": start a scan of this site and open it on xSpeed's
	// scan experience page. The tab is opened inside the click (so popup
	// blockers allow it) and is pointed at the scan once the site has started
	// it. If anything fails it lands on the xspeedcache.com home page, which
	// has its own scan box, never on a dead tab.
	$(document).on('click', '[data-dc-xspeed-scan]', function (e) {
		var $link = $(this);
		var fallback = $link.attr('href');

		if ($link.data('dcBusy')) {
			e.preventDefault();
			return;
		}

		var tab = window.open('', '_blank');
		if (!tab) {
			return; // Blocked: let the plain link open the scan page.
		}
		e.preventDefault();

		try {
			tab.opener = null;
			tab.document.title = cfg.tabTitle;
			tab.document.body.style.cssText = 'font:16px/1.5 system-ui,sans-serif;color:#22282d;display:grid;place-items:center;height:100vh;margin:0';
			tab.document.body.textContent = cfg.tabTitle;
		} catch (err) {}

		var label = $link.text();
		$link.data('dcBusy', true).addClass('is-busy').attr('aria-busy', 'true').text(cfg.starting);
		var $note = $link.closest('.dc-xspeed-card').find('.dc-xspeed-card__note');
		$note.prop('hidden', true).text('');

		$.post(cfg.ajaxUrl, { action: cfg.scanAction, nonce: cfg.nonce, tip: $link.attr('data-dc-xspeed-scan') })
			.done(function (res) {
				var data = res && res.success ? res.data : null;
				tab.location.href = data && data.url ? data.url : fallback;
				if (data && !data.ok && data.message) {
					$note.text(data.message).prop('hidden', false);
				}
			})
			.fail(function () {
				tab.location.href = fallback;
			})
			.always(function () {
				$link.data('dcBusy', false).removeClass('is-busy').removeAttr('aria-busy').text(label);
			});
	});

	$(document).on('disableComments:saved', function () {
		reveal('save');
	});

	// A delete that removes something is followed by a page reload (that is
	// how the review prompt gets on screen), which would wipe a tip shown
	// now. So the count is kept for this tab and the tip is shown on the next
	// load instead, once no review prompt is asking for attention.
	var KEY = 'dcXSpeedDeleted';

	function store(value) {
		try {
			if (value === null) {
				window.sessionStorage.removeItem(KEY);
			} else {
				window.sessionStorage.setItem(KEY, JSON.stringify(value));
			}
		} catch (e) {}
	}

	function pending() {
		try {
			return JSON.parse(window.sessionStorage.getItem(KEY) || 'null');
		} catch (e) {
			return null;
		}
	}

	$(document).on('disableComments:deleted', function (e, deleted) {
		deleted = parseInt(deleted, 10) || 0;
		if (deleted >= cfg.deleteMin) {
			store({ count: deleted, at: Date.now() });
		}
	});

	$(function () {
		var last = pending();
		if (!last) {
			return;
		}
		// Too old to be about something the admin just did.
		if (Date.now() - last.at > 5 * 60 * 1000) {
			store(null);
			return;
		}
		// One ask at a time: the review prompt goes first.
		if ($('#disable_comments_review_prompt:visible').length) {
			return;
		}
		var $tip = $('[data-dc-xspeed-tip="delete"]');
		if (!$tip.length) {
			store(null);
			return;
		}
		$tip.find('.dc-xspeed-tip__count').text(Number(last.count).toLocaleString());
		store(null);
		// Back to the tab the admin was on, so the tip is where they look.
		if (!$('#deleteComments').is(':visible')) {
			$('#deleteCommentsNav a').trigger('click');
		}
		reveal('delete');
	});
})(jQuery);
