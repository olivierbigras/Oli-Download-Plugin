/**
 * Inject a Download button into the Appearance → Themes details overlay.
 *
 * @package Oli_Download_Plugin
 */
(function ($, settings) {
	'use strict';

	if (!settings || !settings.urls) {
		return;
	}

	function currentSlug() {
		var $overlay = $('.theme-overlay');
		var slug = $overlay.find('[data-slug]').first().data('slug');

		if (!slug) {
			slug = $('.theme.expanded, .theme.focus, .theme[aria-describedby]').first().data('slug');
		}

		if (!slug && window.location.hash) {
			var match = window.location.hash.match(/theme=([A-Za-z0-9._-]+)/);
			if (match) {
				slug = match[1];
			}
		}

		return slug || '';
	}

	function inject() {
		var $overlay = $('.theme-overlay');
		if (!$overlay.length) {
			return;
		}

		var slug = currentSlug();
		if (!slug || !settings.urls[slug]) {
			return;
		}

		var $actions = $overlay.find('.theme-actions');
		if (!$actions.length) {
			return;
		}

		$actions.find('.oli-dlp-download').remove();

		$('<a>', {
			href: settings.urls[slug],
			class: 'button oli-dlp-download',
			text: settings.label
		}).appendTo($actions);
	}

	$(document).on('click', '.theme, .left, .right, .next, .previous', function () {
		window.setTimeout(inject, 60);
	});

	$(window).on('hashchange', function () {
		window.setTimeout(inject, 60);
	});

	if (window.MutationObserver) {
		var wrap = document.getElementById('wpwrap');
		if (wrap) {
			new MutationObserver(function () {
				inject();
			}).observe(wrap, { childList: true, subtree: true });
		}
	}

	$(inject);
})(jQuery, window.oliDlpThemes || {});
