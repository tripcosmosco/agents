package co.tripcosmos.salesagents.data.api

import co.tripcosmos.salesagents.data.model.*
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Response
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.*
import java.util.concurrent.TimeUnit

interface TripCosmosApiService {

    @GET("mobile/caller-id")
    suspend fun getCallerId(
        @Query("phone") phone: String,
        @Header("X-Mobile-Token") token: String
    ): Response<CallerIdResponse>

    @GET("mobile/leads")
    suspend fun getLeads(
        @Query("stage") stage: String? = null,
        @Header("X-Mobile-Token") token: String
    ): Response<LeadsResponse>

    @POST("mobile/call-log")
    suspend fun logCall(
        @Header("X-Mobile-Token") token: String,
        @Body payload: CallLogPayload
    ): Response<ApiResponse<Any>>

    @POST("mobile/quick-action")
    suspend fun triggerQuickAction(
        @Header("X-Mobile-Token") token: String,
        @Body payload: QuickActionPayload
    ): Response<ApiResponse<Any>>

    companion object {
        private const val DEFAULT_BASE_URL = "https://tripcosmos.co/wp-json/tc-agents/v1/"

        fun create(baseUrl: String = DEFAULT_BASE_URL): TripCosmosApiService {
            val logger = HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BODY
            }

            val client = OkHttpClient.Builder()
                .addInterceptor(logger)
                .connectTimeout(15, TimeUnit.SECONDS)
                .readTimeout(15, TimeUnit.SECONDS)
                .build()

            val normalizedUrl = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"

            return Retrofit.Builder()
                .baseUrl(normalizedUrl)
                .client(client)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
                .create(TripCosmosApiService::class.java)
        }
    }
}
