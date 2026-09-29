/**
 * Adds a "Ref TC-XXXXX" code to clicks on the business WhatsApp link and records the visit,
 * so the message that arrives in WhatsApp can be matched back to this page, ad source and chat session.
 */
(function () {
	'use strict';

	var cfg = window.tcWaAttr;
	if (!cfg || !cfg.clickUrl || !cfg.number) {
		return;
	}

	var ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
	var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign'];

	function store(key, value) {
		try { sessionStorage.setItem(key, value); } catch (e) { /* storage blocked */ }
	}

	function load(key) {
		try { return sessionStorage.getItem(key) || ''; } catch (e) { return ''; }
	}

	// Ad parameters and the original referrer are only on the landing page, so keep them for the session.
	(function captureLanding() {
		var params = new URLSearchParams(window.location.search);
		var utm = {};
		try { utm = JSON.parse(load('tc_utm') || '{}') || {}; } catch (e) { utm = {}; }
		var changed = false;
		UTM_KEYS.forEach(function (key) {
			var value = params.get(key);
			if (value) { utm[key] = value; changed = true; }
		});
		if (changed) { store('tc_utm', JSON.stringify(utm)); }
		if (!load('tc_first_ref') && document.referrer) { store('tc_first_ref', document.referrer); }
	})();

	function digits(value) {
		return String(value || '').replace(/\D/g, '');
	}

	function isOurNumber(number) {
		var a = digits(number).slice(-10);
		return a.length === 10 && a === digits(cfg.number).slice(-10);
	}

	function parseWhatsAppLink(href) {
		var url;
		try { url = new URL(href, window.location.href); } catch (e) { return null; }
		var host = url.hostname.replace(/^www\./, '');
		var number = '';
		if (host === 'wa.me') {
			number = url.pathname.replace('/', '');
		} else if (host === 'api.whatsapp.com' && url.pathname === '/send') {
			number = url.searchParams.get('phone') || '';
		} else {
			return null;
		}
		return isOurNumber(number) ? url : null;
	}

	function newRef() {
		var out = 'TC-';
		var bytes = new Uint8Array(5);
		(window.crypto || window.msCrypto).getRandomValues(bytes);
		for (var i = 0; i < 5; i++) {
			out += ALPHABET.charAt(bytes[i] % ALPHABET.length);
		}
		return out;
	}

	function buildUrl(url, text) {
		var parts = [];
		url.searchParams.forEach(function (value, key) {
			if (key !== 'text') { parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value)); }
		});
		parts.push('text=' + encodeURIComponent(text));
		return url.origin + url.pathname + '?' + parts.join('&');
	}

	function send(payload) {
		var body = JSON.stringify(payload);
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(cfg.clickUrl, new Blob([body], { type: 'application/json' }))) {
				return;
			}
		} catch (e) { /* fall through to fetch */ }
		try {
			fetch(cfg.clickUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
		} catch (e) { /* tracking must never block the click */ }
	}

	function onClick(event) {
		var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
		if (!link) { return; }
		var url = parseWhatsAppLink(link.getAttribute('href'));
		if (!url) { return; }

		var text = url.searchParams.get('text') || cfg.defaultText || 'Hi TripCosmos!';
		if (/\bRef\s+TC-[A-Z0-9]{5}\b/i.test(text)) { return; }

		var ref = newRef();
		link.setAttribute('href', buildUrl(url, text + ' (Ref ' + ref + ')'));

		var utm = {};
		try { utm = JSON.parse(load('tc_utm') || '{}') || {}; } catch (e) { utm = {}; }
		send({
			ref: ref,
			session_id: load('tc_agent_session_id'),
			page_url: window.location.href,
			referrer: load('tc_first_ref'),
			utm_source: utm.utm_source || '',
			utm_medium: utm.utm_medium || '',
			utm_campaign: utm.utm_campaign || '',
			context: (link.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 80)
		});
	}

	document.addEventListener('click', onClick, true);
	document.addEventListener('auxclick', onClick, true);
})();
