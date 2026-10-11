<?php
/**
 * Admin Agent Personas and Tools View - Modern Celestial Design.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_personas = $wpdb->prefix . 'tc_agent_personas';

$edit_id = absint( $_GET['edit'] ?? 0 );
$agent   = null;

if ( $edit_id > 0 ) {
	$agent = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_personas WHERE id = %d", $edit_id ), ARRAY_A );
}

$all_agents = $wpdb->get_results( "SELECT * FROM $table_personas ORDER BY id ASC", ARRAY_A ) ?: array();
$available_tools = TC_Agent_Tools::get_definitions();
$saved_tools = array();
if ( $agent && ! empty( $agent['allowed_tools'] ) ) {
	$saved_tools = json_decode( $agent['allowed_tools'], true ) ?: array();
}
?>

<style>
/* Modern Luxury Design Tokens - Self Contained */
.tc-personas-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1260px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-personas-hero {
	background: linear-gradient(135deg, #0a0f1d 0%, #111e38 60%, #1e1b4b 100%);
	border-radius: 20px;
	padding: 28px 36px;
	color: #ffffff;
	box-shadow: 0 10px 30px rgba(10, 15, 29, 0.25);
	margin-bottom: 28px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	border: 1px solid rgba(245, 158, 11, 0.2);
}
.tc-personas-hero h1 {
	font-size: 26px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
	letter-spacing: -0.5px;
}
.tc-personas-hero p {
	margin: 0;
	color: #94a3b8;
	font-size: 14px;
	max-width: 650px;
	line-height: 1.5;
}
.tc-badge-gold {
	background: linear-gradient(135deg, #f59e0b, #d97706);
	color: #0f172a;
	font-weight: 800;
	font-size: 11px;
	padding: 4px 10px;
	border-radius: 20px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.tc-card-lux {
	background: #ffffff;
	border-radius: 16px;
	border: 1px solid #e2e8f0;
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
	padding: 24px;
	margin-bottom: 24px;
	transition: all 0.2s ease;
}
.tc-card-lux:hover {
	box-shadow: 0 8px 26px rgba(0, 0, 0, 0.07);
	border-color: #cbd5e1;
}
.tc-card-header-flex {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 14px;
}
.tc-card-title {
	font-size: 17px;
	font-weight: 700;
	color: #0f172a;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tc-grid-layout {
	display: grid;
	grid-template-columns: 1.25fr 0.95fr;
	gap: 24px;
}
@media (max-width: 1080px) {
	.tc-grid-layout { grid-template-columns: 1fr; }
}

.tc-table-lux {
	width: 100%;
	border-collapse: collapse;
	font-size: 13px;
	margin-top: 8px;
}
.tc-table-lux th {
	background: #f8fafc;
	color: #475569;
	font-weight: 700;
	text-align: left;
	padding: 10px 14px;
	border-bottom: 1.5px solid #e2e8f0;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
.tc-table-lux td {
	padding: 12px 14px;
	border-bottom: 1px solid #f1f5f9;
	color: #1e293b;
	vertical-align: middle;
}
.tc-table-lux tr:hover td {
	background: #f8fafc;
}

.tc-tag-pill {
	display: inline-block;
	background: #f1f5f9;
	color: #475569;
	padding: 2px 7px;
	border-radius: 6px;
	font-size: 11px;
	font-family: monospace;
	margin: 2px;
	border: 1px solid #e2e8f0;
}
.tc-badge-active {
	background: #dcfce7;
	color: #15803d;
	padding: 3px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 700;
}
.tc-badge-disabled {
	background: #fee2e2;
	color: #b91c1c;
	padding: 3px 8px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 700;
}

.tc-input-lux, select.tc-input-lux, textarea.tc-input-lux {
	width: 100%;
	padding: 9px 12px;
	border: 1.5px solid #cbd5e1;
	border-radius: 8px;
	font-size: 13px;
	color: #1e293b;
	background: #f8fafc;
	outline: none;
	transition: all 0.2s ease;
}
.tc-input-lux:focus, select.tc-input-lux:focus, textarea.tc-input-lux:focus {
	border-color: #f59e0b;
	background: #ffffff;
	box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}

.tc-btn-gold {
	background: linear-gradient(135deg, #f59e0b, #d97706);
	color: #ffffff !important;
	border: none;
	font-weight: 700;
	padding: 10px 20px;
	border-radius: 8px;
	cursor: pointer;
	box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
	text-decoration: none;
}
.tc-btn-gold:hover {
	transform: translateY(-1px);
	box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
	color: #ffffff;
}
.tc-btn-secondary {
	background: #f1f5f9;
	color: #334155;
	border: 1px solid #cbd5e1;
	font-weight: 600;
	padding: 8px 14px;
	border-radius: 8px;
	cursor: pointer;
	font-size: 12.5px;
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	text-decoration: none;
}
.tc-btn-secondary:hover {
	background: #e2e8f0;
	color: #0f172a;
}
</style>

<div class="wrap tc-personas-wrap">
	<!-- Hero -->
	<div class="tc-personas-hero">
		<div>
			<h1>
				<span>🧙‍♂️</span>
				Agent Personas &amp; Cognitive Directives
				<span class="tc-badge-gold">AI Core</span>
			</h1>
			<p>Configure specialized concierge personas, system instructions, temperature limits, and allowed function tool surfaces for web, WhatsApp, and voice channels.</p>
		</div>
		<div>
			<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="tc-btn-gold">
				<span>+</span> New Persona
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; color: #065f46; font-weight: 600;">
			✓ Agent persona saved and active across conversational channels.
		</div>
	<?php endif; ?>

	<div class="tc-grid-layout">
		<!-- Left: Personas List -->
		<div>
			<div class="tc-card-lux">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>👥</span> Configured Personas (<?php echo count( $all_agents ); ?>)
					</div>
				</div>

				<table class="tc-table-lux">
					<thead>
						<tr>
							<th>Agent Name</th>
							<th>Channels</th>
							<th>Permitted Tools</th>
							<th>Temp</th>
							<th>Status</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_agents as $a ) : ?>
							<?php $tools = json_decode( $a['allowed_tools'], true ) ?: array(); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $a['name'] ); ?></strong><br />
									<code style="font-size: 11px; color: #0284c7;"><?php echo esc_html( $a['slug'] ); ?></code>
								</td>
								<td><small><?php echo esc_html( $a['channels'] ); ?></small></td>
								<td>
									<div style="max-width: 220px; display: flex; flex-wrap: wrap;">
										<?php foreach ( $tools as $t ) : ?>
											<span class="tc-tag-pill"><?php echo esc_html( $t ); ?></span>
										<?php endforeach; ?>
									</div>
								</td>
								<td><code><?php echo esc_html( $a['temperature'] ); ?></code></td>
								<td>
									<span class="<?php echo $a['is_active'] ? 'tc-badge-active' : 'tc-badge-disabled'; ?>">
										<?php echo $a['is_active'] ? 'Active' : 'Disabled'; ?>
									</span>
								</td>
								<td>
									<div style="display: flex; gap: 4px;">
										<a href="<?php echo esc_url( add_query_arg( 'edit', $a['id'] ) ); ?>" class="button button-small">Edit</a>
										<?php if ( 'tripcosmos-guide' !== $a['slug'] ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-personas' ) ); ?>" style="display:inline;" onsubmit="return confirm('Delete this persona?');">
												<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
												<input type="hidden" name="tc_agents_action" value="delete_agent" />
												<input type="hidden" name="agent_id" value="<?php echo esc_attr( $a['id'] ); ?>" />
												<button type="submit" class="button button-small button-link-delete">Del</button>
											</form>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Right: Editor Form -->
		<div>
			<div class="tc-card-lux" style="position: sticky; top: 32px; border-left: 5px solid #f59e0b;">
				<div class="tc-card-header-flex">
					<div class="tc-card-title">
						<span>🛠️</span>
						<?php echo $agent ? 'Edit Persona: ' . esc_html( $agent['name'] ) : 'Create New Persona'; ?>
					</div>
					<?php if ( $agent ) : ?>
						<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="tc-btn-secondary" style="padding: 4px 10px; font-size: 11px;">Cancel</a>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-personas' ) ); ?>">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<input type="hidden" name="tc_agents_action" value="save_agent" />
					<input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent['id'] ?? 0 ); ?>" />

					<?php if ( ! $agent ) : ?>
						<div style="margin-bottom: 12px;">
							<label for="slug" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Agent Slug (Unique ID):</label>
							<input type="text" name="slug" id="slug" class="tc-input-lux" placeholder="e.g. varanasi-yatra-guide" required />
						</div>
					<?php endif; ?>

					<div style="margin-bottom: 12px;">
						<label for="name" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Display Name:</label>
						<input type="text" name="name" id="name" class="tc-input-lux" value="<?php echo esc_attr( $agent['name'] ?? '' ); ?>" required />
					</div>

					<div style="margin-bottom: 12px;">
						<label for="channels" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Active Channels (Comma-separated):</label>
						<input type="text" name="channels" id="channels" class="tc-input-lux" value="<?php echo esc_attr( $agent['channels'] ?? 'web,whatsapp' ); ?>" placeholder="web,whatsapp,voice" />
					</div>

					<div style="margin-bottom: 12px;">
						<label for="system_prompt" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">System Instructions (Prompt):</label>
						<textarea name="system_prompt" id="system_prompt" rows="8" class="tc-input-lux" required><?php echo esc_textarea( $agent['system_prompt'] ?? '' ); ?></textarea>
					</div>

					<div style="margin-bottom: 12px;">
						<label for="greeting_message" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Welcome Greeting Message:</label>
						<textarea name="greeting_message" id="greeting_message" rows="3" class="tc-input-lux"><?php echo esc_textarea( $agent['greeting_message'] ?? '' ); ?></textarea>
					</div>

					<div style="margin-bottom: 14px;">
						<label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Permitted Tools:</label>
						<div style="max-height: 180px; overflow-y: auto; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px;">
							<?php foreach ( $available_tools as $t_def ) : ?>
								<?php $t_name = $t_def['function']['name']; ?>
								<label style="display: block; margin-bottom: 6px; font-size: 12.5px; cursor: pointer;">
									<input type="checkbox" name="allowed_tools[]" value="<?php echo esc_attr( $t_name ); ?>" <?php checked( in_array( $t_name, $saved_tools, true ) || ( ! $agent && in_array( $t_name, array( 'search_trips', 'lookup_contact_crm', 'sync_lead_crm', 'request_human_handoff' ), true ) ) ); ?> />
									<code><?php echo esc_html( $t_name ); ?></code>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
						<div>
							<label for="temperature" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Temperature:</label>
							<input type="number" step="0.05" min="0" max="1" name="temperature" id="temperature" value="<?php echo esc_attr( $agent['temperature'] ?? 0.70 ); ?>" class="tc-input-lux" />
						</div>

						<div>
							<label for="routing_override" style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Provider Override:</label>
							<select name="routing_override" id="routing_override" class="tc-input-lux">
								<option value="">Use Global Priority Chain</option>
								<option value="openrouter" <?php selected( $agent['routing_override'] ?? '', 'openrouter' ); ?>>Force OpenRouter</option>
								<option value="gateway" <?php selected( $agent['routing_override'] ?? '', 'gateway' ); ?>>Force AI Gateway</option>
								<option value="aipuffer" <?php selected( $agent['routing_override'] ?? '', 'aipuffer' ); ?>>Force AIPKit (AIPuffer)</option>
								<option value="gemini" <?php selected( $agent['routing_override'] ?? '', 'gemini' ); ?>>Force Gemini</option>
							</select>
						</div>
					</div>

					<div style="margin-bottom: 16px;">
						<label style="font-weight: 600; font-size: 13px; color: #1e293b; cursor: pointer;">
							<input type="checkbox" name="is_active" value="1" <?php checked( ! isset( $agent['is_active'] ) || ! empty( $agent['is_active'] ) ); ?> />
							Active Persona (Live across channels)
						</label>
					</div>

					<div>
						<button type="submit" class="tc-btn-gold" style="width: 100%; justify-content: center;">
							Save Agent Persona
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
