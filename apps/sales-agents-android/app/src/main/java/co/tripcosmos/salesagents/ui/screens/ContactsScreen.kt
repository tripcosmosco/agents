package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
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
fun ContactsScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var searchQuery by remember { mutableStateOf("") }
    var contacts by remember { mutableStateOf<List<Lead>>(emptyList()) }
    var isLoading by remember { mutableStateOf(false) }

    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
    val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""

    LaunchedEffect(Unit) {
        scope.launch {
            isLoading = true
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val res = api.getLeads(null, token)
                if (res.isSuccessful) {
                    contacts = res.body() ?: emptyList()
                }
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                isLoading = false
            }
        }
    }

    val filteredContacts = remember(searchQuery, contacts) {
        if (searchQuery.isBlank()) contacts
        else contacts.filter {
            it.name.contains(searchQuery, ignoreCase = true) ||
            it.phone.contains(searchQuery) ||
            (it.destination ?: "").contains(searchQuery, ignoreCase = true)
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Traveler Directory", fontWeight = FontWeight.Bold) }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                placeholder = { Text("Search by name, phone, or destination...") },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                singleLine = true
            )

            if (isLoading) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = OrangePrimary)
                }
            } else if (filteredContacts.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("No contacts found.", color = Color.Gray)
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(filteredContacts) { contact ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(16.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        text = contact.name.ifBlank { "Traveler" },
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 16.sp
                                    )
                                    Text(
                                        text = contact.phone,
                                        fontSize = 13.sp,
                                        color = Color.Gray
                                    )
                                    if (!contact.destination.isNullOrBlank()) {
                                        Text(
                                            text = "📍 " + contact.destination,
                                            fontSize = 12.sp,
                                            color = OrangePrimary
                                        )
                                    }
                                }

                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    FilledTonalIconButton(
                                        onClick = { DialerManager.dialViaCarrierSim(context, contact.phone) },
                                        colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = Color(0xFFFFEDD5))
                                    ) {
                                        Icon(Icons.Default.Call, contentDescription = "Free Call", tint = OrangePrimary)
                                    }

                                    FilledTonalIconButton(
                                        onClick = {
                                            DialerManager.openWhatsAppChat(
                                                context,
                                                contact.phone,
                                                "Namaste ${contact.name} ji! Reaching out from TripCosmos Varanasi."
                                            )
                                        },
                                        colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = Color(0xFFDCFCE7))
                                    ) {
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
