=== TripCosmos Agents ===
Contributors: tripcosmos
Tags: ai, chatbot, agent, travel, crm, whatsapp, openrouter, aipuffer
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: Proprietary
License URI: https://tripcosmos.co

Unified conversational AI agent layer for TripCosmos.co with automatic multi-provider LLM failover, live catalog search, multi-channel plumbings, and strict safety guardrails.

== Description ==

TripCosmos Agents provides TripCosmos.co with a reliable, supervised AI conversational layer across customer-facing web chat, WhatsApp, voice calls, and internal staff tools.

= Key Features =

* **AI Provider Failover Chain**: Primary AI Puffer with automatic circuit-breaking fallback to OpenRouter, Omniroute, and in-house VMStudio endpoints.
* **Safety Guardrails**: 1-click global emergency kill switch, rate limits per session, daily WhatsApp/Voice caps, and draft-for-approval transaction gating.
* **Master Agent Console**: Full staff workspace in wp-admin to supervise agents, query catalogs, and draft customer responses.
* **Togo & WooCommerce Integration**: Tool-calling queries live trek packages, pricing, durations, and destinations directly from site data.
* **Multi-Channel Plumbings**: Native Fluent CRM lead tagging, Twenty CRM REST syncing, WhatsApp (`wa.vmstudio.digital`) gateway bridge, and Google Sheets streaming.
* **Theme & Cache Resilient**: Floating widget isolated from theme conflicts and tested for Elementor Pro and LiteSpeed Cache compatibility.

== Installation ==

1. Upload the `tripcosmos-agents` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to `Tripcosmos Agents -> Routing` to configure provider credentials and priorities.
4. Customize the assistant persona in `Tripcosmos Agents -> Agents`.
5. Connect CRM, WhatsApp, and Voice settings in `Tripcosmos Agents -> Integrations`.

== Changelog ==

= 1.0.0 =
* Initial production release.
