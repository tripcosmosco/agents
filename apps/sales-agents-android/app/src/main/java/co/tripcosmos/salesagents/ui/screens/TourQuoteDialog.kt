package co.tripcosmos.salesagents.ui.screens

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import androidx.compose.ui.window.DialogProperties
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.GenerateQuotePayload
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TourQuoteDialog(
    initialTravelerName: String = "Traveler",
    initialPhone: String = "",
    initialDestination: String = "varanasi_3d2n",
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val apiService = remember { TripCosmosApiService.create() }

    var travelerName by remember { mutableStateOf(initialTravelerName) }
    var phone by remember { mutableStateOf(initialPhone) }
    var selectedDestination by remember {
        mutableStateOf(
            if (initialDestination.contains("ayodhya", ignoreCase = true) && !initialDestination.contains("4d3n", ignoreCase = true)) {
                "ayodhya_day_trip"
            } else if (initialDestination.contains("prayagraj", ignoreCase = true) || initialDestination.contains("4d3n", ignoreCase = true)) {
                "varanasi_prayagraj_ayodhya_4d3n"
            } else {
                "varanasi_3d2n"
            }
        )
    }
    var selectedTier by remember { mutableStateOf("deluxe") } // deluxe, luxury
    var paxCount by remember { mutableStateOf(2) }
    var travelDates by remember { mutableStateOf("Upcoming Weekend") }

    var isLoading by remember { mutableStateOf(false) }
    var quoteText by remember { mutableStateOf("") }
    var calculatedPrice by remember { mutableStateOf(14500.0) }
    var advanceRequired by remember { mutableStateOf(2000.0) }
    var packageTitle by remember { mutableStateOf("3D2N Spiritual Kashi Tour") }

    // Local instant calculation fallback if offline
    fun fallbackCalculate() {
        val multiplier = kotlin.math.max(1, kotlin.math.ceil(paxCount / 2.0).toInt())
        when (selectedDestination) {
            "ayodhya_day_trip" -> {
                packageTitle = "Varanasi to Ayodhya Ram Mandir Day Excursion"
                calculatedPrice = if (selectedTier == "luxury") 7500.0 else 4500.0
                advanceRequired = 1500.0
                val vehicle = if (selectedTier == "luxury") "Innova Crysta AC (6+1)" else "Swift Dzire AC"
                quoteText = "🚗 *TripCosmos Ayodhya Ram Janmabhoomi Day Excursion*\n\n" +
                        "Namaste ${travelerName.ifBlank { "Traveler" }} ji! 🙏 Here is your customized private cab quote:\n\n" +
                        "• *Vehicle:* $vehicle\n" +
                        "• *Dates:* $travelDates\n" +
                        "• *Travelers:* $paxCount Pax\n" +
                        "• *Inclusions:* Fuel, Highway Tolls, Parking, Driver Allowance.\n" +
                        "• *Sightseeing:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Sarayu Ghat Aarti.\n\n" +
                        "💰 *Total All-Inclusive Fare:* ₹${calculatedPrice.toInt()}\n" +
                        "🔒 *Token Advance to Block Cab:* ₹${advanceRequired.toInt()} via UPI\n" +
                        "👉 *Instant Booking Link:* https://tripcosmos.co/book?ref=ayodhya-${System.currentTimeMillis() / 1000}"
            }
            "varanasi_prayagraj_ayodhya_4d3n" -> {
                packageTitle = "4D3N Sacred Triangle (Varanasi, Prayagraj Sangam & Ayodhya)"
                calculatedPrice = if (selectedTier == "luxury") (28000.0 * multiplier) else (19500.0 * multiplier)
                advanceRequired = 3000.0
                val hotel = if (selectedTier == "luxury") "4-Star Luxury Heritage Hotel" else "3-Star Deluxe Hotel near Ghats"
                quoteText = "🌟 *TripCosmos 4D3N Sacred Triangle Pilgrimage Tour*\n\n" +
                        "Namaste ${travelerName.ifBlank { "Traveler" }} ji! 🙏 Here is your comprehensive spiritual itinerary:\n\n" +
                        "• *Day 1:* Varanasi Arrival, Hotel Check-in, Dashashwamedh Ghat Evening Ganga Aarti with Reserved Boat Seating.\n" +
                        "• *Day 2:* Subah-e-Banaras Sunrise Boat Ride, Kashi Vishwanath VIP Darshan Pass, Annapurna Mandir, Kaal Bhairav, Sarnath Tour.\n" +
                        "• *Day 3:* Early Drive to Prayagraj, Triveni Sangam Holy Snan & Boat, Bade Hanuman Mandir, Anand Bhavan, Drive to Ayodhya & Overnight Hotel.\n" +
                        "• *Day 4:* Ayodhya Shri Ram Janmabhoomi VIP Darshan, Hanuman Garhi, Sarayu Ghat Aarti, Return to Varanasi Drop.\n\n" +
                        "🏨 *Hotel:* $hotel with Daily Breakfast\n" +
                        "🚗 *Vehicle:* Dedicated AC Sedan / Innova throughout\n" +
                        "💰 *Total Package Price ($paxCount Pax):* ₹${calculatedPrice.toInt()}\n" +
                        "🔒 *Token Advance to Secure Booking:* ₹${advanceRequired.toInt()} via UPI\n" +
                        "👉 *Official Booking Link:* https://tripcosmos.co/book?ref=triangle-${System.currentTimeMillis() / 1000}"
            }
            else -> {
                packageTitle = "3D2N Spiritual Kashi Tour"
                calculatedPrice = if (selectedTier == "luxury") (22500.0 * multiplier) else (14500.0 * multiplier)
                advanceRequired = 2000.0
                val hotel = if (selectedTier == "luxury") "4-Star Premium Hotel with Swimming Pool" else "3-Star Deluxe Hotel with Breakfast near Ghats"
                quoteText = "🌟 *TripCosmos 3D2N Spiritual Varanasi Pilgrimage*\n\n" +
                        "Namaste ${travelerName.ifBlank { "Traveler" }} ji! 🙏 Here is your complete private package itinerary:\n\n" +
                        "• *Day 1:* Airport/Station Pickup, Hotel Check-in, Evening Ganga Aarti VIP Boat Cruise at Dashashwamedh Ghat.\n" +
                        "• *Day 2:* Sunrise Morning Boat Ride, Kashi Vishwanath VIP Darshan Pass, Annapurna Temple, Sankat Mochan, BHU, Sarnath Deer Park.\n" +
                        "• *Day 3:* Morning Ghat Walk, Local Banarasi Silk Weaving Tour, Airport Drop.\n\n" +
                        "🏨 *Accommodation:* $hotel\n" +
                        "🚗 *Transportation:* Private AC Cab for all days (Pick to Drop)\n" +
                        "🚤 *Boating:* Private Ghat Boat Cruise included\n" +
                        "💰 *Total All-Inclusive Package ($paxCount Pax):* ₹${calculatedPrice.toInt()}\n" +
                        "🔒 *Token Advance to Confirm Dates:* ₹${advanceRequired.toInt()} via UPI\n" +
                        "👉 *Official Booking Link:* https://tripcosmos.co/book?ref=varanasi-${System.currentTimeMillis() / 1000}"
            }
        }
    }

    // Fetch quotation dynamically from API or local fallback
    fun refreshQuote() {
        coroutineScope.launch {
            isLoading = true
            try {
                val res = withContext(Dispatchers.IO) {
                    apiService.generateQuote(
                        payload = GenerateQuotePayload(
                            destination = selectedDestination,
                            tier = selectedTier,
                            pax = paxCount,
                            customerName = travelerName.ifBlank { "Traveler" },
                            dates = travelDates
                        )
                    )
                }

                if (res.isSuccessful && res.body()?.ok == true) {
                    val data = res.body()!!
                    quoteText = data.quoteText
                    calculatedPrice = data.pricing
                    advanceRequired = data.advanceRequired
                    packageTitle = data.packageTitle
                } else {
                    fallbackCalculate()
                }
            } catch (e: Exception) {
                fallbackCalculate()
            } finally {
                isLoading = false
            }
        }
    }

    // Trigger initial calculation
    LaunchedEffect(selectedDestination, selectedTier, paxCount) {
        refreshQuote()
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 16.dp),
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth(0.95f)
                .wrapContentHeight()
                .clip(RoundedCornerShape(24.dp)),
            colors = CardDefaults.cardColors(containerColor = LightSurface)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp)
                    .verticalScroll(rememberScrollState())
            ) {
                // Header
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(40.dp)
                                .clip(CircleShape)
                                .background(OrangeLight),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(Icons.Default.Calculate, contentDescription = null, tint = OrangePrimary, modifier = Modifier.size(22.dp))
                        }
                        Spacer(modifier = Modifier.width(10.dp))
                        Column {
                            Text("Instant Quote & Itinerary", fontWeight = FontWeight.Bold, fontSize = 17.sp, color = TextPrimary)
                            Text("Dynamic Pricing & Token Advance", fontSize = 11.sp, color = TextSecondary)
                        }
                    }
                    IconButton(onClick = onDismiss) {
                        Icon(Icons.Default.Close, contentDescription = "Close", tint = TextSecondary)
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Traveler Name & Phone Inputs
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedTextField(
                        value = travelerName,
                        onValueChange = { travelerName = it },
                        label = { Text("Traveler Name", fontSize = 12.sp) },
                        modifier = Modifier.weight(1f),
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp)
                    )
                    OutlinedTextField(
                        value = phone,
                        onValueChange = { phone = it },
                        label = { Text("Phone / WhatsApp", fontSize = 12.sp) },
                        modifier = Modifier.weight(1f),
                        singleLine = true,
                        shape = RoundedCornerShape(12.dp)
                    )
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Destination Selection Chips
                Text("Select Pilgrimage Package:", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
                Spacer(modifier = Modifier.height(6.dp))

                val packages = listOf(
                    Triple("varanasi_3d2n", "🕉️ Varanasi 3D2N", "VIP Darshan + Ganga Aarti"),
                    Triple("ayodhya_day_trip", "🚗 Ayodhya Day Trip", "Ram Mandir Excursion Cab"),
                    Triple("varanasi_prayagraj_ayodhya_4d3n", "🌟 Sacred Triangle 4D3N", "Varanasi • Prayagraj • Ayodhya")
                )

                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    packages.forEach { (key, title, subtitle) ->
                        val isSelected = selectedDestination == key
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { selectedDestination = key },
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (isSelected) SuperfoneBlueLight else LightBackground
                            ),
                            border = androidx.compose.foundation.BorderStroke(
                                1.dp,
                                if (isSelected) SuperfoneBlue else CardBorder
                            )
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(10.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                RadioButton(
                                    selected = isSelected,
                                    onClick = { selectedDestination = key },
                                    colors = RadioButtonDefaults.colors(selectedColor = SuperfoneBlue)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Column {
                                    Text(title, fontWeight = FontWeight.Bold, fontSize = 13.sp, color = TextPrimary)
                                    Text(subtitle, fontSize = 10.sp, color = TextSecondary)
                                }
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(14.dp))

                // Tier & Pax Controls Row
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Tier Selector
                    Column {
                        Text("Package Tier:", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
                        Spacer(modifier = Modifier.height(4.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            FilterChip(
                                selected = selectedTier == "deluxe",
                                onClick = { selectedTier = "deluxe" },
                                label = { Text("Deluxe (3★)", fontSize = 11.sp) },
                                colors = FilterChipDefaults.filterChipColors(
                                    selectedContainerColor = SuperfoneBlue,
                                    selectedLabelColor = Color.White
                                )
                            )
                            FilterChip(
                                selected = selectedTier == "luxury",
                                onClick = { selectedTier = "luxury" },
                                label = { Text("Luxury (4★)", fontSize = 11.sp) },
                                colors = FilterChipDefaults.filterChipColors(
                                    selectedContainerColor = OrangePrimary,
                                    selectedLabelColor = Color.White
                                )
                            )
                        }
                    }

                    // Pax Stepper
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Travelers (Pax):", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
                        Spacer(modifier = Modifier.height(4.dp))
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(LightBackground)
                                .padding(2.dp)
                        ) {
                            IconButton(
                                onClick = { if (paxCount > 1) paxCount-- },
                                modifier = Modifier.size(30.dp)
                            ) {
                                Icon(Icons.Default.Remove, contentDescription = "Decrease", modifier = Modifier.size(16.dp))
                            }
                            Text(
                                text = "$paxCount",
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp,
                                modifier = Modifier.padding(horizontal = 8.dp)
                            )
                            IconButton(
                                onClick = { if (paxCount < 20) paxCount++ },
                                modifier = Modifier.size(30.dp)
                            ) {
                                Icon(Icons.Default.Add, contentDescription = "Increase", modifier = Modifier.size(16.dp))
                            }
                        }
                    }
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Calculated Price & Token Highlight Card
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = Color(0xFFF0FDF4)),
                    border = androidx.compose.foundation.BorderStroke(1.dp, Color(0xFFBBF7D0))
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text("Total Package Price ($paxCount Pax)", fontSize = 11.sp, color = Color(0xFF166534))
                                Text(
                                    "₹${calculatedPrice.toInt()}",
                                    fontWeight = FontWeight.ExtraBold,
                                    fontSize = 22.sp,
                                    color = Color(0xFF15803D)
                                )
                            }
                            Column(horizontalAlignment = Alignment.End) {
                                Text("Token Advance", fontSize = 11.sp, color = Color(0xFF166534))
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(6.dp))
                                        .background(Color(0xFFDCFCE7))
                                        .padding(horizontal = 8.dp, vertical = 4.dp)
                                ) {
                                    Text(
                                        "₹${advanceRequired.toInt()} UPI",
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 13.sp,
                                        color = Color(0xFF166534)
                                    )
                                }
                            }
                        }

                        Spacer(modifier = Modifier.height(8.dp))
                        Divider(color = Color(0xFFDCFCE7), thickness = 1.dp)
                        Spacer(modifier = Modifier.height(8.dp))

                        // Preview of Quote Text
                        Text(
                            text = quoteText,
                            fontSize = 11.sp,
                            color = Color(0xFF14532D),
                            lineHeight = 15.sp,
                            maxLines = 6
                        )
                    }
                }

                Spacer(modifier = Modifier.height(18.dp))

                // Actions: Send on WhatsApp & Copy
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    OutlinedButton(
                        onClick = {
                            val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                            val clip = ClipData.newPlainText("TripCosmos Tour Quote", quoteText)
                            clipboard.setPrimaryClip(clip)
                            Toast.makeText(context, "Quote copied to clipboard! 📋", Toast.LENGTH_SHORT).show()
                        },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Copy Quote", fontSize = 13.sp)
                    }

                    Button(
                        onClick = {
                            if (phone.isNotBlank()) {
                                DialerManager.openWhatsAppChat(context, phone, quoteText)
                            } else {
                                DialerManager.openWhatsAppChat(context, "", quoteText)
                            }
                            Toast.makeText(context, "Opening WhatsApp with ready quote! 🚀", Toast.LENGTH_SHORT).show()
                            onDismiss()
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                        modifier = Modifier.weight(1.3f),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Icon(Icons.Default.Send, contentDescription = null, tint = Color.White, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Send on WhatsApp", fontWeight = FontWeight.Bold, fontSize = 13.sp, color = Color.White)
                    }
                }
            }
        }
    }
}
