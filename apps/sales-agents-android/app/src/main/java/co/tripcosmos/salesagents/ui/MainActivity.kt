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
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import co.tripcosmos.salesagents.telephony.PhoneStateReceiver
import co.tripcosmos.salesagents.ui.screens.*
import co.tripcosmos.salesagents.ui.theme.*

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
    ) { _ ->
        // Permissions granted
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
            Manifest.permission.READ_CALL_LOG,
            Manifest.permission.READ_CONTACTS
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
    var currentTab by remember { mutableStateOf(2) } // Default to 2 = Center WhatsApp Hub
    var showMayaPopup by remember { mutableStateOf(false) } // Popup Intelligent Chatbot state

    Scaffold(
        bottomBar = {
            NavigationBar(
                containerColor = LightSurface,
                tonalElevation = 8.dp
            ) {
                // Tab 0: Pipeline
                NavigationBarItem(
                    selected = currentTab == 0,
                    onClick = { currentTab = 0 },
                    icon = { Icon(Icons.Default.TrendingUp, contentDescription = "Pipeline") },
                    label = { Text("Pipeline", fontSize = 11.sp) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = SuperfoneBlue,
                        selectedTextColor = SuperfoneBlue,
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = SuperfoneBlueLight
                    )
                )

                // Tab 1: Tasks
                NavigationBarItem(
                    selected = currentTab == 1,
                    onClick = { currentTab = 1 },
                    icon = { Icon(Icons.Default.TaskAlt, contentDescription = "Tasks") },
                    label = { Text("Tasks", fontSize = 11.sp) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = SuperfoneBlue,
                        selectedTextColor = SuperfoneBlue,
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = SuperfoneBlueLight
                    )
                )

                // Tab 2: CENTER ELEVATED WHATSAPP HUB
                NavigationBarItem(
                    selected = currentTab == 2,
                    onClick = { currentTab = 2 },
                    icon = {
                        Box(
                            modifier = Modifier
                                .size(44.dp)
                                .clip(CircleShape)
                                .background(if (currentTab == 2) WhatsAppGreen else Color(0xFFDCFCE7)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                Icons.Default.Chat,
                                contentDescription = "WhatsApp Hub",
                                tint = if (currentTab == 2) Color.White else WhatsAppDark,
                                modifier = Modifier.size(24.dp)
                            )
                        }
                    },
                    label = {
                        Text(
                            "WhatsApp",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (currentTab == 2) WhatsAppDark else TextSecondary
                        )
                    },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = WhatsAppGreen,
                        selectedTextColor = WhatsAppDark,
                        indicatorColor = Color.Transparent
                    )
                )

                // Tab 3: Contacts Directory
                NavigationBarItem(
                    selected = currentTab == 3,
                    onClick = { currentTab = 3 },
                    icon = { Icon(Icons.Default.People, contentDescription = "Contacts") },
                    label = { Text("Contacts", fontSize = 11.sp) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = SuperfoneBlue,
                        selectedTextColor = SuperfoneBlue,
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = SuperfoneBlueLight
                    )
                )

                // Tab 4: Calls & AI Intelligence
                NavigationBarItem(
                    selected = currentTab == 4,
                    onClick = { currentTab = 4 },
                    icon = { Icon(Icons.Default.PhoneCallback, contentDescription = "Calls") },
                    label = { Text("Calls", fontSize = 11.sp) },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = SuperfoneBlue,
                        selectedTextColor = SuperfoneBlue,
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = SuperfoneBlueLight
                    )
                )
            }
        },
        floatingActionButton = {
            // Floating Popup Trigger for Master AI Chatbot (Maya AI)
            ExtendedFloatingActionButton(
                onClick = { showMayaPopup = true },
                containerColor = Color.Transparent,
                contentColor = Color.White,
                shape = RoundedCornerShape(24.dp),
                elevation = FloatingActionButtonDefaults.elevation(6.dp),
                modifier = Modifier
                    .clip(RoundedCornerShape(24.dp))
                    .background(Brush.linearGradient(listOf(AiGradientPink, AiGradientPurple)))
            ) {
                Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = Color.White, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(6.dp))
                Text("Maya AI", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = Color.White)
            }
        }
    ) { padding ->
        Surface(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            color = LightBackground
        ) {
            when (currentTab) {
                0 -> PipelineScreen()
                1 -> TasksScreen()
                2 -> WhatsAppHubScreen()
                3 -> ContactsScreen()
                4 -> CallHistoryScreen()
            }

            // Master AI Popup Chatbot (Opens on top of any screen!)
            if (showMayaPopup) {
                AiCopilotPopupDialog(onDismiss = { showMayaPopup = false })
            }

            // Post-Call Fast Action Dialog (triggers automatically when carrier SIM call ends)
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
