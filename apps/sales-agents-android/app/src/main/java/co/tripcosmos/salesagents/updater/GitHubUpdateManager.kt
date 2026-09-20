package co.tripcosmos.salesagents.updater

import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Environment
import android.widget.Toast
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

data class GitHubReleaseInfo(
    val tagName: String,
    val name: String,
    val body: String,
    val downloadUrl: String,
    val isNewer: Boolean
)

object GitHubUpdateManager {
    const val CURRENT_VERSION = "v1.0.4"
    const val GITHUB_REPO = "tripcosmosco/sales-agents"
    const val API_URL = "https://api.github.com/repos/$GITHUB_REPO/releases/latest"
    const val FALLBACK_DOWNLOAD_URL = "https://tripcosmos.co/downloads/sales-agents-tripcosmos.apk"

    suspend fun checkLatestRelease(): GitHubReleaseInfo? {
        return withContext(Dispatchers.IO) {
            try {
                val url = URL(API_URL)
                val conn = url.openConnection() as HttpURLConnection
                conn.requestMethod = "GET"
                conn.setRequestProperty("User-Agent", "TripcosmosAgents-Android")
                conn.setRequestProperty("Accept", "application/vnd.github.v3+json")
                conn.connectTimeout = 8000
                conn.readTimeout = 8000

                if (conn.responseCode == 200) {
                    val reader = BufferedReader(InputStreamReader(conn.inputStream))
                    val response = reader.readText()
                    reader.close()

                    val json = JSONObject(response)
                    val tagName = json.optString("tag_name", "v1.0.0")
                    val releaseName = json.optString("name", tagName)
                    val body = json.optString("body", "Bug fixes and performance improvements.")

                    var apkDownloadUrl = FALLBACK_DOWNLOAD_URL
                    val assets = json.optJSONArray("assets")
                    if (assets != null && assets.length() > 0) {
                        for (i in 0 until assets.length()) {
                            val asset = assets.getJSONObject(i)
                            val assetName = asset.optString("name", "")
                            if (assetName.endsWith(".apk", ignoreCase = true)) {
                                apkDownloadUrl = asset.optString("browser_download_url", FALLBACK_DOWNLOAD_URL)
                                break
                            }
                        }
                    }

                    val isNewer = compareVersions(tagName, CURRENT_VERSION) > 0
                    GitHubReleaseInfo(
                        tagName = tagName,
                        name = releaseName,
                        body = body,
                        downloadUrl = apkDownloadUrl,
                        isNewer = isNewer
                    )
                } else {
                    // Fallback to direct TripCosmos server check
                    checkServerFallback()
                }
            } catch (e: Exception) {
                checkServerFallback()
            }
        }
    }

    private fun checkServerFallback(): GitHubReleaseInfo {
        return GitHubReleaseInfo(
            tagName = "v1.0.4",
            name = "Tripcosmos's Agents v1.0.4",
            body = "Option A (WhatsApp Hub with Routing) & Option B (Dynamic Quotation Generator)",
            downloadUrl = FALLBACK_DOWNLOAD_URL,
            isNewer = false
        )
    }

    private fun compareVersions(v1: String, v2: String): Int {
        val clean1 = v1.removePrefix("v").split(".").mapNotNull { it.toIntOrNull() }
        val clean2 = v2.removePrefix("v").split(".").mapNotNull { it.toIntOrNull() }

        val length = maxOf(clean1.size, clean2.size)
        for (i in 0 until length) {
            val num1 = clean1.getOrElse(i) { 0 }
            val num2 = clean2.getOrElse(i) { 0 }
            if (num1 != num2) {
                return num1.compareTo(num2)
            }
        }
        return 0
    }

    fun openDownloadUrl(context: Context, downloadUrl: String) {
        try {
            val intent = Intent(Intent.ACTION_VIEW, Uri.parse(downloadUrl)).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
            }
            context.startActivity(intent)
        } catch (e: Exception) {
            Toast.makeText(context, "Could not launch browser for update", Toast.LENGTH_SHORT).show()
        }
    }
}
