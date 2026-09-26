package co.tripcosmos.salesagents.ui.screens

import co.tripcosmos.salesagents.AppConfig

import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.DirectionsCar
import androidx.compose.material.icons.filled.TempleHindu
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.CallLogPayload
import co.tripcosmos.salesagents.data.model.QuickActionPayload
import co.tripcosmos.salesagents.ui.theme.OrangePrimary
import co.tripcosmos.salesagents.ui.theme.WhatsAppGreen
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PostCallDialog(
    phone: String,
    durationSeconds: Long,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var notes by remember { mutableStateOf("") }
    var selectedStage by remember { mutableStateOf("qualified") }
    var isSubmitting by remember { mutableStateOf(false) }

    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val token = AppConfig.token()
    val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""

    fun sendQuickAction(actionType: String) {
        scope.launch {
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val res = api.triggerQuickAction(
                    token = token,
                    payload = QuickActionPayload(
                        contactId = 0, // Backend resolves from phone
                        actionType = actionType,
                        phone = phone
                    )
                )
                if (res.isSuccessful) {
                    Toast.makeText(context, "WhatsApp brochure dispatched successfully!", Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                Toast.makeText(context, "Quick action failed: ${e.message}", Toast.LENGTH_LONG).show()
            }
        }
    }

    fun saveCallSummary() {
        scope.launch {
            isSubmitting = true
            try {
                val api = TripCosmosApiService.create(baseUrl)
                api.logCall(
                    token = token,
                    payload = CallLogPayload(
                        phone = phone,
                        callType = "outgoing",
                        durationSeconds = durationSeconds,
                        notes = "Stage: $selectedStage • Notes: $notes"
                    )
                )
                Toast.makeText(context, "Call summary synced to TripCosmos CRM!", Toast.LENGTH_SHORT).show()
                onDismiss()
            } catch (e: Exception) {
                Toast.makeText(context, "Sync failed: ${e.message}", Toast.LENGTH_SHORT).show()
            } finally {
                isSubmitting = false
            }
        }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Column {
                Text("Post-Call Summary", fontWeight = FontWeight.Bold)
                Text(
                    text = "Call Duration: ${durationSeconds / 60}m ${durationSeconds % 60}s • $phone",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.Gray
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("1-Tap WhatsApp Fast Action:", fontWeight = FontWeight.SemiBold, fontSize = 13.sp)

                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(
                        onClick = { sendQuickAction("send_itinerary") },
                        colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                        modifier = Modifier.weight(1f),
                        contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.Description, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Itinerary", fontSize = 11.sp)
                    }

                    Button(
                        onClick = { sendQuickAction("send_tariff") },
                        colors = ButtonDefaults.buttonColors(containerColor = OrangePrimary),
                        modifier = Modifier.weight(1f),
                        contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.DirectionsCar, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Cab Tariff", fontSize = 11.sp)
                    }

                    Button(
                        onClick = { sendQuickAction("send_darshan_guide") },
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF6366F1)),
                        modifier = Modifier.weight(1f),
                        contentPadding = PaddingValues(horizontal = 4.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.TempleHindu, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Darshan", fontSize = 11.sp)
                    }
                }

                Spacer(modifier = Modifier.height(4.dp))

                OutlinedTextField(
                    value = notes,
                    onValueChange = { notes = it },
                    label = { Text("Call Notes / Custom Requirements") },
                    placeholder = { Text("e.g. 4 pax family, Innova Crysta needed, wants Ganga Aarti boat") },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3
                )
            }
        },
        confirmButton = {
            Button(
                onClick = { saveCallSummary() },
                colors = ButtonDefaults.buttonColors(containerColor = OrangePrimary),
                enabled = !isSubmitting
            ) {
                Text(if (isSubmitting) "Saving..." else "Save to CRM")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Skip")
            }
        },
        shape = RoundedCornerShape(16.dp)
    )
}
