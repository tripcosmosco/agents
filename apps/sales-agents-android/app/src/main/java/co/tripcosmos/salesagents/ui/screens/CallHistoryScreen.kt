package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.db.AppDatabase
import co.tripcosmos.salesagents.data.model.AiCallSummary
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CallHistoryScreen() {
    val context = LocalContext.current
    val db = remember { AppDatabase.getDatabase(context) }
    val callLogs by db.callLogDao().getRecentCalls().collectAsState(initial = emptyList())
    var selectedTab by remember { mutableStateOf(0) } // 0 = AI Call Summaries, 1 = Raw Carrier Call Logs

    val dateFormat = remember { SimpleDateFormat("dd MMM, hh:mm a", Locale.getDefault()) }

    // Superfone-style AI Call Summaries (Matching Screenshot 2 & 4)
    val aiSummaries = remember {
        listOf(
            AiCallSummary(
                customerName = "Arvind Sharma",
                phone = "+919848826512",
                callTime = "Today, 11:42 AM",
                duration = "3m 45s",
                destination = "Varanasi & Ayodhya Spiritual Tour",
                travelers = "4 adults",
                dates = "Mid-next week, 3D2N",
                budget = "₹20,000 - ₹25,000",
                tags = listOf("Spiritual", "Ayodhya Cab", "VIP Darshan"),
                reminder = "Tomorrow, 5:00 PM • Follow up on hotel selection",
                nextAction = "Send customized package PDF with Kashi Vishwanath VIP Darshan inclusions."
            ),
            AiCallSummary(
                customerName = "Vikram Singh",
                phone = "+919898989898",
                callTime = "Today, 09:15 AM",
                duration = "5m 12s",
                destination = "Kashi Darshan + Prayagraj Sangam Snan",
                travelers = "2 senior citizens + 1 adult",
                dates = "Coming weekend",
                budget = "₹15,000",
                tags = listOf("Senior Citizen", "Boat Ride", "AC Sedan"),
                reminder = "Today, 6:00 PM • Confirm wheelchair & battery car request",
                nextAction = "Call back to confirm hotel with lift near Godowlia."
            ),
            AiCallSummary(
                customerName = "Neha Mani",
                phone = "+917289026009",
                callTime = "Yesterday, 04:30 PM",
                duration = "2m 10s",
                destination = "Outstation Cab to Bodhgaya & Ayodhya",
                travelers = "6 pax (Innova Crysta)",
                dates = "Next month first week",
                budget = "₹32,000",
                tags = listOf("Cab Only", "Innova Crysta", "Bodhgaya"),
                reminder = "In 2 days • Follow up on flight booking status",
                nextAction = "Send Outstation cab rate chart via WhatsApp."
            )
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("AI Call Intelligence", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = TextPrimary)
                        Text("Superfone Automated Call Summaries & Reminders", fontSize = 12.sp, color = TextSecondary)
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = LightSurface)
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .background(LightBackground)
                .padding(padding)
        ) {
            // Tab Selector: AI Call Summaries vs Raw Carrier Call Logs
            TabRow(
                selectedTabIndex = selectedTab,
                containerColor = LightSurface,
                contentColor = SuperfoneBlue
            ) {
                Tab(
                    selected = selectedTab == 0,
                    onClick = { selectedTab = 0 },
                    text = { Text("AI Summaries (${aiSummaries.size})", fontWeight = FontWeight.Bold) }
                )
                Tab(
                    selected = selectedTab == 1,
                    onClick = { selectedTab = 1 },
                    text = { Text("SIM Logs (${callLogs.size})", fontWeight = FontWeight.Bold) }
                )
            }

            if (selectedTab == 0) {
                // AI Summaries View (Matching Superfone Screenshot 2)
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp)
                ) {
                    items(aiSummaries, key = { it.id }) { summary ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(18.dp),
                            colors = CardDefaults.cardColors(containerColor = LightSurface),
                            border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
                            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                // Header: Call ended + time
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .size(32.dp)
                                                .clip(CircleShape)
                                                .background(PillRoseBg),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Icon(Icons.Default.CallEnd, contentDescription = null, tint = PillRoseText, modifier = Modifier.size(16.dp))
                                        }
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text("Call ended", fontWeight = FontWeight.Bold, fontSize = 14.sp, color = TextPrimary)
                                    }
                                    Text(summary.callTime, fontSize = 12.sp, color = TextSecondary)
                                }

                                Spacer(modifier = Modifier.height(12.dp))

                                // AI Call Summary Generated Box (Matching Superfone)
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(12.dp),
                                    colors = CardDefaults.cardColors(containerColor = Color(0xFFFBF8FF)),
                                    border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFE9D5FF))
                                ) {
                                    Column(modifier = Modifier.padding(12.dp)) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = AiGradientPurple, modifier = Modifier.size(16.dp))
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Text(
                                                "AI Call summary generated",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 12.sp,
                                                color = Color(0xFF7E22CE)
                                            )
                                        }

                                        Spacer(modifier = Modifier.height(8.dp))

                                        Text("Customer inquiry for ${summary.destination}:", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = TextPrimary)
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text("• Destination: ${summary.destination}", fontSize = 12.sp, color = TextSecondary)
                                        Text("• Travelers: ${summary.travelers}", fontSize = 12.sp, color = TextSecondary)
                                        Text("• Dates: ${summary.dates}", fontSize = 12.sp, color = TextSecondary)
                                        Text("• Budget: ${summary.budget}", fontSize = 12.sp, color = TextSecondary)
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))

                                // AI Suggested Action Card
                                Card(
                                    modifier = Modifier.fillMaxWidth(),
                                    shape = RoundedCornerShape(12.dp),
                                    colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)),
                                    border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder)
                                ) {
                                    Column(modifier = Modifier.padding(12.dp)) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Icon(Icons.Default.CheckCircle, contentDescription = null, tint = WhatsAppGreen, modifier = Modifier.size(16.dp))
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Text(
                                                "AI Suggested action",
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 12.sp,
                                                color = TextPrimary
                                            )
                                        }

                                        Spacer(modifier = Modifier.height(6.dp))

                                        Text("👤 Customer: ${summary.customerName}", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = TextPrimary)

                                        Spacer(modifier = Modifier.height(6.dp))

                                        // Tags
                                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                            summary.tags.forEach { tag ->
                                                PillTag(tag, PillCyanBg, PillCyanText)
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(8.dp))

                                        Text("⏰ Reminder: ${summary.reminder}", fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = SuperfoneBlue)
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(summary.nextAction, fontSize = 12.sp, color = TextSecondary)
                                    }
                                }

                                Spacer(modifier = Modifier.height(12.dp))

                                // Fast Action Buttons (Call & WhatsApp)
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.End,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    FilledTonalButton(
                                        onClick = { DialerManager.dialViaCarrierSim(context, summary.phone) },
                                        colors = ButtonDefaults.filledTonalButtonColors(containerColor = SuperfoneBlueLight),
                                        shape = RoundedCornerShape(10.dp)
                                    ) {
                                        Icon(Icons.Default.Call, contentDescription = null, tint = SuperfoneBlue, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("Call", color = SuperfoneBlue, fontSize = 12.sp)
                                    }

                                    Spacer(modifier = Modifier.width(8.dp))

                                    Button(
                                        onClick = {
                                            val msg = "Namaste ${summary.customerName} ji! Following up on our call regarding ${summary.destination}."
                                            DialerManager.openWhatsAppChat(context, summary.phone, msg)
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                                        shape = RoundedCornerShape(10.dp)
                                    ) {
                                        Icon(Icons.Default.Chat, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("WhatsApp", color = Color.White, fontSize = 12.sp)
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Raw SIM logs
                if (callLogs.isEmpty()) {
                    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text("No outbound SIM calls recorded yet.", color = Color.Gray)
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(callLogs) { log ->
                            Card(
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(12.dp),
                                colors = CardDefaults.cardColors(containerColor = LightSurface),
                                border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder)
                            ) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(14.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                                        when (log.callType) {
                                            "incoming" -> Icon(Icons.Default.CallReceived, contentDescription = null, tint = WhatsAppGreen)
                                            "missed" -> Icon(Icons.Default.CallMissed, contentDescription = null, tint = Color.Red)
                                            else -> Icon(Icons.Default.CallMade, contentDescription = null, tint = SuperfoneBlue)
                                        }

                                        Spacer(modifier = Modifier.width(12.dp))

                                        Column {
                                            Text(
                                                text = log.contactName ?: log.phone,
                                                fontWeight = FontWeight.Bold,
                                                fontSize = 15.sp,
                                                color = TextPrimary
                                            )
                                            Text(
                                                text = "${dateFormat.format(Date(log.timestamp))} • ${log.durationSeconds / 60}m ${log.durationSeconds % 60}s",
                                                fontSize = 12.sp,
                                                color = TextSecondary
                                            )
                                        }
                                    }

                                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        IconButton(onClick = { DialerManager.dialViaCarrierSim(context, log.phone) }) {
                                            Icon(Icons.Default.Call, contentDescription = "Redial", tint = SuperfoneBlue)
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
    }
}
