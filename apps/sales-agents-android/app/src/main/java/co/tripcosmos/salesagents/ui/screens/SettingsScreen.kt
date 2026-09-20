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
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.ui.theme.OrangePrimary

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen() {
    val context = LocalContext.current
    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)

    var baseUrl by remember { mutableStateOf(prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: "") }
    var apiToken by remember { mutableStateOf(prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: "") }
    var agentName by remember { mutableStateOf(prefs.getString("agent_name", "Varanasi Concierge Desk") ?: "") }

    var hasOverlayPermission by remember {
        mutableStateOf(
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) Settings.canDrawOverlays(context) else true
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("App Settings", fontWeight = FontWeight.Bold) }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
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

            Text("Superfone Intelligence Permissions", fontWeight = FontWeight.Bold, fontSize = 16.sp)

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

            Spacer(modifier = Modifier.weight(1f))

            Button(
                onClick = {
                    prefs.edit()
                        .putString("base_url", baseUrl.trim())
                        .putString("mobile_api_token", apiToken.trim())
                        .putString("agent_name", agentName.trim())
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
