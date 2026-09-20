package co.tripcosmos.salesagents.ui.screens

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.provider.ContactsContract
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.Chat
import androidx.compose.material.icons.filled.ContactPhone
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Search
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
import androidx.core.content.ContextCompat
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.Lead
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.OrangePrimary
import co.tripcosmos.salesagents.ui.theme.WhatsAppGreen
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ContactsScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var searchQuery by remember { mutableStateOf("") }
    var crmContacts by remember { mutableStateOf<List<Lead>>(emptyList()) }
    var deviceContacts by remember { mutableStateOf<List<Lead>>(emptyList()) }
    var selectedFilter by remember { mutableStateOf("All") } // "All", "CRM Leads", "Device Contacts"
    var isLoading by remember { mutableStateOf(false) }
    var hasContactsPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.READ_CONTACTS) == PackageManager.PERMISSION_GRANTED
        )
    }

    val prefs = context.getSharedPreferences("tc_agents_prefs", Context.MODE_PRIVATE)
    val token = prefs.getString("mobile_api_token", "tc_mobile_secret_2026") ?: ""
    val baseUrl = prefs.getString("base_url", "https://tripcosmos.co/wp-json/tc-agents/v1/") ?: ""

    fun loadDeviceContacts() {
        if (!hasContactsPermission) return
        scope.launch(Dispatchers.IO) {
            val list = mutableListOf<Lead>()
            try {
                val cursor = context.contentResolver.query(
                    ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
                    arrayOf(
                        ContactsContract.CommonDataKinds.Phone._ID,
                        ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME,
                        ContactsContract.CommonDataKinds.Phone.NUMBER
                    ),
                    null,
                    null,
                    ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME + " ASC"
                )
                cursor?.use { c ->
                    val idIdx = c.getColumnIndex(ContactsContract.CommonDataKinds.Phone._ID)
                    val nameIdx = c.getColumnIndex(ContactsContract.CommonDataKinds.Phone.DISPLAY_NAME)
                    val numIdx = c.getColumnIndex(ContactsContract.CommonDataKinds.Phone.NUMBER)
                    val seen = mutableSetOf<String>()
                    while (c.moveToNext()) {
                        val id = if (idIdx != -1) c.getLong(idIdx) else 0L
                        val name = if (nameIdx != -1) c.getString(nameIdx) ?: "Contact" else "Contact"
                        val num = if (numIdx != -1) c.getString(numIdx) ?: "" else ""
                        val clean = num.replace(Regex("[^0-9]"), "")
                        if (clean.length >= 10 && !seen.contains(clean)) {
                            seen.add(clean)
                            list.add(
                                Lead(
                                    id = 200000L + id,
                                    name = name,
                                    phone = num,
                                    stage = "phonebook",
                                    destination = "Device Contact"
                                )
                            )
                        }
                    }
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
            withContext(Dispatchers.Main) {
                deviceContacts = list
            }
        }
    }

    fun loadCrmContacts() {
        scope.launch {
            isLoading = true
            try {
                val api = TripCosmosApiService.create(baseUrl)
                val res = api.getLeads(null, token)
                if (res.isSuccessful) {
                    crmContacts = res.body()?.leads ?: emptyList()
                }
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                isLoading = false
            }
        }
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        hasContactsPermission = isGranted
        if (isGranted) {
            loadDeviceContacts()
        }
    }

    LaunchedEffect(Unit) {
        loadCrmContacts()
        if (hasContactsPermission) {
            loadDeviceContacts()
        }
    }

    // Merge contacts according to selected filter
    val combinedList = remember(selectedFilter, crmContacts, deviceContacts) {
        when (selectedFilter) {
            "CRM Leads" -> crmContacts
            "Device Contacts" -> deviceContacts
            else -> {
                // All: CRM contacts prioritized, then device contacts not already in CRM
                val crmPhones = crmContacts.map { it.phone.replace(Regex("[^0-9]"), "").takeLast(10) }.toSet()
                val uniqueDevice = deviceContacts.filter {
                    val p = it.phone.replace(Regex("[^0-9]"), "").takeLast(10)
                    !crmPhones.contains(p)
                }
                crmContacts + uniqueDevice
            }
        }
    }

    val filteredContacts = remember(searchQuery, combinedList) {
        if (searchQuery.isBlank()) combinedList
        else combinedList.filter {
            it.name.contains(searchQuery, ignoreCase = true) ||
            it.phone.contains(searchQuery) ||
            (it.destination ?: "").contains(searchQuery, ignoreCase = true)
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Traveler Directory", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                        Text("${filteredContacts.size} contacts available", fontSize = 12.sp, color = Color.Gray)
                    }
                },
                actions = {
                    IconButton(onClick = {
                        loadCrmContacts()
                        if (hasContactsPermission) loadDeviceContacts()
                    }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                }
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            // Search Input
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                placeholder = { Text("Search by name, phone, or destination...") },
                leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                singleLine = true
            )

            // Filter Chips (All, CRM Leads, Device Contacts)
            val filters = listOf("All", "CRM Leads", "Device Contacts")
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(filters) { f ->
                    FilterChip(
                        selected = selectedFilter == f,
                        onClick = { selectedFilter = f },
                        label = {
                            val count = when (f) {
                                "CRM Leads" -> crmContacts.size
                                "Device Contacts" -> deviceContacts.size
                                else -> combinedList.size
                            }
                            Text("$f ($count)")
                        },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = OrangePrimary,
                            selectedLabelColor = Color.White
                        )
                    )
                }
            }

            // If device contacts permission not yet granted, show quick prompt banner
            if (!hasContactsPermission) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 16.dp, vertical = 6.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFEFF6FF))
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.weight(1f)
                        ) {
                            Icon(Icons.Default.ContactPhone, contentDescription = null, tint = Color(0xFF2563EB))
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                "Sync device phonebook with CRM caller ID",
                                fontSize = 12.sp,
                                color = Color(0xFF1E40AF)
                            )
                        }
                        TextButton(onClick = { permissionLauncher.launch(Manifest.permission.READ_CONTACTS) }) {
                            Text("Enable", fontWeight = FontWeight.Bold, color = Color(0xFF2563EB))
                        }
                    }
                }
            }

            if (isLoading) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = OrangePrimary)
                }
            } else if (filteredContacts.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text("No contacts found.", color = Color.Gray)
                        Spacer(modifier = Modifier.height(8.dp))
                        Button(
                            onClick = {
                                loadCrmContacts()
                                if (hasContactsPermission) loadDeviceContacts()
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = OrangePrimary)
                        ) {
                            Text("Refresh Directory")
                        }
                    }
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
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text(
                                            text = contact.name.ifBlank { "Traveler" },
                                            fontWeight = FontWeight.Bold,
                                            fontSize = 16.sp
                                        )
                                        if (contact.stage == "phonebook") {
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Box(
                                                modifier = Modifier
                                                    .clip(RoundedCornerShape(4.dp))
                                                    .background(Color(0xFFF1F5F9))
                                                    .padding(horizontal = 4.dp, vertical = 2.dp)
                                            ) {
                                                Text("Phone", fontSize = 10.sp, color = Color.Gray)
                                            }
                                        } else {
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Box(
                                                modifier = Modifier
                                                    .clip(RoundedCornerShape(4.dp))
                                                    .background(Color(0xFFFEF3C7))
                                                    .padding(horizontal = 4.dp, vertical = 2.dp)
                                            ) {
                                                Text("CRM", fontSize = 10.sp, color = Color(0xFFD97706), fontWeight = FontWeight.Bold)
                                            }
                                        }
                                    }
                                    Text(
                                        text = contact.phone,
                                        fontSize = 13.sp,
                                        color = Color.Gray
                                    )
                                    if (!contact.destination.isNullOrBlank() && contact.destination != "Device Contact") {
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
