package co.tripcosmos.salesagents.data.model

import com.google.gson.annotations.SerializedName

data class Lead(
    val id: Long = 0L,
    val name: String = "Traveler",
    val phone: String = "",
    val email: String? = null,
    val stage: String = "inquiry", // inquiry, qualified, proposal, negotiation, won, lost
    @SerializedName("deal_value") val dealValue: Double = 0.0,
    @SerializedName("score") val score: Int = 50,
    val destination: String? = null,
    val requirements: String? = null,
    val owner: String = "Ajay Verma", // Team assignment (Ajay Verma, Meera Singh, Rahul Sharma, Travel Desk)
    val tags: List<String> = listOf("New Lead", "Travel Package"),
    @SerializedName("created_at") val createdAt: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null
)

data class LeadsResponse(
    val ok: Boolean = true,
    val leads: List<Lead> = emptyList()
)

data class CallerIdContact(
    val id: Long,
    val name: String,
    val phone: String,
    val email: String?,
    val stage: String,
    @SerializedName("lead_score") val leadScore: Int,
    @SerializedName("deal_value") val dealValue: Double,
    val destination: String?,
    val requirements: String?,
    @SerializedName("ai_summary") val aiSummary: String?,
    @SerializedName("next_best_action") val nextBestAction: String?
)

data class CallerIdResponse(
    val ok: Boolean,
    val found: Boolean,
    val phone: String? = null,
    val hint: String? = null,
    val contact: CallerIdContact? = null
)

data class CallLogPayload(
    val phone: String,
    @SerializedName("call_type") val callType: String, // incoming, outgoing, missed
    @SerializedName("duration_seconds") val durationSeconds: Long,
    val notes: String = "",
    @SerializedName("recording_url") val recordingUrl: String = ""
)

data class QuickActionPayload(
    @SerializedName("contact_id") val contactId: Long,
    @SerializedName("action_type") val actionType: String, // send_itinerary, send_tariff, send_darshan_guide
    val phone: String
)

data class ApiResponse<T>(
    val ok: Boolean,
    val message: String? = null,
    val data: T? = null
)

// Superfone Task Model
data class LeadTask(
    val id: String = java.util.UUID.randomUUID().toString(),
    val title: String,
    val leadName: String,
    val phone: String,
    val dueDate: String,
    val isCompleted: Boolean = false,
    val assignedTo: String = "Ajay Verma",
    val priority: String = "Normal" // High, Normal
)

// Superfone Master Chatbot Message Model
data class ChatMessage(
    val id: String = java.util.UUID.randomUUID().toString(),
    val text: String,
    val isFromUser: Boolean,
    val timestamp: String = "Just now",
    val suggestedWhatsAppText: String? = null
)

// Superfone AI Call Summary Model
data class AiCallSummary(
    val id: String = java.util.UUID.randomUUID().toString(),
    val customerName: String,
    val phone: String,
    val callTime: String,
    val duration: String,
    val destination: String,
    val travelers: String,
    val dates: String,
    val budget: String,
    val tags: List<String>,
    val reminder: String,
    val nextAction: String,
    val assignedAgent: String = "Ajay Verma"
)

// Superfone WhatsApp Lead & Manager Assignment Model
data class WhatsAppLead(
    val id: String = java.util.UUID.randomUUID().toString(),
    val customerName: String,
    val phone: String,
    val lastMessage: String,
    val timeAgo: String,
    val unreadCount: Int = 0,
    val tourInterest: String = "Varanasi Spiritual Tour",
    val estimatedBudget: Double = 15000.0,
    val assignedManager: String? = null, // null if unassigned
    val leadScore: Int = 75, // 0-100
    val status: String = "new" // new, assigned, quoted, converted
)
