/**
 * TripCosmos Public AI Chat Widget JavaScript
 * High-Intelligence Conversational Sales & Lead Capture Hub.
 */

(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		const triggerBtn        = document.getElementById('tc-widget-trigger');
		const chatWindow        = document.getElementById('tc-widget-window');
		const closeBtn          = document.getElementById('tc-widget-close');
		const chatForm          = document.getElementById('tc-widget-form');
		const chatInput         = document.getElementById('tc-widget-input');
		const messagesContainer = document.getElementById('tc-widget-messages');
		const starterChips      = document.getElementById('tc-starter-chips');
		const teaserEl          = document.getElementById('tc-widget-teaser');
		const teaserCloseBtn    = document.getElementById('tc-teaser-close');
		const quotePanel        = document.getElementById('tc-widget-quote-panel');
		const quoteForm         = document.getElementById('tc-quote-form');
		const quoteSuccess      = document.getElementById('tc-quote-success');
		const micBtn            = document.getElementById('tc-widget-mic');
		const tabBtns           = document.querySelectorAll('#tc-agent-widget-root .tc-tab-btn');
		const widgetFooter      = document.querySelector('#tc-agent-widget-root .tc-widget-footer');

		if (!triggerBtn || !chatWindow) return;

		// 1. Session Storage Management
		let sessionId = sessionStorage.getItem('tc_agent_session_id');
		if (!sessionId) {
			sessionId = 'web_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now();
			sessionStorage.setItem('tc_agent_session_id', sessionId);
		}

		// 2. Omni-Channel Analytics & DataLayer Tracker
		function trackEvent(eventName, params) {
			params = params || {};
			const payload = Object.assign({
				event: eventName,
				session_id: sessionId,
				timestamp: new Date().toISOString(),
				page_url: window.location.href,
				page_title: document.title
			}, params);

			// Native CustomEvent for client scripts
			document.dispatchEvent(new CustomEvent('tc_agent_event', { detail: payload }));

			// Google Tag Manager / DataLayer Integration
			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push(payload);
		}

		// Restore previous chat history if available
		restoreHistory();

		// 3. Open / Close Toggle
		let isOpen = false;

		function toggleWidget() {
			isOpen = !isOpen;
			chatWindow.style.display = isOpen ? 'flex' : 'none';
			triggerBtn.querySelector('.tc-fab-icon-chat').style.display = isOpen ? 'none' : 'block';
			triggerBtn.querySelector('.tc-fab-icon-close').style.display = isOpen ? 'block' : 'none';

			if (teaserEl) teaserEl.style.display = 'none';

			if (isOpen) {
				trackEvent('tc_agent_chat_opened');
				scrollToBottom();
				setTimeout(function() {
					if (chatInput && quotePanel.style.display !== 'block') {
						chatInput.focus();
					}
				}, 100);
			}
		}

		triggerBtn.addEventListener('click', toggleWidget);
		closeBtn.addEventListener('click', toggleWidget);

		// 4. Proactive Teaser Balloon
		const teaserDismissed = sessionStorage.getItem('tc_teaser_dismissed');
		if (!teaserDismissed && teaserEl) {
			setTimeout(function() {
				if (!isOpen && !sessionStorage.getItem('tc_teaser_dismissed')) {
					teaserEl.style.display = 'block';
					trackEvent('tc_agent_teaser_shown');
				}
			}, 6000);

			teaserEl.addEventListener('click', function(e) {
				if (e.target === teaserCloseBtn || teaserCloseBtn.contains(e.target)) {
					e.stopPropagation();
					teaserEl.style.display = 'none';
					sessionStorage.setItem('tc_teaser_dismissed', '1');
				} else {
					teaserEl.style.display = 'none';
					sessionStorage.setItem('tc_teaser_dismissed', '1');
					if (!isOpen) toggleWidget();
				}
			});
		}

		// 5. Tabs Navigation (Chat vs Quote Form)
		tabBtns.forEach(function(btn) {
			btn.addEventListener('click', function() {
				const tab = btn.getAttribute('data-tab');
				tabBtns.forEach(function(b) { b.classList.remove('active'); });
				btn.classList.add('active');

				if (tab === 'quote') {
					messagesContainer.style.display = 'none';
					quotePanel.style.display = 'block';
					if (widgetFooter) widgetFooter.style.display = 'none';
					trackEvent('tc_agent_quote_tab_opened');
				} else {
					quotePanel.style.display = 'none';
					messagesContainer.style.display = 'flex';
					if (widgetFooter) widgetFooter.style.display = 'block';
					scrollToBottom();
				}
			});
		});

		// 6. Fast Quote Form Lead Capture
		if (quoteForm) {
			quoteForm.addEventListener('submit', function(e) {
				e.preventDefault();
				const submitBtn = document.getElementById('tc-q-submit');
				const name  = (document.getElementById('tc-q-name').value || '').trim();
				const phone = (document.getElementById('tc-q-phone').value || '').trim();
				const email = (document.getElementById('tc-q-email').value || '').trim();
				const dest  = (document.getElementById('tc-q-destination').value || '').trim();
				const month = (document.getElementById('tc-q-month').value || '').trim();
				const group = (document.getElementById('tc-q-group').value || '').trim();

				if (!name || !phone) return;

				submitBtn.disabled = true;
				submitBtn.innerText = 'Syncing details...';

				const payload = {
					name: name,
					phone: phone,
					email: email,
					destination: dest,
					travel_month: month,
					group_size: group,
					session_id: sessionId,
					page_url: window.location.href,
					page_title: document.title
				};

				fetch(tcChatWidget.leadCaptureUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload)
				})
				.then(function(res) { return res.json(); })
				.then(function(data) {
					trackEvent('tc_agent_lead_captured', {
						lead_name: name,
						lead_phone: phone,
						destination: dest
					});

					quoteForm.style.display = 'none';
					quoteSuccess.style.display = 'block';

					setTimeout(function() {
						// Switch back to chat tab and have the AI greet the user by name!
						const chatTabBtn = document.querySelector('#tc-agent-widget-root .tc-tab-btn[data-tab="chat"]');
						if (chatTabBtn) chatTabBtn.click();

						const followUpPrompt = "Hi " + name + "! I have logged your request for " + (dest || "your Himalayan expedition") + " for " + (month || "upcoming dates") + ". Here is what we can do for you:";
						appendMessage('bot', followUpPrompt);
						saveToHistory('bot', followUpPrompt);
					}, 2200);
				})
				.catch(function(err) {
					submitBtn.disabled = false;
					submitBtn.innerText = '⚡ Get Custom Itinerary & Pricing';
					alert('Connection error. Please try again or reach out on WhatsApp.');
				});
			});
		}

		// 7. Voice Input (Web Speech Recognition)
		if (micBtn) {
			const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
			if (!SpeechRecognition) {
				micBtn.style.display = 'none'; // Browser does not support speech recognition
			} else {
				const recognition = new SpeechRecognition();
				recognition.continuous = false;
				recognition.interimResults = false;
				recognition.lang = 'en-IN';

				let isListening = false;

				micBtn.addEventListener('click', function() {
					if (isListening) {
						recognition.stop();
					} else {
						try {
							recognition.start();
							isListening = true;
							micBtn.classList.add('listening');
							chatInput.placeholder = 'Listening... speak now';
						} catch (e) {
							isListening = false;
							micBtn.classList.remove('listening');
						}
					}
				});

				recognition.onresult = function(event) {
					const transcript = event.results[0][0].transcript;
					chatInput.value = (chatInput.value ? chatInput.value + ' ' : '') + transcript;
					chatInput.focus();
					trackEvent('tc_agent_voice_input_used');
				};

				recognition.onend = function() {
					isListening = false;
					micBtn.classList.remove('listening');
					chatInput.placeholder = 'Ask about treks, itineraries, pricing...';
				};

				recognition.onerror = function() {
					isListening = false;
					micBtn.classList.remove('listening');
					chatInput.placeholder = 'Ask about treks, itineraries, pricing...';
				};
			}
		}

		// 8. Quick Starter Chips
		if (starterChips) {
			starterChips.addEventListener('click', function(e) {
				const chip = e.target.closest('.tc-chip');
				if (chip) {
					const query = chip.getAttribute('data-query');
					if (query) {
						sendMessage(query);
						starterChips.style.display = 'none';
					}
				}
			});
		}

		// 9. Form Submit
		chatForm.addEventListener('submit', function(e) {
			e.preventDefault();
			const text = chatInput.value.trim();
			if (!text) return;
			sendMessage(text);
			chatInput.value = '';
			if (starterChips) starterChips.style.display = 'none';
		});

		// 10. Send Message Flow with Streaming & Context
		function sendMessage(text) {
			// Auto-detect phone / email on client side for immediate tracking
			const phoneMatch = text.match(/(?:\+91|91|0)?[6-9]\d{9}|\+?[0-9]{8,15}/);
			if (phoneMatch) {
				trackEvent('tc_agent_phone_detected', { phone: phoneMatch[0] });
			}

			trackEvent('tc_agent_message_sent', { length: text.length });

			// Append user bubble
			appendMessage('user', text);
			saveToHistory('user', text);

			// Append bot typing placeholder
			const botBubble = document.createElement('div');
			botBubble.className = 'tc-chat-bubble tc-bubble-bot';
			const textDiv = document.createElement('div');
			textDiv.className = 'tc-bubble-text';
			textDiv.innerHTML = '<span class="tc-typing-dot"></span><span class="tc-typing-dot"></span><span class="tc-typing-dot"></span>';
			botBubble.appendChild(textDiv);
			messagesContainer.appendChild(botBubble);
			scrollToBottom();

			let fullReply = '';
			let isFirstChunk = true;

			// Fetch with Streaming & Context Awareness
			fetch(tcChatWidget.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'text/event-stream, application/json'
				},
				body: JSON.stringify({
					message: text,
					session_id: sessionId,
					channel: 'web',
					agent_slug: 'tripcosmos-guide',
					stream: true,
					page_url: window.location.href,
					page_title: document.title
				})
			})
			.then(function(response) {
				const contentType = response.headers.get('content-type') || '';
				if (!response.ok) {
					throw new Error('Server returned HTTP ' + response.status);
				}

				if (contentType.includes('text/event-stream') && window.ReadableStream) {
					const reader = response.body.getReader();
					const decoder = new TextDecoder('utf-8');
					let buffer = '';

					function readStream() {
						return reader.read().then(function(result) {
							if (result.done) {
								if (fullReply) saveToHistory('bot', fullReply);
								return;
							}

							buffer += decoder.decode(result.value, { stream: true });
							const lines = buffer.split('\n\n');
							buffer = lines.pop(); // Keep incomplete chunk

							for (let i = 0; i < lines.length; i++) {
								const line = lines[i].trim();
								if (line.startsWith('data: ')) {
									try {
										const event = JSON.parse(line.substring(6));
										if (event.type === 'chunk' && event.text) {
											if (isFirstChunk) {
												textDiv.innerHTML = '';
												isFirstChunk = false;
											}
											fullReply += event.text;
											textDiv.innerHTML = formatMarkdown(fullReply);
											scrollToBottom();
										} else if (event.type === 'done') {
											if (event.handoff && event.handoff.whatsapp_url) {
												renderHandoffCta(event.handoff.whatsapp_url);
											}
										} else if (event.type === 'error') {
											textDiv.innerHTML = escapeHtml(event.error || 'Agent service is paused.');
										}
									} catch (e) {}
								}
							}
							return readStream();
						});
					}
					return readStream();
				} else {
					// Fallback to standard JSON
					return response.json().then(function(data) {
						if (data.reply) {
							textDiv.innerHTML = formatMarkdown(data.reply);
							saveToHistory('bot', data.reply);
							if (data.handoff && data.handoff.whatsapp_url) {
								renderHandoffCta(data.handoff.whatsapp_url);
							}
						}
					});
				}
			})
			.catch(function(err) {
				textDiv.innerHTML = 'Our expedition specialist is ready on WhatsApp for immediate custom planning.';
				renderHandoffCta();
			});
		}

		function appendMessage(role, text) {
			const bubble = document.createElement('div');
			bubble.className = 'tc-chat-bubble tc-bubble-' + role;

			const textDiv = document.createElement('div');
			textDiv.className = 'tc-bubble-text';
			textDiv.innerHTML = (role === 'bot') ? formatMarkdown(text) : escapeHtml(text).replace(/\n/g, '<br />');

			bubble.appendChild(textDiv);
			messagesContainer.appendChild(bubble);
			scrollToBottom();
		}

		function renderHandoffCta(customUrl) {
			const url = customUrl || ('https://wa.me/' + tcChatWidget.whatsappNumber + '?text=' + encodeURIComponent('Hi TripCosmos Team, I am looking for a custom Himalayan itinerary and group quotation.'));
			const cta = document.createElement('div');
			cta.className = 'tc-handoff-cta';
			cta.innerHTML = '<p style="margin:0 0 6px 0; font-size:12px; color:#065f46; font-weight:600;">Want to connect with an expedition leader directly?</p>' +
							'<a href="' + url + '" target="_blank" rel="noopener" class="tc-whatsapp-btn" id="tc-cta-wa">' +
							'<span>💬 Chat on WhatsApp with Specialist</span>' +
							'</a>';
			cta.querySelector('#tc-cta-wa').addEventListener('click', function() {
				trackEvent('tc_agent_whatsapp_click', { url: url });
			});
			messagesContainer.appendChild(cta);
			scrollToBottom();
		}

		function scrollToBottom() {
			messagesContainer.scrollTop = messagesContainer.scrollHeight;
		}

		function escapeHtml(str) {
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}

		// Light Markdown Parser: bold, links, lists, newlines
		function formatMarkdown(str) {
			let safe = escapeHtml(str);
			// Bold **text**
			safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
			// Links [text](url)
			safe = safe.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1 &nearr;</a>');
			// Bullet points
			safe = safe.replace(/^[\*\-]\s+(.*)$/gm, '<li>$1</li>');
			safe = safe.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
			// Newlines
			safe = safe.replace(/\n/g, '<br />');
			return safe;
		}

		function saveToHistory(role, text) {
			let history = [];
			try {
				history = JSON.parse(sessionStorage.getItem('tc_chat_history')) || [];
			} catch (e) {}
			history.push({ role: role, text: text });
			if (history.length > 25) history = history.slice(-25);
			sessionStorage.setItem('tc_chat_history', JSON.stringify(history));
		}

		function restoreHistory() {
			try {
				const history = JSON.parse(sessionStorage.getItem('tc_chat_history'));
				if (Array.isArray(history) && history.length > 0) {
					if (starterChips) starterChips.style.display = 'none';
					history.forEach(function(item) {
						appendMessage(item.role, item.text);
					});
				}
			} catch (e) {}
		}
	});
})();
