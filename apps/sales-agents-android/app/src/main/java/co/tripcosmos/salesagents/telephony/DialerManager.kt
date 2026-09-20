package co.tripcosmos.salesagents.telephony

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.widget.Toast

/**
 * Superfone-Grade Zero-Cost Telephony Engine.
 * Utilizes native Android Carrier SIM (Jio / Airtel unlimited voice calling)
 * to deliver 100% FREE outbound calling without per-minute cloud PBX charges.
 */
object DialerManager {

    /**
     * Place outbound voice call via device's native carrier SIM (100% Free PSTN).
     */
    fun dialViaCarrierSim(context: Context, rawPhone: String) {
        val cleanPhone = rawPhone.replace(Regex("[^0-9+]"), "")
        if (cleanPhone.isBlank()) {
            Toast.makeText(context, "Invalid phone number", Toast.LENGTH_SHORT).show()
            return
        }

        try {
            val intent = Intent(Intent.ACTION_CALL).apply {
                data = Uri.parse("tel:$cleanPhone")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
            }
            context.startActivity(intent)
        } catch (e: SecurityException) {
            // Fallback to ACTION_DIAL if CALL_PHONE permission not yet granted
            val dialIntent = Intent(Intent.ACTION_DIAL).apply {
                data = Uri.parse("tel:$cleanPhone")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
            }
            context.startActivity(dialIntent)
        } catch (e: Exception) {
            Toast.makeText(context, "Could not initiate call: ${e.message}", Toast.LENGTH_LONG).show()
        }
    }

    /**
     * Open 1-Tap WhatsApp chat with pre-filled message template.
     */
    fun openWhatsAppChat(context: Context, rawPhone: String, prefillMessage: String = "") {
        val digitsOnly = rawPhone.replace(Regex("[^0-9]"), "")
        val formattedPhone = if (digitsOnly.length == 10) "91$digitsOnly" else digitsOnly

        try {
            val url = "https://api.whatsapp.com/send?phone=$formattedPhone&text=" + Uri.encode(prefillMessage)
            val intent = Intent(Intent.ACTION_VIEW).apply {
                data = Uri.parse(url)
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
                setPackage("com.whatsapp")
            }
            context.startActivity(intent)
        } catch (e: Exception) {
            // Fallback to browser WhatsApp Web
            val browserIntent = Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$formattedPhone?text=" + Uri.encode(prefillMessage))).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
            }
            context.startActivity(browserIntent)
        }
    }
}
