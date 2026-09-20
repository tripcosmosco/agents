package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.model.LeadTask
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TasksScreen(
    onOpenMaya: (() -> Unit)? = null
) {
    val context = LocalContext.current
    var selectedFilter by remember { mutableStateOf("Today") }
    var showCreateDialog by remember { mutableStateOf(false) }

    // Superfone AI suggested follow-up tasks
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
            "Today" -> taskList.filter { !it.isCompleted && (it.dueDate.contains("Today") || it.dueDate.contains("PM")) }
            "Upcoming" -> taskList.filter { !it.isCompleted && it.dueDate.contains("Tomorrow") }
            "Overdue" -> taskList.filter { !it.isCompleted && (it.dueDate.contains("Overdue") || it.dueDate.contains("Yesterday")) }
            "Done" -> taskList.filter { it.isCompleted }
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
                    // Sleek Maya AI Top Bar Trigger (Replaces obstructive floating blob)
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
                        Spacer(modifier = Modifier.width(8.dp))
                    }

                    IconButton(onClick = { showCreateDialog = true }) {
                        Icon(Icons.Default.AddCircle, contentDescription = "Add Task", tint = SuperfoneBlue, modifier = Modifier.size(26.dp))
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
            // Elegant Segmented Filter Control (No text clipping on any screen!)
            val filters = listOf("Today", "Upcoming", "Overdue", "Done")
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(Color(0xFFF1F5F9))
                    .padding(3.dp),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                filters.forEach { filter ->
                    val isSelected = selectedFilter == filter
                    val count = when (filter) {
                        "Today" -> taskList.count { !it.isCompleted && (it.dueDate.contains("Today") || it.dueDate.contains("PM")) }
                        "Upcoming" -> taskList.count { !it.isCompleted && it.dueDate.contains("Tomorrow") }
                        "Overdue" -> taskList.count { !it.isCompleted && (it.dueDate.contains("Overdue") || it.dueDate.contains("Yesterday")) }
                        "Done" -> taskList.count { it.isCompleted }
                        else -> 0
                    }

                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isSelected) Color.White else Color.Transparent)
                            .clickable { selectedFilter = filter }
                            .padding(vertical = 8.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = filter,
                                fontSize = 12.sp,
                                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSelected) SuperfoneBlue else TextSecondary
                            )
                            if (count > 0 && !isSelected) {
                                Spacer(modifier = Modifier.width(4.dp))
                                Text(
                                    text = "$count",
                                    fontSize = 10.sp,
                                    color = TextSecondary
                                )
                            }
                        }
                    }
                }
            }

            if (filteredTasks.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.CheckCircle, contentDescription = null, tint = WhatsAppGreen, modifier = Modifier.size(44.dp))
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("All Caught Up! 🎉", fontWeight = FontWeight.Bold, fontSize = 15.sp, color = TextPrimary)
                        Text("No pending tasks in this category.", fontSize = 12.sp, color = TextSecondary)
                    }
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 24.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(filteredTasks, key = { it.id }) { task ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(16.dp),
                            colors = CardDefaults.cardColors(containerColor = LightSurface),
                            border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
                            elevation = CardDefaults.cardElevation(defaultElevation = 0.5.dp)
                        ) {
                            Column(modifier = Modifier.padding(14.dp)) {
                                // Row 1: Checkbox + Title (Full width for natural breathing room)
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.Top
                                ) {
                                    Checkbox(
                                        checked = task.isCompleted,
                                        onCheckedChange = { isChecked ->
                                            taskList = taskList.map {
                                                if (it.id == task.id) it.copy(isCompleted = isChecked) else it
                                            }
                                        },
                                        colors = CheckboxDefaults.colors(
                                            checkedColor = WhatsAppGreen,
                                            uncheckedColor = Color.LightGray
                                        ),
                                        modifier = Modifier.size(24.dp)
                                    )

                                    Spacer(modifier = Modifier.width(10.dp))

                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            text = task.title,
                                            fontWeight = FontWeight.SemiBold,
                                            fontSize = 14.sp,
                                            lineHeight = 19.sp,
                                            color = if (task.isCompleted) Color.Gray else TextPrimary,
                                            textDecoration = if (task.isCompleted) TextDecoration.LineThrough else TextDecoration.None
                                        )

                                        Spacer(modifier = Modifier.height(6.dp))

                                        // Row 2: Metadata Line (Single clean line, no bulky wrapping boxes)
                                        val isOverdue = task.dueDate.contains("Overdue") || task.dueDate.contains("Yesterday")
                                        Row(
                                            verticalAlignment = Alignment.CenterVertically,
                                            modifier = Modifier.fillMaxWidth()
                                        ) {
                                            Text(
                                                text = task.leadName,
                                                fontSize = 12.sp,
                                                fontWeight = FontWeight.Medium,
                                                color = SuperfoneBlue
                                            )
                                            Text(" • ", fontSize = 12.sp, color = Color.LightGray)
                                            Text(
                                                text = task.dueDate,
                                                fontSize = 11.sp,
                                                fontWeight = if (isOverdue) FontWeight.Bold else FontWeight.Normal,
                                                color = if (isOverdue) PillRoseText else TextSecondary
                                            )
                                        }
                                    }
                                }

                                Spacer(modifier = Modifier.height(10.dp))
                                HorizontalDivider(color = Color(0xFFF1F5F9), thickness = 1.dp)
                                Spacer(modifier = Modifier.height(10.dp))

                                // Row 3: Action Footer (Owner pill on left, Quick Call & WhatsApp on right)
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    // Owner Pill
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Box(
                                            modifier = Modifier
                                                .clip(RoundedCornerShape(6.dp))
                                                .background(Color(0xFFF1F5F9))
                                                .padding(horizontal = 8.dp, vertical = 3.dp)
                                        ) {
                                            Text(
                                                text = task.assignedTo,
                                                fontSize = 11.sp,
                                                fontWeight = FontWeight.Medium,
                                                color = TextSecondary
                                            )
                                        }
                                    }

                                    // Quick Call & WhatsApp Buttons
                                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                        if (task.phone.isNotBlank()) {
                                            // Call Button
                                            OutlinedButton(
                                                onClick = { DialerManager.dialViaCarrierSim(context, task.phone) },
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp),
                                                modifier = Modifier.height(32.dp),
                                                colors = ButtonDefaults.outlinedButtonColors(contentColor = SuperfoneBlue)
                                            ) {
                                                Icon(Icons.Default.Call, contentDescription = "Call", modifier = Modifier.size(14.dp))
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("Call", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                            }

                                            // WhatsApp Button
                                            Button(
                                                onClick = {
                                                    val msg = "Namaste ${task.leadName} ji! Following up from TripCosmos Varanasi regarding: ${task.title}"
                                                    DialerManager.openWhatsAppChat(context, task.phone, msg)
                                                },
                                                shape = RoundedCornerShape(8.dp),
                                                contentPadding = PaddingValues(horizontal = 10.dp, vertical = 4.dp),
                                                modifier = Modifier.height(32.dp),
                                                colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen)
                                            ) {
                                                Icon(Icons.Default.Chat, contentDescription = "WhatsApp", tint = Color.White, modifier = Modifier.size(14.dp))
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
            title = { Text("Create Follow-up Task", fontWeight = FontWeight.Bold, fontSize = 16.sp) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = newTitle,
                        onValueChange = { newTitle = it },
                        label = { Text("Task Description", fontSize = 12.sp) },
                        placeholder = { Text("e.g. Call back to confirm package") },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    )
                    OutlinedTextField(
                        value = newLeadName,
                        onValueChange = { newLeadName = it },
                        label = { Text("Traveler Name", fontSize = 12.sp) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    )
                    OutlinedTextField(
                        value = newPhone,
                        onValueChange = { newPhone = it },
                        label = { Text("Phone Number", fontSize = 12.sp) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
                    )
                    OutlinedTextField(
                        value = newDueDate,
                        onValueChange = { newDueDate = it },
                        label = { Text("Due Date & Time", fontSize = 12.sp) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp)
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
                    colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue),
                    shape = RoundedCornerShape(10.dp)
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
