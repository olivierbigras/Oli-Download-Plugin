/**
 * Inject Download buttons on Appearance → Themes (cards + details overlay).
 *
 * @package Oli_Download_Plugin
 */
(function ($, settings) {
	'use strict';

	if (!settings || !settings.urls) {
		return;
	}

	var overlayObserver = null;

	function decodeUrl(url) {
		if (!url || typeof url !== 'string') {
			return '';
		}
		return url.replace(/&amp;/g, '&');
	}

	function currentSlug() {
		var $overlay = $('.theme-overlay');
		var slug = $overlay.find('[data-slug]').first().data('slug');

		if (!slug) {
			slug = $('.theme.expanded, .theme.focus').first().data('slug');
		}

		if (!slug && window.location.hash) {
			var match = window.location.hash.match(/theme=([A-Za-z0-9._-]+)/);
			if (match) {
				slug = match[1];
			}
		}

		return slug || '';
	}

	function makeButton(slug) {
		var url = decodeUrl(settings.urls[slug] || '');
		if (!url) {
			return $();
		}

		return $('<a>', {
			href: url,
			class: 'button button-primary oli-dlp-download',
			text: settings.label,
			'data-slug': slug
		});
	}

	function injectCards() {
		$('.themes .theme').each(function () {
			var $theme = $(this);
			var slug = $theme.attr('data-slug');
			if (!slug || !settings.urls[slug]) {
				return;
			}

			// WP 7.0 nests .theme-actions one level deeper than a direct child.
			$theme.find('.theme-actions').each(function () {
				var $actions = $(this);
				if ($actions.find('.oli-dlp-download').length) {
					return;
				}

				makeButton(slug).prependTo($actions);
			});
		});
	}

	function injectOverlay() {
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

		var $existing = $actions.find('.oli-dlp-download');
		if ($existing.length) {
			if ($existing.attr('data-slug') === slug) {
				return;
			}
			$existing.remove();
		}

		makeButton(slug).appendTo($actions);
	}

	function withObserverPaused(fn) {
		if (overlayObserver) {
			overlayObserver.disconnect();
		}
		fn();
		observeOverlay();
	}

	function observeOverlay() {
		if (!window.MutationObserver) {
			return;
		}

		var target = document.querySelector('.theme-overlay');
		if (!target) {
			return;
		}

		if (!overlayObserver) {
			overlayObserver = new MutationObserver(function () {
				withObserverPaused(injectOverlay);
			});
		}

		overlayObserver.observe(target, { childList: true, subtree: true });
	}

	$(function () {
		injectCards();
		injectOverlay();
		observeOverlay();
	});

	$(document).on('click', '.theme, .left, .right, .next, .previous', function () {
		window.setTimeout(function () {
			withObserverPaused(function () {
				injectCards();
				injectOverlay();
			});
		}, 60);
	});

	$(window).on('hashchange', function () {
		window.setTimeout(function () {
			withObserverPaused(injectOverlay);
		}, 60);
	});
})(jQuery, window.oliDlpThemes || {});
