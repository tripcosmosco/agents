/**
 * TripCosmos Master AI Agent - Global Floating Assistant & Drawer Controller
 * Free from page navigation across all WordPress Admin pages.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $fab = $('#tc-master-fab');
		const $popup = $('#tc-master-popup');
		if (!$popup.length) return;

		const $feed = $('#tc-popup-feed');
		const $input = $('#tc-popup-input');
		const $form = $('#tc-popup-form');
		const $sendBtn = $('#tc-popup-send');
		const $agentSelect = $('#tc-popup-agent');
		const $closeBtn = $('#tc-popup-close');
		const $expandBtn = $('#tc-popup-expand');
		const $clearBtn = $('#tc-popup-clear');
		const $takeoverBtn = $('#tc-popup-takeover');

		const STORAGE_KEY = 'tc_master_chat_session_v1';
		const SESSION_ID_KEY = 'tc_master_session_id';

		let sessionId = sessionStorage.getItem(SESSION_ID_KEY);
		if (!sessionId) {
			sessionId = 'staff_master_' + Math.random().toString(36).substring(2, 10);
			sessionStorage.setItem(SESSION_ID_KEY, sessionId);
		}

		let isHumanMode = false;

		// Toggle Human Agent Takeover Mode
		$takeoverBtn.on('click', function(e) {
			e.preventDefault();
			const origText = $(this).text();
			$(this).prop('disabled', true).text('⏳...');

			$.ajax({
				url: (window.tcAgentsAdmin && window.tcAgentsAdmin.ajaxUrl) ? window.tcAgentsAdmin.ajaxUrl : '/wp-admin/admin-ajax.php',
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'tc_toggle_human_takeover',
					session_id: sessionId,
					nonce: (window.tcAgentsAdmin && window.tcAgentsAdmin.adminNonce) ? window.tcAgentsAdmin.adminNonce : ''
				},
				success: function(res) {
					$takeoverBtn.prop('disabled', false);
					if (res && res.success) {
						isHumanMode = res.data.is_human;
						$takeoverBtn.text(res.data.label);
						if (isHumanMode) {
							$takeoverBtn.css({ 'background': 'rgba(245, 158, 11, 0.2)', 'color': '#f59e0b', 'border-color': 'rgba(245, 158, 11, 0.4)' });
							appendSystemMessage('👤 Human Takeover active. AI auto-pilot paused for this session.');
						} else {
							$takeoverBtn.css({ 'background': 'rgba(16, 185, 129, 0.15)', 'color': '#10b981', 'border-color': 'rgba(16, 185, 129, 0.3)' });
							appendSystemMessage('🤖 AI Auto-Pilot resumed.');
						}
					} else {
						$takeoverBtn.text(origText);
					}
				},
				error: function() {
					$takeoverBtn.prop('disabled', false).text(origText);
				}
			});
		});

		// Restore history if saved in this session
		restoreChatHistory();

		// Auto-open if query param open_master_chat=1 is present
		if (window.location.search.indexOf('open_master_chat=1') !== -1) {
			setTimeout(function() {
				openPopup();
				if (window.history && window.history.replaceState) {
					var cleanUrl = window.location.href.replace(/([&?])open_master_chat=1(&|$)/, '$1').replace(/[?&]$/, '');
					window.history.replaceState({}, document.title, cleanUrl);
				}
			}, 300);
		}

		// Toggle Popup via FAB
		$fab.on('click', function(e) {
			e.preventDefault();
			togglePopup();
		});

		// Toggle via WP Admin Bar
		$(document).on('click', '#wp-admin-bar-tc-master-agent a, #wp-admin-bar-tc-master-agent', function(e) {
			e.preventDefault();
			openPopup();
		});

		// Close button
		$closeBtn.on('click', function(e) {
			e.preventDefault();
			closePopup();
		});

		// Expand / Maximize toggle
		$expandBtn.on('click', function(e) {
			e.preventDefault();
			$popup.toggleClass('tc-expanded');
			const isExpanded = $popup.hasClass('tc-expanded');
			$(this).html(isExpanded ? '⤢' : '⤡');
			$(this).attr('title', isExpanded ? 'Compact View' : 'Expand View');
		});

		// Clear chat
		$clearBtn.on('click', function(e) {
			e.preventDefault();
			if (confirm('Clear active chat session?')) {
				sessionStorage.removeItem(STORAGE_KEY);
				sessionId = 'staff_master_' + Math.random().toString(36).substring(2, 10);
				sessionStorage.setItem(SESSION_ID_KEY, sessionId);
				$feed.find('.tc-msg-row-user, .tc-msg-row-assistant, .tc-msg-system').remove();
				scrollToBottom();
			}
		});

		// Global Keyboard shortcut: Ctrl+Space (or Alt+A) toggles popup
		$(document).on('keydown', function(e) {
			if ((e.ctrlKey && e.code === 'Space') || (e.altKey && (e.key === 'a' || e.key === 'A'))) {
				e.preventDefault();
				togglePopup();
			} else if (e.key === 'Escape' && $popup.hasClass('tc-open')) {
				closePopup();
			}
		});

		// Quick Scenario chips
		$('.tc-chip').on('click', function(e) {
			e.preventDefault();
			const prompt = $(this).data('prompt');
			if (prompt) {
				$input.val(prompt).focus();
				autoGrowInput();
			}
		});

		// Auto grow input
		$input.on('input', function() {
			autoGrowInput();
		});

		function autoGrowInput() {
			$input.css('height', 'auto');
			$input.css('height', Math.min(120, Math.max(48, $input[0].scrollHeight)) + 'px');
		}

		// Handle Enter to submit (Shift+Enter for newline)
		$input.on('keydown', function(e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				$form.trigger('submit');
			}
		});

		// Submit form
		$form.on('submit', function(e) {
			e.preventDefault();
			const message = $.trim($input.val());
			if (!message) return;

			if (isHumanMode) {
				$input.val('').css('height', '48px');
				appendAssistantMessage(message, '👤 Staff (Direct)', 0, false);
				saveChatMessage('assistant', message, 'staff', 0);
				return;
			}

			// Append User message
			appendUserMessage(message);
			saveChatMessage('user', message);

			$input.val('').css('height', '48px');

			// Append Loading Placeholder
			const $loader = $(
				'<div class="tc-msg-row-assistant tc-loading-row">' +
					'<div class="tc-bubble-assistant">' +
						'<div class="tc-assistant-meta">' +
							'<span class="tc-assistant-tag">TripCosmos AI</span>' +
							'<span>Thinking...</span>' +
						'</div>' +
						'<div class="tc-typing-indicator">' +
							'<span class="tc-typing-dot"></span>' +
							'<span class="tc-typing-dot"></span>' +
							'<span class="tc-typing-dot"></span>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
			$feed.append($loader);
			scrollToBottom();

			$sendBtn.prop('disabled', true);

			const restUrl = (window.tcAgentsAdmin && window.tcAgentsAdmin.restUrl) ? window.tcAgentsAdmin.restUrl : '/wp-json/tc-agents/v1/';
			const nonce = (window.tcAgentsAdmin && window.tcAgentsAdmin.nonce) ? window.tcAgentsAdmin.nonce : '';

			$.ajax({
				url: restUrl + 'chat',
				method: 'POST',
				headers: {
					'X-WP-Nonce': nonce
				},
				contentType: 'application/json',
				data: JSON.stringify({
					message: message,
					session_id: sessionId,
					channel: 'admin_popup',
					agent_slug: $agentSelect.val() || 'tripcosmos-guide'
				}),
				success: function(res) {
					$loader.remove();
					$sendBtn.prop('disabled', false);

					if (res && res.success) {
						appendAssistantMessage(res.reply, res.provider_used, res.latency_ms, true);
						saveChatMessage('assistant', res.reply, res.provider_used, res.latency_ms);
					} else {
						const err = (res && res.error) ? res.error : 'No response from agent.';
						appendSystemMessage('⚠️ Error: ' + err);
					}
				},
				error: function(xhr) {
					$loader.remove();
					$sendBtn.prop('disabled', false);
					let err = 'Server connection error';
					try {
						const json = JSON.parse(xhr.responseText);
						err = json.error || json.message || err;
					} catch (e) {}
					appendSystemMessage('⚠️ ' + err);
				}
			});
		});

		function openPopup() {
			$popup.addClass('tc-open');
			$fab.hide();
			setTimeout(function() {
				$input.focus();
				scrollToBottom();
			}, 150);
		}

		function closePopup() {
			$popup.removeClass('tc-open');
			$fab.show();
		}

		function togglePopup() {
			if ($popup.hasClass('tc-open')) {
				closePopup();
			} else {
				openPopup();
			}
		}

		function appendUserMessage(text) {
			const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
			const $row = $(
				'<div class="tc-msg-row-user">' +
					'<div class="tc-bubble-user">' +
						formatSafeMarkdown(text) +
					'</div>' +
				'</div>'
			);
			$feed.append($row);
			scrollToBottom();
		}

		function appendAssistantMessage(text, provider, latency, animate) {
			const latSec = latency ? (latency / 1000).toFixed(1) + 's' : 'Fast';
			const providerLabel = provider ? escapeHtml(provider) : 'AI Gateway';
			if (typeof animate === 'undefined') animate = false;

			const $row = $(
				'<div class="tc-msg-row-assistant">' +
					'<div class="tc-bubble-assistant">' +
						'<div class="tc-assistant-meta">' +
							'<span class="tc-assistant-tag">' + providerLabel + '</span>' +
							'<span class="tc-latency-pill">⏱️ ' + latSec + '</span>' +
						'</div>' +
						'<div class="tc-assistant-content"></div>' +
						'<div class="tc-assistant-actions" style="display:none;">' +
							'<button type="button" class="tc-copy-snippet-btn">📋 Copy</button>' +
						'</div>' +
					'</div>' +
				'</div>'
			);

			$feed.append($row);
			scrollToBottom();

			const $content = $row.find('.tc-assistant-content');
			const $actions = $row.find('.tc-assistant-actions');

			$row.find('.tc-copy-snippet-btn').on('click', function() {
				navigator.clipboard.writeText(text);
				$(this).text('✅ Copied');
				const b = this;
				setTimeout(function() { $(b).text('📋 Copy'); }, 1800);
			});

			if (!animate || text.length < 15) {
				$content.html(formatSafeMarkdown(text));
				$actions.show();
				return;
			}

			// Smooth streaming typewriter effect
			const words = text.split(/(\s+)/);
			let curr = '';
			let idx = 0;
			const timer = setInterval(function() {
				if (idx < words.length) {
					curr += words[idx];
					$content.html(formatSafeMarkdown(curr));
					scrollToBottom();
					idx++;
				} else {
					clearInterval(timer);
					$content.html(formatSafeMarkdown(text));
					$actions.fadeIn(200);
					scrollToBottom();
				}
			}, 14);
		}

		function appendSystemMessage(msg) {
			const $row = $(
				'<div class="tc-msg-system" style="text-align: center; margin: 4px 0;">' +
					'<span style="background: #fee2e2; color: #b91c1c; font-size: 11px; padding: 3px 8px; border-radius: 6px;">' +
						escapeHtml(msg) +
					'</span>' +
				'</div>'
			);
			$feed.append($row);
			scrollToBottom();
		}

		function scrollToBottom() {
			$feed.scrollTop($feed[0].scrollHeight);
		}

		function escapeHtml(str) {
			return $('<div>').text(str || '').html();
		}

		/**
		 * Robust Markdown formatter without regex link double-tagging bug.
		 */
		function formatSafeMarkdown(str) {
			if (!str) return '';
			let safe = escapeHtml(str);

			// Bold & Italics
			safe = safe.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
			safe = safe.replace(/__([^_]+)__/g, '<strong>$1</strong>');
			safe = safe.replace(/(^|[^*_])\*([^*]+)\*(?=[^*_]|$)/g, '$1<em>$2</em>');
			safe = safe.replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.06);padding:2px 5px;border-radius:4px;font-size:12px;font-family:monospace;">$1</code>');

			// Markdown Links: [label](URL) -> convert to placeholders first
			const placeholders = [];
			safe = safe.replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/g, function(match, label, url) {
				const idx = placeholders.length;
				placeholders.push('<a href="' + url + '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;font-weight:600;">' + label + ' ↗</a>');
				return '%%TC_MD_LINK_' + idx + '%%';
			});

			// Now safely linkify bare URLs (that are not part of an existing link tag)
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

			// Format bullet lists
			const lines = safe.split('\n');
			const out = [];
			let inList = false;

			lines.forEach(function(line) {
				const trimmed = line.trim();
				const m = trimmed.match(/^[-*•]\s+(.+)$/);
				if (m) {
					if (!inList) {
						out.push('<ul style="margin:6px 0;padding-left:18px;list-style-type:disc;">');
						inList = true;
					}
					out.push('<li style="margin-bottom:3px;line-height:1.5;">' + m[1] + '</li>');
				} else {
					if (inList) {
						out.push('</ul>');
						inList = false;
					}
					if (trimmed) {
						out.push('<p style="margin:4px 0;">' + line + '</p>');
					}
				}
			});
			if (inList) {
				out.push('</ul>');
			}

			return out.join('');
		}

		function saveChatMessage(role, text, provider, latency) {
			try {
				let history = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
				history.push({ role: role, text: text, provider: provider, latency: latency, time: Date.now() });
				if (history.length > 30) history = history.slice(-30);
				sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history));
			} catch (e) {}
		}

		function restoreChatHistory() {
			try {
				const history = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
				if (history && history.length) {
					history.forEach(function(item) {
						if (item.role === 'user') {
							appendUserMessage(item.text);
						} else if (item.role === 'assistant') {
							appendAssistantMessage(item.text, item.provider, item.latency);
						}
					});
				}
			} catch (e) {}
		}
	});
})(jQuery);
