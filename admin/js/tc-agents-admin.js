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
		initWidgetToggle();
		initGitHubUpdater();
		initPresetsSwitcher();
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
			const $loader = $(
				'<div class="tc-chat-row tc-row-assistant tc-loading-row">' +
					'<div class="tc-bubble-assistant" style="border-left-color: #f59e0b; opacity: 0.9;">' +
						'<div class="tc-bubble-header">' +
							'<div class="tc-bot-identity">' +
								'<span class="tc-bot-avatar">AI</span>' +
								'<span class="tc-bot-name">TripCosmos AI Agent</span>' +
								'<span class="tc-bot-gateway">Orchestrator</span>' +
							'</div>' +
						'</div>' +
						'<div class="tc-bubble-text">⏳ <em>Consulting AI Puffer RAG Bot 155464 & pilgrimage catalogs...</em></div>' +
					'</div>' +
				'</div>'
			);
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
							appendMessage('system', '🔗 Human handoff triggered: <a href="' + res.handoff.whatsapp_url + '" target="_blank" rel="noopener">Connect via WhatsApp</a>');
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
			let $row;
			const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

			if (role === 'user') {
				$row = $(
					'<div class="tc-chat-row tc-row-user">' +
						'<div class="tc-bubble-user">' +
							'<div class="tc-bubble-header">' +
								'<span>👤 Staff Supervisor</span>' +
								'<span>' + timeStr + '</span>' +
							'</div>' +
							'<div class="tc-bubble-text">' + formatMarkdown(text) + '</div>' +
						'</div>' +
					'</div>'
				);
			} else if (role === 'assistant') {
				const latSec = latency ? (latency / 1000).toFixed(1) + 's' : 'Fast';
				const latClass = latency < 3000 ? 'tc-latency-good' : (latency < 6000 ? 'tc-latency-warn' : 'tc-latency-bad');
				const providerTag = provider ? '<span class="tc-bot-gateway">' + escapeHtml(provider) + '</span>' : '';

				$row = $(
					'<div class="tc-chat-row tc-row-assistant">' +
						'<div class="tc-bubble-assistant">' +
							'<div class="tc-bubble-header">' +
								'<div class="tc-bot-identity">' +
									'<span class="tc-bot-avatar">AI</span>' +
									'<span class="tc-bot-name">TripCosmos AI Agent</span>' +
									providerTag +
								'</div>' +
								'<div class="tc-bot-meta">' +
									'<span class="tc-latency-tag ' + latClass + '">⏱️ ' + latSec + '</span>' +
									'<span>' + timeStr + '</span>' +
								'</div>' +
							'</div>' +
							'<div class="tc-bubble-text">' + formatMarkdown(text) + '</div>' +
							'<div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">' +
								'<button type="button" class="button button-small tc-copy-btn" style="font-size: 11px;">📋 Copy Text</button>' +
							'</div>' +
						'</div>' +
					'</div>'
				);

				$row.find('.tc-copy-btn').on('click', function() {
					navigator.clipboard.writeText(text);
					$(this).text('✅ Copied!');
					const btn = this;
					setTimeout(function() { $(btn).text('📋 Copy Text'); }, 2000);
				});
			} else {
				$row = $(
					'<div class="tc-chat-row tc-row-tool" style="justify-content: center; margin: 8px 0;">' +
						'<div style="background: #e2e8f0; color: #475569; padding: 4px 12px; border-radius: 12px; font-size: 12px;">' +
							text +
						'</div>' +
					'</div>'
				);
			}

			$feed.append($row);
			scrollToBottom();
		}

		function scrollToBottom() {
			$feed.scrollTop($feed[0].scrollHeight);
		}

		function escapeHtml(str) {
			return $('<div>').text(str).html();
		}

		function formatMarkdown(str) {
			if (!str) return '';
			let safe = escapeHtml(str);
			safe = safe.replace(/\*\*([^*]+)\*\*/g, '<strong style="color:inherit;font-weight:700;">$1</strong>');
			safe = safe.replace(/__([^_]+)__/g, '<strong style="color:inherit;font-weight:700;">$1</strong>');
			safe = safe.replace(/(^|[^*_])\*([^*]+)\*(?=[^*_]|$)/g, '$1<em>$2</em>');
			safe = safe.replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.06);padding:2px 6px;border-radius:4px;font-size:12px;font-family:monospace;">$1</code>');
			// Markdown Links: [label](URL) -> convert to placeholders first
			const placeholders = [];
			safe = safe.replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/g, function(match, label, url) {
				const idx = placeholders.length;
				placeholders.push('<a href="' + url + '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;font-weight:600;">' + label + ' ↗</a>');
				return '%%TC_MD_LINK_' + idx + '%%';
			});

			// Now safely linkify bare URLs (avoiding attributes)
			safe = safe.replace(/(https?:\/\/[^\s<"'\)]+)/gi, function(match) {
				let url = match;
				let trailing = '';
				const p = url.match(/([.,;:]+)$/);
				if (p) {
					trailing = p[1];
					url = url.slice(0, -trailing.length);
				}
				return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;font-weight:600;">' + url + ' ↗</a>' + trailing;
			});

			// Restore formatted markdown links
			for (let i = 0; i < placeholders.length; i++) {
				safe = safe.replace('%%TC_MD_LINK_' + i + '%%', placeholders[i]);
			}
			const lines = safe.split('\n');
			const output = [];
			let inList = false;
			lines.forEach(function(line) {
				const trimmed = line.trim();
				const m = trimmed.match(/^[-*•]\s+(.+)$/);
				if (m) {
					if (!inList) {
						output.push('<ul style="margin:8px 0;padding-left:20px;list-style-type:disc;">');
						inList = true;
					}
					output.push('<li style="margin-bottom:4px;line-height:1.5;">' + m[1] + '</li>');
				} else {
					if (inList) {
						output.push('</ul>');
						inList = false;
					}
					output.push(line);
				}
			});
			if (inList) output.push('</ul>');
			let res = output.join('\n');
			res = res.replace(/\n(?!\s*<\/?ul|\s*<li)/g, '<br />\n');
			res = res.replace(/<\/ul><br \/>\n/g, '</ul>\n').replace(/<\/li><br \/>\n/g, '</li>\n');
			return res;
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
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> Sync Tours & Posts to RAG');
					if (res.success) {
						alert(res.data.message || 'Catalog synced successfully!');
						location.reload();
					} else {
						alert('Sync error: ' + (res.data ? res.data.message : 'Unknown error'));
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import"></span> Sync Tours & Posts to RAG');
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

		const $status = $('#tc-aipuffer-bot-status');

		if (!$syncBtn.length) return;

		$syncBtn.on('click', function(e) {
			e.preventDefault();
			const originalHtml = $syncBtn.html();
			$syncBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Syncing...');
			$status.hide();

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
					if (res.success && res.data && res.data.bots && res.data.bots.length) {
						const currentVal = $select.val() || $manual.val();
						$select.empty().append('<option value="">— Select Remote/Local Brain —</option>');
						$.each(res.data.bots, function(i, b) {
							const selected = (String(b.id) === String(currentVal)) ? ' selected' : '';
							$select.append('<option value="' + b.id + '"' + selected + '>' + b.name + ' [ID: ' + b.id + ']</option>');
						});
						$select.show();
						$manual.hide();
						$toggleBtn.text('Manual ID');
						$status.html('<span style="color:#10b981;font-weight:600;">✓ ' + (res.data.message || 'Synced bots successfully!') + '</span>').slideDown(200);
					} else {
						// Standard AIPKit host detected - auto-reveal manual ID field with helpful instructions
						$select.hide();
						$manual.show().focus();
						$toggleBtn.text('Dropdown');
						$status.html('<span style="color:#d97706;font-weight:600;">ℹ️ Connected to AIPKit host. Standard AIPKit keeps bot catalogs protected for security. Please look at your <strong>AI Puffer - Dashboard &rarr; Chatbots</strong> tab to find your Chatbot ID (e.g. <code>1</code> or <code>2</code>) and enter it above.</span>').slideDown(200);
					}
				},
				error: function(xhr) {
					$syncBtn.prop('disabled', false).html(originalHtml);
					$select.hide();
					$manual.show().focus();
					$toggleBtn.text('Dropdown');
					$status.html('<span style="color:#ef4444;font-weight:600;">⚠️ Could not automatically query bot catalog. Please enter your Bot ID manually above.</span>').slideDown(200);
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

	/**
	 * Instant 1-Click Frontend Chatbot Toggle
	 */
	function initWidgetToggle() {
		$(document).on('click', '#tc-quick-toggle-widget-btn, #tc-quick-toggle-widget', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const origText = $btn.text();
			$btn.prop('disabled', true).text('Updating status...');

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'tc_toggle_widget',
					nonce: tcAgentsAdmin.adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false);
					if (res.success) {
						const isLive = res.data.enabled;
						if ($('#tc-quick-toggle-widget-btn').length) {
							$('#tc-quick-toggle-widget-btn')
								.removeClass('button-primary button-secondary')
								.addClass(isLive ? 'button-secondary' : 'button-primary')
								.text(isLive ? '🔴 Disable Chatbot' : '🟢 Enable Chatbot');
							$('#widget_enabled_input').val(isLive ? '1' : '0');
							const $badge = $('#tc-bot-status-badge');
							$badge.removeClass('tc-status-healthy tc-status-down').addClass(isLive ? 'tc-status-healthy' : 'tc-status-down');
							$badge.find('.tc-indicator-dot').removeClass('online down').addClass(isLive ? 'online' : 'down');
							$('#tc-bot-status-text').text(isLive ? 'ACTIVE & VISIBLE ON WEBSITE' : 'DISABLED & HIDDEN FROM VISITORS');
							$btn.closest('.tc-card').css({
								'border-left-color': isLive ? '#10b981' : '#ef4444',
								'background': isLive ? '#f0fdf4' : '#fef2f2'
							});
						}
						if ($('#tc-quick-toggle-widget').length) {
							$('#tc-quick-toggle-widget')
								.removeClass('button-primary button-secondary')
								.addClass(isLive ? 'button-secondary' : 'button-primary')
								.text(isLive ? '🟢 Chatbot: Active (Click to Disable)' : '🔴 Chatbot: Disabled (Click to Enable)');
						}
					} else {
						$btn.text(origText);
						alert('Error: ' + (res.data ? res.data.message : 'Could not toggle widget.'));
					}
				},
				error: function() {
					$btn.prop('disabled', false).text(origText);
					alert('Connection error while toggling chatbot status.');
				}
			});
		});
	}

	/**
	 * GitHub Plugin Updates & 1-Click Upgrade Manager
	 */
	/**
	 * Two-Way GitHub Code Synchronizer & Updater Manager
	 */
	function initGitHubUpdater() {
		const $syncBtn = $('#tc-check-github-sync-btn, #tc-check-github-update-btn');
		const $pushBtn = $('#tc-push-github-btn');
		const $updateBtn = $('#tc-perform-github-update-btn');
		const $statusBox = $('#tc-github-update-status-box');
		const $remoteVerLabel = $('#tc-remote-version-label');
		const $syncBadge = $('#tc-sync-direction-badge');
		const $syncText = $('#tc-sync-direction-text');
		const $commitCard = $('#tc-github-commit-card');

		if (!$syncBtn.length && !$pushBtn.length) return;

		// 1. Compare Versions / Check Sync
		$syncBtn.on('click', function(e) {
			e.preventDefault();
			const origHtml = $(this).html();
			$(this).prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Comparing with GitHub...');
			$statusBox.hide();
			$commitCard.hide();

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'tc_agents_check_github_sync',
					nonce: tcAgentsAdmin.adminNonce
				},
				success: function(res) {
					$syncBtn.prop('disabled', false).html(origHtml);
					if (res.success) {
						const data = res.data;
						$remoteVerLabel.text('v' + (data.remote_version || '--'));

						// Populate latest commit card if available
						if (data.latest_commit) {
							$('#tc-commit-link').attr('href', data.latest_commit.url).text(data.latest_commit.sha);
							$('#tc-commit-message').text(data.latest_commit.message);
							$('#tc-commit-author').text('Committed by: ' + (data.latest_commit.author || 'GitHub'));
							$('#tc-commit-date').text(data.latest_commit.date || '');
							$commitCard.slideDown(200);
						}

						if (data.sync_status === 'wp_ahead') {
							// WordPress is ahead of GitHub
							$pushBtn.show();
							$updateBtn.hide();
							$syncBadge.css({ 'background': 'rgba(16, 185, 129, 0.15)', 'border-color': 'rgba(16, 185, 129, 0.4)', 'color': '#34d399' });
							$syncText.html('WordPress Ahead &rarr; GitHub');
							$statusBox
								.css({ 'background': '#ecfdf5', 'border': '1px solid #6ee7b7', 'color': '#065f46' })
								.html('<strong>🚀 WordPress Code is Ahead!</strong> ' + data.message + ' Click <strong>Push to GitHub</strong> to publish this release to repository <code>' + data.repo + '</code>.')
								.slideDown(200);
						} else if (data.sync_status === 'gh_ahead') {
							// GitHub is ahead of WordPress
							$updateBtn.show().data('version', data.remote_version);
							$pushBtn.hide();
							$syncBadge.css({ 'background': 'rgba(245, 158, 11, 0.15)', 'border-color': 'rgba(245, 158, 11, 0.4)', 'color': '#fbbf24' });
							$syncText.html('GitHub Ahead &rarr; WordPress');
							$statusBox
								.css({ 'background': '#fef3c7', 'border': '1px solid #f59e0b', 'color': '#92400e' })
								.html('<strong>📦 Remote Update Available!</strong> ' + data.message + ' Click <strong>Update from GitHub</strong> to download and install.')
								.slideDown(200);
						} else {
							// In sync
							$pushBtn.show().text('Force Push to GitHub');
							$updateBtn.hide();
							$syncBadge.css({ 'background': 'rgba(59, 130, 246, 0.15)', 'border-color': 'rgba(59, 130, 246, 0.4)', 'color': '#60a5fa' });
							$syncText.html('In Sync (v' + data.local_version + ')');
							$statusBox
								.css({ 'background': '#f0fdf4', 'border': '1px solid #86efac', 'color': '#166534' })
								.html('<strong>✅ In Perfect Sync!</strong> ' + data.message)
								.slideDown(200);
						}
					} else {
						$pushBtn.hide();
						$updateBtn.hide();
						$syncBadge.css({ 'background': 'rgba(239, 68, 68, 0.15)', 'border-color': 'rgba(239, 68, 68, 0.4)', 'color': '#f87171' });
						$syncText.html('Sync Check Notice');
						$statusBox
							.css({ 'background': '#fef2f2', 'border': '1px solid #fca5a5', 'color': '#991b1b' })
							.html('<strong>⚠️ Notice:</strong> ' + (res.data ? res.data.message : 'Could not contact GitHub API.'))
							.slideDown(200);
					}
				},
				error: function() {
					$syncBtn.prop('disabled', false).html(origHtml);
					$pushBtn.hide();
					$updateBtn.hide();
					$statusBox
						.css({ 'background': '#fef2f2', 'border': '1px solid #fca5a5', 'color': '#991b1b' })
						.html('<strong>⚠️ Connection Error:</strong> Unable to reach WordPress server.')
						.slideDown(200);
				}
			});
		});

		// 2. Push WordPress Code to GitHub
		$pushBtn.on('click', function(e) {
			e.preventDefault();
			const defaultMsg = 'Sync TripCosmos Agents v' + ($('#tc-local-version-label').text().replace(/[^0-9\.]/g, '') || '1.9.0') + ' from WordPress site';
			const commitMsg = prompt('Enter a commit message for GitHub:', defaultMsg);
			if (commitMsg === null) return;

			const origHtml = $pushBtn.html();
			$pushBtn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Pushing to GitHub...');
			$syncBtn.prop('disabled', true);

			$statusBox
				.css({ 'background': '#eff6ff', 'border': '1px solid #93c5fd', 'color': '#1e40af' })
				.html('<strong>⏳ Committing &amp; Pushing:</strong> Packaging plugin files and syncing with GitHub... Please wait.')
				.slideDown(200);

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'tc_agents_push_github',
					commit_message: commitMsg,
					nonce: tcAgentsAdmin.adminNonce
				},
				success: function(res) {
					$pushBtn.prop('disabled', false).html(origHtml);
					$syncBtn.prop('disabled', false);
					if (res.success) {
						$statusBox
							.css({ 'background': '#f0fdf4', 'border': '1px solid #86efac', 'color': '#166534' })
							.html('<strong>🎉 Push Successful!</strong> ' + res.data.message + ' <a href="' + res.data.url + '" target="_blank" rel="noopener noreferrer" style="font-weight:700;color:#047857;text-decoration:underline;">View commit on GitHub ↗</a>');
						setTimeout(function() {
							$syncBtn.first().trigger('click');
						}, 1200);
					} else {
						let detail = res.data ? res.data.message : 'Push failed.';
						$statusBox
							.css({ 'background': '#fef2f2', 'border': '1px solid #fca5a5', 'color': '#991b1b' })
							.html('<strong>❌ Push Failed:</strong> ' + detail);
					}
				},
				error: function() {
					$pushBtn.prop('disabled', false).html(origHtml);
					$syncBtn.prop('disabled', false);
					$statusBox
						.css({ 'background': '#fef2f2', 'border': '1px solid #fca5a5', 'color': '#991b1b' })
						.html('<strong>❌ Connection Error:</strong> Server request failed during git push.');
				}
			});
		});

		// 3. Update from GitHub
		$updateBtn.on('click', function(e) {
			e.preventDefault();
			const targetVer = $remoteVerLabel.text() || 'Latest';
			if (!confirm('Are you sure you want to download and install ' + targetVer + ' directly from GitHub? The plugin will be upgraded in-place.')) {
				return;
			}

			const origHtml = $updateBtn.html();
			$updateBtn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 6px 0 0;"></span> Downloading & Updating...');
			$syncBtn.prop('disabled', true);
			$pushBtn.prop('disabled', true);

			$statusBox
				.css({ 'background': '#eff6ff', 'border': '1px solid #93c5fd', 'color': '#1e40af' })
				.html('<strong>⏳ Upgrading Plugin:</strong> Downloading release archive from GitHub, extracting, and installing... Please do not close this window.')
				.slideDown(200);

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'tc_agents_perform_github_update',
					nonce: tcAgentsAdmin.adminNonce
				},
				success: function(res) {
					if (res.success) {
						$statusBox
							.css({ 'background': '#f0fdf4', 'border': '1px solid #86efac', 'color': '#166534' })
							.html('<strong>🎉 Update Complete!</strong> ' + res.data.message);
						setTimeout(function() {
							window.location.reload();
						}, 2000);
					} else {
						$updateBtn.prop('disabled', false).html(origHtml);
						$syncBtn.prop('disabled', false);
						$pushBtn.prop('disabled', false);
						$statusBox
							.css({ 'background': '#fef2f2', 'border': '1px solid #fca5a5', 'color': '#991b1b' })
							.html('<strong>❌ Update Failed:</strong> ' + (res.data ? res.data.message : 'Unknown installation error.'));
					}
				},
				error: function() {
					$updateBtn.prop('disabled', false).html(origHtml);
					$syncBtn.prop('disabled', false);
					$pushBtn.prop('disabled', false);
					$statusBox
						.css({ 'background': '#fef2f2', 'border': '1px solid #fca5a5', 'color': '#991b1b' })
						.html('<strong>❌ Request Timeout or Connection Error:</strong> Check your server logs or update manually.');
				}
			});
	/**
	 * 1-Click Universal Industry Presets Switcher
	 */
	function initPresetsSwitcher() {
		$(document).on('click', '.tc-btn-apply-preset', function(e) {
			e.preventDefault();
			const $btn = $(this);
			const presetId = $btn.data('preset');
			const title = $btn.data('title');

			if (!confirm('Switch plugin to ' + title + ' mode? This will configure industry personas, greetings, and branding.')) {
				return;
			}

			const origHtml = $btn.html();
			$btn.prop('disabled', true).html('⏳ Applying...');

			$.ajax({
				url: tcAgentsAdmin.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'tc_apply_preset',
					preset: presetId,
					nonce: tcAgentsAdmin.adminNonce
				},
				success: function(res) {
					if (res.success) {
						$btn.html('✓ Applied!');
						alert(res.data.message || 'Preset applied successfully!');
						window.location.reload();
					} else {
						$btn.prop('disabled', false).html(origHtml);
						alert('Error: ' + (res.data ? res.data.message : 'Failed to apply preset.'));
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Connection error occurred while applying preset.');
				}
			});
		});
	}

})(jQuery);
