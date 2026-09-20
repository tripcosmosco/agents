package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.Refresh
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
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.OrangePrimary
import co.tripcosmos.salesagents.ui.theme.WhatsAppGreen
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PipelineScreen(
    onLeadSelected: (Lead) -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    val stages = listOf("all", "inquiry", "qualified", "proposal", "negotiation", "won", "lost")
    var selectedStage by remember { mutableStateOf("all") }
    var leads by remember { mutableStateOf<List<Lead>>(emptyList()) }
    var isLoading by remember { mutableStateOf(false) }

    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
    val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""

    fun loadLeads() {
        scope.launch {
            isLoading = true
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val stageParam = if (selectedStage == "all") null else selectedStage
                val res = api.getLeads(stageParam, token)
                if (res.isSuccessful) {
                    leads = res.body() ?: emptyList()
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

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("TripCosmos Sales Pipeline", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                        Text("${leads.size} active inquiries", style = MaterialTheme.typography.bodySmall, color = Color.Gray)
                    }
                },
                actions = {
                    IconButton(onClick = { loadLeads() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.surface
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            // Horizontal Stage Filter Chips
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(stages) { st ->
                    val isSelected = st == selectedStage
                    FilterChip(
                        selected = isSelected,
                        onClick = { selectedStage = st },
                        label = { Text(st.replaceFirstChar { it.uppercase() }) },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = OrangePrimary,
                            selectedLabelColor = Color.White
                        )
                    )
                }
            }

            if (isLoading) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = OrangePrimary)
                }
            } else if (leads.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("No leads found in this stage.", color = Color.Gray)
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(leads) { lead ->
                        LeadCard(lead = lead, onCall = {
                            DialerManager.dialViaCarrierSim(context, lead.phone)
                        }, onWhatsApp = {
                            DialerManager.openWhatsAppChat(context, lead.phone, "Namaste ${lead.name} ji! Reaching out from TripCosmos Varanasi.")
                        }, onClick = {
                            onLeadSelected(lead)
                        })
                    }
                }
            }
        }
    }
}

@Composable
fun LeadCard(
    lead: Lead,
    onCall: () -> Unit,
    onWhatsApp: () -> Unit,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = lead.name.ifBlank { "Traveler" },
                    fontWeight = FontWeight.Bold,
                    fontSize = 17.sp
                )
                // Deal value badge
                Text(
                    text = "₹" + lead.dealValue.toInt(),
                    fontWeight = FontWeight.ExtraBold,
                    color = OrangePrimary,
                    fontSize = 15.sp
                )
            }

            Spacer(modifier = Modifier.height(4.dp))

            Text(
                text = lead.destination ?: "Varanasi Spiritual Tour",
                color = Color.Gray,
                fontSize = 13.sp
            )

            Spacer(modifier = Modifier.height(8.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Stage Pill
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(Color(0xFFE0E7FF))
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = lead.stage.uppercase(),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color(0xFF3730A3)
                    )
                }

                // Quick Communication Actions (100% Free Carrier SIM Call & WhatsApp)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    FilledTonalIconButton(
                        onClick = onCall,
                        colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = Color(0xFFFFEDD5))
                    ) {
                        Icon(Icons.Default.Call, contentDescription = "Free Call", tint = OrangePrimary)
                    }

                    FilledTonalIconButton(
                        onClick = onWhatsApp,
                        colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = Color(0xFFDCFCE7))
                    ) {
                        Icon(Icons.Default.Chat, contentDescription = "WhatsApp", tint = WhatsAppGreen)
                    }
                }
            }
        }
    }
}
