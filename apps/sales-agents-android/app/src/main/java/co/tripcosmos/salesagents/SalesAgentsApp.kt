package co.tripcosmos.salesagents

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Context
import android.os.Build

class SalesAgentsApp : Application() {

    companion object {
        const val CALLER_ID_CHANNEL_ID = "tc_caller_id_channel"
        lateinit var instance: SalesAgentsApp
            private set
    }

    override fun onCreate() {
        super.onCreate()
        instance = this
        AppConfig.init(this)
        createNotificationChannels()
    }

    private fun createNotificationChannels() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val name = "TripCosmos Caller ID"
            val descriptionText = "Displays real-time lead and caller intelligence during incoming calls"
            val importance = NotificationManager.IMPORTANCE_LOW
            val channel = NotificationChannel(CALLER_ID_CHANNEL_ID, name, importance).apply {
                description = descriptionText
            }
            val notificationManager: NotificationManager =
                getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.createNotificationChannel(channel)
        }
    }
}
