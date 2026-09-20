package co.tripcosmos.salesagents.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Superfone Brand Palette & Light Aesthetic
val SuperfoneBlue = Color(0xFF2563EB)
val SuperfoneBlueDark = Color(0xFF1D4ED8)
val SuperfoneBlueLight = Color(0xFFEFF6FF)
val OrangePrimary = Color(0xFFF97316)
val OrangeSecondary = Color(0xFFEA580C)
val WhatsAppGreen = Color(0xFF25D366)
val WhatsAppDark = Color(0xFF128C7E)
val LightBackground = Color(0xFFF8FAFC)
val LightSurface = Color(0xFFFFFFFF)
val CardBorder = Color(0xFFE2E8F0)
val TextPrimary = Color(0xFF0F172A)
val TextSecondary = Color(0xFF64748B)

// Superfone Pill Badges
val PillCyanBg = Color(0xFFE0F2FE)
val PillCyanText = Color(0xFF0369A1)
val PillAmberBg = Color(0xFFFEF3C7)
val PillAmberText = Color(0xFFB45309)
val PillPurpleBg = Color(0xFFF3E8FF)
val PillPurpleText = Color(0xFF7E22CE)
val PillGreenBg = Color(0xFFDCFCE7)
val PillGreenText = Color(0xFF15803D)
val PillRoseBg = Color(0xFFFFE4E6)
val PillRoseText = Color(0xFFBE123C)

// AI Gradient Accent
val AiGradientPink = Color(0xFFEC4899)
val AiGradientPurple = Color(0xFF8B5CF6)
val AiSparkle = Color(0xFFF59E0B)

private val SuperfoneLightColorScheme = lightColorScheme(
    primary = SuperfoneBlue,
    onPrimary = Color.White,
    primaryContainer = SuperfoneBlueLight,
    onPrimaryContainer = SuperfoneBlueDark,
    secondary = OrangePrimary,
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFFFEDD5),
    onSecondaryContainer = OrangeSecondary,
    tertiary = WhatsAppGreen,
    background = LightBackground,
    onBackground = TextPrimary,
    surface = LightSurface,
    onSurface = TextPrimary,
    surfaceVariant = Color(0xFFF1F5F9),
    onSurfaceVariant = TextSecondary,
    outline = CardBorder
)

@Composable
fun SalesAgentsTheme(
    content: @Composable () -> Unit
) {
    // Force Superfone clean Light Mode for crisp readability and professional CRM look
    MaterialTheme(
        colorScheme = SuperfoneLightColorScheme,
        content = content
    )
}
