package co.tripcosmos.salesagents.data.db

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "leads")
data class LeadEntity(
    @PrimaryKey val id: Long,
    val name: String,
    val phone: String,
    val email: String?,
    val stage: String,
    val dealValue: Double,
    val score: Int,
    val destination: String?,
    val requirements: String?,
    val updatedAt: String?
)

@Entity(tableName = "call_logs")
data class CallLogEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val phone: String,
    val contactName: String?,
    val callType: String, // incoming, outgoing, missed
    val durationSeconds: Long,
    val timestamp: Long = System.currentTimeMillis(),
    val notes: String = "",
    val syncedToCrm: Boolean = false
)
