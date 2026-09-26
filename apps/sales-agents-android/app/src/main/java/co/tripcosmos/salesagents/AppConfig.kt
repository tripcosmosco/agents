package co.tripcosmos.salesagents

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Holds the API token in encrypted storage. There is no built-in default token:
 * the agent must paste one generated in WordPress (Integrations > Mobile App Access).
 */
object AppConfig {

    private const val LEGACY_PREFS = "tc_agents_prefs"
    private const val SECURE_PREFS = "tc_agents_secure"
    private const val KEY_TOKEN = "mobile_api_token"
    private val REJECTED_LEGACY_TOKENS = setOf("tc_mobile_secret_2026", "test")

    private var appContext: Context? = null
    private val secure: SharedPreferences by lazy { openSecurePrefs() }

    fun init(context: Context) {
        appContext = context.applicationContext
        migrateLegacyToken()
    }

    fun token(): String {
        if (appContext == null) return ""
        return secure.getString(KEY_TOKEN, "").orEmpty()
    }

    fun setToken(value: String) {
        if (appContext == null) return
        secure.edit().putString(KEY_TOKEN, value.trim()).apply()
    }

    fun isPaired(): Boolean = token().isNotBlank()

    private fun context(): Context = checkNotNull(appContext) { "AppConfig.init() was not called" }

    private fun openSecurePrefs(): SharedPreferences {
        return try {
            createEncrypted()
        } catch (e: Exception) {
            // Keystore state can be corrupted after a restore; start clean rather than crash.
            context().deleteSharedPreferences(SECURE_PREFS)
            try {
                createEncrypted()
            } catch (e2: Exception) {
                context().getSharedPreferences("${SECURE_PREFS}_fallback", Context.MODE_PRIVATE)
            }
        }
    }

    private fun createEncrypted(): SharedPreferences {
        val ctx = context()
        val masterKey = MasterKey.Builder(ctx)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        return EncryptedSharedPreferences.create(
            ctx,
            SECURE_PREFS,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        )
    }

    /** Moves a real token out of the old plain-text prefs and drops the old shared default. */
    private fun migrateLegacyToken() {
        val legacy = context().getSharedPreferences(LEGACY_PREFS, Context.MODE_PRIVATE)
        val old = legacy.getString(KEY_TOKEN, null) ?: return
        if (old.isNotBlank() && old !in REJECTED_LEGACY_TOKENS && secure.getString(KEY_TOKEN, "").isNullOrBlank()) {
            secure.edit().putString(KEY_TOKEN, old).apply()
        }
        legacy.edit().remove(KEY_TOKEN).apply()
    }
}
