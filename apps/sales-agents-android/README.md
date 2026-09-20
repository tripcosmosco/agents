# Sales Agents by tripCosmos.co 🚀📱
### Superfone-Style Intelligent CRM & Zero-Cost Telephony Companion for Android

An enterprise-grade, lightweight Android sales companion app tailored for **TripCosmos** travel concierges, cab dispatchers, and B2B ground-handling teams.

---

## 🌟 Why This Architecture is 100% FREE (Zero Per-Minute Bills)
Traditional cloud telephony systems (Superfone, Exotel, Twilio) charge ₹1.20 to ₹2.50 per minute for call forwarding, costing sales teams ₹5,000 to ₹15,000 every month.

**Our Smart Architecture:**
1. **Zero-Cost PSTN Calling:** Leverages standard Indian carrier SIM cards (Jio, Airtel, Vi) that offer **Unlimited Free Voice Calling** on standard monthly recharges.
2. **Native Telephony Bridge:** `DialerManager.dialViaCarrierSim()` dials directly via the agent's phone SIM with zero per-minute bills.
3. **1-Tap WhatsApp Team Integration:** Seamlessly opens WhatsApp with pre-filled quotations or triggers the TripCosmos **Evolution API** (`wa.vmstudio.digital`) backend for official white-label brochure dispatches.

---

## ⚡ Superfone-Grade Core Features

### 1. Smart Caller ID (Floating HUD Overlay)
- When a client calls, `PhoneStateReceiver` queries `GET /tc-agents/v1/mobile/caller-id?phone=...`.
- Displays a floating HUD over the incoming call screen containing:
  - 🛕 **Traveler Name & City** (e.g. *Rajesh Sharma, Mumbai*)
  - 💰 **Deal Value & Stage** (e.g. *₹24,500 • Qualified*)
  - 📍 **Destination** (e.g. *Kashi Vishwanath VIP Darshan + Ayodhya 4 Pax*)
  - 💡 **AI Talking Points** (*"Inquiring for 4-day tour. Recommend Innova Crysta over Dzire for luggage."*)
  - 🟢 **1-Tap WhatsApp Button**

### 2. Automatic Call Logging & Analytics
- Upon call termination (`EXTRA_STATE_IDLE`), the app calculates talk-time duration and automatically ingests it to the CRM (`POST /tc-agents/v1/mobile/call-log`).

### 3. Post-Call Fast Action Sheet
- The moment a call ends, an action modal pops up:
  - 📄 **Send Day-Wise Tour Itinerary PDF** via WhatsApp.
  - 🚗 **Send Cab Tariff Rate Sheet** (Dzire / Innova Crysta).
  - 🛕 **Send Sugam Darshan & Aarti Guidelines**.
  - 📝 **Log Custom Requirements / Notes** directly into Twenty CRM & FluentCRM.

### 4. Interactive Deal Pipeline (Kanban)
- Filter leads by stage (*Inquiry, Qualified, Proposal, Negotiation, Won, Lost*).
- One-tap Free SIM calling and WhatsApp chatting directly from lead cards.

---

## 🛠️ Project Structure
```
apps/sales-agents-android/
├── app/
│   ├── build.gradle.kts
│   └── src/main/
│       ├── AndroidManifest.xml
│       ├── java/co/tripcosmos/salesagents/
│       │   ├── SalesAgentsApp.kt
│       │   ├── data/
│       │   │   ├── api/TripCosmosApiService.kt
│       │   │   └── model/Models.kt
│       │   ├── telephony/
│       │   │   ├── CallerIdOverlayService.kt
│       │   │   ├── DialerManager.kt
│       │   │   └── PhoneStateReceiver.kt
│       │   └── ui/
│       │       ├── MainActivity.kt
│       │       ├── screens/
│       │       │   ├── PipelineScreen.kt
│       │       │   ├── PostCallDialog.kt
│       │       │   └── SettingsScreen.kt
│       │       └── theme/Theme.kt
│       └── res/
│           ├── values/colors.xml, strings.xml, themes.xml
│           └── xml/backup_rules.xml, data_extraction_rules.xml
├── build.gradle.kts
└── settings.gradle.kts
```

---

## 🚀 How to Build & Run

### In Android Studio:
1. Open Android Studio.
2. Select **Open** and choose `c:\Users\tripc\Local Sites\dev\app\public\wp-content\plugins\tripcosmos-agents\apps\sales-agents-android`.
3. Allow Gradle to sync dependencies.
4. Run on a connected Android phone or Android Virtual Device (AVD).

### Initial Configuration in App:
1. Open the app on the phone.
2. Tap **Settings**.
3. Confirm the WordPress API URL: `https://tripcosmos.co/wp-json/tc-agents/v1/`.
4. Enter your secret API token.
5. Tap **Grant Overlay Permission** to enable the Superfone-style floating caller HUD.
