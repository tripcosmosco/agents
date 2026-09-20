package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.AssignLeadPayload
import co.tripcosmos.salesagents.data.model.WhatsAppLead
import co.tripcosmos.salesagents.data.model.formatMaskedPhone
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WhatsAppHubScreen(
    onOpenMaya: (() -> Unit)? = null
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val apiService = remember { TripCosmosApiService.create() }
    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val maskPhoneNumbers = prefs.getBoolean("mask_phone_numbers", false)

    var selectedFilter by remember { mutableStateOf("All") }
    var searchQuery by remember { mutableStateOf("") }
    var activeAssignLead by remember { mutableStateOf<WhatsAppLead?>(null) }
    var activeQuoteLead by remember { mutableStateOf<WhatsAppLead?>(null) }
    var isRefreshing by remember { mutableStateOf(false) }

    // Sample fallback active WhatsApp inbound inquiries
    var leadsList by remember {
        mutableStateOf(
            listOf(
                WhatsAppLead(
                    id = "101",
                    customerName = "Sunil Agarwal",
                    phone = "+919839012345",
                    lastMessage = "Namaste! We are 4 adults planning for Kashi Vishwanath VIP Darshan + evening Ganga Aarti boat cruise on 26th. Please share pricing.",
                    timeAgo = "3m ago",
                    unreadCount = 2,
                    tourInterest = "Varanasi 3D2N + VIP Darshan",
                    estimatedBudget = 22000.0,
                    assignedManager = null, // Unassigned
                    leadScore = 92,
                    status = "new"
                ),
                WhatsAppLead(
                    id = "102",
                    customerName = "Pooja Hegde",
                    phone = "+919741289012",
                    lastMessage = "Need AC Innova Crysta for Varanasi to Ayodhya Ram Mandir return trip for senior citizens. Are wheelchairs available at temple?",
                    timeAgo = "11m ago",
                    unreadCount = 1,
                    tourInterest = "Ayodhya Day Excursion Cab",
                    estimatedBudget = 8500.0,
                    assignedManager = null, // Unassigned
                    leadScore = 88,
                    status = "new"
                ),
                WhatsAppLead(
                    id = "103",
                    customerName = "Rajiv Menon",
                    phone = "+919845019283",
                    lastMessage = "Thanks for the initial brochure. Can we customize the hotel to 4-star near Dashashwamedh Ghat? Please update quote.",
                    timeAgo = "28m ago",
                    unreadCount = 0,
                    tourInterest = "4-Star Spiritual Package",
                    estimatedBudget = 35000.0,
                    assignedManager = "Ajay Verma",
                    leadScore = 85,
                    status = "assigned"
                ),
                WhatsAppLead(
                    id = "104",
                    customerName = "Deepak Chawla",
                    phone = "+919811823901",
                    lastMessage = "Looking for Prayagraj Sangam Snan cab package + Varanasi hotel for 6 pax next week.",
                    timeAgo = "1h ago",
                    unreadCount = 0,
                    tourInterest = "Varanasi + Prayagraj 4D3N",
                    estimatedBudget = 28000.0,
                    assignedManager = "Meera Singh",
                    leadScore = 78,
                    status = "assigned"
                ),
                WhatsAppLead(
                    id = "105",
                    customerName = "Ananya Roy",
                    phone = "+919051283746",
                    lastMessage = "Hi, need airport pickup at 8 AM and drop at Assi Ghat hotel tomorrow morning.",
                    timeAgo = "2h ago",
                    unreadCount = 0,
                    tourInterest = "Airport Transfer Cab",
                    estimatedBudget = 1200.0,
                    assignedManager = "Rahul Sharma",
                    leadScore = 65,
                    status = "assigned"
                )
            )
        )
    }

    // Fetch live WhatsApp leads from TripCosmos WordPress REST API
    fun fetchLiveLeads(showToast: Boolean = false) {
        coroutineScope.launch {
            isRefreshing = true
            try {
                val res = withContext(Dispatchers.IO) { apiService.getWhatsAppLeads() }
                if (res.isSuccessful && res.body()?.ok == true) {
                    val liveLeads = res.body()!!.leads
                    if (liveLeads.isNotEmpty()) {
                        // Merge live server leads with fallback list
                        val liveIds = liveLeads.map { it.id }.toSet()
                        val combined = liveLeads + leadsList.filter { it.id !in liveIds }
                        leadsList = combined
                        if (showToast) {
                            Toast.makeText(context, "Synced ${liveLeads.size} live leads from server!", Toast.LENGTH_SHORT).show()
                        }
                    }
                }
            } catch (e: Exception) {
                // Graceful fallback to cached state
            } finally {
                isRefreshing = false
            }
        }
    }

    LaunchedEffect(Unit) {
        fetchLiveLeads(showToast = false)
    }

    val unassignedCount = leadsList.count { it.assignedManager == null }

    val filteredLeads = remember(selectedFilter, searchQuery, leadsList) {
        leadsList.filter { lead ->
            val matchSearch = searchQuery.isBlank() ||
                    lead.customerName.contains(searchQuery, ignoreCase = true) ||
                    lead.phone.contains(searchQuery) ||
                    lead.tourInterest.contains(searchQuery, ignoreCase = true)

            val matchFilter = when (selectedFilter) {
                "Unassigned" -> lead.assignedManager == null
                "Assigned" -> lead.assignedManager != null
                "Hot Leads" -> lead.leadScore >= 85
                else -> true
            }

            matchSearch && matchFilter
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(36.dp)
                                .clip(CircleShape)
                                .background(WhatsAppGreen),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Default.Chat, contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
                        }
                        Spacer(modifier = Modifier.width(10.dp))
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text("WhatsApp Inbound Hub", fontWeight = FontWeight.Bold, fontSize = 17.sp, color = TextPrimary)
                                if (unassignedCount > 0) {
                                    Spacer(modifier = Modifier.width(6.dp))
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(10.dp))
                                            .background(Color(0xFFEF4444))
                                            .padding(horizontal = 6.dp, vertical = 2.dp)
                                    ) {
                                        Text("$unassignedCount NEW", color = Color.White, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold)
                                    }
                                }
                            }
                            Text("Admin Dispatch & Manager Routing", fontSize = 11.sp, color = TextSecondary)
                        }
                    }
                },
                actions = {
                    if (onOpenMaya != null) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(20.dp))
                                .background(Brush.linearGradient(listOf(AiGradientPink, AiGradientPurple)))
                                .clickable { onOpenMaya() }
                                .padding(horizontal = 10.dp, vertical = 6.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.AutoAwesome, contentDescription = "Maya AI", tint = Color.White, modifier = Modifier.size(14.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Maya AI", fontWeight = FontWeight.Bold, fontSize = 11.sp, color = Color.White)
                            }
                        }
                        Spacer(modifier = Modifier.width(6.dp))
                    }
                    IconButton(onClick = { fetchLiveLeads(showToast = true) }) {
                        if (isRefreshing) {
                            CircularProgressIndicator(modifier = Modifier.size(20.dp), strokeWidth = 2.dp, color = SuperfoneBlue)
                        } else {
                            Icon(Icons.Default.Refresh, contentDescription = "Refresh", tint = TextPrimary)
                        }
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
            // Intelligent Overview Bar (KPI Stats for Admin)
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                shape = RoundedCornerShape(16.dp),
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
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("Active Chats", fontSize = 11.sp, color = TextSecondary)
                        Text("${leadsList.size}", fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, color = TextPrimary)
                    }
                    Divider(modifier = Modifier.height(28.dp).width(1.dp), color = CardBorder)
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("Unassigned", fontSize = 11.sp, color = TextSecondary)
                        Text("$unassignedCount", fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, color = if (unassignedCount > 0) Color(0xFFDC2626) else SuperfoneBlue)
                    }
                    Divider(modifier = Modifier.height(28.dp).width(1.dp), color = CardBorder)
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("Pipeline Est.", fontSize = 11.sp, color = TextSecondary)
                        val totalVal = leadsList.sumOf { it.estimatedBudget }.toInt()
                        Text("₹${totalVal / 1000}k", fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, color = WhatsAppDark)
                    }
                    Divider(modifier = Modifier.height(28.dp).width(1.dp), color = CardBorder)
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("Avg Response", fontSize = 11.sp, color = TextSecondary)
                        Text("< 2m", fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, color = OrangePrimary)
                    }
                }
            }

            // Search Bar
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
                placeholder = { Text("Search by traveler, phone, or tour...", fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = Color.Gray) },
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            // Filter Chips
            val filters = listOf("All", "Unassigned", "Assigned", "Hot Leads")
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(filters) { f ->
                    val isSelected = selectedFilter == f
                    FilterChip(
                        selected = isSelected,
                        onClick = { selectedFilter = f },
                        label = {
                            val badge = when (f) {
                                "Unassigned" -> if (unassignedCount > 0) " ($unassignedCount)" else ""
                                else -> ""
                            }
                            Text("$f$badge", fontSize = 12.sp)
                        },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = if (f == "Unassigned" && unassignedCount > 0) Color(0xFFDC2626) else WhatsAppGreen,
                            selectedLabelColor = Color.White,
                            containerColor = LightSurface,
                            labelColor = TextPrimary
                        ),
                        border = FilterChipDefaults.filterChipBorder(
                            enabled = true,
                            selected = isSelected,
                            borderColor = if (isSelected) WhatsAppGreen else CardBorder
                        )
                    )
                }
            }

            // Leads List
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                items(filteredLeads, key = { it.id }) { lead ->
                    WhatsAppLeadCard(
                        lead = lead,
                        maskPhone = maskPhoneNumbers,
                        onAssignClick = { activeAssignLead = lead },
                        onQuoteClick = { activeQuoteLead = lead },
                        onChatTraveler = {
                            DialerManager.openWhatsAppChat(
                                context,
                                lead.phone,
                                "Namaste ${lead.customerName} ji! TripCosmos Varanasi sales desk here. How can we finalize your tour package?"
                            )
                        },
                        onAlertManager = {
                            if (lead.assignedManager != null) {
                                val dispatchMsg = "🚨 *TripCosmos WhatsApp Lead Assigned to you!*\n" +
                                        "• *Traveler:* ${lead.customerName}\n" +
                                        "• *Phone:* ${lead.phone}\n" +
                                        "• *Inquiry:* ${lead.tourInterest}\n" +
                                        "• *Est. Budget:* ₹${lead.estimatedBudget.toInt()}\n" +
                                        "• *Last Msg:* \"${lead.lastMessage}\"\n\n" +
                                        "Please open WhatsApp and reply to traveler immediately."
                                DialerManager.openWhatsAppChat(context, "", dispatchMsg)
                            } else {
                                activeAssignLead = lead
                            }
                        }
                    )
                }
            }
        }
    }

    // Modal Sheet: Admin Assigns Lead to Manager
    activeAssignLead?.let { leadToAssign ->
        AssignManagerDialog(
            lead = leadToAssign,
            onDismiss = { activeAssignLead = null },
            onAssigned = { selectedManager, notifyManagerOnWhatsApp ->
                leadsList = leadsList.map {
                    if (it.id == leadToAssign.id) it.copy(assignedManager = selectedManager, status = "assigned") else it
                }
                activeAssignLead = null

                // Sync assignment to backend REST API
                val leadIdNum = leadToAssign.id.toLongOrNull() ?: 0L
                if (leadIdNum > 0) {
                    coroutineScope.launch {
                        try {
                            withContext(Dispatchers.IO) {
                                apiService.assignLead(
                                    payload = AssignLeadPayload(
                                        leadId = leadIdNum,
                                        managerName = selectedManager,
                                        notifyManager = notifyManagerOnWhatsApp
                                    )
                                )
                            }
                        } catch (e: Exception) {
                            // Ignored
                        }
                    }
                }

                if (notifyManagerOnWhatsApp) {
                    val alertText = "🚨 *New Traveler Assigned to you ($selectedManager)*\n" +
                            "• *Traveler:* ${leadToAssign.customerName}\n" +
                            "• *Phone:* ${leadToAssign.phone}\n" +
                            "• *Inquiry:* ${leadToAssign.tourInterest}\n" +
                            "• *Budget:* ₹${leadToAssign.estimatedBudget.toInt()}\n" +
                            "• *Traveler Message:* \"${leadToAssign.lastMessage}\"\n\n" +
                            "Follow up immediately via Sales Agents companion app."
                    DialerManager.openWhatsAppChat(context, "", alertText)
                }

                Toast.makeText(context, "Lead assigned to $selectedManager!", Toast.LENGTH_SHORT).show()
            }
        )
    }

    // Modal Sheet: Instant Dynamic Tour Quotation & Itinerary Generator (Option B)
    activeQuoteLead?.let { quoteLead ->
        TourQuoteDialog(
            initialTravelerName = quoteLead.customerName,
            initialPhone = quoteLead.phone,
            initialDestination = quoteLead.tourInterest,
            onDismiss = { activeQuoteLead = null }
        )
    }
}

@Composable
fun WhatsAppLeadCard(
    lead: WhatsAppLead,
    maskPhone: Boolean = false,
    onAssignClick: () -> Unit,
    onQuoteClick: () -> Unit,
    onChatTraveler: () -> Unit,
    onAlertManager: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = LightSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            // Header Row: Avatar, Customer Name, Phone, Time Ago & Heat Score
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                    val initials = lead.customerName.split(" ").mapNotNull { it.firstOrNull()?.toString() }.take(2).joinToString("").ifBlank { "WA" }
                    Box(
                        modifier = Modifier
                            .size(40.dp)
                            .clip(CircleShape)
                            .background(Color(0xFFDCFCE7)),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = initials.uppercase(),
                            fontWeight = FontWeight.Bold,
                            color = WhatsAppDark,
                            fontSize = 14.sp
                        )
                    }

                    Spacer(modifier = Modifier.width(10.dp))

                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = lead.customerName,
                                fontWeight = FontWeight.Bold,
                                fontSize = 16.sp,
                                color = TextPrimary
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Icon(Icons.Default.Verified, contentDescription = null, tint = WhatsAppGreen, modifier = Modifier.size(16.dp))
                        }
                        Text(text = formatMaskedPhone(lead.phone, maskPhone), fontSize = 12.sp, color = TextSecondary)
                    }
                }

                Column(horizontalAlignment = Alignment.End) {
                    Text(text = lead.timeAgo, fontSize = 11.sp, color = TextSecondary)
                    Spacer(modifier = Modifier.height(2.dp))
                    // Heat Score Badge
                    val isHot = lead.leadScore >= 85
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(if (isHot) PillRoseBg else PillAmberBg)
                            .padding(horizontal = 6.dp, vertical = 2.dp)
                    ) {
                        Text(
                            text = if (isHot) "🔥 Hot ${lead.leadScore}%" else "⚡ Warm ${lead.leadScore}%",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isHot) PillRoseText else PillAmberText
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(10.dp))

            // Message Bubble Preview
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFFF0FDF4)),
                border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFDCFCE7))
            ) {
                Row(
                    modifier = Modifier.padding(10.dp),
                    verticalAlignment = Alignment.Top
                ) {
                    Icon(Icons.Default.FormatQuote, contentDescription = null, tint = WhatsAppGreen, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = lead.lastMessage,
                        fontSize = 12.sp,
                        color = Color(0xFF166534),
                        lineHeight = 16.sp,
                        maxLines = 3
                    )
                }
            }

            Spacer(modifier = Modifier.height(10.dp))

            // Tour Intent & Budget Row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    PillTag("📍 " + lead.tourInterest, PillCyanBg, PillCyanText)
                    PillTag("₹" + lead.estimatedBudget.toInt(), PillAmberBg, PillAmberText)
                }
            }

            Spacer(modifier = Modifier.height(12.dp))
            Divider(color = CardBorder, thickness = 0.5.dp)
            Spacer(modifier = Modifier.height(10.dp))

            // Manager Assignment & Fast Dispatch Controls
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (lead.assignedManager == null) {
                    // Unassigned state: Admin Call-to-Action
                    Button(
                        onClick = onAssignClick,
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFDC2626)),
                        shape = RoundedCornerShape(8.dp),
                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.PersonAdd, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Assign Manager", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    }
                } else {
                    // Assigned Manager state
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.clickable { onAssignClick() }
                    ) {
                        Box(
                            modifier = Modifier
                                .size(24.dp)
                                .clip(CircleShape)
                                .background(PillPurpleBg),
                            contentAlignment = Alignment.Center
                        ) {
                            val mInitials = lead.assignedManager.split(" ").mapNotNull { it.firstOrNull()?.toString() }.take(2).joinToString("")
                            Text(mInitials, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = PillPurpleText)
                        }
                        Spacer(modifier = Modifier.width(6.dp))
                        Column {
                            Text("Assigned to", fontSize = 9.sp, color = TextSecondary)
                            Text(lead.assignedManager, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = TextPrimary)
                        }
                    }
                }

                // WhatsApp Action Buttons
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                    // Fast Tour Quote Generator (Option B)
                    OutlinedButton(
                        onClick = onQuoteClick,
                        shape = RoundedCornerShape(10.dp),
                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 6.dp),
                        border = androidx.compose.foundation.BorderStroke(1.dp, OrangePrimary)
                    ) {
                        Icon(Icons.Default.Calculate, contentDescription = "Instant Quote", tint = OrangePrimary, modifier = Modifier.size(15.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Quote", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = OrangePrimary)
                    }

                    // Alert Manager button
                    if (lead.assignedManager != null) {
                        FilledTonalIconButton(
                            onClick = onAlertManager,
                            colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = PillPurpleBg)
                        ) {
                            Icon(Icons.Default.Share, contentDescription = "Notify Manager", tint = PillPurpleText, modifier = Modifier.size(18.dp))
                        }
                    }

                    // Direct Traveler Chat
                    Button(
                        onClick = onChatTraveler,
                        colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                        shape = RoundedCornerShape(10.dp),
                        contentPadding = PaddingValues(horizontal = 10.dp, vertical = 6.dp)
                    ) {
                        Icon(Icons.Default.Chat, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Reply", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    }
                }
            }
        }
    }
}

@Composable
fun AssignManagerDialog(
    lead: WhatsAppLead,
    onDismiss: () -> Unit,
    onAssigned: (manager: String, notifyOnWhatsApp: Boolean) -> Unit
) {
    var selectedManager by remember { mutableStateOf("Ajay Verma") }
    var notifyOnWhatsApp by remember { mutableStateOf(true) }

    val managers = listOf(
        Pair("Ajay Verma", "Senior Varanasi Tour Specialist • 3 Active"),
        Pair("Meera Singh", "Ayodhya & Outstation Cab Manager • 2 Active"),
        Pair("Rahul Sharma", "VIP Darshan & Temple Guide Desk • 4 Active"),
        Pair("TripCosmos Ops Desk", "General Ground Ops Team • Online")
    )

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Column {
                Text("Assign Lead to Manager", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                Text("Inquiry from ${lead.customerName} (${lead.phone})", fontSize = 12.sp, color = TextSecondary)
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("Select Team Manager:", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = TextPrimary)

                managers.forEach { (name, role) ->
                    val isSelected = selectedManager == name
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { selectedManager = name },
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (isSelected) SuperfoneBlueLight else LightSurface
                        ),
                        border = androidx.compose.foundation.BorderStroke(
                            1.dp,
                            if (isSelected) SuperfoneBlue else CardBorder
                        )
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(12.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            RadioButton(
                                selected = isSelected,
                                onClick = { selectedManager = name },
                                colors = RadioButtonDefaults.colors(selectedColor = SuperfoneBlue)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Column {
                                Text(name, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = TextPrimary)
                                Text(role, fontSize = 11.sp, color = TextSecondary)
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(6.dp))

                // Toggle: Auto-notify manager on WhatsApp
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Checkbox(
                        checked = notifyOnWhatsApp,
                        onCheckedChange = { notifyOnWhatsApp = it },
                        colors = CheckboxDefaults.colors(checkedColor = WhatsAppGreen)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        "Send instant briefing to Manager on WhatsApp",
                        fontSize = 12.sp,
                        color = TextPrimary
                    )
                }
            }
        },
        confirmButton = {
            Button(
                onClick = { onAssigned(selectedManager, notifyOnWhatsApp) },
                colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue),
                shape = RoundedCornerShape(8.dp)
            ) {
                Text("Confirm Assignment")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Cancel")
            }
        }
    )
}
