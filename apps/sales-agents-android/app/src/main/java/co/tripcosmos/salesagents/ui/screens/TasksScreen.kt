package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.background
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.model.LeadTask
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TasksScreen() {
    val context = LocalContext.current
    var selectedFilter by remember { mutableStateOf("Due Today") }
    var showCreateDialog by remember { mutableStateOf(false) }

    // Sample default Superfone AI suggested follow-up tasks
    var taskList by remember {
        mutableStateOf(
            listOf(
                LeadTask(
                    title = "Call back Vikram Singh - confirm booking slot for Varanasi tour",
                    leadName = "Vikram Singh",
                    phone = "9898989898",
                    dueDate = "Today, 5:00 PM",
                    isCompleted = false,
                    assignedTo = "Ajay Verma",
                    priority = "High"
                ),
                LeadTask(
                    title = "Send Ayodhya Cab Fare Chart to Arvind Sharma",
                    leadName = "Arvind Sharma",
                    phone = "9848826512",
                    dueDate = "Today, 6:30 PM",
                    isCompleted = false,
                    assignedTo = "Meera Singh",
                    priority = "Normal"
                ),
                LeadTask(
                    title = "Confirm Kashi Vishwanath VIP Darshan slot for 4 Pax",
                    leadName = "Satyagurudevarao",
                    phone = "9848826512",
                    dueDate = "Tomorrow, 10:00 AM",
                    isCompleted = false,
                    assignedTo = "Ajay Verma",
                    priority = "High"
                ),
                LeadTask(
                    title = "Follow up on Advance Token Payment ₹5,000",
                    leadName = "Jibak Dutta",
                    phone = "9093152521",
                    dueDate = "Overdue (2h ago)",
                    isCompleted = false,
                    assignedTo = "Rahul Sharma",
                    priority = "High"
                ),
                LeadTask(
                    title = "Send Bodhgaya + Prayagraj Itinerary PDF via WhatsApp",
                    leadName = "Neha Mani",
                    phone = "7289026009",
                    dueDate = "Yesterday",
                    isCompleted = true,
                    assignedTo = "TripCosmos Travel Desk",
                    priority = "Normal"
                )
            )
        )
    }

    val filteredTasks = remember(selectedFilter, taskList) {
        when (selectedFilter) {
            "Due Today" -> taskList.filter { !it.isCompleted && (it.dueDate.contains("Today") || it.dueDate.contains("PM")) }
            "Upcoming" -> taskList.filter { !it.isCompleted && it.dueDate.contains("Tomorrow") }
            "Overdue" -> taskList.filter { !it.isCompleted && (it.dueDate.contains("Overdue") || it.dueDate.contains("Yesterday")) }
            "Completed" -> taskList.filter { it.isCompleted }
            else -> taskList
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Tasks & Reminders", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = TextPrimary)
                        Text("${taskList.count { !it.isCompleted }} pending follow-ups", fontSize = 12.sp, color = TextSecondary)
                    }
                },
                actions = {
                    IconButton(onClick = { showCreateDialog = true }) {
                        Icon(Icons.Default.AddCircle, contentDescription = "Add Task", tint = SuperfoneBlue, modifier = Modifier.size(28.dp))
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
            // Filter Chips (Due Today, Upcoming, Overdue, Completed)
            val filters = listOf("Due Today", "Upcoming", "Overdue", "Completed")
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(filters) { filter ->
                    val isSelected = selectedFilter == filter
                    FilterChip(
                        selected = isSelected,
                        onClick = { selectedFilter = filter },
                        label = { Text(filter) },
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

            if (filteredTasks.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.CheckCircle, contentDescription = null, tint = WhatsAppGreen, modifier = Modifier.size(48.dp))
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("No tasks in this list!", fontWeight = FontWeight.Bold, color = TextPrimary)
                        Text("You're all caught up on follow-ups.", fontSize = 13.sp, color = TextSecondary)
                    }
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(filteredTasks, key = { it.id }) { task ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = LightSurface),
                            border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
                            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.Top,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    // Checkbox to mark complete
                                    Checkbox(
                                        checked = task.isCompleted,
                                        onCheckedChange = { isChecked ->
                                            taskList = taskList.map {
                                                if (it.id == task.id) it.copy(isCompleted = isChecked) else it
                                            }
                                        },
                                        colors = CheckboxDefaults.colors(
                                            checkedColor = WhatsAppGreen,
                                            uncheckedColor = Color.Gray
                                        )
                                    )

                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            text = task.title,
                                            fontWeight = FontWeight.SemiBold,
                                            fontSize = 15.sp,
                                            color = if (task.isCompleted) Color.Gray else TextPrimary,
                                            textDecoration = if (task.isCompleted) TextDecoration.LineThrough else TextDecoration.None
                                        )

                                        Spacer(modifier = Modifier.height(4.dp))

                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                                        ) {
                                            Text(
                                                text = "👤 ${task.leadName}",
                                                fontSize = 12.sp,
                                                color = TextSecondary
                                            )

                                            // Due Date Pill
                                            val isOverdue = task.dueDate.contains("Overdue") || task.dueDate.contains("Yesterday")
                                            val pillBg = if (isOverdue) PillRoseBg else PillAmberBg
                                            val pillText = if (isOverdue) PillRoseText else PillAmberText
                                            Box(
                                                modifier = Modifier
                                                    .clip(RoundedCornerShape(6.dp))
                                                    .background(pillBg)
                                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                                            ) {
                                                Text(
                                                    text = "⏰ ${task.dueDate}",
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = pillText
                                                )
                                            }
                                        }

                                        Spacer(modifier = Modifier.height(6.dp))

                                        // Assigned Team Member badge
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text("Owner: ", fontSize = 11.sp, color = Color.Gray)
                                            Box(
                                                modifier = Modifier
                                                    .clip(RoundedCornerShape(4.dp))
                                                    .background(PillPurpleBg)
                                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                                            ) {
                                                Text(
                                                    text = task.assignedTo,
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.Bold,
                                                    color = PillPurpleText
                                                )
                                            }
                                        }
                                    }

                                    // Quick Call & WhatsApp
                                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                        if (task.phone.isNotBlank()) {
                                            FilledTonalIconButton(
                                                onClick = { DialerManager.dialViaCarrierSim(context, task.phone) },
                                                colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = SuperfoneBlueLight)
                                            ) {
                                                Icon(Icons.Default.Call, contentDescription = "Call", tint = SuperfoneBlue, modifier = Modifier.size(18.dp))
                                            }

                                            FilledTonalIconButton(
                                                onClick = {
                                                    val msg = "Namaste ${task.leadName} ji! Following up from TripCosmos Varanasi regarding: ${task.title}"
                                                    DialerManager.openWhatsAppChat(context, task.phone, msg)
                                                },
                                                colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = PillGreenBg)
                                            ) {
                                                Icon(Icons.Default.Chat, contentDescription = "WhatsApp", tint = WhatsAppGreen, modifier = Modifier.size(18.dp))
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

    // Modal to create new task
    if (showCreateDialog) {
        var newTitle by remember { mutableStateOf("") }
        var newLeadName by remember { mutableStateOf("") }
        var newPhone by remember { mutableStateOf("") }
        var newDueDate by remember { mutableStateOf("Today, 6:00 PM") }
        var newOwner by remember { mutableStateOf("Ajay Verma") }

        AlertDialog(
            onDismissRequest = { showCreateDialog = false },
            title = { Text("Create Follow-up Task", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = newTitle,
                        onValueChange = { newTitle = it },
                        label = { Text("Task Description") },
                        placeholder = { Text("e.g. Call back to confirm package") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = newLeadName,
                        onValueChange = { newLeadName = it },
                        label = { Text("Traveler Name") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = newPhone,
                        onValueChange = { newPhone = it },
                        label = { Text("Phone Number") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = newDueDate,
                        onValueChange = { newDueDate = it },
                        label = { Text("Due Date & Time") },
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (newTitle.isNotBlank()) {
                            taskList = listOf(
                                LeadTask(
                                    title = newTitle,
                                    leadName = newLeadName.ifBlank { "Traveler" },
                                    phone = newPhone,
                                    dueDate = newDueDate,
                                    assignedTo = newOwner
                                )
                            ) + taskList
                            showCreateDialog = false
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue)
                ) {
                    Text("Add Task")
                }
            },
            dismissButton = {
                TextButton(onClick = { showCreateDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}
