/**
 * TripCosmos Agents Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initMasterConsole();
		initKillSwitch();
		initHealthRefresh();
	});

	/**
	 * Master Console Chat Logic
	 */
	function initMasterConsole() {
		const $form = $('#tc-console-form');
		const $input = $('#tc-console-input');
		const $feed = $('#tc-console-feed');
		const $sendBtn = $('#tc-console-send');
		const $agentSelect = $('#tc-console-agent-select');
		const $clearBtn = $('#tc-clear-console');

		if (!$form.length) return;

		let sessionId = 'admin_console_' + Math.random().toString(36).substring(2, 9);

		// Handle Enter to send (Shift+Enter for newline)
		$input.on('keydown', function(e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				$form.trigger('submit');
			}
		});

		// Quick Prompts
		$('.tc-quick-prompt').on('click', function(e) {
			e.preventDefault();
			const prompt = $(this).data('prompt');
			$input.val(prompt).focus();
		});

		// Clear View
		$clearBtn.on('click', function() {
			$feed.html('<div class="tc-msg tc-msg-system"><div class="tc-bubble">Console cleared. Session ID: <code>' + sessionId + '</code></div></div>');
		});

		// Form submit
		$form.on('submit', function(e) {
			e.preventDefault();
			const message = $.trim($input.val());
			if (!message) return;

			// Append User Message
			appendMessage('user', message);
			$input.val('');

			// Append Thinking Placeholder
			const $loader = $('<div class="tc-msg tc-msg-assistant tc-loading"><div class="tc-bubble">⏳ <em>Agent orchestrator consulting providers & tools...</em></div></div>');
			$feed.append($loader);
			scrollToBottom();

			$sendBtn.prop('disabled', true);

			$.ajax({
				url: tcAgentsAdmin.restUrl + 'chat',
				method: 'POST',
				headers: {
					'X-WP-Nonce': tcAgentsAdmin.nonce
				},
				contentType: 'application/json',
				data: JSON.stringify({
					message: message,
					session_id: sessionId,
					channel: 'admin',
					agent_slug: $agentSelect.val() || 'tripcosmos-guide'
				}),
				success: function(res) {
					$loader.remove();
					$sendBtn.prop('disabled', false);

					if (res.success) {
						appendMessage('assistant', res.reply, res.provider_used, res.latency_ms);
						if (res.handoff) {
							appendMessage('system', '🔔 Human handoff triggered: <a href="' + res.handoff.whatsapp_url + '" target="_blank">Connect via WhatsApp</a>');
						}
					} else {
						appendMessage('system', '⚠️ Error: ' + (res.error || 'Unknown error occurred.'));
					}
				},
				error: function(xhr) {
					$loader.remove();
					$sendBtn.prop('disabled', false);
					let err = 'Server error';
					try {
						const json = JSON.parse(xhr.responseText);
						err = json.error || json.message || err;
					} catch (e) {}
					appendMessage('system', '⚠️ ' + err);
				}
			});
		});

		function appendMessage(role, text, provider, latency) {
			const $msg = $('<div class="tc-msg tc-msg-' + role + '"></div>');
			const $bubble = $('<div class="tc-bubble"></div>');

			// Format newlines
			$bubble.html(escapeHtml(text).replace(/\n/g, '<br />'));
			$msg.append($bubble);

			if (provider) {
				const $meta = $('<div class="tc-msg-meta">Served by: <strong>' + provider + '</strong> (' + latency + 'ms)</div>');
				$msg.append($meta);
			}

			$feed.append($msg);
			scrollToBottom();
		}

		function scrollToBottom() {
			$feed.scrollTop($feed[0].scrollHeight);
		}

		function escapeHtml(str) {
			return $('<div>').text(str).html();
		}
	}

	/**
	 * Kill Switch Toggle
	 */
	function initKillSwitch() {
		const $btn = $('#tc-toggle-kill-switch');
		if (!$btn.length) return;

		$btn.on('click', function(e) {
			e.preventDefault();
			const current = $btn.data('current');
			const nextState = current === '1' ? '0' : '1';

			const confirmText = nextState === '1'
				? 'Are you sure you want to ENGAGE the Emergency Kill Switch? This will instantly pause all outgoing agent operations across all channels.'
				: 'Disengage Kill Switch and resume agent operations?';

			if (!confirm(confirmText)) return;

			$btn.prop('disabled', true).text('Updating...');

			$.ajax({
				url: tcAgentsAdmin.restUrl + 'kill-switch',
				method: 'POST',
				headers: {
					'X-WP-Nonce': tcAgentsAdmin.nonce
				},
				contentType: 'application/json',
				data: JSON.stringify({ active: nextState === '1' }),
				success: function(res) {
					location.reload();
				},
				error: function(xhr) {
					alert('Failed to toggle kill switch.');
					$btn.prop('disabled', false);
				}
			});
		});
	}

	/**
	 * Health Check Refresher
	 */
	function initHealthRefresh() {
		const $refreshBtn = $('#tc-refresh-health');
		if (!$refreshBtn.length) return;

		$refreshBtn.on('click', function(e) {
			e.preventDefault();
			$refreshBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testing...');

			$.ajax({
				url: tcAgentsAdmin.restUrl + 'health',
				method: 'GET',
				headers: {
					'X-WP-Nonce': tcAgentsAdmin.nonce
				},
				success: function(res) {
					$refreshBtn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh Health Check');
					if (res.providers) {
						$.each(res.providers, function(slug, data) {
							const $card = $('#card-' + slug);
							if ($card.length) {
								$card.find('.tc-latency-val').text(data.latency_ms + ' ms');
								$card.find('.tc-msg-val').text(data.message || data.status);
								$card.attr('class', 'tc-card tc-health-card tc-status-card-' + data.status);
								$card.find('.tc-indicator-dot').attr('class', 'tc-indicator-dot ' + data.status);
							}
						});
					}
				},
				error: function() {
					$refreshBtn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Refresh Health Check');
					alert('Could not refresh health status.');
				}
			});
		});
	}

})(jQuery);
