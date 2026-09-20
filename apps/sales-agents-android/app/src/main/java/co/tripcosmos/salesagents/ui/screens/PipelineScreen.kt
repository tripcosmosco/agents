package co.tripcosmos.salesagents.ui.screens

import android.content.Context
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
import co.tripcosmos.salesagents.data.model.Lead
import co.tripcosmos.salesagents.data.model.formatMaskedPhone
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PipelineScreen(
    onOpenMaya: (() -> Unit)? = null,
    onLeadSelected: (Lead) -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    val stages = listOf("all", "inquiry", "qualified", "proposal", "negotiation", "won", "lost")
    var selectedStage by remember { mutableStateOf("all") }
    var selectedTeamFilter by remember { mutableStateOf("All Team") }
    var searchQuery by remember { mutableStateOf("") }
    var leads by remember { mutableStateOf<List<Lead>>(emptyList()) }
    var isLoading by remember { mutableStateOf(false) }
    var activeLeadForDetail by remember { mutableStateOf<Lead?>(null) }

    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
    val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""
    val maskPhoneNumbers = prefs.getBoolean("mask_phone_numbers", false)

    fun loadLeads() {
        scope.launch {
            isLoading = true
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val stageParam = if (selectedStage == "all") null else selectedStage
                val res = api.getLeads(stageParam, token)
                if (res.isSuccessful) {
                    val rawLeads = res.body()?.leads ?: emptyList()
                    // Distribute leads evenly across team for realistic team assignment demo
                    val team = listOf("Ajay Verma", "Meera Singh", "Rahul Sharma", "TripCosmos Travel Desk")
                    leads = rawLeads.mapIndexed { idx, lead ->
                        lead.copy(owner = team[idx % team.size])
                    }
                }
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                isLoading = false
            }
        }
    }

    LaunchedEffect(selectedStage) {
        loadLeads()
    }

    val filteredLeads = remember(searchQuery, selectedTeamFilter, leads) {
        leads.filter { lead ->
            val matchSearch = searchQuery.isBlank() ||
                    lead.name.contains(searchQuery, ignoreCase = true) ||
                    lead.phone.contains(searchQuery) ||
                    (lead.destination ?: "").contains(searchQuery, ignoreCase = true)

            val matchTeam = selectedTeamFilter == "All Team" || lead.owner == selectedTeamFilter

            matchSearch && matchTeam
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Sales Pipeline & Deals", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = TextPrimary)
                        Text("${filteredLeads.size} inquiries • Tripcosmos\'s Agents Lead Manager", fontSize = 12.sp, color = TextSecondary)
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
                    IconButton(onClick = { loadLeads() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh", tint = SuperfoneBlue)
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
            // Search Input
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 6.dp),
                placeholder = { Text("Search by traveler, phone, or tour...", fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null, tint = Color.Gray) },
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            // Team Member Assignment Filter Row
            val teamOptions = listOf("All Team", "Ajay Verma", "Meera Singh", "Rahul Sharma")
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 2.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(teamOptions) { teamMember ->
                    val isSelected = selectedTeamFilter == teamMember
                    FilterChip(
                        selected = isSelected,
                        onClick = { selectedTeamFilter = teamMember },
                        label = { Text(teamMember, fontSize = 11.sp) },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = PillPurpleBg,
                            selectedLabelColor = PillPurpleText,
                            containerColor = LightSurface,
                            labelColor = TextSecondary
                        ),
                        border = FilterChipDefaults.filterChipBorder(
                            enabled = true,
                            selected = isSelected,
                            borderColor = if (isSelected) PillPurpleText else CardBorder
                        )
                    )
                }
            }

            // Horizontal Stage Filter Chips (All, Inquiry, Qualified, etc.)
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(stages) { st ->
                    val isSelected = st == selectedStage
                    FilterChip(
                        selected = isSelected,
                        onClick = { selectedStage = st },
                        label = { Text(st.replaceFirstChar { it.uppercase() }, fontSize = 12.sp) },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = SuperfoneBlue,
                            selectedLabelColor = Color.White,
                            containerColor = LightSurface,
                            labelColor = TextPrimary
                        ),
                        border = FilterChipDefaults.filterChipBorder(
                            enabled = true,
                            selected = isSelected,
                            borderColor = if (isSelected) SuperfoneBlue else CardBorder
                        )
                    )
                }
            }

            if (isLoading) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = SuperfoneBlue)
                }
            } else if (filteredLeads.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("No leads found in this filter.", color = Color.Gray, fontSize = 14.sp)
                        Spacer(modifier = Modifier.height(8.dp))
                        Button(
                            onClick = { loadLeads() },
                            colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue)
                        ) {
                            Text("Refresh Pipeline")
                        }
                    }
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(filteredLeads, key = { it.id }) { lead ->
                        SuperfoneLeadCard(
                            lead = lead,
                            maskPhone = maskPhoneNumbers,
                            onClick = {
                                activeLeadForDetail = lead
                                onLeadSelected(lead)
                            },
                            onCall = {
                                DialerManager.dialViaCarrierSim(context, lead.phone)
                            },
                            onWhatsApp = {
                                val msg = "Namaste ${lead.name} ji! Reaching out from TripCosmos Varanasi regarding your travel inquiry."
                                DialerManager.openWhatsAppChat(context, lead.phone, msg)
                            }
                        )
                    }
                }
            }
        }
    }

    // Superfone Lead Profile Detail Dialog
    activeLeadForDetail?.let { currentLead ->
        LeadDetailDialog(
            lead = currentLead,
            onDismiss = { activeLeadForDetail = null },
            onLeadUpdated = { updated ->
                leads = leads.map { if (it.id == updated.id) updated else it }
                activeLeadForDetail = null
            }
        )
    }
}

@Composable
fun SuperfoneLeadCard(
    lead: Lead,
    maskPhone: Boolean = false,
    onClick: () -> Unit,
    onCall: () -> Unit,
    onWhatsApp: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = LightSurface),
        border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            // Row 1: Avatar + Name + Deal Value (Superfone style)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                    val cleanName = lead.name.replace(Regex("[^a-zA-Z0-9 ]"), " ").trim()
                    val initials = cleanName.split(" ").filter { it.isNotBlank() }.map { it.first().toString() }.take(2).joinToString("").ifBlank { "TR" }
                    Box(
                        modifier = Modifier
                            .size(40.dp)
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
                            fontSize = 16.sp,
                            color = TextPrimary
                        )
                        Text(
                            text = formatMaskedPhone(lead.phone, maskPhone),
                            fontSize = 12.sp,
                            color = TextSecondary
                        )
                    }
                }

                // Deal value badge
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(PillAmberBg)
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = "₹" + String.format(java.util.Locale.US, "%,d", lead.dealValue.toInt()),
                        fontWeight = FontWeight.ExtraBold,
                        color = PillAmberText,
                        fontSize = 15.sp
                    )
                }
            }

            Spacer(modifier = Modifier.height(10.dp))

            // Row 2: Lead Owner Pill (Superfone Screenshot 3 style)
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("LEAD OWNER", fontSize = 10.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
                    Spacer(modifier = Modifier.width(6.dp))
                    Box(
                        modifier = Modifier
                            .size(20.dp)
                            .clip(CircleShape)
                            .background(PillPurpleBg),
                        contentAlignment = Alignment.Center
                    ) {
                        val oInitials = lead.owner.split(" ").mapNotNull { it.firstOrNull()?.toString() }.take(2).joinToString("")
                        Text(oInitials, fontSize = 9.sp, fontWeight = FontWeight.Bold, color = PillPurpleText)
                    }
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(lead.owner, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, color = TextPrimary)
                }

                // Destination tag
                Text(
                    text = "📍 " + (lead.destination ?: "Varanasi Tour"),
                    fontSize = 12.sp,
                    color = SuperfoneBlue,
                    fontWeight = FontWeight.Medium
                )
            }

            Spacer(modifier = Modifier.height(10.dp))

            // Row 3: Tags + Stage Pill + Action Buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Stage Pill
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .background(PillAmberBg)
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = lead.stage.uppercase(),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = PillAmberText
                    )
                }

                // Quick Communication Actions (100% Free Carrier SIM Call & WhatsApp)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    FilledTonalIconButton(
                        onClick = onCall,
                        colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = SuperfoneBlueLight)
                    ) {
                        Icon(Icons.Default.Call, contentDescription = "Free Call", tint = SuperfoneBlue, modifier = Modifier.size(20.dp))
                    }

                    FilledTonalIconButton(
                        onClick = onWhatsApp,
                        colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = PillGreenBg)
                    ) {
                        Icon(Icons.Default.Chat, contentDescription = "WhatsApp", tint = WhatsAppGreen, modifier = Modifier.size(20.dp))
                    }
                }
            }
        }
    }
}
