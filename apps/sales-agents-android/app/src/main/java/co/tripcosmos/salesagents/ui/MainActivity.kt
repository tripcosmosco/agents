package co.tripcosmos.salesagents.ui

import android.Manifest
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.core.content.ContextCompat
import co.tripcosmos.salesagents.telephony.PhoneStateReceiver
import co.tripcosmos.salesagents.ui.screens.PipelineScreen
import co.tripcosmos.salesagents.ui.screens.PostCallDialog
import co.tripcosmos.salesagents.ui.screens.SettingsScreen
import co.tripcosmos.salesagents.ui.theme.OrangePrimary
import co.tripcosmos.salesagents.ui.theme.SalesAgentsTheme

class MainActivity : ComponentActivity() {

    private var activeCallEndedPhone by mutableStateOf<String?>(null)
    private var activeCallDuration by mutableStateOf(0L)

    private val callEndedReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            if (intent?.action == PhoneStateReceiver.ACTION_CALL_ENDED_PROMPT) {
                val phone = intent.getStringExtra(PhoneStateReceiver.EXTRA_CALL_PHONE) ?: ""
                val duration = intent.getLongExtra(PhoneStateReceiver.EXTRA_CALL_DURATION, 0L)
                if (phone.isNotBlank()) {
                    activeCallEndedPhone = phone
                    activeCallDuration = duration
                }
            }
        }
    }

    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        // Handle permissions
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        requestEssentialPermissions()

        val filter = IntentFilter(PhoneStateReceiver.ACTION_CALL_ENDED_PROMPT)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            registerReceiver(callEndedReceiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            registerReceiver(callEndedReceiver, filter)
        }

        setContent {
            SalesAgentsTheme {
                MainAppContainer(
                    callEndedPhone = activeCallEndedPhone,
                    callDuration = activeCallDuration,
                    onDismissPostCall = {
                        activeCallEndedPhone = null
                        activeCallDuration = 0L
                    }
                )
            }
        }
    }

    private fun requestEssentialPermissions() {
        val permissions = mutableListOf(
            Manifest.permission.CALL_PHONE,
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.READ_CALL_LOG
        )
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            permissions.add(Manifest.permission.POST_NOTIFICATIONS)
        }

        val needed = permissions.filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }

        if (needed.isNotEmpty()) {
            permissionLauncher.launch(needed.toTypedArray())
        }
    }

    override fun onDestroy() {
        try {
            unregisterReceiver(callEndedReceiver)
        } catch (e: Exception) {
            // Receiver not registered
        }
        super.onDestroy()
    }
}

@Composable
fun MainAppContainer(
    callEndedPhone: String?,
    callDuration: Long,
    onDismissPostCall: () -> Unit
) {
    var currentTab by remember { mutableStateOf(0) } // 0 = Pipeline, 1 = Contacts, 2 = Calls, 3 = Settings

    Scaffold(
        bottomBar = {
            NavigationBar(containerColor = MaterialTheme.colorScheme.surface) {
                NavigationBarItem(
                    selected = currentTab == 0,
                    onClick = { currentTab = 0 },
                    icon = { Icon(Icons.Default.List, contentDescription = "Pipeline") },
                    label = { Text("Pipeline") },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = OrangePrimary,
                        selectedTextColor = OrangePrimary,
                        indicatorColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                )

                NavigationBarItem(
                    selected = currentTab == 1,
                    onClick = { currentTab = 1 },
                    icon = { Icon(Icons.Default.Person, contentDescription = "Contacts") },
                    label = { Text("Contacts") },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = OrangePrimary,
                        selectedTextColor = OrangePrimary,
                        indicatorColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                )

                NavigationBarItem(
                    selected = currentTab == 2,
                    onClick = { currentTab = 2 },
                    icon = { Icon(Icons.Default.Call, contentDescription = "Call History") },
                    label = { Text("Calls") },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = OrangePrimary,
                        selectedTextColor = OrangePrimary,
                        indicatorColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                )

                NavigationBarItem(
                    selected = currentTab == 3,
                    onClick = { currentTab = 3 },
                    icon = { Icon(Icons.Default.Settings, contentDescription = "Settings") },
                    label = { Text("Settings") },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = OrangePrimary,
                        selectedTextColor = OrangePrimary,
                        indicatorColor = MaterialTheme.colorScheme.surfaceVariant
                    )
                )
            }
        }
    ) { padding ->
        Surface(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            when (currentTab) {
                0 -> PipelineScreen(onLeadSelected = { /* open detail */ })
                1 -> co.tripcosmos.salesagents.ui.screens.ContactsScreen()
                2 -> co.tripcosmos.salesagents.ui.screens.CallHistoryScreen()
                3 -> SettingsScreen()
            }

            // Post-Call Fast Action Dialog (triggers automatically when call terminates)
            if (!callEndedPhone.isNullOrBlank()) {
                PostCallDialog(
                    phone = callEndedPhone,
                    durationSeconds = callDuration,
                    onDismiss = onDismissPostCall
                )
            }
        }
    }
}
