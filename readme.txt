=== TripCosmos Agents ===
Contributors: tripcosmos
Tags: ai, chatbot, agent, travel, crm, whatsapp, openrouter, gemini, b2b
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.4.5
License: Proprietary
License URI: https://tripcosmos.co

Unified conversational AI agent layer for TripCosmos.co with automatic multi-provider LLM failover, live catalog search, multi-channel plumbings, and strict safety guardrails.

== Description ==

TripCosmos Agents provides TripCosmos.co with a reliable, supervised AI conversational layer across customer-facing web chat, WhatsApp, voice calls, and internal staff tools.

= Key Features =

* **AI Provider Failover Chain**: Canonical OpenRouter + AI Gateway (consolidates AI Puffer/Omniroute/ai.vmstudio.digital) + Google Gemini, with circuit-breaking and live model sync.
* **Live Model Sync**: OpenRouter / Gateway /models + Gemini catalogue refresh on Save, 2x-daily cron, and manual REST sync.
* **B2B Partner Hunter**: Google Business/Places daily import of Indian travel agencies + WhatsApp (Evolution API) + Brevo email outreach, logged to FluentCRM + TwentyCRM.
* **WhatsApp Dual-Mode**: Legacy wa.vmstudio.digital + Evolution API send/normalize on one shared webhook.
* **Semantic Vector Store & Hybrid RAG**: Full-text and packed float32 embedding search with automatic overlapping chunking across published tours, packages, and site knowledge.
* **Asynchronous Background Queue**: Non-blocking background worker for memory synthesis, CRM syncing, and catalog indexing keeping chats fast.
* **Visual Kanban Deals Pipeline**: Drag-and-drop sales stage board with instant AJAX persistence and quick WhatsApp follow-up triggers.
* **Rich Tour & Cab Cards & Carousel**: Interactive frontend widget cards displaying tour duration, cab vehicle types, pricing, and 1-click WhatsApp booking.
* **Safety Guardrails**: 1-click global emergency kill switch, rate limits per session, daily WhatsApp/Voice caps, and draft-for-approval transaction gating.
* **Multi-Channel Plumbings**: Native Fluent CRM lead tagging, Twenty CRM REST syncing, WhatsApp (legacy + Evolution API) gateway bridge, Brevo email, and Google Sheets streaming.

== Installation ==

1. Upload the `tripcosmos-agents` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to `Tripcosmos Agents -> Routing` to configure provider credentials and priorities.
4. Customize the assistant persona in `Tripcosmos Agents -> Agents`.
5. Connect CRM, WhatsApp, and Voice settings in `Tripcosmos Agents -> Integrations`.

== Changelog ==

= 1.4.4 =
* Fix: Fixed launcher FAB chat button styling glitch where `.tc-status-pulse` was sticking outside the corner radius and hovering awkwardly over the close 'X' button when active.
* Feature: Implemented proactive sales agent auto-talking and auto-initiation. The agent reaches out proactively after 3.5s or on scroll with an authentic sales greeting, live presence indicator, and interactive action chips ("🛕 4D Tour", "🚗 Cab Fares", "🕉️ VIP Darshan").
* Feature: Clicking any proactive action chip automatically opens the chat and dispatches the query to the live consultant, starting the conversation immediately.
* Feature: Upgraded AI agent persona and prompt engineering with the "TripCosmos Elite Human Sales Consultant & Customer Care Protocol" to behave like a genuine, high-touch Varanasi travel advisor (proactive discovery questions, transparent pricing, senior citizen darshan care, cab options, and seamless WhatsApp handoff).
* Fix: Added missing `maybe_upgrade()` and `upgrade_to_v144()` routines in `TC_Agents_Activator` to safely migrate existing databases to the updated sales greetings.

= 1.4.3 =
* Fix: Guaranteed all 4 canonical providers (AI Puffer, OpenRouter, OmniRoute Gateway, Google Gemini) are rendered in Failover Chain Priority (#1 to #4), preventing missing slots when database option was partially populated.
* Audit: Enhanced AI Puffer (AIPKit) bot discovery with additional REST endpoints (`/wp-json/wpaicg/v1/chatbots` and `/wp-json/aipkit/v1/chatbots`).
* Audit: Added `get_models()` method to `TC_Provider_AIPuffer` for full integration with background model catalogue sync.
* Audit: Expanded AI Puffer `chat()` response parser to handle all AI Power/AIPKit response variations including string `message`, `data.message`, and `result`.

= 1.4.2 =
* Fix: Hardened `TC_Agents_Vault` encryption and decryption with defensive Throwable error boundaries, preventing fatal `ValueError` tag exceptions on PHP 8+.
* Fix: Resolved undefined `$code` variable in Brevo API integration (`class-tc-integration-brevo.php`) causing all email dispatches to fail.
* Fix: Preserved WhatsApp webhook secret token on Integrations save, preventing accidental authentication credential wipes.
* Fix: Added missing Voice Telephony master toggle checkbox in Integrations view and handler.
* Fix: Restored AIPKit (AIPuffer) option in agent persona provider override dropdown and stopped unwanted rewrite to gateway.
* Fix: Added `is_active` persona toggle and delete action for custom agent personas.
* Fix: Masked GitHub personal access token in General Settings and guarded against empty overwrite.
* Fix: Enforced explicit form actions across all admin views and replaced `check_admin_referer` in `admin_notices` with `wp_verify_nonce`.
* Fix: Corrected cross-channel sequence failover logic and ensured drip WhatsApp dispatches are recorded against daily guardrails quota.
* Fix: Enforced margin ceiling protection on direct `discount_pct` tool arguments in `TC_Agents_Guardrails`.

= 1.4.1 =
* Fixed GitHub updater filesystem handling by explicitly requiring `file.php` and initializing `WP_Filesystem()`.
* Made GitHub updater `is_upgrade` flag static to guarantee proper directory renaming across parallel filter callbacks.
* Fixed Gemini multi-turn tool calling sequence by preserving tool execution turns and avoiding duplicate user-role merges.
* Normalized Gemini model names by stripping redundant `models/` prefixes to prevent 404 API errors.
* Added missing Twenty CRM Person ID persistence in contact leads table for bi-directional status updates.
* Fixed CORS pre-flight HTTP OPTIONS requests on public `/tc-agents/v1/chat` endpoint.
* Fixed Evolution API and WPAICG bot bridge payload compatibility by supplying `message`, `prompt`, and `messages`.
* Enforced complete bot silence on WhatsApp channel during human specialist takeovers.
* Added popular Delhi, Lucknow, and Bodhgaya circuit routes to cab distance matrix.
* Replaced all remaining legacy trek metadata tags with spiritual circuit and tour package branding.
* Fixed critical CRM background queue processor method mismatch errors.
* Fixed multi-turn LLM context poisoning on subsequent conversation turns.
* Cleaned up restored conversation history in REST API to filter raw JSON payloads.
* Fixed frontend quote form submit handler and eliminated duplicate listeners.
* Added audio visualizer animation lifecycle management to stop background CPU and battery drain.
* Fixed concurrent message streaming collisions and race conditions.
* Enhanced markdown parser with paragraph spacing and support for relative, tel:, and mailto: links.
* Added dedicated in-plugin GitHub update manager and status checker in General Settings.
* Cleaned up obsolete build files and hardened uninstaller.

= 1.3.0 =
* Added outstation cab fare calculation engine (TC_Cab_Fare_Engine).
* Added branded itinerary and PDF document generator (TC_Itinerary_Generator).
* Added live multilingual pilgrim language switcher (English, Hindi, Gujarati, Telugu).
* Added authenticated temple protocol and darshan timing knowledge tools.

= 1.2.0 =
* Consolidated AI Puffer / Omniroute / ai.vmstudio.digital into one canonical AI Gateway (old slugs kept as BC aliases with auto-migration).
* Added native Google Gemini provider with live catalogue sync.
* Added live model sync (OpenRouter + Gateway + Gemini) on Save, 2x-daily cron, REST endpoint.
* Added B2B Partner Hunter agent + Google Business/Places India import + Evolution API WhatsApp + Brevo outreach, FluentCRM/TwentyCRM logging.
* Added WhatsApp dual-mode (legacy + Evolution API) with shared inbound webhook.

= 1.1.0 =
* Added Semantic Vector Store & Hybrid RAG (`TC_Agent_Vector_Store`, `TC_Agent_Chunker`).
* Added 1-Click Catalog and Trek sync into knowledge base.
* Added Asynchronous Background Queue (`TC_Agents_Queue`) for sub-second chat latency.
* Added HTML5 Drag-and-Drop Kanban Deal Pipeline with AJAX stage saving.
* Added Interactive Trek Recommendation Cards & Carousel in public chat widget.
* Added 2 new specialist personas: Booking & Gear Concierge and Safety & Field Support Desk.
* Added automated voice telephony follow-up dispatch hook.

= 1.0.0 =
* Initial production release.
