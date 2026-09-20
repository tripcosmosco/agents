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
		const restartBtn        = document.getElementById('tc-widget-restart');
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
		const voicePanel        = document.getElementById('tc-widget-voice-panel');
		const voiceToggleBtn    = document.getElementById('tc-voice-toggle-btn');
		const voiceRing         = document.getElementById('tc-voice-ring');
		const voiceStatus       = document.getElementById('tc-voice-status');
		const voiceCanvas       = document.getElementById('tc-voice-visualizer');
		const voiceTranscript   = document.getElementById('tc-voice-live-transcript');
		const directPhoneInput  = document.getElementById('tc-direct-phone-input');
		const triggerCallBtn    = document.getElementById('tc-btn-trigger-mobile-call');
		const phoneCallStatus   = document.getElementById('tc-phone-call-status');

		const widgetRoot        = document.getElementById('tc-agent-widget-root');
		const historyPanel      = document.getElementById('tc-widget-history-panel');
		const historyList       = document.getElementById('tc-history-list');
		const clearHistoryBtn   = document.getElementById('tc-clear-history-btn');

		if (!triggerBtn || !chatWindow) return;

		// 1. Session Storage Management
		let sessionId = sessionStorage.getItem('tc_agent_session_id');
		if (!sessionId) {
			sessionId = 'web_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now();
			sessionStorage.setItem('tc_agent_session_id', sessionId);
		}

		// 1b. Multilingual Pilgrim Language Management
		const langPills = document.querySelectorAll('#tc-agent-widget-root .tc-lang-pill');
		let currentLang = sessionStorage.getItem('tc_chat_lang') || 'en';

		const langStarters = {
			en: [
				{ label: '🛕 Kashi Ayodhya Tour', query: 'Tell me about Kashi Ayodhya Prayagraj 4 Days Tour Package' },
				{ label: '🚗 Outstation Cabs', query: 'Book outstation cab Innova Crysta for Varanasi to Ayodhya' },
				{ label: '⛵ Ganga Aarti Boat', query: 'Book private boat for evening Ganga Aarti at Dashashwamedh Ghat' },
				{ label: '🏨 Ghat Hotels', query: 'Best ghat-view hotels in Varanasi near Kashi Vishwanath' }
			],
			hi: [
				{ label: '🛕 काशी अयोध्या दर्शन', query: 'काशी अयोध्या प्रयागराज 4 दिवसीय दर्शन पैकेज की जानकारी और दरें बताएं' },
				{ label: '🚗 आउटस्टेशन कैब', query: 'वाराणसी से अयोध्या के लिए इनोवा क्रिस्टा कैब का किराया बताएं' },
				{ label: '⛵ गंगा आरती नाव', query: 'दशाश्वमेध घाट पर शाम की गंगा आरती के लिए नाव बुकिंग कैसे करें' },
				{ label: '🏨 घाट होटल', query: 'काशी विश्वनाथ मंदिर के पास सर्वोत्तम घाट दृश्य वाले होटल' }
			],
			gu: [
				{ label: '🛕 કાશી અયોધ્યા યાત્રા', query: 'કાશી અયોધ્યા પ્રયાગરાજ 4 દિવસના યાત્રા પેકેજની વિગતો અને ભાડું જણાવો' },
				{ label: '🚗 આઉટસ્ટેશન કેબ', query: 'વારાણસી થી અયોધ્યા ઇનોવા ક્રિસ્ટા કેબનું ભાડું કેટલું થશે?' },
				{ label: '⛵ ગંગા આરતી બોટ', query: 'દશાશ્વમેઘ ઘાટ પર સાંજની ગંગા આરતી માટે પ્રાઇવેટ બોટ બુકિંગ' },
				{ label: '🏨 ઘાટ હોટેલ્સ', query: 'કાશી વિશ્વનાથ મંદિર નજીક શ્રેષ્ઠ ઘાટ ફેસિંગ હોટેલ' }
			],
			te: [
				{ label: '🛕 కాశీ అయోధ్య యాత్ర', query: 'కాశీ అయోధ్య ప్రయాగ్రాజ్ 4 రోజుల యాత్రా ప్యాకేజీ వివరాలు మరియు ధరలు చెప్పండి' },
				{ label: '🚗 క్యాబ్ బుకింగ్', query: 'వారణాసి నుండి అయోధ్య ఇన్నోవా క్రిస్టా క్యాబ్ ఛార్జీల వివరాలు' },
				{ label: '⛵ గంగా హారతి బోట్', query: 'దశాశ్వమేధ ఘాట్ వద్ద సాయంత్రం గంగా హారతి కోసం ప్రైవేట్ బోట్ బుకింగ్' },
				{ label: '🏨 ఘాట్ హోటళ్ళు', query: 'కాశీ విశ్వనాథ ఆలయం సమీపంలో గంగా నది కనిపించే ఉత్తమ హోటళ్ళు' }
			]
		};

		function setChatLanguage(lang) {
			currentLang = lang;
			sessionStorage.setItem('tc_chat_lang', lang);
			langPills.forEach(function(pill) {
				const isActive = pill.getAttribute('data-lang') === lang;
				pill.classList.toggle('active', isActive);
			});
			updateStarterChips(lang);
			trackEvent('tc_agent_language_changed', { language: lang });
		}

		function updateStarterChips(lang) {
			if (!starterChips) return;
			const chips = langStarters[lang] || langStarters.en;
			let html = '';
			chips.forEach(function(item) {
				html += '<button type="button" class="tc-chip" data-query="' + escapeHtml(item.query) + '">' + escapeHtml(item.label) + '</button>';
			});
			starterChips.innerHTML = html;
		}

		if (langPills.length) {
			langPills.forEach(function(pill) {
				pill.addEventListener('click', function() {
					const lang = pill.getAttribute('data-lang');
					if (lang) setChatLanguage(lang);
				});
			});
			setChatLanguage(currentLang);
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

		function toggleWidget(forceState) {
			if (typeof forceState === 'boolean') {
				isOpen = forceState;
			} else {
				isOpen = !isOpen;
			}
			chatWindow.style.setProperty('display', isOpen ? 'flex' : 'none', 'important');
			triggerBtn.classList.toggle('active', isOpen);
			if (widgetRoot) widgetRoot.classList.toggle('tc-window-open', isOpen);

			const chatIcon = triggerBtn.querySelector('.tc-fab-icon-chat');
			const closeIcon = triggerBtn.querySelector('.tc-fab-icon-close');
			if (chatIcon) chatIcon.style.setProperty('display', isOpen ? 'none' : 'flex', 'important');
			if (closeIcon) closeIcon.style.setProperty('display', isOpen ? 'flex' : 'none', 'important');

			const badgeEl = document.getElementById('tc-fab-badge');
			if (badgeEl && isOpen) badgeEl.style.display = 'none';

			if (teaserEl) teaserEl.style.display = 'none';

			if (isOpen) {
				trackEvent('tc_agent_chat_opened');
				scrollToBottom();
				setTimeout(function() {
					if (chatInput && quotePanel && quotePanel.style.display !== 'block') {
						chatInput.focus();
					}
				}, 100);
			} else {
				stopVisualizerLoop();
				if (isInVoiceCall) endVoiceCall();
			}
		}

		triggerBtn.addEventListener('click', function(e) {
			e.preventDefault();
			toggleWidget();
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				toggleWidget(false);
			});
		}

		if (restartBtn) {
			restartBtn.addEventListener('click', function() {
				if (!confirm('Start a fresh conversation? This will clear your current chat history.')) {
					return;
				}
				sessionStorage.removeItem('tc_chat_history');
				sessionId = 'web_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now();
				sessionStorage.setItem('tc_agent_session_id', sessionId);

				const greeting = tcChatWidget.greeting || "Namaste! 🙏 Welcome to TripCosmos — Varanasi's premier pilgrimage & tour desk.\n\nLooking for Kashi Vishwanath VIP darshan, Ayodhya Ram Mandir packages, outstation cabs (Innova Crysta / Swift Dzire), hotel bookings, or private Ganga Aarti boat rides? How can I assist you today?";
				const greetingHtml = '<div class="tc-chat-bubble tc-bubble-bot"><div class="tc-bubble-text">' + escapeHtml(greeting).replace(/\n/g, '<br />') + '</div></div>';
				messagesContainer.innerHTML = greetingHtml;
				if (starterChips) {
					messagesContainer.appendChild(starterChips);
					starterChips.style.display = 'flex';
				}
				trackEvent('tc_agent_chat_restarted');
			});
		}

		// 4. Proactive Human Sales Agent Auto-Initiation
		const teaserDismissed = sessionStorage.getItem('tc_teaser_dismissed');
		const openBtn         = document.getElementById('tc-teaser-open-btn');
		const badgeEl         = document.getElementById('tc-fab-badge');

		function playSoftChime() {
			try {
				const AudioCtx = window.AudioContext || window.webkitAudioContext;
				if (!AudioCtx) return;
				const ctx = new AudioCtx();
				if (ctx.state === 'suspended') ctx.resume();
				const osc = ctx.createOscillator();
				const gain = ctx.createGain();
				osc.type = 'sine';
				osc.frequency.setValueAtTime(587.33, ctx.currentTime);
				osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12);
				gain.gain.setValueAtTime(0.04, ctx.currentTime);
				gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
				osc.connect(gain);
				gain.connect(ctx.destination);
				osc.start();
				osc.stop(ctx.currentTime + 0.36);
			} catch (e) {}
		}

		let teaserShown = false;
		function showProactiveTeaser() {
			if (teaserShown || isOpen || sessionStorage.getItem('tc_teaser_dismissed') || !teaserEl) return;
			teaserShown = true;
			teaserEl.style.display = 'block';
			if (badgeEl) badgeEl.style.display = 'flex';
			playSoftChime();
			trackEvent('tc_agent_proactive_sales_outreach_shown');
		}

		if (!teaserDismissed && teaserEl) {
			// Proactively engage after 3.5s of page load
			setTimeout(showProactiveTeaser, 3500);

			// Or proactively engage as soon as traveler scrolls down past 250px
			window.addEventListener('scroll', function onScrollTrigger() {
				if (window.scrollY > 250) {
					showProactiveTeaser();
					window.removeEventListener('scroll', onScrollTrigger);
				}
			}, { passive: true });

			// Handle quick action chips inside the proactive card!
			const teaserChips = teaserEl.querySelectorAll('.tc-teaser-chip');
			teaserChips.forEach(function(chip) {
				chip.addEventListener('click', function(e) {
					e.stopPropagation();
					const query = chip.getAttribute('data-query');
					teaserEl.style.display = 'none';
					sessionStorage.setItem('tc_teaser_dismissed', '1');
					if (badgeEl) badgeEl.style.display = 'none';
					if (!isOpen) toggleWidget();
					if (query) {
						setTimeout(function() {
							sendMessage(query);
							if (starterChips) starterChips.style.display = 'none';
						}, 250);
					}
				});
			});

			if (openBtn) {
				openBtn.addEventListener('click', function(e) {
					e.stopPropagation();
					teaserEl.style.display = 'none';
					sessionStorage.setItem('tc_teaser_dismissed', '1');
					if (badgeEl) badgeEl.style.display = 'none';
					if (!isOpen) toggleWidget();
				});
			}

			teaserEl.addEventListener('click', function(e) {
				if (e.target === teaserCloseBtn || (teaserCloseBtn && teaserCloseBtn.contains(e.target))) {
					e.stopPropagation();
					teaserEl.style.display = 'none';
					sessionStorage.setItem('tc_teaser_dismissed', '1');
				} else if (!e.target.closest('.tc-teaser-chip') && e.target !== openBtn) {
					teaserEl.style.display = 'none';
					sessionStorage.setItem('tc_teaser_dismissed', '1');
					if (badgeEl) badgeEl.style.display = 'none';
					if (!isOpen) toggleWidget();
				}
			});
		}

		// 5. Voice Input (Web Speech Recognition)
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
					chatInput.placeholder = 'Ask about tours, darshan, cabs, hotels, pricing...';
				};

				recognition.onerror = function() {
					isListening = false;
					micBtn.classList.remove('listening');
					chatInput.placeholder = 'Ask about tours, darshan, cabs, hotels, pricing...';
				};
			}
		}

		// 7b. Interactive In-Browser WebCall Engine
		let isInVoiceCall = false;
		let voiceRecInstance = null;
		let animFrameId = null;
		let wavePhase = 0;

		function stopVisualizerLoop() {
			if (animFrameId) {
				cancelAnimationFrame(animFrameId);
				animFrameId = null;
			}
			if (voiceCanvas) {
				const ctx = voiceCanvas.getContext('2d');
				if (ctx) ctx.clearRect(0, 0, voiceCanvas.width, voiceCanvas.height);
			}
		}

		function startVisualizerLoop() {
			if (!voiceCanvas) return;
			const ctx = voiceCanvas.getContext('2d');
			if (!ctx) return;

			function draw() {
				const w = voiceCanvas.width;
				const h = voiceCanvas.height;
				ctx.clearRect(0, 0, w, h);

				const bars = 24;
				const barWidth = w / bars - 3;

				for (let i = 0; i < bars; i++) {
					let barHeight;
					if (isInVoiceCall) {
						barHeight = Math.sin(wavePhase + i * 0.4) * (h * 0.35) + (h * 0.45);
					} else {
						barHeight = 4 + Math.sin(wavePhase + i * 0.2) * 3;
					}

					const x = i * (barWidth + 3) + 2;
					const y = (h - barHeight) / 2;

					ctx.fillStyle = isInVoiceCall ? '#10b981' : '#cbd5e1';
					ctx.beginPath();
					if (ctx.roundRect) {
						ctx.roundRect(x, y, barWidth, barHeight, 3);
					} else {
						ctx.rect(x, y, barWidth, barHeight);
					}
					ctx.fill();
				}

				wavePhase += isInVoiceCall ? 0.15 : 0.03;
				animFrameId = requestAnimationFrame(draw);
			}

			if (!animFrameId) draw();
		}

		if (voiceToggleBtn) {
			const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;

			voiceToggleBtn.addEventListener('click', function() {
				if (isInVoiceCall) {
					endVoiceCall();
				} else {
					startVoiceCall(SpeechRec);
				}
			});
		}

		function startVoiceCall(SpeechRec) {
			if (!SpeechRec) {
				alert('Live voice recognition requires Google Chrome, Microsoft Edge, or Safari.');
				return;
			}

			isInVoiceCall = true;
			voiceToggleBtn.classList.add('in-call');
			voiceToggleBtn.querySelector('.tc-voice-btn-label').textContent = 'End Voice Call';
			voiceToggleBtn.querySelector('.tc-voice-btn-icon').textContent = '🔴';
			if (voiceRing) voiceRing.classList.add('active');
			if (voiceStatus) voiceStatus.textContent = 'Listening... Speak about your travel & darshan plans';

			trackEvent('tc_agent_webcall_started');

			try {
				voiceRecInstance = new SpeechRec();
				voiceRecInstance.continuous = true;
				voiceRecInstance.interimResults = true;
				voiceRecInstance.lang = 'en-IN';

				voiceRecInstance.onresult = function(e) {
					let interim = '';
					let finalTranscript = '';

					for (let i = e.resultIndex; i < e.results.length; ++i) {
						if (e.results[i].isFinal) {
							finalTranscript += e.results[i][0].transcript;
						} else {
							interim += e.results[i][0].transcript;
						}
					}

					if (interim && voiceTranscript) {
						voiceTranscript.textContent = '"' + interim + '..."';
					}

					if (finalTranscript) {
						if (voiceTranscript) voiceTranscript.textContent = '"' + finalTranscript + '"';
						if (voiceStatus) voiceStatus.textContent = 'Processing guide advice...';
						sendVoiceQuery(finalTranscript);
					}
				};

				voiceRecInstance.onerror = function() {
					if (voiceStatus) voiceStatus.textContent = 'Listening... Speak freely';
				};

				voiceRecInstance.onend = function() {
					if (isInVoiceCall && voiceRecInstance) {
						try { voiceRecInstance.start(); } catch (err) {}
					}
				};

				voiceRecInstance.start();
			} catch (e) {
				endVoiceCall();
			}
		}

		function endVoiceCall() {
			isInVoiceCall = false;
			if (voiceToggleBtn) {
				voiceToggleBtn.classList.remove('in-call');
				voiceToggleBtn.querySelector('.tc-voice-btn-label').textContent = 'Start Voice Call';
				voiceToggleBtn.querySelector('.tc-voice-btn-icon').textContent = '🎙️';
			}
			if (voiceRing) voiceRing.classList.remove('active');
			if (voiceStatus) voiceStatus.textContent = 'Voice call ended. Tap to start again.';
			if (voiceRecInstance) {
				try { voiceRecInstance.stop(); } catch (e) {}
				voiceRecInstance = null;
			}
			if (window.speechSynthesis) {
				window.speechSynthesis.cancel();
			}
			stopVisualizerLoop();
			trackEvent('tc_agent_webcall_ended');
		}

		function sendVoiceQuery(queryText) {
			fetch(tcChatWidget.restUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					message: queryText,
					session_id: sessionId,
					channel: 'web_voice',
					agent_slug: 'tripcosmos-guide',
					stream: false,
					page_url: window.location.href,
					page_title: document.title,
					language: currentLang
				})
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				const reply = data.reply || 'Namaste! I am ready to help you plan your Varanasi, Ayodhya, and spiritual tour.';
				if (voiceTranscript) {
					voiceTranscript.textContent = reply.length > 180 ? reply.substring(0, 180) + '...' : reply;
				}
				if (voiceStatus) voiceStatus.textContent = 'Speaking...';

				// Text-to-Speech playback in browser
				if (window.speechSynthesis) {
					window.speechSynthesis.cancel();
					const cleanText = reply.replace(/[#*`_\[\]]/g, '').replace(/https?:\/\/\S+/g, '');
					const utterance = new SpeechSynthesisUtterance(cleanText);
					utterance.rate = 1.05;
					utterance.onend = function() {
						if (isInVoiceCall && voiceStatus) {
							voiceStatus.textContent = 'Listening... Speak freely';
						}
					};
					window.speechSynthesis.speak(utterance);
				} else {
					if (voiceStatus) voiceStatus.textContent = 'Listening... Speak freely';
				}

				saveToHistory('user', queryText);
				saveToHistory('bot', reply);
				appendMessage('user', queryText);
				appendMessage('bot', reply);
			})
			.catch(function() {
				if (voiceStatus) voiceStatus.textContent = 'Listening... Speak freely';
			});
		}

		// 7c. Direct Outbound Mobile Call Trigger Button
		if (triggerCallBtn && directPhoneInput) {
			triggerCallBtn.addEventListener('click', function() {
				triggerMobileCall(directPhoneInput.value, 'Traveler', 'Expedition consultation');
			});
		}

		function triggerMobileCall(phone, name, reason) {
			phone = (phone || '').trim();
			if (!phone) {
				alert('Please enter a valid phone number with country code (e.g. +91 98765 43210).');
				return;
			}
			trackEvent('tc_agent_mobile_call_requested', { phone: phone });
			if (phoneCallStatus) {
				phoneCallStatus.style.display = 'block';
				phoneCallStatus.style.color = '#0284c7';
				phoneCallStatus.textContent = 'Initiating automated AI phone call...';
			}

			fetch(tcChatWidget.triggerCallUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					phone: phone,
					name: name || 'Traveler',
					reason: reason || 'Varanasi tour and cab consultation',
					session_id: sessionId
				})
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				if (data.success) {
					trackEvent('tc_agent_mobile_call_dispatched', { phone: phone });
					if (phoneCallStatus) {
						phoneCallStatus.style.color = '#10b981';
						phoneCallStatus.innerHTML = '✓ Call dispatched! Your mobile <strong>' + escapeHtml(phone) + '</strong> will ring in a few seconds.';
					}
					appendMessage('bot', '📞 **Automated Phone Consultation Dispatched!**\nOur AI travel specialist is placing a live phone call to **' + escapeHtml(phone) + '**. Please answer your mobile.');
					saveToHistory('bot', '📞 Automated Phone Consultation Dispatched to ' + phone);
				} else {
					if (phoneCallStatus) {
						phoneCallStatus.style.color = '#ef4444';
						phoneCallStatus.textContent = data.message || 'Call could not be placed. Please reach out on WhatsApp.';
					}
				}
			})
			.catch(function(err) {
				if (phoneCallStatus) {
					phoneCallStatus.style.color = '#ef4444';
					phoneCallStatus.textContent = 'Connection error. Reach out directly on WhatsApp.';
				}
			});
		}

		function renderCallActionCard(phone) {
			const card = document.createElement('div');
			card.className = 'tc-call-action-card';
			card.innerHTML =
				'<div class="tc-call-action-card-header">📞 Instant AI Phone Call Ready</div>' +
				'<p>Would you like our travel specialist AI to ring your mobile now to discuss your itinerary, cab options, and pricing?</p>' +
				'<button type="button" class="tc-call-action-btn">📞 Ring My Mobile Now (' + escapeHtml(phone) + ')</button>';

			card.querySelector('.tc-call-action-btn').addEventListener('click', function(e) {
				e.currentTarget.disabled = true;
				e.currentTarget.textContent = 'Dialing mobile...';
				triggerMobileCall(phone, 'Traveler', 'Live website consultation');
			});

			messagesContainer.appendChild(card);
			scrollToBottom(true);
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

		// 9b. Fast Itinerary & Group Quote Form Submit
		if (quoteForm) {
			quoteForm.addEventListener('submit', function(e) {
				e.preventDefault();
				const submitBtn = document.getElementById('tc-quote-submit');
				const name = (document.getElementById('tc-q-name').value || '').trim();
				const phone = (document.getElementById('tc-q-phone').value || '').trim();
				const email = (document.getElementById('tc-q-email').value || '').trim();
				const destination = (document.getElementById('tc-q-destination').value || '').trim();
				const travelMonth = (document.getElementById('tc-q-month').value || '').trim();
				const groupReq = (document.getElementById('tc-q-group').value || '').trim();

				if (!name || !phone) {
					alert('Please enter your Name and WhatsApp Number.');
					return;
				}

				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = '⚡ Preparing Itinerary & Fare...';
				}

				fetch(tcChatWidget.leadCaptureUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						name: name,
						phone: phone,
						email: email,
						destination: destination,
						travel_month: travelMonth,
						group_size: groupReq,
						session_id: sessionId,
						page_url: window.location.href,
						page_title: document.title
					})
				})
				.then(function(res) { return res.json(); })
				.then(function(data) {
					if (quoteSuccess) quoteSuccess.style.display = 'block';
					quoteForm.style.display = 'none';

					// Auto-switch back to Guide chat after 1.2s and ask AI to deliver the full customized itinerary
					setTimeout(function() {
						switchTab('chat');
						quoteForm.reset();
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = '⚡ Get Custom Itinerary & Cab Fare';
						}
						if (quoteSuccess) quoteSuccess.style.display = 'none';
						quoteForm.style.display = 'block';

						// Prompt AI to generate the tailored itinerary directly in the chat window!
						const promptText = 'I have submitted my itinerary request: Name: ' + name + ', WhatsApp: ' + phone + (email ? ', Email: ' + email : '') + (destination ? ', Destination: ' + destination : '') + (travelMonth ? ', Travel Dates: ' + travelMonth : '') + ', Requirement: ' + groupReq + '. Please prepare and show my customized itinerary and cab fare breakdown now!';
						sendMessage(promptText);
					}, 1200);
				})
				.catch(function(err) {
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = '⚡ Get Custom Itinerary & Cab Fare';
					}
					alert('Could not submit inquiry. Please talk to our guide directly.');
				});
			});
		}

		// 9c. Bottom Navigation Tab Switching
		function switchTab(tabId) {
			tabBtns.forEach(function(btn) {
				const isActive = btn.getAttribute('data-tab') === tabId;
				btn.classList.toggle('active', isActive);
				btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
			});

			const panels = [messagesContainer, quotePanel, voicePanel, historyPanel];
			panels.forEach(function(p) {
				if (p) p.classList.remove('active');
			});

			const inputArea = document.getElementById('tc-widget-input-area');

			if (tabId === 'chat') {
				if (messagesContainer) messagesContainer.classList.add('active');
				if (inputArea) inputArea.style.display = 'block';
				stopVisualizerLoop();
				scrollToBottom();
				if (chatInput) chatInput.focus();
			} else if (tabId === 'quote') {
				if (quotePanel) quotePanel.classList.add('active');
				if (inputArea) inputArea.style.display = 'none';
				stopVisualizerLoop();
			} else if (tabId === 'voice') {
				if (voicePanel) voicePanel.classList.add('active');
				if (inputArea) inputArea.style.display = 'none';
				startVisualizerLoop();
			} else if (tabId === 'history') {
				if (historyPanel) historyPanel.classList.add('active');
				if (inputArea) inputArea.style.display = 'none';
				stopVisualizerLoop();
				renderHistoryPanel();
			}
		}

		tabBtns.forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				const tabId = btn.getAttribute('data-tab');
				if (tabId) switchTab(tabId);
			});
		});

		function renderHistoryPanel() {
			if (!historyList) return;
			try {
				const history = JSON.parse(sessionStorage.getItem('tc_chat_history')) || [];
				if (!history.length) {
					historyList.innerHTML = '<div class="tc-history-empty"><span style="font-size:28px;">🛕</span><p>No past chat transcripts yet. Ask a question or request an itinerary to start saving your journey history!</p></div>';
					return;
				}
				let html = '<div class="tc-history-items">';
				history.forEach(function(item, idx) {
					const isUser = item.role === 'user';
					const label = isUser ? 'You' : 'TripCosmos Guide';
					const icon = isUser ? '👤' : '🛕';
					html += '<div class="tc-history-item tc-hist-' + item.role + '"><div class="tc-hist-header"><span>' + icon + ' ' + label + '</span></div><div class="tc-hist-body">' + escapeHtml(item.text).substring(0, 180) + (item.text.length > 180 ? '...' : '') + '</div></div>';
				});
				html += '</div>';
				historyList.innerHTML = html;
			} catch (e) {}
		}

		if (clearHistoryBtn) {
			clearHistoryBtn.addEventListener('click', function() {
				sessionStorage.removeItem('tc_chat_history');
				renderHistoryPanel();
				const greeting = tcChatWidget.greeting || 'Namaste! How can I assist you today?';
				messagesContainer.innerHTML = '<div class="tc-chat-bubble tc-bubble-bot"><div class="tc-bubble-text">' + escapeHtml(greeting).replace(/\n/g, '<br />') + '</div></div>';
				if (starterChips) {
					messagesContainer.appendChild(starterChips);
					starterChips.style.display = 'flex';
				}
			});
		}

		// 10. Send Message Flow with Streaming & Context
		let isSending = false;
		const chatSubmitBtn = chatForm ? chatForm.querySelector('button[type="submit"]') : null;

		function setSendingState(sending) {
			isSending = sending;
			if (chatInput) chatInput.disabled = sending;
			if (chatSubmitBtn) chatSubmitBtn.disabled = sending;
			if (micBtn) micBtn.disabled = sending;
			if (!sending && chatInput) chatInput.focus();
		}

		function sendMessage(text) {
			if (isSending) return;
			setSendingState(true);

			// Auto-detect phone / email on client side for immediate tracking and 1-click call option
			const phoneMatch = text.match(/(?:\+91|91|0)?[6-9]\d{9}|\+?[0-9]{8,15}/);
			if (phoneMatch) {
				const detectedPhone = phoneMatch[0];
				trackEvent('tc_agent_phone_detected', { phone: detectedPhone });
				fetch(tcChatWidget.leadCaptureUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						phone: detectedPhone,
						session_id: sessionId,
						page_url: window.location.href,
						page_title: document.title
					})
				}).catch(function() {});

				// Render instant 1-click Outbound Phone Call card
				setTimeout(function() {
					renderCallActionCard(detectedPhone);
				}, 1200);
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
					agent_slug: (window.tcChatWidget && tcChatWidget.agentSlug) ? tcChatWidget.agentSlug : 'tripcosmos-guide',
					stream: true,
					page_url: window.location.href,
					page_title: document.title,
					language: currentLang
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
								setSendingState(false);
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
											scrollToBottom(false);
										} else if (event.type === 'done') {
											setSendingState(false);
											scrollToBottom(true);
											if (event.handoff && event.handoff.whatsapp_url) {
												renderHandoffCta(event.handoff.whatsapp_url);
											}
											if (event.executed_tools && event.executed_tools.length) {
												event.executed_tools.forEach(function(t) {
													if (t.tool === 'search_trips' && t.output && t.output.trips) {
														renderTripCards(t.output.trips);
													}
													if (t.tool === 'calculate_cab_tariff' && t.output && !t.output.error) {
														renderCabFareCard(t.output);
													}
													if (t.tool === 'generate_itinerary_pdf' && t.output && !t.output.error) {
														renderItineraryCard(t.output);
													}
													if (t.tool === 'lookup_temple_protocol' && t.output && !t.output.error) {
														renderTempleProtocolCard(t.output);
													}
													if (t.tool === 'request_voice_call' && t.output && t.output.phone) {
														appendMessage('bot', '📞 ' + (t.output.message || 'AI phone call dispatched to ' + t.output.phone));
													}
												});
											}
										} else if (event.type === 'error') {
											setSendingState(false);
											textDiv.innerHTML = escapeHtml(event.error || 'Agent service is paused.');
											scrollToBottom(true);
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
						setSendingState(false);
						if (data.reply) {
							textDiv.innerHTML = formatMarkdown(data.reply);
							saveToHistory('bot', data.reply);
							if (data.handoff && data.handoff.whatsapp_url) {
								renderHandoffCta(data.handoff.whatsapp_url);
							}
							if (data.executed_tools && data.executed_tools.length) {
								data.executed_tools.forEach(function(t) {
									if (t.tool === 'search_trips' && t.output && t.output.trips) {
										renderTripCards(t.output.trips);
									}
									if (t.tool === 'calculate_cab_tariff' && t.output && !t.output.error) {
										renderCabFareCard(t.output);
									}
									if (t.tool === 'generate_itinerary_pdf' && t.output && !t.output.error) {
										renderItineraryCard(t.output);
									}
									if (t.tool === 'lookup_temple_protocol' && t.output && !t.output.error) {
										renderTempleProtocolCard(t.output);
									}
								});
							}
						}
					});
				}
			})
			.catch(function(err) {
				setSendingState(false);
				textDiv.innerHTML = '<span style="color:#b91c1c; font-weight:600;">⚠️ Connection issue.</span> Our expedition specialist is ready on WhatsApp for immediate custom planning.';
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

		function renderTripCards(trips) {
			if (!trips || !trips.length) return;

			const container = document.createElement('div');
			container.className = 'tc-trip-cards-scroll';

			trips.forEach(function(trip) {
				const card = document.createElement('div');
				card.className = 'tc-trip-card';

				const thumb = trip.thumbnail || 'https://images.unsplash.com/photo-1544735716-392fe2489ffa?w=600&q=80';
				const waNumber = tcChatWidget.whatsappNumber || '919876543210';
				const waUrl = 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent('Hi TripCosmos! I am inquiring about the ' + trip.title + ' (' + (trip.price || 'package') + '). Could you share upcoming dates?');

				card.innerHTML =
					'<img src="' + escapeHtml(thumb) + '" alt="' + escapeHtml(trip.title) + '" class="tc-trip-thumb" loading="lazy" />' +
					'<div class="tc-trip-content">' +
						'<div class="tc-trip-title">' + escapeHtml(trip.title) + '</div>' +
						'<div class="tc-trip-meta-row">' +
							'<span class="tc-trip-badge">⏳ ' + escapeHtml(trip.duration || 'Flexible') + '</span>' +
							'<span class="tc-trip-badge">🛕 ' + escapeHtml(trip.altitude || 'Spiritual Circuit') + '</span>' +
						'</div>' +
						'<div class="tc-trip-price">' + escapeHtml(trip.price || 'Inquire') + '</div>' +
						'<div class="tc-trip-actions">' +
							'<a href="' + waUrl + '" target="_blank" rel="noopener" class="tc-trip-btn-wa">WhatsApp</a>' +
							'<a href="' + escapeHtml(trip.url || '#') + '" target="_blank" rel="noopener" class="tc-trip-btn-view">Details</a>' +
						'</div>' +
					'</div>';

				container.appendChild(card);
			});

			messagesContainer.appendChild(container);
			scrollToBottom();
		}

		function renderHandoffCta(customUrl) {
			const url = customUrl || ('https://wa.me/' + tcChatWidget.whatsappNumber + '?text=' + encodeURIComponent('Hi TripCosmos Team, I am looking for a custom Varanasi/Ayodhya tour package and cab quotation.'));
			const cta = document.createElement('div');
			cta.className = 'tc-handoff-cta';
			cta.innerHTML = '<p style="margin:0 0 6px 0; font-size:12px; color:#065f46; font-weight:600;">Want to connect with our Varanasi travel desk directly?</p>' +
							'<a href="' + url + '" target="_blank" rel="noopener" class="tc-whatsapp-btn" id="tc-cta-wa">' +
							'<span>💬 Chat on WhatsApp with Travel Specialist</span>' +
							'</a>';
			cta.querySelector('#tc-cta-wa').addEventListener('click', function() {
				trackEvent('tc_agent_whatsapp_click', { url: url });
			});
			messagesContainer.appendChild(cta);
			scrollToBottom();
		}

		function renderCabFareCard(data) {
			if (!data || !data.origin || !data.destination) return;
			const card = document.createElement('div');
			card.className = 'tc-cab-fare-card';

			const waNumber = tcChatWidget.whatsappNumber || '919876543210';
			const waMsg = 'Namaste TripCosmos! I would like to book a cab for ' + data.origin + ' to ' + data.destination + ' (' + data.vehicle_name + ', ' + data.estimated_fare + '). Please confirm availability.';
			const waUrl = 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent(waMsg);

			card.innerHTML =
				'<div class="tc-card-header">' +
					'<span class="tc-card-title">🚗 ' + escapeHtml(data.vehicle_name) + '</span>' +
					'<span class="tc-card-badge">' + escapeHtml(data.origin) + ' &rarr; ' + escapeHtml(data.destination) + '</span>' +
				'</div>' +
				'<div class="tc-fare-amount">' + escapeHtml(data.estimated_fare) + ' <span>(All-inclusive est.)</span></div>' +
				'<div class="tc-card-grid">' +
					'<div class="tc-card-stat"><div class="tc-card-stat-label">Distance / Trip</div><div class="tc-card-stat-val">' + escapeHtml(String(data.total_billable_km || '')) + ' km (' + (data.is_round_trip ? 'Round Trip' : 'One Way') + ')</div></div>' +
					'<div class="tc-card-stat"><div class="tc-card-stat-label">Duration</div><div class="tc-card-stat-val">' + escapeHtml(String(data.days || 1)) + ' Day(s)</div></div>' +
					'<div class="tc-card-stat"><div class="tc-card-stat-label">Driver DA</div><div class="tc-card-stat-val">' + escapeHtml(data.driver_allowance || 'Included') + '</div></div>' +
					'<div class="tc-card-stat"><div class="tc-card-stat-label">Toll & Parking</div><div class="tc-card-stat-val">' + escapeHtml(data.toll_tax_estimate || 'Est. Included') + '</div></div>' +
				'</div>' +
				'<a href="' + waUrl + '" target="_blank" rel="noopener" class="tc-card-action-btn">💬 Book This Cab via WhatsApp</a>';

			messagesContainer.appendChild(card);
			scrollToBottom(true);
		}

		function renderItineraryCard(data) {
			if (!data || !data.view_url) return;
			const card = document.createElement('div');
			card.className = 'tc-itinerary-card';

			const waNumber = tcChatWidget.whatsappNumber || '919876543210';
			const waMsg = 'Namaste TripCosmos! I have reviewed my tour itinerary (' + (data.itinerary_id || '') + ' - ' + (data.summary || '') + '). I would like to proceed with booking.';
			const waUrl = 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent(waMsg);

			card.innerHTML =
				'<div class="tc-itin-badge">📜 OFFICIAL TRIPCOSMOS ITINERARY</div>' +
				'<div class="tc-itin-title">🛕 ' + escapeHtml(data.summary || 'Custom Spiritual Circuit Itinerary') + '</div>' +
				'<div class="tc-itin-meta"><strong>Reference ID:</strong> <code>' + escapeHtml(data.itinerary_id || '') + '</code></div>' +
				(data.total_fare ? '<div class="tc-itin-meta"><strong>Estimated Package / Cab Fare:</strong> ' + escapeHtml(data.total_fare) + '</div>' : '') +
				(data.vehicle ? '<div class="tc-itin-meta"><strong>Allocated Vehicle:</strong> ' + escapeHtml(data.vehicle) + '</div>' : '') +
				'<div class="tc-itin-actions">' +
					'<a href="' + escapeHtml(data.view_url) + '" target="_blank" rel="noopener" class="tc-itin-btn-view">📄 View & Print Itinerary</a>' +
					'<a href="' + waUrl + '" target="_blank" rel="noopener" class="tc-itin-btn-wa">💬 Confirm via WhatsApp</a>' +
				'</div>';

			messagesContainer.appendChild(card);
			scrollToBottom(true);
		}

		function renderTempleProtocolCard(data) {
			if (!data || !data.temple_name) return;
			const card = document.createElement('div');
			card.className = 'tc-temple-card';

			card.innerHTML =
				'<div class="tc-temple-header">🛕 ' + escapeHtml(data.temple_name) + ' Protocol</div>' +
				'<div style="margin:4px 0; font-size:11.5px; color:#475569;"><strong>⏱️ Timings:</strong> ' + escapeHtml(data.timings || 'Open for Darshan') + '</div>' +
				'<div style="margin:4px 0; font-size:11.5px; color:#475569;"><strong>🕉️ Prime Aarti:</strong> ' + escapeHtml(data.mangala_aarti || 'Check schedule') + '</div>' +
				(data.sparsh_darshan_rules ? '<div style="margin:4px 0; font-size:11.5px; color:#334155;"><strong>👗 Dress Code & Sparsh:</strong> ' + escapeHtml(data.sparsh_darshan_rules) + '</div>' : '') +
				(data.sugam_darshan_pass ? '<div style="margin:4px 0; font-size:11.5px; color:#065f46;"><strong>🎟️ VIP Sugam Entry:</strong> ' + escapeHtml(data.sugam_darshan_pass) + '</div>' : '') +
				(data.wheelchair_facility ? '<div style="margin:4px 0; font-size:11.5px; color:#1e293b;"><strong>♿ Accessibility:</strong> ' + escapeHtml(data.wheelchair_facility) + '</div>' : '');

			messagesContainer.appendChild(card);
			scrollToBottom(true);
		}

		function scrollToBottom(force) {
			if (force) {
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
				return;
			}
			const distanceFromBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
			if (distanceFromBottom < 140) {
				messagesContainer.scrollTop = messagesContainer.scrollHeight;
			}
		}

		function escapeHtml(str) {
			const div = document.createElement('div');
			div.textContent = str || '';
			return div.innerHTML;
		}

		// Clean line-aware Markdown Parser: bold, italic, code, headings, lists, links
		function formatMarkdown(str) {
			if (!str) return '';
			let safe = escapeHtml(str);

			// Code blocks (inline `code`)
			safe = safe.replace(/`([^`]+)`/g, '<code class="tc-inline-code">$1</code>');

			// Bold **text** or __text__
			safe = safe.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
			safe = safe.replace(/__([^_]+)__/g, '<strong>$1</strong>');

			// Italic *text*
			safe = safe.replace(/(^|[^*_])\*([^*]+)\*(?=[^*_]|$)/g, '$1<em>$2</em>');

			// Headings: ### Title or ## Title
			safe = safe.replace(/^###\s+(.*)$/gm, '<h5 class="tc-md-h5">$1</h5>');
			safe = safe.replace(/^##\s+(.*)$/gm, '<h4 class="tc-md-h4">$1</h4>');

			// Links: [text](url) - support http, https, relative (/), tel:, and mailto:
			safe = safe.replace(/\[([^\]]+)\]\(((?:https?:\/\/|\/|tel:|mailto:)[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1 &nearr;</a>');

			// Process lists and paragraphs line by line to prevent swallowing paragraphs
			const lines = safe.split('\n');
			let inList = false;
			let listType = ''; // 'ul' or 'ol'
			const out = [];

			for (let i = 0; i < lines.length; i++) {
				const line = lines[i];
				const ulMatch = line.match(/^[\*\-]\s+(.*)$/);
				const olMatch = line.match(/^(\d+)\.\s+(.*)$/);

				if (ulMatch) {
					if (!inList || listType !== 'ul') {
						if (inList) out.push('</' + listType + '>');
						out.push('<ul>');
						inList = true;
						listType = 'ul';
					}
					out.push('<li>' + ulMatch[1] + '</li>');
				} else if (olMatch) {
					if (!inList || listType !== 'ol') {
						if (inList) out.push('</' + listType + '>');
						out.push('<ol>');
						inList = true;
						listType = 'ol';
					}
					out.push('<li>' + olMatch[2] + '</li>');
				} else {
					if (inList) {
						out.push('</' + listType + '>');
						inList = false;
						listType = '';
					}
					if (line.trim().length > 0) {
						out.push(line + '<br />');
					} else {
						out.push('<div class="tc-md-spacer"></div>');
					}
				}
			}
			if (inList) {
				out.push('</' + listType + '>');
			}

			return out.join('');
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
					messagesContainer.innerHTML = '';
					history.forEach(function(item) {
						appendMessage(item.role, item.text);
					});
				}
			} catch (e) {}
		}
	});
})();
