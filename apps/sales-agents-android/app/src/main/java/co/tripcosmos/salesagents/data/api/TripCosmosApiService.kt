package co.tripcosmos.salesagents.data.api

import co.tripcosmos.salesagents.data.model.*
import co.tripcosmos.salesagents.AppConfig
import co.tripcosmos.salesagents.BuildConfig
import okhttp3.Interceptor
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

    @GET("mobile/whatsapp-leads")
    suspend fun getWhatsAppLeads(
        @Header("X-Mobile-Token") token: String = ""
    ): Response<WhatsAppLeadsResponse>

    @POST("mobile/assign-lead")
    suspend fun assignLead(
        @Header("X-Mobile-Token") token: String = "",
        @Body payload: AssignLeadPayload
    ): Response<ApiResponse<Any>>

    @POST("mobile/generate-quote")
    suspend fun generateQuote(
        @Header("X-Mobile-Token") token: String = "",
        @Body payload: GenerateQuotePayload
    ): Response<QuoteResponse>

    @POST("mobile/send-dispatch")
    suspend fun sendDispatch(
        @Header("X-Mobile-Token") token: String = "",
        @Body payload: DriverDispatchPayload
    ): Response<DriverDispatchResponse>


    companion object {
        private const val DEFAULT_BASE_URL = "https://tripcosmos.co/wp-json/tc-agents/v1/"

        fun create(baseUrl: String = DEFAULT_BASE_URL): TripCosmosApiService {
            val logger = HttpLoggingInterceptor().apply {
                level = if (BuildConfig.DEBUG) HttpLoggingInterceptor.Level.BASIC else HttpLoggingInterceptor.Level.NONE
                redactHeader("X-Mobile-Token")
            }

            val authInterceptor = Interceptor { chain ->
                val builder = chain.request().newBuilder()
                val token = AppConfig.token()
                if (token.isNotBlank()) builder.header("X-Mobile-Token", token)
                chain.proceed(builder.build())
            }

            val client = OkHttpClient.Builder()
                .addInterceptor(authInterceptor)
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
