package co.tripcosmos.salesagents.telephony

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.telephony.TelephonyManager
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.CallLogPayload
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * BroadcastReceiver monitoring incoming & outgoing carrier calls.
 * Triggers Superfone-style live Caller ID and auto-syncs call logs to TripCosmos CRM.
 */
class PhoneStateReceiver : BroadcastReceiver() {

    companion object {
        private var lastState = TelephonyManager.EXTRA_STATE_IDLE
        private var callStartTime: Long = 0
        private var savedNumber: String = ""
        private var isIncoming: Boolean = false

        const val ACTION_CALL_ENDED_PROMPT = "co.tripcosmos.ACTION_CALL_ENDED_PROMPT"
        const val EXTRA_CALL_PHONE = "extra_call_phone"
        const val EXTRA_CALL_DURATION = "extra_call_duration"
    }

    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != TelephonyManager.ACTION_PHONE_STATE_CHANGED) return

        val stateStr = intent.getStringExtra(TelephonyManager.EXTRA_STATE) ?: return
        val number = intent.getStringExtra(TelephonyManager.EXTRA_INCOMING_NUMBER) ?: ""

        if (number.isNotBlank()) {
            savedNumber = number
        }

        when (stateStr) {
            TelephonyManager.EXTRA_STATE_RINGING -> {
                isIncoming = true
                lastState = TelephonyManager.EXTRA_STATE_RINGING

                if (savedNumber.isNotBlank()) {
                    lookupAndShowCallerId(context, savedNumber)
                }
            }

            TelephonyManager.EXTRA_STATE_OFFHOOK -> {
                if (lastState == TelephonyManager.EXTRA_STATE_RINGING) {
                    isIncoming = true
                } else {
                    isIncoming = false
                }
                callStartTime = System.currentTimeMillis()
                lastState = TelephonyManager.EXTRA_STATE_OFFHOOK
            }

            TelephonyManager.EXTRA_STATE_IDLE -> {
                if (lastState == TelephonyManager.EXTRA_STATE_OFFHOOK) {
                    // Call connected and completed
                    val durationSeconds = if (callStartTime > 0) {
                        (System.currentTimeMillis() - callStartTime) / 1000
                    } else 0L

                    handleCallCompleted(context, savedNumber, isIncoming, durationSeconds)
                } else if (lastState == TelephonyManager.EXTRA_STATE_RINGING) {
                    // Missed call
                    handleCallCompleted(context, savedNumber, isIncoming = true, durationSeconds = 0L)
                }

                // Dismiss caller ID overlay
                CallerIdOverlayService.hideOverlay(context)
                lastState = TelephonyManager.EXTRA_STATE_IDLE
                callStartTime = 0
            }
        }
    }

    private fun lookupAndShowCallerId(context: Context, phone: String) {
        val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
        val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
        val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val response = api.getCallerId(phone, token)
                if (response.isSuccessful && response.body()?.found == true) {
                    val contact = response.body()!!.contact!!
                    CallerIdOverlayService.showOverlay(
                        context = context,
                        name = contact.name,
                        phone = contact.phone,
                        dealValue = contact.dealValue,
                        stage = contact.stage,
                        destination = contact.destination ?: "Varanasi & Ayodhya",
                        aiSummary = contact.aiSummary ?: "Active pilgrimage inquiry",
                        nextAction = contact.nextBestAction ?: "Confirm travel dates and vehicle selection"
                    )
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    private fun handleCallCompleted(context: Context, phone: String, isIncoming: Boolean, durationSeconds: Long) {
        if (phone.isBlank()) return

        val callType = if (durationSeconds == 0L && isIncoming) "missed" else if (isIncoming) "incoming" else "outgoing"

        val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
        val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
        val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""

        // 1. Ingest call log to WordPress / Twenty CRM backend silently
        CoroutineScope(Dispatchers.IO).launch {
            try {
                val api = TripCosmosApiService.create(baseUrl)
                api.logCall(
                    token = token,
                    payload = CallLogPayload(
                        phone = phone,
                        callType = callType,
                        durationSeconds = durationSeconds,
                        notes = "Logged automatically via Sales Agents Android App (Native Carrier SIM)"
                    )
                )
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }

        // 2. Broadcast call ended so UI can show the Post-Call Fast Action Sheet
        val promptIntent = Intent(ACTION_CALL_ENDED_PROMPT).apply {
            putExtra(EXTRA_CALL_PHONE, phone)
            putExtra(EXTRA_CALL_DURATION, durationSeconds)
            setPackage(context.packageName)
        }
        context.sendBroadcast(promptIntent)
    }
}
