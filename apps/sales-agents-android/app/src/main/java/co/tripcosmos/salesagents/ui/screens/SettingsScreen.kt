package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.ui.theme.OrangePrimary

import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import co.tripcosmos.salesagents.updater.GitHubReleaseInfo
import co.tripcosmos.salesagents.updater.GitHubUpdateManager
import co.tripcosmos.salesagents.ui.theme.SuperfoneBlue
import co.tripcosmos.salesagents.ui.theme.SuperfoneBlueLight
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    onClose: (() -> Unit)? = null
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)

    var baseUrl by remember { mutableStateOf(prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: "") }
    var apiToken by remember { mutableStateOf(prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: "") }
    var agentName by remember { mutableStateOf(prefs.getString("agent_name", "Varanasi Concierge Desk") ?: "") }
    var maskPhoneNumbers by remember { mutableStateOf(prefs.getBoolean("mask_phone_numbers", false)) }

    var isCheckingUpdate by remember { mutableStateOf(false) }
    var releaseInfo by remember { mutableStateOf<GitHubReleaseInfo?>(null) }

    var hasOverlayPermission by remember {
        mutableStateOf(
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) Settings.canDrawOverlays(context) else true
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Terminal Settings", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    if (onClose != null) {
                        IconButton(onClick = onClose) {
                            Icon(Icons.Default.Close, contentDescription = "Close Settings")
                        }
                    }
                }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // GitHub OTA Auto-Updater Card
            Card(
                colors = CardDefaults.cardColors(containerColor = SuperfoneBlueLight),
                shape = RoundedCornerShape(16.dp),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text("GitHub OTA Auto-Updater", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = SuperfoneBlue)
                            Text("Version: ${GitHubUpdateManager.CURRENT_VERSION} • github.com/${GitHubUpdateManager.GITHUB_REPO}", fontSize = 11.sp, color = Color.DarkGray)
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    releaseInfo?.let { release ->
                        Card(
                            colors = CardDefaults.cardColors(containerColor = Color.White),
                            shape = RoundedCornerShape(10.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(modifier = Modifier.padding(10.dp)) {
                                Text(
                                    if (release.isNewer) "🎉 Newer Version Available: ${release.tagName}" else "✓ App is on Latest Version (${release.tagName})",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 12.sp,
                                    color = if (release.isNewer) OrangePrimary else Color(0xFF16A34A)
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(release.body, fontSize = 11.sp, color = Color.Gray, maxLines = 4)
                            }
                        }
                        Spacer(modifier = Modifier.height(10.dp))
                    }

                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        OutlinedButton(
                            onClick = {
                                coroutineScope.launch {
                                    isCheckingUpdate = true
                                    val res = GitHubUpdateManager.checkLatestRelease()
                                    releaseInfo = res
                                    isCheckingUpdate = false
                                    if (res != null && res.isNewer) {
                                        Toast.makeText(context, "New version ${res.tagName} found!", Toast.LENGTH_SHORT).show()
                                    } else {
                                        Toast.makeText(context, "App is up to date!", Toast.LENGTH_SHORT).show()
                                    }
                                }
                            },
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            if (isCheckingUpdate) {
                                CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                            } else {
                                Icon(Icons.Default.Refresh, contentDescription = null, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Check GitHub", fontSize = 12.sp)
                            }
                        }

                        Button(
                            onClick = {
                                val url = releaseInfo?.downloadUrl ?: GitHubUpdateManager.FALLBACK_DOWNLOAD_URL
                                GitHubUpdateManager.openDownloadUrl(context, url)
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue),
                            modifier = Modifier.weight(1.2f),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Icon(Icons.Default.CloudDownload, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(4.dp))
                            Text("Download APK", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            Text("TripCosmos Backend Connection", fontWeight = FontWeight.Bold, fontSize = 16.sp)

            OutlinedTextField(
                value = baseUrl,
                onValueChange = { baseUrl = it },
                label = { Text("WordPress REST API URL") },
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = apiToken,
                onValueChange = { apiToken = it },
                label = { Text("Mobile API Secret Token") },
                modifier = Modifier.fillMaxWidth()
            )

            OutlinedTextField(
                value = agentName,
                onValueChange = { agentName = it },
                label = { Text("Sales Agent Name / Terminal") },
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(modifier = Modifier.height(8.dp))

            Text("Tripcosmos Intelligence Permissions", fontWeight = FontWeight.Bold, fontSize = 16.sp)

            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text("Floating Smart Caller ID", fontWeight = FontWeight.Bold)
                    Text(
                        "Allows displaying the traveler's CRM profile over incoming phone calls.",
                        fontSize = 13.sp,
                        color = Color.Gray
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    Button(
                        onClick = {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                                val intent = Intent(
                                    Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                                    Uri.parse("package:${context.packageName}")
                                )
                                context.startActivity(intent)
                            }
                        },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (hasOverlayPermission) Color(0xFF10B981) else OrangePrimary
                        )
                    ) {
                        Text(if (hasOverlayPermission) "Overlay Permission Granted ✓" else "Grant Overlay Permission")
                    }
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Text("Security & Anti-Poaching Protection", fontWeight = FontWeight.Bold, fontSize = 16.sp)

            Card(
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                modifier = Modifier.fillMaxWidth()
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("Mask Traveler Phone Numbers", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                            Text(
                                "Protects customer contacts by displaying +91 98390 •••••. 1-tap SIM calling and WhatsApp remain fully functional.",
                                fontSize = 12.sp,
                                color = Color.DarkGray
                            )
                        }
                        Spacer(modifier = Modifier.width(8.dp))
                        Switch(
                            checked = maskPhoneNumbers,
                            onCheckedChange = { maskPhoneNumbers = it },
                            colors = SwitchDefaults.colors(checkedTrackColor = OrangePrimary)
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Button(
                onClick = {
                    prefs.edit()
                        .putString("base_url", baseUrl.trim())
                        .putString("mobile_api_token", apiToken.trim())
                        .putString("agent_name", agentName.trim())
                        .putBoolean("mask_phone_numbers", maskPhoneNumbers)
                        .apply()
                    Toast.makeText(context, "Settings saved successfully!", Toast.LENGTH_SHORT).show()
                },
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(containerColor = OrangePrimary)
            ) {
                Text("Save Configuration")
            }
        }
    }
}

