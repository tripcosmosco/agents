package co.tripcosmos.salesagents.data.model

import com.google.gson.annotations.SerializedName

data class Lead(
    val id: Long,
    val name: String,
    val phone: String,
    val email: String? = null,
    val stage: String = "inquiry", // inquiry, qualified, proposal, negotiation, won, lost
    @SerializedName("deal_value") val dealValue: Double = 0.0,
    @SerializedName("score") val score: Int = 50,
    val destination: String? = null,
    val requirements: String? = null,
    @SerializedName("updated_at") val updatedAt: String? = null
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
