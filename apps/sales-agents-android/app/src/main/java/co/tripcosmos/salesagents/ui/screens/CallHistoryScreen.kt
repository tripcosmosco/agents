package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.CallMade
import androidx.compose.material.icons.filled.CallMissed
import androidx.compose.material.icons.filled.CallReceived
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.db.AppDatabase
import co.tripcosmos.salesagents.data.db.CallLogEntity
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.OrangePrimary
import co.tripcosmos.salesagents.ui.theme.WhatsAppGreen
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CallHistoryScreen() {
    val context = LocalContext.current
    val db = remember { AppDatabase.getDatabase(context) }
    val callLogs by db.callLogDao().getRecentCalls().collectAsState(initial = emptyList())

    val dateFormat = remember { SimpleDateFormat("dd MMM, hh:mm a", Locale.getDefault()) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Call Logs & Telephony History", fontWeight = FontWeight.Bold) }
            )
        }
    ) { padding ->
        if (callLogs.isEmpty()) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("No calls recorded yet.", color = Color.Gray, fontSize = 16.sp)
                    Spacer(modifier = Modifier.height(4.dp))
                    Text("Outbound calls using Free SIM will appear here.", color = Color.LightGray, fontSize = 13.sp)
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(callLogs) { log ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                                when (log.callType) {
                                    "incoming" -> Icon(Icons.Default.CallReceived, contentDescription = null, tint = WhatsAppGreen)
                                    "missed" -> Icon(Icons.Default.CallMissed, contentDescription = null, tint = Color.Red)
                                    else -> Icon(Icons.Default.CallMade, contentDescription = null, tint = OrangePrimary)
                                }

                                Spacer(modifier = Modifier.width(12.dp))

                                Column {
                                    Text(
                                        text = log.contactName ?: log.phone,
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 15.sp
                                    )
                                    Text(
                                        text = "${dateFormat.format(Date(log.timestamp))} • ${log.durationSeconds / 60}m ${log.durationSeconds % 60}s",
                                        fontSize = 12.sp,
                                        color = Color.Gray
                                    )
                                    if (log.notes.isNotBlank()) {
                                        Text(
                                            text = log.notes,
                                            fontSize = 12.sp,
                                            color = Color.DarkGray
                                        )
                                    }
                                }
                            }

                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                IconButton(onClick = { DialerManager.dialViaCarrierSim(context, log.phone) }) {
                                    Icon(Icons.Default.Call, contentDescription = "Redial", tint = OrangePrimary)
                                }
                                IconButton(onClick = { DialerManager.openWhatsAppChat(context, log.phone) }) {
                                    Icon(Icons.Default.Chat, contentDescription = "WhatsApp", tint = WhatsAppGreen)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
