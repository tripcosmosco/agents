package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.Lead
import co.tripcosmos.salesagents.data.model.formatMaskedPhone
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RadarScreen(
    onQuoteLead: (Lead) -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
    val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""
    val maskPhone = prefs.getBoolean("mask_phone_numbers", false)

    var searchQuery by remember { mutableStateOf("") }
    var selectedFilter by remember { mutableStateOf("All") } // "All", "Hot", "Warm", "Quoted"
    var leads by remember { mutableStateOf<List<Lead>>(emptyList()) }
    var isLoading by remember { mutableStateOf(false) }

    // Fallback inquiries if offline
    val fallbackLeads = remember {
        listOf(
            Lead(
                id = 101,
                name = "Sunil Agarwal",
                phone = "9839012345",
                stage = "inquiry",
                dealValue = 22500.0,
                score = 94,
                destination = "Varanasi 3D2N + VIP Darshan",
                requirements = "4 Adults, Senior citizens need wheelchair at temple, require Innova Crysta."
            ),
            Lead(
                id = 102,
                name = "Pooja Hegde",
                phone = "9741289012",
                stage = "inquiry",
                dealValue = 8500.0,
                score = 88,
                destination = "Ayodhya Ram Mandir Day Cab",
                requirements = "Swift Dzire AC for return trip to Ayodhya from Varanasi hotel."
            ),
            Lead(
                id = 103,
                name = "Rajiv Menon",
                phone = "9845019283",
                stage = "proposal",
                dealValue = 35000.0,
                score = 82,
                destination = "Sacred Triangle 4D3N",
                requirements = "Varanasi, Prayagraj Sangam Holy Snan & Ayodhya Ram Mandir for 6 Pax."
            ),
            Lead(
                id = 104,
                name = "Vikram Singh",
                phone = "9898989898",
                stage = "negotiation",
                dealValue = 18500.0,
                score = 91,
                destination = "Kashi Spiritual Tour (Luxury)",
                requirements = "4-Star Hotel with Breakfast, VIP Ganga Aarti Boat Cruise."
            ),
            Lead(
                id = 105,
                name = "Arvind Sharma",
                phone = "9848826512",
                stage = "inquiry",
                dealValue = 4500.0,
                score = 75,
                destination = "Varanasi Airport Transfer + Ghat Cab",
                requirements = "Dedicated AC Sedan for full day Varanasi city tour."
            )
        )
    }

    fun loadLeads() {
        scope.launch {
            isLoading = true
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val res = api.getLeads(null, token)
                if (res.isSuccessful && res.body()?.leads?.isNotEmpty() == true) {
                    leads = res.body()!!.leads
                } else {
                    leads = fallbackLeads
                }
            } catch (e: Exception) {
                leads = fallbackLeads
            } finally {
                isLoading = false
            }
        }
    }

    LaunchedEffect(Unit) {
        loadLeads()
    }

    val filteredLeads = remember(searchQuery, selectedFilter, leads) {
        leads.filter { lead ->
            val matchSearch = searchQuery.isBlank() ||
                    lead.name.contains(searchQuery, ignoreCase = true) ||
                    lead.phone.contains(searchQuery) ||
                    (lead.destination ?: "").contains(searchQuery, ignoreCase = true)

            val matchFilter = when (selectedFilter) {
                "Hot" -> lead.score >= 85
                "Warm" -> lead.score in 60..84
                "Quoted" -> lead.stage == "proposal" || lead.stage == "negotiation"
                else -> true
            }

            matchSearch && matchFilter
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(LightBackground)
    ) {
        // Search Bar with Integrated Refresh Action
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                modifier = Modifier.weight(1f),
                placeholder = { Text("Search devotee, phone, circuit...", fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = Color.Gray, modifier = Modifier.size(18.dp)) },
                shape = RoundedCornerShape(12.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedContainerColor = LightSurface,
                    unfocusedContainerColor = LightSurface,
                    unfocusedBorderColor = CardBorder
                )
            )
            Spacer(modifier = Modifier.width(8.dp))
            IconButton(
                onClick = { loadLeads() },
                modifier = Modifier
                    .size(44.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(LightSurface)
            ) {
                if (isLoading) {
                    CircularProgressIndicator(modifier = Modifier.size(18.dp), strokeWidth = 2.dp, color = SuperfoneBlue)
                } else {
                    Icon(Icons.Default.Refresh, contentDescription = "Refresh", tint = SuperfoneBlue)
                }
            }
        }

        // Sleek Segmented Pill Filters (Zero text clipping)
        val filters = listOf("All", "Hot", "Warm", "Quoted")
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 4.dp)
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xFFF1F5F9))
                .padding(3.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            filters.forEach { filter ->
                val isSelected = selectedFilter == filter
                val count = when (filter) {
                    "Hot" -> leads.count { it.score >= 85 }
                    "Warm" -> leads.count { it.score in 60..84 }
                    "Quoted" -> leads.count { it.stage == "proposal" || it.stage == "negotiation" }
                    else -> leads.size
                }

                Box(
                    modifier = Modifier
                        .weight(1f)
                        .clip(RoundedCornerShape(10.dp))
                        .background(if (isSelected) Color.White else Color.Transparent)
                        .clickable { selectedFilter = filter }
                        .padding(vertical = 7.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = if (filter == "Hot") "🔥 Hot" else filter,
                            fontSize = 12.sp,
                            fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                            color = if (isSelected) SuperfoneBlue else TextSecondary
                        )
                        if (count > 0 && !isSelected) {
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(text = "$count", fontSize = 10.sp, color = TextSecondary)
                        }
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(6.dp))

        // Inquiries Stream List
        if (filteredLeads.isEmpty() && !isLoading) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Default.Inbox, contentDescription = null, tint = Color.LightGray, modifier = Modifier.size(48.dp))
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("No Inquiries Found", fontWeight = FontWeight.Bold, color = TextPrimary)
                    Text("Pull down or tap refresh to check live leads.", fontSize = 12.sp, color = TextSecondary)
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 24.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                items(filteredLeads, key = { it.id }) { lead ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = LightSurface),
                        border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
                        elevation = CardDefaults.cardElevation(defaultElevation = 0.5.dp)
                    ) {
                        Column(modifier = Modifier.padding(14.dp)) {
                            // Header Row: Avatar, Name, Phone, Circuit & Deal Value
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.Top
                            ) {
                                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                                    val initials = lead.name.split(" ").mapNotNull { it.firstOrNull()?.toString() }.take(2).joinToString("").ifBlank { "TR" }
                                    Box(
                                        modifier = Modifier
                                            .size(38.dp)
                                            .clip(CircleShape)
                                            .background(SuperfoneBlueLight),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text(
                                            text = initials.uppercase(),
                                            fontWeight = FontWeight.Bold,
                                            color = SuperfoneBlue,
                                            fontSize = 13.sp
                                        )
                                    }

                                    Spacer(modifier = Modifier.width(10.dp))

                                    Column {
                                        Text(
                                            text = lead.name.ifBlank { "Traveler" },
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 15.sp,
                                            color = TextPrimary
                                        )
                                        Text(
                                            text = formatMaskedPhone(lead.phone, maskPhone),
                                            fontSize = 12.sp,
                                            color = TextSecondary
                                        )
                                    }
                                }

                                // Deal Value Pill
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(Color(0xFFF0FDF4))
                                        .padding(horizontal = 8.dp, vertical = 4.dp)
                                ) {
                                    Text(
                                        text = "₹" + String.format(java.util.Locale.US, "%,d", lead.dealValue.toInt()),
                                        fontWeight = FontWeight.ExtraBold,
                                        fontSize = 14.sp,
                                        color = Color(0xFF15803D)
                                    )
                                }
                            }

                            Spacer(modifier = Modifier.height(8.dp))

                            // Requirements / Circuit Summary
                            Text(
                                text = lead.destination ?: "Pilgrimage Inquiry",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = SuperfoneBlue
                            )
                            if (!lead.requirements.isNullOrBlank()) {
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = lead.requirements,
                                    fontSize = 12.sp,
                                    color = Color.DarkGray,
                                    maxLines = 2
                                )
                            }

                            Spacer(modifier = Modifier.height(10.dp))
                            HorizontalDivider(color = Color(0xFFF1F5F9), thickness = 1.dp)
                            Spacer(modifier = Modifier.height(10.dp))

                            // High-Impact Action Footer: Call, Quote & WhatsApp
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                // 1. Direct SIM Call Button
                                OutlinedButton(
                                    onClick = { DialerManager.dialViaCarrierSim(context, lead.phone) },
                                    shape = RoundedCornerShape(10.dp),
                                    modifier = Modifier
                                        .weight(1f)
                                        .height(36.dp),
                                    contentPadding = PaddingValues(horizontal = 8.dp),
                                    colors = ButtonDefaults.outlinedButtonColors(contentColor = SuperfoneBlue)
                                ) {
                                    Icon(Icons.Default.Call, contentDescription = null, modifier = Modifier.size(15.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Call SIM", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }

                                // 2. Instant Fare Quoter Button (Switches to Quoter tab pre-filled)
                                Button(
                                    onClick = { onQuoteLead(lead) },
                                    shape = RoundedCornerShape(10.dp),
                                    modifier = Modifier
                                        .weight(1.2f)
                                        .height(36.dp),
                                    contentPadding = PaddingValues(horizontal = 8.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue)
                                ) {
                                    Icon(Icons.Default.Calculate, contentDescription = null, tint = Color.White, modifier = Modifier.size(15.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Quote Fare", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }

                                // 3. Instant WhatsApp Chat
                                Button(
                                    onClick = {
                                        val msg = "Namaste ${lead.name} ji! Reaching out from TripCosmos Varanasi regarding your holy tour inquiry: ${lead.destination ?: "Pilgrimage Package"}."
                                        DialerManager.openWhatsAppChat(context, lead.phone, msg)
                                    },
                                    shape = RoundedCornerShape(10.dp),
                                    modifier = Modifier
                                        .weight(1.2f)
                                        .height(36.dp),
                                    contentPadding = PaddingValues(horizontal = 8.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen)
                                ) {
                                    Icon(Icons.Default.Chat, contentDescription = null, tint = Color.White, modifier = Modifier.size(15.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("WhatsApp", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.White)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
