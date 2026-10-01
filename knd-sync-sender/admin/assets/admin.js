(function ($) {
	'use strict';

	function setResult($el, ok, message) {
		$el.text(message || '').css('color', ok ? '#0f5132' : '#842029');
	}

	$(document).on('click', '#knd-sync-test-connection', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var $out = $('#knd-sync-test-result');
		$btn.prop('disabled', true);
		$out.text('…');

		$.post(kndSyncSender.ajaxUrl, {
			action: 'knd_sync_test_connection',
			nonce: kndSyncSender.nonce
		})
			.done(function (resp) {
				if (resp && resp.success) {
					setResult($out, true, (resp.data && resp.data.message) || kndSyncSender.i18n.connected);
				} else {
					setResult($out, false, (resp && resp.data && resp.data.message) || kndSyncSender.i18n.failed);
				}
			})
			.fail(function (xhr) {
				var msg = kndSyncSender.i18n.failed;
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				setResult($out, false, msg);
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$(document).on('click', '#knd-sync-now', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var postId = $btn.data('post-id');
		$btn.prop('disabled', true).text(kndSyncSender.i18n.syncing);

		$.post(kndSyncSender.ajaxUrl, {
			action: 'knd_sync_manual_sync',
			nonce: kndSyncSender.nonce,
			post_id: postId
		})
			.done(function (resp) {
				if (resp && resp.success && resp.data) {
					$('.knd-sync-post-status').text(resp.data.status || '');
					if (resp.data.target) {
						$('.knd-sync-target-id').text(resp.data.target);
					}
					if (resp.data.last) {
						$('.knd-sync-last').text(resp.data.last + ' UTC');
					}
					if (resp.data.error) {
						alert(resp.data.error);
					}
				} else {
					alert((resp && resp.data && resp.data.message) || 'Sync failed');
				}
			})
			.fail(function (xhr) {
				var msg = 'Sync failed';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				alert(msg);
			})
			.always(function () {
				$btn.prop('disabled', false).text('Sync Now');
			});
	});

	$(document).on('click', '#knd-sync-preview', function (e) {
		e.preventDefault();
		var $btn = $(this);
		var postId = $btn.data('post-id');
		var $box = $('#knd-sync-preview-box');

		$.post(kndSyncSender.ajaxUrl, {
			action: 'knd_sync_preview',
			nonce: kndSyncSender.nonce,
			post_id: postId
		})
			.done(function (resp) {
				if (resp && resp.success && resp.data && resp.data.preview) {
					$box.prop('hidden', false).text(JSON.stringify(resp.data.preview, null, 2));
				} else {
					alert((resp && resp.data && resp.data.message) || 'Preview failed');
				}
			})
			.fail(function () {
				alert('Preview failed');
			});
	});
})(jQuery);
