/**
 * TripCosmos Agents Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		initMasterConsole();
		initKillSwitch();
		initHealthRefresh();
		initKanbanDragAndDrop();
		initCatalogSync();
		initAvatarUploader();
		initAIPufferBotSync();
		initProviderModelSync();
	});

	/**
	 * Bot Profile Image / Avatar WordPress Media Uploader
	 */
	function initAvatarUploader() {
		const $uploadBtn = $('#tc-upload-bot-avatar');
		const $removeBtn = $('#tc-remove-bot-avatar');
		const $input = $('#tc_widget_avatar');
		const $preview = $('#tc-bot-avatar-preview');
		const $emojiInput = $('#widget_avatar_emoji');

		if (!$uploadBtn.length) return;

		let mediaFrame;

		$uploadBtn.on('click', function(e) {
			e.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: 'Select Chatbot Profile Image (Avatar)',
				button: {
					text: 'Use as Chatbot Avatar'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			mediaFrame.on('select', function() {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				if (attachment && attachment.url) {
					$input.val(attachment.url);
					$preview.html('<img src="' + attachment.url + '" alt="Avatar" style="width:100%;height:100%;object-fit:cover;" />');
					$removeBtn.show();
				}
			});

			mediaFrame.open();
		});

		$removeBtn.on('click', function(e) {
			e.preventDefault();
			$input.val('');
			const emoji = $emojiInput.val() || '🛕';
			$preview.html('<span id="tc-bot-avatar-emoji-preview">' + emoji + '</span>');
			$removeBtn.hide();
		});

		$emojiInput.on('input change', function() {
			if (!$input.val()) {
				$('#tc-bot-avatar-emoji-preview').text($(this).val() || '🛕');
			}
		});
	}

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
							appendMessage('system', '🔗 Human handoff triggered: <a href="' + res.handoff.whatsapp_url + '" target="_blank">Connect via WhatsApp</a>');
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
	 * Kanban Drag-and-Drop Pipeline
	 */
	function initKanbanDragAndDrop() {
		const $cards = $('.tc-kanban-card');
		const $columns = $('.tc-kanban-cards');

		if (!$cards.length || !$columns.length) return;

		let draggedCard = null;

		$cards.on('dragstart', function(e) {
			draggedCard = this;
			$(this).addClass('is-dragging');
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.setData('text/plain', $(this).data('lead-id'));
				e.originalEvent.dataTransfer.effectAllowed = 'move';
			}
		});

		$cards.on('dragend', function() {
			$(this).removeClass('is-dragging');
			$('.tc-kanban-cards').removeClass('drag-over');
			draggedCard = null;
		});

		$columns.on('dragover', function(e) {
			e.preventDefault();
			if (e.originalEvent && e.originalEvent.dataTransfer) {
				e.originalEvent.dataTransfer.dropEffect = 'move';
			}
			$(this).addClass('drag-over');
		});

		$columns.on('dragleave', function(e) {
			if (!this.contains(e.relatedTarget)) {
				$(this).removeClass('drag-over');
			}
		});

		$columns.on('drop', function(e) {
			e.preventDefault();
			$(this).removeClass('drag-over');

			if (!draggedCard) return;

			const targetStage = $(this).data('stage');
			const prevStage = $(draggedCard).data('stage');
			const leadId = $(draggedCard).data('lead-id');

			if (targetStage === prevStage) return;

			// Move card visually
			$(this).find('.tc-kanban-empty').remove();
			$(this).append(draggedCard);
			$(draggedCard).data('stage', targetStage);
			$(draggedCard).find('.tc-stage-quick-select').val(targetStage);

			// Recalculate column counters & totals
			updateKanbanTotals();

			// Persist via AJAX
			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'tc_update_lead_stage',
					lead_id: leadId,
					stage: targetStage,
					nonce: tcAgentsAdmin.adminNonce
				},
				error: function() {
					alert('Failed to update lead stage on server. Reloading...');
					location.reload();
				}
			});
		});

		function updateKanbanTotals() {
			$('.tc-kanban-col').each(function() {
				const $col = $(this);
				const $cardsInCol = $col.find('.tc-kanban-card');
				$col.find('.tc-kanban-count').text($cardsInCol.length);

				let colTotal = 0;
				$cardsInCol.each(function() {
					colTotal += parseFloat($(this).data('val') || 0);
				});
				$col.find('.tc-kanban-col-val').text('₹' + Math.round(colTotal).toLocaleString());
			});
		}
	}

	/**
	 * 1-Click Knowledge Catalog Sync
	 */
	function initCatalogSync() {
		const $btn = $('#tc-sync-catalog-btn');
		if (!$btn.length) return;

		$btn.on('click', function(e) {
			e.preventDefault();
			if (!confirm('Sync all published tour packages, cabs, hotel pages, and circuits into the semantic Knowledge Base?')) return;

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Indexing Tour Catalog...');

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'tc_sync_catalog_kb',
					nonce: tcAgentsAdmin.adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> Sync Treks & Posts to RAG');
					if (res.success) {
						alert(res.data.message || 'Catalog synced successfully!');
						location.reload();
					} else {
						alert('Sync error: ' + (res.data ? res.data.message : 'Unknown error'));
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> Sync Treks & Posts to RAG');
					alert('Could not complete catalog sync.');
				}
			});
		});
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
						// Remove stale alias cards (omniroute and vmstudio now map to gateway).
						var canonical = ['openrouter', 'gateway', 'aipuffer', 'gemini'];
						$('#tc-health-container .tc-health-card').each(function() {
							var id = $(this).attr('id') || '';
							var slug = id.replace('card-', '');
							if (slug && canonical.indexOf(slug) === -1) { $(this).remove(); }
						});
						$.each(res.providers, function(slug, data) {
							const $card = $('#card-' + slug);
							if ($card.length) {
								$card.find('.tc-latency-val').text(data.latency_ms + ' ms');
								$card.find('.tc-msg-val').text(data.message || data.status);
								$card.attr('class', 'tc-card tc-health-card tc-status-card-' + data.status);
								$card.find('.tc-indicator-dot').attr('class', 'tc-indicator-dot ' + data.status);
								$card.find('.tc-indicator-badge').contents().last().replaceWith(' ' + String(data.status || '').toUpperCase());
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

	/**
	 * AI Puffer Bot Sync & Selection
	 */
	function initAIPufferBotSync() {
		const $syncBtn = $('#tc-sync-aipuffer-bots');
		const $toggleBtn = $('#tc-toggle-bot-manual');
		const $select = $('#tc_agents_aipuffer_bot_id');
		const $manual = $('#tc_agents_aipuffer_bot_id_manual');

		if (!$syncBtn.length) return;

		$syncBtn.on('click', function(e) {
			e.preventDefault();
			const originalHtml = $syncBtn.html();
			$syncBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Syncing...');

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'tc_sync_aipuffer_bots',
					nonce: tcAgentsAdmin.adminNonce,
					base_url: $('#aipuffer_base_url').val() || '',
					api_key: $('#aipuffer_api_key').val() || ''
				},
				success: function(res) {
					$syncBtn.prop('disabled', false).html(originalHtml);
					if (res.success && res.data && res.data.bots) {
						const currentVal = $select.val() || $manual.val();
						$select.empty().append('<option value="">— Select Remote/Local Brain —</option>');
						$.each(res.data.bots, function(i, b) {
							const selected = (String(b.id) === String(currentVal)) ? ' selected' : '';
							$select.append('<option value="' + b.id + '"' + selected + '>' + b.name + ' [ID: ' + b.id + ']</option>');
						});
						$select.show();
						$manual.hide();
						alert(res.data.message || 'Synced bots successfully!');
					} else {
						alert('Bot discovery result: ' + (res.data ? res.data.message : 'No bots detected.'));
					}
				},
				error: function(xhr) {
					$syncBtn.prop('disabled', false).html(originalHtml);
					alert('Could not connect to AI Puffer / AIPKit endpoint.');
				}
			});
		});

		$toggleBtn.on('click', function(e) {
			e.preventDefault();
			if ($manual.is(':visible')) {
				$manual.hide();
				$select.show();
				$toggleBtn.text('Manual ID');
			} else {
				$select.hide();
				$manual.show().focus();
				$toggleBtn.text('Dropdown');
			}
		});
	}

	/**
	 * Provider Live Model Sync (OpenRouter, OmniRoute, Gemini)
	 */
	function initProviderModelSync() {
		$('.tc-sync-models-btn').on('click', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const provider = $btn.data('provider');
			const originalHtml = $btn.html();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Syncing...');

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'tc_sync_provider_models',
					nonce: tcAgentsAdmin.adminNonce,
					provider: provider
				},
				success: function(res) {
					$btn.prop('disabled', false).html(originalHtml);
					if (res.success && res.data && res.data.models) {
						let $select = $('#' + provider + '_model');
						if (!$select.is('select')) {
							// Convert input to select
							const current = $select.val();
							const name = $select.attr('name');
							const id = $select.attr('id');
							$select.replaceWith('<select name="' + name + '" id="' + id + '" class="regular-text"></select>');
							$select = $('#' + id);
						}
						const currentVal = $select.val();
						$select.empty();
						$.each(res.data.models, function(i, m) {
							const selected = (m.id === currentVal) ? ' selected' : '';
							$select.append('<option value="' + m.id + '"' + selected + '>' + (m.name || m.id) + '</option>');
						});
						alert(res.data.message || 'Models synced successfully!');
					} else {
						alert('Sync result: ' + (res.data ? res.data.message : 'No models returned. Check your API key.'));
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(originalHtml);
					alert('Failed to sync provider models.');
				}
			});
		});
	}

})(jQuery);
