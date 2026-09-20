package co.tripcosmos.salesagents.ui.screens

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.widget.Toast
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.model.ChatMessage
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AiCopilotScreen() {
    val context = LocalContext.current
    var inputText by remember { mutableStateOf("") }

    var messages by remember {
        mutableStateOf(
            listOf(
                ChatMessage(
                    text = "Namaste! I am Maya AI, your TripCosmos Master Sales Copilot. 🛕✨\n\nI can help you instantly generate tour quotes, answer pilgrim inquiries about Kashi Vishwanath & Ayodhya, draft WhatsApp pitches, or create customized itineraries for travelers.",
                    isFromUser = false,
                    suggestedWhatsAppText = null
                )
            )
        )
    }

    val promptSuggestions = listOf(
        "✨ 3D2N Varanasi Quote",
        "🚗 Ayodhya Cab Rates",
        "🙏 Kashi Darshan FAQ",
        "💬 Follow-up WhatsApp Pitch",
        "🚤 Ghat Boat Ride Rates"
    )

    fun handleSend(query: String) {
        if (query.isBlank()) return
        val userMsg = ChatMessage(text = query, isFromUser = true)
        messages = messages + userMsg

        // AI Response Logic
        val lower = query.lowercase()
        val aiReply: String
        val whatsappDraft: String?

        when {
            lower.contains("varanasi quote") || lower.contains("3d2n") -> {
                aiReply = "🌟 *TripCosmos 3D2N Spiritual Varanasi Package*\n\n" +
                        "• *Day 1:* Airport Pickup, Hotel Check-in, Evening Ganga Aarti boat cruise at Dashashwamedh Ghat.\n" +
                        "• *Day 2:* Subah-e-Banaras sunrise boat ride, Kashi Vishwanath Temple Darshan, Annapurna Mandir, Kaal Bhairav, Sarnath Tour.\n" +
                        "• *Day 3:* Banaras Hindu University, Sankat Mochan, Airport Drop.\n\n" +
                        "💰 *Pricing:* ₹14,500 for 2 Adults (AC Sedan Cab + 3-Star Deluxe Hotel with Breakfast + Private Boat Cruise)."
                whatsappDraft = aiReply
            }
            lower.contains("ayodhya") || lower.contains("cab") -> {
                aiReply = "🚗 *TripCosmos Outstation Cab Tariff (Varanasi to Ayodhya Day Trip)*\n\n" +
                        "• *Swift Dzire (AC Sedan):* ₹4,500 (Includes toll, parking, driver allowance)\n" +
                        "• *Innova Crysta (6+1 AC):* ₹7,500\n" +
                        "• *Tempo Traveller (12/17 Seater):* ₹12,500\n\n" +
                        "⏱️ *Travel Duration:* ~4 Hours each way via NH330.\n" +
                        "📍 *Sightseeing:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Sarayu Ghat Aarti."
                whatsappDraft = aiReply
            }
            lower.contains("darshan") || lower.contains("kashi") -> {
                aiReply = "🙏 *Kashi Vishwanath Temple Darshan Guidelines:*\n\n" +
                        "• *VIP Sugam Darshan:* ₹300/person (Fast-track entry through Gate 4 / Corridor).\n" +
                        "• *Dress Code:* Traditional attire recommended (Dhoti/Kurta for men, Saree/Salwar for women for Sparsh Darshan).\n" +
                        "• *Timings:* Mangala Aarti (3:00 AM - 4:00 AM), Bhog Aarti (11:15 AM - 12:20 PM), Sandhya Aarti (7:00 PM - 8:15 PM).\n" +
                        "• *Lockers:* Available inside the Kashi Vishwanath Corridor."
                whatsappDraft = "Namaste! Here are the official Kashi Vishwanath Darshan details & VIP entry guidelines from TripCosmos: \n" + aiReply
            }
            lower.contains("follow-up") || lower.contains("whatsapp") -> {
                aiReply = "Here is a high-converting follow-up message to send the traveler:\n\n" +
                        "\"Namaste! 🙏 Hope you are having a wonderful day. We have secured your tentative tour dates for the Varanasi Spiritual Tour & Cab booking.\n\n" +
                        "As auspicious festival dates are approaching, hotels near Ghats are filling fast. Would you like us to block your deluxe room and AC vehicle today with a ₹2,000 token advance? Let me know so we can assist you!\""
                whatsappDraft = "Namaste! 🙏 Hope you are having a wonderful day. We have secured your tentative tour dates for the Varanasi Spiritual Tour & Cab booking. As hotels near Ghats are filling fast, shall we block your deluxe room and AC vehicle today with a ₹2,000 token advance?"
            }
            lower.contains("boat") -> {
                aiReply = "🚤 *Varanasi Ghat Boat Ride Tariffs:*\n\n" +
                        "• *Hand-rowed Wooden Boat (1-4 Pax):* ₹800 - ₹1,200 for 1.5 Hours\n" +
                        "• *Motor Boat (up to 10 Pax):* ₹1,800 - ₹2,500\n" +
                        "• *Luxury Alaknanda / Bajra Cruise:* ₹750 - ₹1,200 per seat with cultural music\n" +
                        "• *Best Time:* 5:30 AM for Sunrise or 6:00 PM for Ganga Aarti."
                whatsappDraft = aiReply
            }
            else -> {
                aiReply = "I have analyzed your query: \"$query\".\n\nFor customized arrangements, our local Varanasi ground team is stationed near Dashashwamedh Ghat with dedicated cabs and verified tour guides. You can pitch the package with complimentary Ganga Aarti boat seating to close the inquiry faster!"
                whatsappDraft = "Namaste! Reaching out from TripCosmos Varanasi regarding your travel inquiry. We have prepared your customized itinerary. When would be a good time for a quick 2-minute call?"
            }
        }

        val aiMsg = ChatMessage(text = aiReply, isFromUser = false, suggestedWhatsAppText = whatsappDraft)
        messages = messages + aiMsg
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        // AI Avatar with gradient ring
                        Box(
                            modifier = Modifier
                                .size(38.dp)
                                .clip(CircleShape)
                                .background(
                                    Brush.linearGradient(listOf(AiGradientPink, AiGradientPurple))
                                ),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Default.AutoAwesome, contentDescription = null, tint = Color.White, modifier = Modifier.size(20.dp))
                        }
                        Spacer(modifier = Modifier.width(10.dp))
                        Column {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text("Maya AI", fontWeight = FontWeight.Bold, fontSize = 17.sp, color = TextPrimary)
                                Spacer(modifier = Modifier.width(6.dp))
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(4.dp))
                                        .background(SuperfoneBlueLight)
                                        .padding(horizontal = 4.dp, vertical = 2.dp)
                                ) {
                                    Text("SALES COPILOT", fontSize = 9.sp, fontWeight = FontWeight.ExtraBold, color = SuperfoneBlue)
                                }
                            }
                            Text("TripCosmos Master Sales Intelligence", fontSize = 11.sp, color = TextSecondary)
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
            // Quick Prompt Chips
            LazyRow(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(promptSuggestions) { prompt ->
                    SuggestionChip(
                        onClick = { handleSend(prompt) },
                        label = { Text(prompt, fontSize = 12.sp) },
                        colors = SuggestionChipDefaults.suggestionChipColors(
                            containerColor = LightSurface,
                            labelColor = TextPrimary
                        ),
                        border = SuggestionChipDefaults.suggestionChipBorder(
                            enabled = true,
                            borderColor = CardBorder
                        )
                    )
                }
            }

            // Chat Messages List
            LazyColumn(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                items(messages, key = { it.id }) { msg ->
                    if (msg.isFromUser) {
                        // User message (Right aligned)
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End
                        ) {
                            Box(
                                modifier = Modifier
                                    .widthIn(max = 300.dp)
                                    .clip(RoundedCornerShape(16.dp, 16.dp, 4.dp, 16.dp))
                                    .background(SuperfoneBlue)
                                    .padding(14.dp)
                            ) {
                                Text(text = msg.text, color = Color.White, fontSize = 14.sp)
                            }
                        }
                    } else {
                        // AI message (Left aligned with actions)
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.Start
                        ) {
                            Card(
                                modifier = Modifier.widthIn(max = 320.dp),
                                shape = RoundedCornerShape(16.dp, 16.dp, 16.dp, 4.dp),
                                colors = CardDefaults.cardColors(containerColor = LightSurface),
                                border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
                                elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                            ) {
                                Column(modifier = Modifier.padding(14.dp)) {
                                    Text(
                                        text = msg.text,
                                        color = TextPrimary,
                                        fontSize = 14.sp,
                                        lineHeight = 20.sp
                                    )

                                    if (!msg.suggestedWhatsAppText.isNullOrBlank()) {
                                        Spacer(modifier = Modifier.height(10.dp))
                                        Divider(color = CardBorder, thickness = 0.5.dp)
                                        Spacer(modifier = Modifier.height(8.dp))

                                        Row(
                                            modifier = Modifier.fillMaxWidth(),
                                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                                        ) {
                                            // Copy text button
                                            OutlinedButton(
                                                onClick = {
                                                    val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                                    clipboard.setPrimaryClip(ClipData.newPlainText("Copilot Draft", msg.suggestedWhatsAppText))
                                                    Toast.makeText(context, "Copied to clipboard!", Toast.LENGTH_SHORT).show()
                                                },
                                                shape = RoundedCornerShape(8.dp),
                                                modifier = Modifier.weight(1f),
                                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp)
                                            ) {
                                                Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(16.dp))
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("Copy", fontSize = 12.sp)
                                            }

                                            // WhatsApp 1-tap dispatch
                                            Button(
                                                onClick = {
                                                    DialerManager.openWhatsAppChat(context, "", msg.suggestedWhatsAppText)
                                                },
                                                colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                                                shape = RoundedCornerShape(8.dp),
                                                modifier = Modifier.weight(1f),
                                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 4.dp)
                                            ) {
                                                Icon(Icons.Default.Chat, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                                                Spacer(modifier = Modifier.width(4.dp))
                                                Text("WhatsApp", fontSize = 12.sp, color = Color.White)
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Input Bar
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(12.dp),
                shape = RoundedCornerShape(24.dp),
                colors = CardDefaults.cardColors(containerColor = LightSurface),
                border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 12.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    TextField(
                        value = inputText,
                        onValueChange = { inputText = it },
                        modifier = Modifier.weight(1f),
                        placeholder = { Text("Ask Maya AI for quotes, rates, or pitches...", fontSize = 13.sp) },
                        colors = TextFieldDefaults.colors(
                            focusedContainerColor = Color.Transparent,
                            unfocusedContainerColor = Color.Transparent,
                            disabledContainerColor = Color.Transparent,
                            focusedIndicatorColor = Color.Transparent,
                            unfocusedIndicatorColor = Color.Transparent
                        ),
                        singleLine = true
                    )

                    IconButton(
                        onClick = {
                            if (inputText.isNotBlank()) {
                                val query = inputText
                                inputText = ""
                                handleSend(query)
                            }
                        },
                        colors = IconButtonDefaults.iconButtonColors(contentColor = SuperfoneBlue)
                    ) {
                        Icon(Icons.Default.Send, contentDescription = "Send")
                    }
                }
            }
        }
    }
}
