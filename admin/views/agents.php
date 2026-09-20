<?php
/**
 * Admin Agent Personas and Tools View.
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

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-groups"></span> Agent Personas & Capabilities</h1>
			<p class="description">Define personas, system instructions, temperature, and permitted tool surfaces for each conversational agent.</p>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Agent persona saved successfully.</p></div>
	<?php endif; ?>

	<div class="tc-two-col-layout">
		<!-- Left: Personas List -->
		<div class="tc-col-main">
			<div class="tc-card">
				<div class="tc-card-header">
					<h3>Configured Agents</h3>
					<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="button button-primary">+ New Agent Persona</a>
				</div>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Name & Slug</th>
							<th>Channels</th>
							<th>Tools Allowed</th>
							<th>Temp</th>
							<th>Status</th>
							<th style="width: 80px;">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_agents as $a ) : ?>
							<?php
							$tools = json_decode( $a['allowed_tools'], true ) ?: array();
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $a['name'] ); ?></strong><br />
									<code><?php echo esc_html( $a['slug'] ); ?></code>
								</td>
								<td><?php echo esc_html( $a['channels'] ); ?></td>
								<td>
									<?php foreach ( $tools as $t ) : ?>
										<span class="tc-tag"><?php echo esc_html( $t ); ?></span>
									<?php endforeach; ?>
								</td>
								<td><?php echo esc_html( $a['temperature'] ); ?></td>
								<td>
									<span class="tc-status-pill tc-status-<?php echo $a['is_active'] ? 'active' : 'resolved'; ?>">
										<?php echo $a['is_active'] ? 'Active' : 'Disabled'; ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'edit', $a['id'] ) ); ?>" class="button button-small">Edit</a>
									<?php if ( 'tripcosmos-guide' !== $a['slug'] ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-personas' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this agent persona?', 'tripcosmos-agents' ); ?>');">
											<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
											<input type="hidden" name="tc_agents_action" value="delete_agent" />
											<input type="hidden" name="agent_id" value="<?php echo esc_attr( $a['id'] ); ?>" />
											<button type="submit" class="button button-small button-link-delete" style="color:#b91c1c;">Delete</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Right: Editor Form -->
		<div class="tc-col-side">
			<div class="tc-card">
				<h3><?php echo $agent ? 'Edit Persona: ' . esc_html( $agent['name'] ) : 'Create New Agent Persona'; ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-personas' ) ); ?>">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<input type="hidden" name="tc_agents_action" value="save_agent" />
					<input type="hidden" name="agent_id" value="<?php echo esc_attr( $agent['id'] ?? 0 ); ?>" />

					<?php if ( ! $agent ) : ?>
						<p>
							<label for="slug"><strong>Agent Slug (unique identifier):</strong></label>
							<input type="text" name="slug" id="slug" class="widefat" placeholder="e.g. varanasi-yatra-guide" required />
						</p>
					<?php endif; ?>

					<p>
						<label for="name"><strong>Display Name:</strong></label>
						<input type="text" name="name" id="name" class="widefat" value="<?php echo esc_attr( $agent['name'] ?? '' ); ?>" required />
					</p>

					<p>
						<label for="channels"><strong>Active Channels:</strong></label>
						<input type="text" name="channels" id="channels" class="widefat" value="<?php echo esc_attr( $agent['channels'] ?? 'web,whatsapp' ); ?>" placeholder="web,whatsapp,voice" />
						<small class="description">Comma-separated (e.g. web, whatsapp, voice)</small>
					</p>

					<p>
						<label for="system_prompt"><strong>System Instructions (Persona Prompt):</strong></label>
						<textarea name="system_prompt" id="system_prompt" rows="8" class="widefat" required><?php echo esc_textarea( $agent['system_prompt'] ?? '' ); ?></textarea>
					</p>

					<p>
						<label for="greeting_message"><strong>Custom Greeting / Welcome Message:</strong></label>
						<textarea name="greeting_message" id="greeting_message" rows="3" class="widefat"><?php echo esc_textarea( $agent['greeting_message'] ?? '' ); ?></textarea>
					</p>

					<p>
						<label><strong>Permitted Tools:</strong></label><br />
						<?php foreach ( $available_tools as $t_def ) : ?>
							<?php $t_name = $t_def['function']['name']; ?>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="allowed_tools[]" value="<?php echo esc_attr( $t_name ); ?>" <?php checked( in_array( $t_name, $saved_tools, true ) || ( ! $agent && in_array( $t_name, array( 'search_trips', 'lookup_contact_crm', 'sync_lead_crm', 'request_human_handoff' ), true ) ) ); ?> />
								<code><?php echo esc_html( $t_name ); ?></code>
								<small style="color: #64748b;">(<?php echo esc_html( wp_trim_words( $t_def['function']['description'], 10 ) ); ?>)</small>
							</label>
						<?php endforeach; ?>
					</p>

					<p>
						<label for="temperature"><strong>Temperature (Creativity: 0.0 to 1.0):</strong></label>
						<input type="number" step="0.05" min="0" max="1" name="temperature" id="temperature" value="<?php echo esc_attr( $agent['temperature'] ?? 0.70 ); ?>" />
					</p>

					<p>
						<label for="routing_override"><strong>Provider Override (Optional):</strong></label>
						<select name="routing_override" id="routing_override" class="widefat">
							<option value="">Use Global Priority Chain</option>
							<option value="openrouter" <?php selected( $agent['routing_override'] ?? '', 'openrouter' ); ?>>Force OpenRouter</option>
							<option value="gateway" <?php selected( $agent['routing_override'] ?? '', 'gateway' ); ?>>Force AI Gateway</option>
							<option value="aipuffer" <?php selected( $agent['routing_override'] ?? '', 'aipuffer' ); ?>>Force AIPKit (AIPuffer)</option>
							<option value="gemini" <?php selected( $agent['routing_override'] ?? '', 'gemini' ); ?>>Force Gemini</option>
						</select>
					</p>

					<p>
						<label>
							<input type="checkbox" name="is_active" value="1" <?php checked( ! isset( $agent['is_active'] ) || ! empty( $agent['is_active'] ) ); ?> />
							<strong>Active Persona</strong> (available for incoming inquiries)
						</label>
					</p>

					<p>
						<input type="submit" class="button button-primary button-large" value="Save Agent Persona" />
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
