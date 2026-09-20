package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
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
import co.tripcosmos.salesagents.data.model.Lead
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LeadDetailDialog(
    lead: Lead,
    onDismiss: () -> Unit,
    onLeadUpdated: (Lead) -> Unit
) {
    val context = LocalContext.current
    var currentStage by remember { mutableStateOf(lead.stage) }
    var currentOwner by remember { mutableStateOf(lead.owner) }
    var stageMenuExpanded by remember { mutableStateOf(false) }
    var ownerMenuExpanded by remember { mutableStateOf(false) }
    var newNoteText by remember { mutableStateOf("") }
    var notesList by remember {
        mutableStateOf(
            listOf(
                "CALL ${lead.updatedAt ?: "Today"}\nNOTE: Inquired for Varanasi Temple Darshan + Ayodhya Cab. Budget ₹${lead.dealValue.toInt()}."
            )
        )
    }

    val teamMembers = listOf("Ajay Verma", "Meera Singh", "Rahul Sharma", "TripCosmos Travel Desk")
    val stageList = listOf("inquiry", "qualified", "proposal", "negotiation", "won", "lost")

    AlertDialog(
        onDismissRequest = onDismiss,
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 16.dp),
        properties = androidx.compose.ui.window.DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth(0.95f)
                .wrapContentHeight(),
            shape = RoundedCornerShape(24.dp),
            colors = CardDefaults.cardColors(containerColor = LightSurface)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
            ) {
                // Header with Back button and Lead Profile (Matching Superfone Screenshot 3)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Back", tint = TextPrimary)
                    }

                    // Avatar Circle
                    val initials = lead.name.split(" ").mapNotNull { it.firstOrNull()?.toString() }.take(2).joinToString("").ifBlank { "TR" }
                    Box(
                        modifier = Modifier
                            .size(44.dp)
                            .clip(CircleShape)
                            .background(SuperfoneBlueLight),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = initials.uppercase(),
                            fontWeight = FontWeight.Bold,
                            color = SuperfoneBlue,
                            fontSize = 15.sp
                        )
                    }

                    Spacer(modifier = Modifier.width(12.dp))

                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = lead.name.ifBlank { "Traveler" },
                            fontWeight = FontWeight.Bold,
                            fontSize = 18.sp,
                            color = TextPrimary
                        )
                        Text(
                            text = lead.phone,
                            fontSize = 13.sp,
                            color = TextSecondary
                        )
                    }

                    // Close Icon
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = "Close", tint = Color.Gray)
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // Team Assignment Row (Superfone Lead Owner)
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)),
                    border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp, vertical = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(
                                text = "LEAD OWNER:",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = TextSecondary
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Box(
                                modifier = Modifier
                                    .size(24.dp)
                                    .clip(CircleShape)
                                    .background(PillPurpleBg),
                                contentAlignment = Alignment.Center
                            ) {
                                val ownerInitials = currentOwner.split(" ").mapNotNull { it.firstOrNull()?.toString() }.take(2).joinToString("")
                                Text(ownerInitials, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = PillPurpleText)
                            }
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = currentOwner,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = TextPrimary
                            )
                        }

                        Box {
                            TextButton(onClick = { ownerMenuExpanded = true }) {
                                Text("Reassign", fontSize = 12.sp, color = SuperfoneBlue)
                            }
                            DropdownMenu(
                                expanded = ownerMenuExpanded,
                                onDismissRequest = { ownerMenuExpanded = false }
                            ) {
                                teamMembers.forEach { member ->
                                    DropdownMenuItem(
                                        text = { Text(member) },
                                        onClick = {
                                            currentOwner = member
                                            ownerMenuExpanded = false
                                            onLeadUpdated(lead.copy(owner = member, stage = currentStage))
                                        }
                                    )
                                }
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(12.dp))

                // Tags Pill Row (Matching Superfone)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    PillTag("New Lead", PillCyanBg, PillCyanText)
                    PillTag(lead.destination ?: "Varanasi", PillPurpleBg, PillPurpleText)
                    PillTag("₹" + lead.dealValue.toInt(), PillAmberBg, PillAmberText)
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Fast Action Buttons (Call, WhatsApp, Share with Team)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceEvenly,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Free Carrier SIM Call
                    Button(
                        onClick = { DialerManager.dialViaCarrierSim(context, lead.phone) },
                        colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue),
                        shape = CircleShape,
                        modifier = Modifier.size(54.dp),
                        contentPadding = PaddingValues(0.dp)
                    ) {
                        Icon(Icons.Default.Call, contentDescription = "Call", tint = Color.White)
                    }

                    // Direct WhatsApp Chat with Traveler
                    Button(
                        onClick = {
                            val msg = "Namaste ${lead.name} ji! Reaching out from TripCosmos Varanasi regarding your spiritual tour inquiry."
                            DialerManager.openWhatsAppChat(context, lead.phone, msg)
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                        shape = CircleShape,
                        modifier = Modifier.size(54.dp),
                        contentPadding = PaddingValues(0.dp)
                    ) {
                        Icon(Icons.Default.Chat, contentDescription = "WhatsApp", tint = Color.White)
                    }

                    // Team WhatsApp Dispatch (Sends lead details to assigned agent via WhatsApp)
                    Button(
                        onClick = {
                            val teamMsg = "🚀 *TripCosmos Lead Assigned to $currentOwner*\n" +
                                    "• *Name:* ${lead.name}\n" +
                                    "• *Phone:* ${lead.phone}\n" +
                                    "• *Destination:* ${lead.destination ?: "Varanasi Tour"}\n" +
                                    "• *Value:* ₹${lead.dealValue.toInt()}\n" +
                                    "• *Stage:* ${currentStage.uppercase()}\n" +
                                    "Please follow up with traveler immediately."
                            DialerManager.openWhatsAppChat(context, "", teamMsg)
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFF1F5F9)),
                        shape = CircleShape,
                        modifier = Modifier.size(54.dp),
                        contentPadding = PaddingValues(0.dp)
                    ) {
                        Icon(Icons.Default.Share, contentDescription = "Forward to Team", tint = SuperfoneBlue)
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Stage Dropdown (Superfone style: "LEAD STAGE: [ BOOKING CONFIRMATION v ]")
                Text(
                    text = "LEAD STAGE",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = TextSecondary
                )
                Spacer(modifier = Modifier.height(4.dp))

                Box(modifier = Modifier.fillMaxWidth()) {
                    OutlinedButton(
                        onClick = { stageMenuExpanded = true },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.outlinedButtonColors(containerColor = PillAmberBg.copy(alpha = 0.4f)),
                        border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFFDE68A))
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = currentStage.uppercase(),
                                fontWeight = FontWeight.Bold,
                                color = PillAmberText,
                                fontSize = 13.sp
                            )
                            Icon(Icons.Default.ArrowDropDown, contentDescription = null, tint = PillAmberText)
                        }
                    }

                    DropdownMenu(
                        expanded = stageMenuExpanded,
                        onDismissRequest = { stageMenuExpanded = false }
                    ) {
                        stageList.forEach { stage ->
                            DropdownMenuItem(
                                text = { Text(stage.uppercase(), fontWeight = FontWeight.Bold) },
                                onClick = {
                                    currentStage = stage
                                    stageMenuExpanded = false
                                    onLeadUpdated(lead.copy(stage = stage, owner = currentOwner))
                                }
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Activity / Notes Timeline
                Text(
                    text = "ACTIVITY & NOTES",
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    color = TextSecondary
                )
                Spacer(modifier = Modifier.height(6.dp))

                notesList.forEach { note ->
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 4.dp),
                        colors = CardDefaults.cardColors(containerColor = Color(0xFFF8FAFC)),
                        border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder)
                    ) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text(text = note, fontSize = 13.sp, color = TextPrimary, lineHeight = 18.sp)
                        }
                    }
                }

                Spacer(modifier = Modifier.height(10.dp))

                // Add quick note
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = newNoteText,
                        onValueChange = { newNoteText = it },
                        modifier = Modifier.weight(1f),
                        placeholder = { Text("Add follow-up note...", fontSize = 12.sp) },
                        shape = RoundedCornerShape(12.dp),
                        singleLine = true
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Button(
                        onClick = {
                            if (newNoteText.isNotBlank()) {
                                notesList = notesList + ("NOTE: $newNoteText (by $currentOwner)")
                                newNoteText = ""
                            }
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = SuperfoneBlue),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text("Save")
                    }
                }
            }
        }
    }
}

@Composable
fun PillTag(text: String, bgColor: Color, textColor: Color) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(bgColor)
            .padding(horizontal = 8.dp, vertical = 4.dp)
    ) {
        Text(
            text = text,
            fontSize = 11.sp,
            fontWeight = FontWeight.SemiBold,
            color = textColor
        )
    }
}
