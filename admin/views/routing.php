<?php
/**
 * Admin AI Routing & Health View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$router          = TC_AI_Router::get_instance();
$priority_chain  = $router->get_priority_chain();
$all_providers   = $router->get_providers();
$health_statuses = $router->check_all_health();
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-randomize"></span> AI Routing & Provider Failover Chain</h1>
			<p class="description">Live health monitoring, automatic circuit breaker failover, and OpenAI-compatible multi-provider configuration.</p>
		</div>
		<div class="tc-header-actions">
			<button type="button" class="button button-secondary" id="tc-refresh-health">
				<span class="dashicons dashicons-update"></span> Refresh Health Check
			</button>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>Routing and provider settings updated.</p></div>
	<?php endif; ?>

	<!-- Live Provider Health Cards -->
	<h2>Current Provider Health Status</h2>
	<div class="tc-health-grid" id="tc-health-container">
		<?php foreach ( $all_providers as $slug => $prov ) : ?>
			<?php
			$h          = $health_statuses[ $slug ] ?? array();
			$status     = $h['status'] ?? 'down';
			$latency    = $h['latency_ms'] ?? 0;
			$msg        = $h['message'] ?? '';
			$configured = $h['configured'] ?? false;
			?>
			<div class="tc-card tc-health-card tc-status-card-<?php echo esc_attr( $status ); ?>" id="card-<?php echo esc_attr( $slug ); ?>">
				<div class="tc-card-header">
					<h4><?php echo esc_html( $prov->get_name() ); ?></h4>
					<span class="tc-indicator-badge tc-status-<?php echo esc_attr( $status ); ?>">
						<span class="tc-indicator-dot <?php echo esc_attr( $status ); ?>"></span>
						<?php echo esc_html( strtoupper( $status ) ); ?>
					</span>
				</div>
				<div class="tc-health-body">
					<p><strong>Latency:</strong> <span class="tc-latency-val"><?php echo $latency > 0 ? esc_html( $latency ) . ' ms' : '—'; ?></span></p>
					<p><strong>Status:</strong> <span class="tc-msg-val"><?php echo esc_html( $msg ?: ( $configured ? 'Operational' : 'Not configured' ) ); ?></span></p>
					<p><strong>Circuit Breaker:</strong>
						<?php echo $router->is_circuit_tripped( $slug ) ? '<span style="color:#ef4444;font-weight:bold;">TRIPPED (Cooling down)</span>' : '<span style="color:#10b981;">Closed (Normal)</span>'; ?>
					</p>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<hr style="margin: 32px 0 24px 0;" />

	<!-- Provider Configuration & Priority Form -->
	<form method="post">
		<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
		<input type="hidden" name="tc_agents_action" value="save_routing" />

		<div class="tc-two-col-layout">
			<!-- Left: Priority & Failover Thresholds -->
			<div class="tc-col-main">
				<div class="tc-card">
					<h3>Failover Chain Priority</h3>
					<p class="description">Select the order in which requests fall through if a provider fails or times out.</p>

					<?php
					$options = array(
						'aipuffer'   => 'AI Puffer (AIPKit native/remote bridge)',
						'openrouter' => 'OpenRouter (Claude, OpenAI, Mistral)',
						'omniroute'  => 'Omniroute API',
						'vmstudio'   => 'ai.vmstudio.digital (In-house router)',
					);
					?>

					<div class="tc-priority-list">
						<?php foreach ( $priority_chain as $idx => $slug ) : ?>
							<div class="tc-priority-item">
								<span class="tc-rank">#<?php echo esc_html( $idx + 1 ); ?></span>
								<select name="provider_priority[]">
									<?php foreach ( $options as $k => $label ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $slug, $k ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endforeach; ?>
					</div>

					<h4 style="margin-top: 24px;">Circuit Breaker & Resilience</h4>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="circuit_breaker_threshold">Failure Threshold</label></th>
							<td>
								<input type="number" min="1" max="10" name="circuit_breaker_threshold" id="circuit_breaker_threshold" value="<?php echo esc_attr( get_option( 'tc_agents_circuit_breaker_threshold', 2 ) ); ?>" />
								<p class="description">Number of consecutive failures before temporarily isolating a provider for 5 minutes.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="timeout_seconds">Request Timeout (seconds)</label></th>
							<td>
								<input type="number" min="3" max="30" name="timeout_seconds" id="timeout_seconds" value="<?php echo esc_attr( get_option( 'tc_agents_timeout_seconds', 8 ) ); ?>" />
								<p class="description">Maximum time to wait before failing over to the next provider (spec default: 8 seconds).</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 1: AI Puffer -->
				<div class="tc-card">
					<h3>1. AI Puffer (AIPKit) Integration</h3>
					<p class="description">
						Preserves and reuses the existing AI Puffer (AI Power/AIPKit) connection. Credentials are auto-detected from existing plugin installations if present.
					</p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="aipuffer_base_url">AI Puffer Base URL</label></th>
							<td>
								<input type="text" name="aipuffer_base_url" id="aipuffer_base_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_aipuffer_base_url', '' ) ); ?>" placeholder="e.g. https://your-aipuffer-instance.com (or leave blank for local)" />
								<p class="description">Auto-detected fallback: <code><?php echo esc_html( site_url() ); ?></code></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aipuffer_api_key">API Key (if remote)</label></th>
							<td>
								<input type="password" name="aipuffer_api_key" id="aipuffer_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'aipuffer_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'aipuffer_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 2: OpenRouter -->
				<div class="tc-card">
					<h3>2. OpenRouter</h3>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="openrouter_api_key">OpenRouter API Key</label></th>
							<td>
								<input type="password" name="openrouter_api_key" id="openrouter_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'openrouter_api_key' ) ?: 'sk-or-v1-...' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'openrouter_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="openrouter_model">Model ID</label></th>
							<td>
								<input type="text" name="openrouter_model" id="openrouter_model" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_openrouter_model', 'anthropic/claude-3.5-sonnet' ) ); ?>" />
								<p class="description">e.g. <code>anthropic/claude-3.5-sonnet</code>, <code>openai/gpt-4o-mini</code></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 3: Omniroute -->
				<div class="tc-card">
					<h3>3. Omniroute</h3>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="omniroute_base_url">Base URL</label></th>
							<td>
								<input type="text" name="omniroute_base_url" id="omniroute_base_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_omniroute_base_url', '' ) ); ?>" placeholder="https://api.omniroute.com/v1" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="omniroute_api_key">API Key</label></th>
							<td>
								<input type="password" name="omniroute_api_key" id="omniroute_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'omniroute_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'omniroute_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="omniroute_model">Model Name</label></th>
							<td>
								<input type="text" name="omniroute_model" id="omniroute_model" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_omniroute_model', '' ) ); ?>" />
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 4: VMStudio In-House Router -->
				<div class="tc-card">
					<h3>4. ai.vmstudio.digital</h3>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="vmstudio_base_url">Base URL</label></th>
							<td>
								<input type="text" name="vmstudio_base_url" id="vmstudio_base_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_vmstudio_base_url', 'https://ai.vmstudio.digital/v1' ) ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="vmstudio_api_key">API Key</label></th>
							<td>
								<input type="password" name="vmstudio_api_key" id="vmstudio_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'vmstudio_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'vmstudio_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
					</table>
				</div>

				<p>
					<input type="submit" class="button button-primary button-large" value="Save AI Routing & Providers" />
				</p>
			</div>
		</div>
	</form>
</div>
