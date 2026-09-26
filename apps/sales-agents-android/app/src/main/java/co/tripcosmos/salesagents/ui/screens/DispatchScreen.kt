package co.tripcosmos.salesagents.ui.screens

import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.background
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import co.tripcosmos.salesagents.data.api.TripCosmosApiService
import co.tripcosmos.salesagents.data.model.DriverDispatchPayload
import co.tripcosmos.salesagents.telephony.DialerManager
import co.tripcosmos.salesagents.ui.theme.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

data class ConfirmedBooking(
    val id: String,
    val customerName: String,
    val phone: String,
    val circuit: String,
    val travelDate: String,
    val pax: Int,
    val tokenPaid: Double,
    val defaultDriver: String = "Santosh Yadav",
    val defaultCabNo: String = "UP65-BT-4219",
    val defaultCabType: String = "Innova Crysta AC",
    var isDispatched: Boolean = false
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DispatchScreen() {
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val apiService = remember { TripCosmosApiService.create() }

    var bookings by remember {
        mutableStateOf(
            listOf(
                ConfirmedBooking(
                    id = "BKG-901",
                    customerName = "Vikram Singh",
                    phone = "9898989898",
                    circuit = "Varanasi 3D2N Spiritual Kashi Tour",
                    travelDate = "Tomorrow, 8:00 AM Pickup",
                    pax = 4,
                    tokenPaid = 3000.0,
                    defaultDriver = "Santosh Yadav",
                    defaultCabNo = "UP65-BT-4219",
                    defaultCabType = "Innova Crysta AC"
                ),
                ConfirmedBooking(
                    id = "BKG-902",
                    customerName = "Pooja Hegde",
                    phone = "9741289012",
                    circuit = "Ayodhya Ram Mandir Day Excursion",
                    travelDate = "24th Sep, 6:00 AM",
                    pax = 3,
                    tokenPaid = 1500.0,
                    defaultDriver = "Rajesh Dubey",
                    defaultCabNo = "UP65-EA-8812",
                    defaultCabType = "Swift Dzire AC"
                ),
                ConfirmedBooking(
                    id = "BKG-903",
                    customerName = "Sunil Agarwal",
                    phone = "9839012345",
                    circuit = "Sacred Triangle 4D3N (Varanasi • Prayagraj • Ayodhya)",
                    travelDate = "26th Sep, 9:00 AM",
                    pax = 6,
                    tokenPaid = 5000.0,
                    defaultDriver = "Ramu Pandey",
                    defaultCabNo = "UP65-CD-1090",
                    defaultCabType = "Innova Crysta AC"
                )
            )
        )
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(LightBackground)
    ) {
        // Summary Header Strip
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text("Fleet & Driver Dispatch", fontWeight = FontWeight.Bold, fontSize = 16.sp, color = TextPrimary)
                Text("${bookings.count { !it.isDispatched }} trips ready for driver assignment", fontSize = 12.sp, color = TextSecondary)
            }
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(Color(0xFFDCFCE7))
                    .padding(horizontal = 8.dp, vertical = 4.dp)
            ) {
                Text("Brevo SMS Live", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF15803D))
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 4.dp, bottom = 24.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(bookings, key = { it.id }) { booking ->
                var driverName by remember { mutableStateOf(booking.defaultDriver) }
                var cabNo by remember { mutableStateOf(booking.defaultCabNo) }
                var isSending by remember { mutableStateOf(false) }

                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = LightSurface),
                    border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder),
                    elevation = CardDefaults.cardElevation(defaultElevation = 0.5.dp)
                ) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        // Header Row: Customer & Token Advance Badge
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Box(
                                    modifier = Modifier
                                        .size(36.dp)
                                        .clip(CircleShape)
                                        .background(SuperfoneBlueLight),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Icon(Icons.Default.DirectionsCar, contentDescription = null, tint = SuperfoneBlue, modifier = Modifier.size(20.dp))
                                }
                                Spacer(modifier = Modifier.width(10.dp))
                                Column {
                                    Text(booking.customerName, fontWeight = FontWeight.Bold, fontSize = 15.sp, color = TextPrimary)
                                    Text(booking.phone, fontSize = 12.sp, color = TextSecondary)
                                }
                            }

                            // Token Advance Badge
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(Color(0xFFDCFCE7))
                                    .padding(horizontal = 8.dp, vertical = 4.dp)
                            ) {
                                Text("Token ₹${booking.tokenPaid.toInt()} Paid ✓", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF166534))
                            }
                        }

                        Spacer(modifier = Modifier.height(8.dp))

                        // Circuit & Dates
                        Text(booking.circuit, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = SuperfoneBlue)
                        Spacer(modifier = Modifier.height(2.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Schedule, contentDescription = null, tint = Color.Gray, modifier = Modifier.size(14.dp))
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(booking.travelDate, fontSize = 12.sp, color = TextSecondary)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("•", color = Color.Gray)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("${booking.pax} Pax", fontSize = 12.sp, fontWeight = FontWeight.Medium, color = TextPrimary)
                        }

                        Spacer(modifier = Modifier.height(10.dp))
                        HorizontalDivider(color = Color(0xFFF1F5F9), thickness = 1.dp)
                        Spacer(modifier = Modifier.height(10.dp))

                        // Driver & Vehicle Assignment Inputs
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = driverName,
                                onValueChange = { driverName = it },
                                label = { Text("Driver Name", fontSize = 10.sp) },
                                modifier = Modifier.weight(1f),
                                singleLine = true,
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = LightBackground, unfocusedContainerColor = LightBackground)
                            )
                            OutlinedTextField(
                                value = cabNo,
                                onValueChange = { cabNo = it },
                                label = { Text("Cab Number", fontSize = 10.sp) },
                                modifier = Modifier.weight(1f),
                                singleLine = true,
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = LightBackground, unfocusedContainerColor = LightBackground)
                            )
                        }

                        Spacer(modifier = Modifier.height(10.dp))

                        // Send Dispatch SMS Button & WhatsApp Call
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            OutlinedButton(
                                onClick = { DialerManager.dialViaCarrierSim(context, booking.phone) },
                                shape = RoundedCornerShape(8.dp),
                                modifier = Modifier
                                    .weight(1f)
                                    .height(36.dp),
                                contentPadding = PaddingValues(horizontal = 8.dp),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = SuperfoneBlue)
                            ) {
                                Icon(Icons.Default.Call, contentDescription = null, modifier = Modifier.size(14.dp))
                                Spacer(modifier = Modifier.width(4.dp))
                                Text("Call", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }

                            Button(
                                onClick = {
                                    coroutineScope.launch {
                                        isSending = true
                                        try {
                                            val res = withContext(Dispatchers.IO) {
                                                apiService.sendDispatch(
                                                    payload = DriverDispatchPayload(
                                                        phone = booking.phone,
                                                        customerName = booking.customerName,
                                                        driverName = driverName,
                                                        vehicleNumber = cabNo,
                                                        vehicleType = booking.defaultCabType
                                                    )
                                                )
                                            }
                                            if (!(res.isSuccessful && res.body()?.ok == true)) throw IllegalStateException("dispatch rejected")
                                            booking.isDispatched = true
                                            Toast.makeText(context, "Driver dispatch SMS sent via Brevo! 📲", Toast.LENGTH_LONG).show()
                                        } catch (e: Exception) {
                                            Toast.makeText(context, "Dispatch NOT sent. Check driver, vehicle, SMS setup and connection.", Toast.LENGTH_LONG).show()
                                        } finally {
                                            isSending = false
                                        }
                                    }
                                },
                                enabled = !isSending,
                                shape = RoundedCornerShape(8.dp),
                                modifier = Modifier
                                    .weight(2f)
                                    .height(36.dp),
                                contentPadding = PaddingValues(horizontal = 8.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = if (booking.isDispatched) Color(0xFF16A34A) else SuperfoneBlue)
                            ) {
                                if (isSending) {
                                    CircularProgressIndicator(modifier = Modifier.size(16.dp), color = Color.White, strokeWidth = 2.dp)
                                } else {
                                    Icon(if (booking.isDispatched) Icons.Default.Check else Icons.Default.Sms, contentDescription = null, tint = Color.White, modifier = Modifier.size(15.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text(if (booking.isDispatched) "Dispatched ✓" else "Send Brevo SMS", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Color.White)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
