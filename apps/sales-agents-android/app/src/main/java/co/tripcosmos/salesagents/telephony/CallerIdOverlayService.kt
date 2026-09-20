package co.tripcosmos.salesagents.telephony

import android.app.Notification
import android.app.Service
import android.content.Context
import android.content.Intent
import android.graphics.PixelFormat
import android.os.Build
import android.os.IBinder
import android.view.Gravity
import android.view.LayoutInflater
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.ImageView
import android.widget.TextView
import androidx.core.app.NotificationCompat
import co.tripcosmos.salesagents.R
import co.tripcosmos.salesagents.SalesAgentsApp

/**
 * Superfone-style Floating Smart Caller ID Overlay Service.
 * Displays real-time traveler CRM dossier over the incoming call screen.
 */
class CallerIdOverlayService : Service() {

    private var windowManager: WindowManager? = null
    private var overlayView: View? = null

    companion object {
        const val ACTION_SHOW_OVERLAY = "co.tripcosmos.SHOW_OVERLAY"
        const val ACTION_HIDE_OVERLAY = "co.tripcosmos.HIDE_OVERLAY"

        const val EXTRA_NAME = "extra_name"
        const val EXTRA_PHONE = "extra_phone"
        const val EXTRA_DEAL_VALUE = "extra_deal_value"
        const val EXTRA_STAGE = "extra_stage"
        const val EXTRA_DESTINATION = "extra_destination"
        const val EXTRA_AI_SUMMARY = "extra_ai_summary"
        const val EXTRA_NEXT_ACTION = "extra_next_action"

        fun showOverlay(
            context: Context,
            name: String,
            phone: String,
            dealValue: Double,
            stage: String,
            destination: String,
            aiSummary: String,
            nextAction: String
        ) {
            val intent = Intent(context, CallerIdOverlayService::class.java).apply {
                action = ACTION_SHOW_OVERLAY
                putExtra(EXTRA_NAME, name)
                putExtra(EXTRA_PHONE, phone)
                putExtra(EXTRA_DEAL_VALUE, dealValue)
                putExtra(EXTRA_STAGE, stage)
                putExtra(EXTRA_DESTINATION, destination)
                putExtra(EXTRA_AI_SUMMARY, aiSummary)
                putExtra(EXTRA_NEXT_ACTION, nextAction)
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent)
            } else {
                context.startService(intent)
            }
        }

        fun hideOverlay(context: Context) {
            val intent = Intent(context, CallerIdOverlayService::class.java).apply {
                action = ACTION_HIDE_OVERLAY
            }
            context.startService(intent)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_SHOW_OVERLAY -> {
                startForegroundNotification()
                renderOverlay(intent)
            }
            ACTION_HIDE_OVERLAY -> {
                removeOverlay()
                stopSelf()
            }
        }
        return START_NOT_STICKY
    }

    private fun startForegroundNotification() {
        val notification: Notification = NotificationCompat.Builder(this, SalesAgentsApp.CALLER_ID_CHANNEL_ID)
            .setContentTitle("TripCosmos Caller ID Active")
            .setContentText("Displaying live traveler intelligence")
            .setSmallIcon(android.R.drawable.sym_call_incoming)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()

        startForeground(1001, notification)
    }

    private fun renderOverlay(intent: Intent) {
        if (overlayView != null) return

        val name = intent.getStringExtra(EXTRA_NAME) ?: "Traveler"
        val phone = intent.getStringExtra(EXTRA_PHONE) ?: ""
        val dealValue = intent.getDoubleExtra(EXTRA_DEAL_VALUE, 0.0)
        val stage = intent.getStringExtra(EXTRA_STAGE) ?: "inquiry"
        val destination = intent.getStringExtra(EXTRA_DESTINATION) ?: "Varanasi Spiritual Circuit"
        val aiSummary = intent.getStringExtra(EXTRA_AI_SUMMARY) ?: "Inquiring about tour package and AC cabs."
        val nextAction = intent.getStringExtra(EXTRA_NEXT_ACTION) ?: "Qualify dates and group size."

        windowManager = getSystemService(Context.WINDOW_SERVICE) as WindowManager

        val layoutType = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
        } else {
            @Suppress("DEPRECATION")
            WindowManager.LayoutParams.TYPE_PHONE
        }

        val params = WindowManager.LayoutParams(
            WindowManager.LayoutParams.MATCH_PARENT,
            WindowManager.LayoutParams.WRAP_CONTENT,
            layoutType,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED,
            PixelFormat.TRANSLUCENT
        ).apply {
            gravity = Gravity.TOP or Gravity.CENTER_HORIZONTAL
            y = 120
        }

        // Programmatic luxury Superfone HUD view
        val cardView = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            setBackgroundColor(android.graphics.Color.parseColor("#1E1B4B")) // Royal dark indigo
            setPadding(36, 32, 36, 32)
            elevation = 24f
        }

        val titleRow = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.HORIZONTAL
        }

        val nameText = TextView(this).apply {
            text = "🛕 $name"
            setTextColor(android.graphics.Color.WHITE)
            textSize = 18f
            setTypeface(null, android.graphics.Typeface.BOLD)
            layoutParams = android.widget.LinearLayout.LayoutParams(0, android.widget.LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
        }

        val closeBtn = TextView(this).apply {
            text = "✕"
            setTextColor(android.graphics.Color.LTGRAY)
            textSize = 20f
            setPadding(12, 0, 12, 0)
            setOnClickListener {
                removeOverlay()
                stopSelf()
            }
        }
        titleRow.addView(nameText)
        titleRow.addView(closeBtn)
        cardView.addView(titleRow)

        val metaText = TextView(this).apply {
            text = "Stage: ${stage.uppercase()} • Deal: ₹${dealValue.toInt()} • $destination"
            setTextColor(android.graphics.Color.parseColor("#F97316")) // Orange
            textSize = 13f
            setTypeface(null, android.graphics.Typeface.BOLD)
            setPadding(0, 8, 0, 8)
        }
        cardView.addView(metaText)

        val aiTipText = TextView(this).apply {
            text = "💡 AI Tip: $nextAction"
            setTextColor(android.graphics.Color.parseColor("#E0E7FF"))
            textSize = 13f
            setPadding(0, 4, 0, 16)
        }
        cardView.addView(aiTipText)

        val waBtn = Button(this).apply {
            text = "Open WhatsApp Chat"
            setBackgroundColor(android.graphics.Color.parseColor("#25D366"))
            setTextColor(android.graphics.Color.WHITE)
            setOnClickListener {
                DialerManager.openWhatsAppChat(context, phone, "Namaste $name ji! Thank you for calling TripCosmos.")
                removeOverlay()
                stopSelf()
            }
        }
        cardView.addView(waBtn)

        overlayView = cardView

        try {
            windowManager?.addView(overlayView, params)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun removeOverlay() {
        if (overlayView != null) {
            try {
                windowManager?.removeView(overlayView)
            } catch (e: Exception) {
                e.printStackTrace()
            }
            overlayView = null
        }
    }

    override fun onDestroy() {
        removeOverlay()
        super.onDestroy()
    }
}
