package co.tripcosmos.salesagents.data.db

import androidx.room.*
import kotlinx.coroutines.flow.Flow

@Dao
interface LeadDao {

    @Query("SELECT * FROM leads ORDER BY updatedAt DESC")
    fun getAllLeads(): Flow<List<LeadEntity>>

    @Query("SELECT * FROM leads WHERE stage = :stage ORDER BY updatedAt DESC")
    fun getLeadsByStage(stage: String): Flow<List<LeadEntity>>

    @Query("SELECT * FROM leads WHERE phone LIKE '%' || :phoneSuffix LIMIT 1")
    suspend fun findByPhoneSuffix(phoneSuffix: String): LeadEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(leads: List<LeadEntity>)

    @Update
    suspend fun update(lead: LeadEntity)

    @Query("DELETE FROM leads")
    suspend fun clearAll()
}

@Dao
interface CallLogDao {

    @Query("SELECT * FROM call_logs ORDER BY timestamp DESC LIMIT 100")
    fun getRecentCalls(): Flow<List<CallLogEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(callLog: CallLogEntity): Long

    @Query("UPDATE call_logs SET syncedToCrm = 1 WHERE id = :id")
    suspend fun markSynced(id: Long)

    @Query("SELECT * FROM call_logs WHERE syncedToCrm = 0")
    suspend fun getUnsyncedCalls(): List<CallLogEntity>
}
