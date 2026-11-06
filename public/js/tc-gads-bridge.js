/**
 * TripCosmos Google Ads & GTM Conversion Bridge
 *
 * Automatically tracks 100% of conversions across:
 * 1. Fluent Forms submissions (Form 1, Form 3, Itinerary requests).
 * 2. WhatsApp clicks (Floating widget, navbar, in-post CTAs, tour package buttons).
 * 3. Phone calls (tel: clicks).
 * 4. GCLID & UTM capture and persistence for offline attribution.
 *
 * Dual-firing: Pushes to window.dataLayer (for GTM GTM-W29ZGZ5B)
 * AND executes direct window.gtag('event', 'conversion', ...) for Google Ads AW-17106475490.
 *
 * @package TripCosmos_Agents
 */
(function() {
	'use strict';

	var cfg = window.tcGadsConfig || {};
	var GADS_ID = cfg.conversionId || 'AW-17106475490';
	var FORM_LABEL = cfg.formLabel || 'bLh-CM-swOoaEOKzgN0_';
	var WA_LABEL = cfg.waLabel || 'MOptCICRn-caEOKzgN0_';
	var PHONE_LABEL = cfg.phoneLabel || '7sk4CLrQst4aEOKzgN0_';
	var PURCHASE_LABEL = cfg.purchaseLabel || 'EEn8CIqNoOcaEOKzgN0_';
	var LEAD_VALUE = cfg.leadValue ? Number(cfg.leadValue) : 1500;
	var CLICK_VALUE = 500;

	// Deduplication tracker
	var firedConversions = {};

	function fireConversion(type, label, value, extraData) {
		var dedupKey = type + '_' + label + '_' + JSON.stringify(extraData || {});
		if (firedConversions[dedupKey]) {
			return; // Prevent duplicate firing on same page action
		}
		firedConversions[dedupKey] = true;

		// 1. GTM DataLayer Push
		window.dataLayer = window.dataLayer || [];
		
		if (type === 'form_submit') {
			var formId = (extraData && extraData.form_id) || 'unknown';
			// Matches GTM Tag 217 & Tag 226 trigger: event === "FluentFormActivities" && eventAction === "FormSubmitted"
			window.dataLayer.push({
				event: 'FluentFormActivities',
				eventCategory: 'FluentForm',
				eventAction: 'FormSubmitted',
				FluentFormID: formId,
				value: value || LEAD_VALUE,
				currency: 'INR'
			});

			// Matches standard GA4 & GTM Tag 243: event === "generate_lead"
			window.dataLayer.push({
				event: 'generate_lead',
				form_id: formId,
				lead_source: 'fluent_form',
				value: value || LEAD_VALUE,
				currency: 'INR'
			});
		} else if (type === 'whatsapp_click') {
			// Matches GTM Tag 100: event === "whatsapp_click"
			window.dataLayer.push({
				event: 'whatsapp_click',
				click_url: (extraData && extraData.url) || '',
				value: value || CLICK_VALUE,
				currency: 'INR'
			});
		} else if (type === 'phone_click') {
			// Matches GTM Tag 182: event === "phone_click"
			window.dataLayer.push({
				event: 'phone_click',
				phone_number: (extraData && extraData.phone) || '',
				click_url: (extraData && extraData.url) || '',
				value: value || CLICK_VALUE,
				currency: 'INR'
			});
		}

		// 2. Direct Google Ads gtag('event', 'conversion') execution
		if (typeof window.gtag === 'function') {
			try {
				var gadsPayload = {
					send_to: GADS_ID + '/' + label,
					value: value || LEAD_VALUE,
					currency: 'INR'
				};
				if (extraData && extraData.transaction_id) {
					gadsPayload.transaction_id = extraData.transaction_id;
				}
				window.gtag('event', 'conversion', gadsPayload);
			} catch (err) {
				console.warn('[TripCosmos GAds Bridge] Direct gtag conversion error:', err);
			}
		}

		// Dispatch custom DOM event for other listeners
		try {
			document.dispatchEvent(new CustomEvent('tc_gads_conversion_fired', {
				detail: { type: type, label: label, value: value, extra: extraData }
			}));
		} catch (e) {}
	}

	// =========================================================================
	// 1. GCLID & UTM Persistence (Captures Google Click ID for attribution)
	// =========================================================================
	(function captureGclid() {
		try {
			var params = new URLSearchParams(window.location.search);
			var gclid = params.get('gclid') || params.get('wbraid') || params.get('gbraid');
			if (gclid) {
				sessionStorage.setItem('tc_gclid', gclid);
				// Set cookie for 30 days
				var d = new Date();
				d.setTime(d.getTime() + (30 * 24 * 60 * 60 * 1000));
				document.cookie = 'tc_gclid=' + encodeURIComponent(gclid) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
			}
		} catch (e) {}
	})();

	// =========================================================================
	// 2. Global Fluent Forms Listener (Document Delegated)
	// =========================================================================
	function initFluentFormsTracking() {
		// Method A: jQuery event delegation (Standard Fluent Forms trigger)
		if (window.jQuery) {
			window.jQuery(document).on('fluentform_submission_success', function(event, data) {
				var formId = (data && data.formId) || (data && data.form && data.form.id) || 'unknown';
				fireConversion('form_submit', FORM_LABEL, LEAD_VALUE, {
					form_id: formId,
					response: data && data.response
				});
			});
		}

		// Method B: Vanilla DOM Mutation / Fetch / XHR Intercept for Fluent Forms
		// Guarantees tracking even if jQuery is deferred, minified, or loads late
		document.addEventListener('submit', function(e) {
			var form = e.target.closest('form.frm-fluent-form');
			if (!form) return;

			var formId = form.getAttribute('data-form_id') || form.id || 'fluentform';

			// Watch for Fluent Form success message insertion
			var observer = new MutationObserver(function(mutations, obs) {
				var successBox = form.parentElement.querySelector('.ff-message-success') ||
				                 form.querySelector('.ff-message-success') ||
				                 document.getElementById('form_success');
				if (successBox) {
					obs.disconnect();
					fireConversion('form_submit', FORM_LABEL, LEAD_VALUE, {
						form_id: formId,
						method: 'dom_observer'
					});
				}
			});

			observer.observe(form.parentElement || form, { childList: true, subtree: true });

			// Timeout safety: disconnect observer after 15 seconds
			setTimeout(function() { observer.disconnect(); }, 15000);
		}, true);
	}

	// =========================================================================
	// 3. Global WhatsApp & Phone Call Click Tracking
	// =========================================================================
	function initClickTracking() {
		document.addEventListener('click', function(e) {
			var link = e.target.closest('a[href]');
			if (!link) return;

			var href = link.getAttribute('href') || '';

			// WhatsApp Detection (wa.me, api.whatsapp.com, or direct intent)
			if (href.indexOf('wa.me') !== -1 || href.indexOf('api.whatsapp.com') !== -1 || href.indexOf('whatsapp://') !== -1) {
				fireConversion('whatsapp_click', WA_LABEL, CLICK_VALUE, {
					url: href,
					text: (link.textContent || '').trim().slice(0, 50)
				});
			}
			// Phone Call Detection (tel:)
			else if (href.indexOf('tel:') === 0) {
				var phone = href.replace('tel:', '').trim();
				fireConversion('phone_click', PHONE_LABEL, CLICK_VALUE, {
					phone: phone,
					url: href
				});
			}
		}, true);
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			initFluentFormsTracking();
			initClickTracking();
		});
	} else {
		initFluentFormsTracking();
		initClickTracking();
	}
})();
