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
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.GenerateQuotePayload
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun QuoterScreen(
    initialTravelerName: String = "",
    initialPhone: String = "",
    initialDestination: String = "varanasi_3d2n"
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
    var rateType by remember { mutableStateOf("b2c") } // "b2c" or "b2b"
    var selectedTier by remember { mutableStateOf("deluxe") } // deluxe, luxury
    var paxCount by remember { mutableStateOf(2) }
    var travelDates by remember { mutableStateOf("Upcoming Weekend") }

    var isLoading by remember { mutableStateOf(false) }
    var quoteText by remember { mutableStateOf("") }
    var whiteLabelQuote by remember { mutableStateOf("") }
    var calculatedPrice by remember { mutableStateOf(14500.0) }
    var retailPrice by remember { mutableStateOf(14500.0) }
    var netPrice by remember { mutableStateOf(12000.0) }
    var commission by remember { mutableStateOf(2500.0) }
    var advanceRequired by remember { mutableStateOf(2000.0) }
    var packageTitle by remember { mutableStateOf("3D2N Spiritual Kashi Tour") }

    // Synchronize inputs if changed from parent (e.g. from Radar tab)
    LaunchedEffect(initialTravelerName, initialPhone) {
        if (initialTravelerName.isNotBlank()) travelerName = initialTravelerName
        if (initialPhone.isNotBlank()) phone = initialPhone
    }

    // Instant offline fallback calculations
    fun fallbackCalculate() {
        val multiplier = kotlin.math.max(1, kotlin.math.ceil(paxCount / 2.0).toInt())
        val isLuxury = selectedTier == "luxury"
        val vehicle = if (isLuxury) "Innova Crysta AC" else "Swift Dzire AC"

        when (selectedDestination) {
            "ayodhya_day_trip" -> {
                packageTitle = "Varanasi to Ayodhya Ram Mandir Day Excursion"
                retailPrice = if (isLuxury) 7500.0 else 4500.0
                netPrice = if (isLuxury) 6200.0 else 3800.0
                advanceRequired = 1500.0

                val vDesc = if (isLuxury) "Innova Crysta AC (6+1)" else "Swift Dzire AC"
                quoteText = "🚗 *TripCosmos Ayodhya Ram Janmabhoomi Day Excursion*\n\n" +
                        "Namaste ${travelerName.ifBlank { "Traveler" }} ji! 🙏 Here is your customized private cab quote:\n\n" +
                        "• *Vehicle:* $vDesc\n" +
                        "• *Dates:* $travelDates\n" +
                        "• *Travelers:* $paxCount Pax\n" +
                        "• *Inclusions:* Fuel, Highway Tolls, Parking, Driver Allowance.\n" +
                        "• *Sightseeing:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Sarayu Ghat Aarti.\n\n" +
                        "💰 *Total All-Inclusive Fare:* ₹${retailPrice.toInt()}\n" +
                        "🔒 *Token Advance to Block Cab:* ₹${advanceRequired.toInt()} via UPI\n" +
                        "👉 *Instant Booking Link:* https://tripcosmos.co/book?ref=ayodhya-${System.currentTimeMillis() / 1000}"

                whiteLabelQuote = "🚗 *Ayodhya Ram Janmabhoomi Day Tour Itinerary*\n\n" +
                        "Guest: ${travelerName.ifBlank { "Traveler" }} | Travelers: $paxCount Pax | Dates: $travelDates\n\n" +
                        "• *Sightseeing:* Shri Ram Janmabhoomi Mandir, Hanuman Garhi, Kanak Bhavan, Sarayu Ghat Aarti.\n" +
                        "• *Vehicle:* Dedicated private $vDesc (all tolls, parking, fuel included).\n\n" +
                        "💰 *All-Inclusive Package Price ($paxCount Pax):* ₹${retailPrice.toInt()}"
            }
            "varanasi_prayagraj_ayodhya_4d3n" -> {
                packageTitle = "4D3N Sacred Triangle (Varanasi, Prayagraj Sangam & Ayodhya)"
                retailPrice = if (isLuxury) (28000.0 * multiplier) else (19500.0 * multiplier)
                netPrice = if (isLuxury) (23000.0 * multiplier) else (16000.0 * multiplier)
                advanceRequired = 3000.0
                val hotel = if (isLuxury) "4-Star Luxury Heritage Hotel" else "3-Star Deluxe Hotel near Ghats"

                quoteText = "🌟 *TripCosmos 4D3N Sacred Triangle Pilgrimage Tour*\n\n" +
                        "Namaste ${travelerName.ifBlank { "Traveler" }} ji! 🙏 Here is your comprehensive spiritual itinerary:\n\n" +
                        "• *Day 1:* Varanasi Arrival, Hotel Check-in, Dashashwamedh Ghat Evening Ganga Aarti with Reserved Boat Seating.\n" +
                        "• *Day 2:* Subah-e-Banaras Sunrise Boat Ride, Kashi Vishwanath VIP Darshan Pass, Annapurna Mandir, Kaal Bhairav, Sarnath Tour.\n" +
                        "• *Day 3:* Early Drive to Prayagraj, Triveni Sangam Holy Snan & Boat, Bade Hanuman Mandir, Anand Bhavan, Drive to Ayodhya & Overnight Hotel.\n" +
                        "• *Day 4:* Ayodhya Shri Ram Janmabhoomi VIP Darshan, Hanuman Garhi, Sarayu Ghat Aarti, Return to Varanasi Drop.\n\n" +
                        "🏨 *Hotel:* $hotel with Daily Breakfast\n" +
                        "🚗 *Vehicle:* Dedicated AC Sedan / Innova throughout\n" +
                        "💰 *Total Package Price ($paxCount Pax):* ₹${retailPrice.toInt()}\n" +
                        "🔒 *Token Advance to Secure Booking:* ₹${advanceRequired.toInt()} via UPI\n" +
                        "👉 *Official Booking Link:* https://tripcosmos.co/book?ref=triangle-${System.currentTimeMillis() / 1000}"

                whiteLabelQuote = "🌟 *4D3N Sacred Triangle Pilgrimage Itinerary*\n" +
                        "(Varanasi • Prayagraj Sangam • Ayodhya Ram Mandir)\n\n" +
                        "Guest: ${travelerName.ifBlank { "Traveler" }} | Travelers: $paxCount Pax | Dates: $travelDates\n\n" +
                        "• *Day 1:* Varanasi Arrival, Ghat transfer, Evening Ganga Aarti boat cruise with reserved seating.\n" +
                        "• *Day 2:* Sunrise boat cruise, Kashi Vishwanath VIP Darshan Pass, Annapurna Temple, Kaal Bhairav, Sarnath Tour.\n" +
                        "• *Day 3:* Triveni Sangam Holy Snan at Prayagraj, Bade Hanuman Mandir, Anand Bhavan, Drive to Ayodhya & Overnight stay.\n" +
                        "• *Day 4:* Ayodhya Shri Ram Janmabhoomi Darshan, Hanuman Garhi, Sarayu Aarti, Return to Varanasi Drop.\n\n" +
                        "🏨 *Accommodation:* $hotel (with Breakfast)\n" +
                        "🚗 *Transportation:* Private $vehicle\n" +
                        "💰 *Package Price ($paxCount Pax):* ₹${retailPrice.toInt()}"
            }
            else -> {
                packageTitle = "3D2N Spiritual Kashi Tour"
                retailPrice = if (isLuxury) (22500.0 * multiplier) else (14500.0 * multiplier)
                netPrice = if (isLuxury) (18500.0 * multiplier) else (12000.0 * multiplier)
                advanceRequired = 2000.0
                val hotel = if (isLuxury) "4-Star Premium Hotel with Swimming Pool" else "3-Star Deluxe Hotel with Breakfast near Ghats"

                quoteText = "🌟 *TripCosmos 3D2N Spiritual Varanasi Pilgrimage*\n\n" +
                        "Namaste ${travelerName.ifBlank { "Traveler" }} ji! 🙏 Here is your complete private package itinerary:\n\n" +
                        "• *Day 1:* Airport/Station Pickup, Hotel Check-in, Evening Ganga Aarti VIP Boat Cruise at Dashashwamedh Ghat.\n" +
                        "• *Day 2:* Sunrise Morning Boat Ride, Kashi Vishwanath VIP Darshan Pass, Annapurna Temple, Sankat Mochan, BHU, Sarnath Deer Park.\n" +
                        "• *Day 3:* Morning Ghat Walk, Local Banarasi Silk Weaving Tour, Airport Drop.\n\n" +
                        "🏨 *Accommodation:* $hotel\n" +
                        "🚗 *Transportation:* Private AC Cab for all days (Pick to Drop)\n" +
                        "🚤 *Boating:* Private Ghat Boat Cruise included\n" +
                        "💰 *Total All-Inclusive Package ($paxCount Pax):* ₹${retailPrice.toInt()}\n" +
                        "🔒 *Token Advance to Confirm Dates:* ₹${advanceRequired.toInt()} via UPI\n" +
                        "👉 *Official Booking Link:* https://tripcosmos.co/book?ref=varanasi-${System.currentTimeMillis() / 1000}"

                whiteLabelQuote = "🌟 *3D2N Spiritual Varanasi Pilgrimage Itinerary*\n\n" +
                        "Guest: ${travelerName.ifBlank { "Traveler" }} | Travelers: $paxCount Pax | Dates: $travelDates\n\n" +
                        "• *Day 1:* Airport/Station Pickup, Hotel Check-in, Evening Ganga Aarti VIP Boat Cruise.\n" +
                        "• *Day 2:* Sunrise Ganga Boat Ride, Kashi Vishwanath VIP Darshan, Annapurna Temple, Sankat Mochan, Sarnath Tour.\n" +
                        "• *Day 3:* Morning Ghat Heritage Walk, Banarasi Silk Weaving, Airport Drop.\n\n" +
                        "🏨 *Hotel:* $hotel (Breakfast included)\n" +
                        "🚗 *Vehicle:* Private $vehicle\n" +
                        "💰 *Package Price ($paxCount Pax):* ₹${retailPrice.toInt()}"
            }
        }

        commission = retailPrice - netPrice
        calculatedPrice = if (rateType == "b2b") netPrice else retailPrice
        if (rateType == "b2b") {
            quoteText = whiteLabelQuote
        }
    }

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
                            dates = travelDates,
                            rateType = rateType
                        )
                    )
                }

                if (res.isSuccessful && res.body()?.ok == true) {
                    val data = res.body()!!
                    quoteText = data.quoteText
                    whiteLabelQuote = data.whiteLabelQuote
                    calculatedPrice = data.pricing
                    retailPrice = if (data.retailPrice > 0) data.retailPrice else data.pricing
                    netPrice = if (data.netPrice > 0) data.netPrice else (data.pricing * 0.85)
                    commission = if (data.commission > 0) data.commission else (retailPrice - netPrice)
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

    LaunchedEffect(selectedDestination, selectedTier, paxCount, rateType) {
        refreshQuote()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(LightBackground)
            .padding(horizontal = 16.dp)
            .verticalScroll(rememberScrollState())
    ) {
        Spacer(modifier = Modifier.height(12.dp))

        // Channel Mode Switcher: B2C Direct vs B2B Wholesale
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(Color(0xFFF1F5F9))
                .padding(3.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Box(
                modifier = Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(10.dp))
                    .background(if (rateType == "b2c") Color.White else Color.Transparent)
                    .clickable { rateType = "b2c" }
                    .padding(vertical = 8.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    "B2C Direct Traveler",
                    fontSize = 12.sp,
                    fontWeight = if (rateType == "b2c") FontWeight.Bold else FontWeight.Medium,
                    color = if (rateType == "b2c") SuperfoneBlue else TextSecondary
                )
            }
            Box(
                modifier = Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(10.dp))
                    .background(if (rateType == "b2b") OrangePrimary else Color.Transparent)
                    .clickable { rateType = "b2b" }
                    .padding(vertical = 8.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    "B2B Partner Agent",
                    fontSize = 12.sp,
                    fontWeight = if (rateType == "b2b") FontWeight.Bold else FontWeight.Medium,
                    color = if (rateType == "b2b") Color.White else TextSecondary
                )
            }
        }

        Spacer(modifier = Modifier.height(14.dp))

        // Traveler Name & Phone
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedTextField(
                value = travelerName,
                onValueChange = { travelerName = it },
                label = { Text("Traveler / Agent Name", fontSize = 11.sp) },
                modifier = Modifier.weight(1f),
                singleLine = true,
                shape = RoundedCornerShape(10.dp),
                colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = LightSurface, unfocusedContainerColor = LightSurface)
            )
            OutlinedTextField(
                value = phone,
                onValueChange = { phone = it },
                label = { Text("WhatsApp Number", fontSize = 11.sp) },
                modifier = Modifier.weight(1f),
                singleLine = true,
                shape = RoundedCornerShape(10.dp),
                colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = LightSurface, unfocusedContainerColor = LightSurface)
            )
        }

        Spacer(modifier = Modifier.height(14.dp))

        // Circuit Selection
        Text("Select Pilgrimage Circuit:", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
        Spacer(modifier = Modifier.height(6.dp))

        val circuits = listOf(
            Triple("varanasi_3d2n", "🕉️ Varanasi 3D2N Tour", "VIP Darshan + Ganga Aarti"),
            Triple("ayodhya_day_trip", "🚗 Ayodhya Day Trip Cab", "Ram Janmabhoomi Excursion"),
            Triple("varanasi_prayagraj_ayodhya_4d3n", "🌟 Sacred Triangle 4D3N", "Varanasi • Sangam • Ayodhya")
        )

        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            circuits.forEach { (key, title, subtitle) ->
                val isSelected = selectedDestination == key
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { selectedDestination = key },
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = if (isSelected) SuperfoneBlueLight else LightSurface),
                    border = androidx.compose.foundation.BorderStroke(1.dp, if (isSelected) SuperfoneBlue else CardBorder)
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
                Text("Tier & Vehicle:", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = TextSecondary)
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
                        .background(LightSurface)
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

        // Price & Margins Card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = if (rateType == "b2b") Color(0xFFEFF6FF) else Color(0xFFF0FDF4)),
            border = androidx.compose.foundation.BorderStroke(1.dp, if (rateType == "b2b") Color(0xFFBFDBFE) else Color(0xFFBBF7D0))
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            if (rateType == "b2b") "Net Wholesale Cost ($paxCount Pax)" else "Total Package Fare ($paxCount Pax)",
                            fontSize = 11.sp,
                            color = if (rateType == "b2b") SuperfoneBlue else Color(0xFF166534)
                        )
                        Text(
                            "₹${calculatedPrice.toInt()}",
                            fontWeight = FontWeight.ExtraBold,
                            fontSize = 22.sp,
                            color = if (rateType == "b2b") SuperfoneBlue else Color(0xFF15803D)
                        )
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Token Advance", fontSize = 11.sp, color = if (rateType == "b2b") SuperfoneBlue else Color(0xFF166534))
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(if (rateType == "b2b") Color(0xFFDBEAFE) else Color(0xFFDCFCE7))
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text(
                                "₹${advanceRequired.toInt()} UPI",
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.sp,
                                color = if (rateType == "b2b") SuperfoneBlue else Color(0xFF166534)
                            )
                        }
                    }
                }

                // B2B Wholesale Profit Margin Breakdown
                if (rateType == "b2b") {
                    Spacer(modifier = Modifier.height(10.dp))
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(Color.White)
                            .padding(8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Column {
                            Text("Retail to Client", fontSize = 10.sp, color = Color.DarkGray)
                            Text("₹${retailPrice.toInt()}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.Black)
                        }
                        Column {
                            Text("Net B2B Cost", fontSize = 10.sp, color = Color.DarkGray)
                            Text("₹${netPrice.toInt()}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = SuperfoneBlue)
                        }
                        Column(horizontalAlignment = Alignment.End) {
                            Text("Partner Margin", fontSize = 10.sp, color = Color(0xFF15803D))
                            Text("+₹${commission.toInt()}", fontSize = 12.sp, fontWeight = FontWeight.ExtraBold, color = Color(0xFF15803D))
                        }
                    }
                }

                Spacer(modifier = Modifier.height(8.dp))
                HorizontalDivider(color = if (rateType == "b2b") Color(0xFFDBEAFE) else Color(0xFFDCFCE7), thickness = 1.dp)
                Spacer(modifier = Modifier.height(8.dp))

                // Preview of Quote Text
                Text(
                    text = quoteText,
                    fontSize = 11.sp,
                    color = if (rateType == "b2b") Color(0xFF1E3A8A) else Color(0xFF14532D),
                    lineHeight = 15.sp,
                    maxLines = 6
                )
            }
        }

        Spacer(modifier = Modifier.height(14.dp))

        // High Impact Action Buttons: WhatsApp, Copy, White-Label
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            OutlinedButton(
                onClick = {
                    val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                    val clip = ClipData.newPlainText("Tour Quote", quoteText)
                    clipboard.setPrimaryClip(clip)
                    Toast.makeText(context, "Quote copied! 📋", Toast.LENGTH_SHORT).show()
                },
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.Default.ContentCopy, contentDescription = null, modifier = Modifier.size(15.dp))
                Spacer(modifier = Modifier.width(4.dp))
                Text("Copy Quote", fontSize = 12.sp)
            }

            if (rateType == "b2b") {
                OutlinedButton(
                    onClick = {
                        val clipboard = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                        val clip = ClipData.newPlainText("White Label Itinerary", whiteLabelQuote.ifBlank { quoteText })
                        clipboard.setPrimaryClip(clip)
                        Toast.makeText(context, "Unbranded itinerary copied! 📄", Toast.LENGTH_SHORT).show()
                    },
                    modifier = Modifier.weight(1.1f),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = OrangePrimary)
                ) {
                    Icon(Icons.Default.Description, contentDescription = null, modifier = Modifier.size(15.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("White-Label", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }

            Button(
                onClick = {
                    if (phone.isNotBlank()) {
                        DialerManager.openWhatsAppChat(context, phone, quoteText)
                    } else {
                        DialerManager.openWhatsAppChat(context, "", quoteText)
                    }
                    Toast.makeText(context, "Opening WhatsApp with quote! 🚀", Toast.LENGTH_SHORT).show()
                },
                colors = ButtonDefaults.buttonColors(containerColor = WhatsAppGreen),
                modifier = Modifier.weight(1.2f),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.Default.Send, contentDescription = null, tint = Color.White, modifier = Modifier.size(15.dp))
                Spacer(modifier = Modifier.width(4.dp))
                Text("WhatsApp", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Color.White)
            }
        }

        Spacer(modifier = Modifier.height(28.dp))
    }
}
