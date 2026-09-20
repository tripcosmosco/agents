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
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.ContextCompat
import co.tripcosmos.salesagents.data.model.Lead
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

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainAppContainer(
    callEndedPhone: String?,
    callDuration: Long,
    onDismissPostCall: () -> Unit
) {
    var currentTab by remember { mutableStateOf(0) } // 0 = Radar, 1 = Quoter, 2 = Dispatch
    var showMayaPopup by remember { mutableStateOf(false) }
    var showSettings by remember { mutableStateOf(false) }
    var prefilledLead by remember { mutableStateOf<Lead?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                "TripCosmos",
                                fontWeight = FontWeight.Black,
                                fontSize = 18.sp,
                                color = SuperfoneBlue
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Surface(
                                shape = RoundedCornerShape(4.dp),
                                color = SuperfoneBlueLight
                            ) {
                                Text(
                                    "2.0",
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = SuperfoneBlue,
                                    modifier = Modifier.padding(horizontal = 5.dp, vertical = 2.dp)
                                )
                            }
                        }
                        Text(
                            "Autonomous Pilgrimage Terminal",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium,
                            color = TextSecondary
                        )
                    }
                },
                actions = {
                    // Maya AI Header Action Pill
                    Surface(
                        onClick = { showMayaPopup = true },
                        shape = RoundedCornerShape(20.dp),
                        color = Color(0xFFF3E8FF),
                        border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFD8B4FE)),
                        modifier = Modifier.padding(end = 4.dp)
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp)
                        ) {
                            Icon(
                                Icons.Default.AutoAwesome,
                                contentDescription = "Maya AI",
                                tint = Color(0xFF9333EA),
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                "Maya AI",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color(0xFF7E22CE)
                            )
                        }
                    }

                    // Settings Button
                    IconButton(onClick = { showSettings = true }) {
                        Icon(
                            Icons.Default.Settings,
                            contentDescription = "Settings",
                            tint = Color(0xFF475569)
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color.White
                )
            )
        },
        bottomBar = {
            NavigationBar(
                containerColor = Color.White,
                tonalElevation = 8.dp
            ) {
                // Tab 0: Radar (Leads & Live Inquiries)
                NavigationBarItem(
                    selected = currentTab == 0,
                    onClick = { currentTab = 0 },
                    icon = { Icon(Icons.Default.Bolt, contentDescription = "Radar") },
                    label = {
                        Text(
                            "Radar",
                            fontSize = 11.sp,
                            fontWeight = if (currentTab == 0) FontWeight.Bold else FontWeight.Normal
                        )
                    },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = SuperfoneBlue,
                        selectedTextColor = SuperfoneBlue,
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = SuperfoneBlueLight
                    )
                )

                // Tab 1: Quoter (Pilgrimage Fares & Margins)
                NavigationBarItem(
                    selected = currentTab == 1,
                    onClick = { currentTab = 1 },
                    icon = { Icon(Icons.Default.Calculate, contentDescription = "Quoter") },
                    label = {
                        Text(
                            "Quoter",
                            fontSize = 11.sp,
                            fontWeight = if (currentTab == 1) FontWeight.Bold else FontWeight.Normal
                        )
                    },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = OrangePrimary,
                        selectedTextColor = OrangePrimary,
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = OrangeLight
                    )
                )

                // Tab 2: Dispatch (Token Confirmed Fleet Dispatch)
                NavigationBarItem(
                    selected = currentTab == 2,
                    onClick = { currentTab = 2 },
                    icon = { Icon(Icons.Default.DirectionsCar, contentDescription = "Dispatch") },
                    label = {
                        Text(
                            "Dispatch",
                            fontSize = 11.sp,
                            fontWeight = if (currentTab == 2) FontWeight.Bold else FontWeight.Normal
                        )
                    },
                    colors = NavigationBarItemDefaults.colors(
                        selectedIconColor = Color(0xFF059669),
                        selectedTextColor = Color(0xFF059669),
                        unselectedIconColor = TextSecondary,
                        unselectedTextColor = TextSecondary,
                        indicatorColor = Color(0xFFD1FAE5)
                    )
                )
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
                0 -> RadarScreen(
                    onQuoteLead = { lead ->
                        prefilledLead = lead
                        currentTab = 1
                    }
                )
                1 -> {
                    key(prefilledLead?.id) {
                        QuoterScreen(
                            initialTravelerName = prefilledLead?.name ?: "",
                            initialPhone = prefilledLead?.phone ?: "",
                            initialDestination = prefilledLead?.destination ?: "varanasi_3d2n"
                        )
                    }
                }
                2 -> DispatchScreen()
            }

            // Master AI Popup Dialog
            if (showMayaPopup) {
                AiCopilotPopupDialog(onDismiss = { showMayaPopup = false })
            }

            // Fullscreen Settings Modal
            if (showSettings) {
                Dialog(
                    onDismissRequest = { showSettings = false },
                    properties = DialogProperties(usePlatformDefaultWidth = false)
                ) {
                    Surface(modifier = Modifier.fillMaxSize()) {
                        SettingsScreen(onClose = { showSettings = false })
                    }
                }
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
