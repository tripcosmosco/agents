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
$all_providers   = method_exists( $router, 'get_display_providers' ) ? $router->get_display_providers() : $router->get_providers();
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
			// Skip retired aliases of gateway
			if ( in_array( $slug, array( 'omniroute', 'vmstudio' ), true ) ) { continue; }
			$h          = $health_statuses[ $slug ] ?? $health_statuses[ $prov->get_slug() ] ?? array();
			$status     = $h['status'] ?? 'unconfigured';
			if ( 'down' === $status && empty( $h ) ) { $status = 'unconfigured'; }
			$latency    = $h['latency_ms'] ?? 0;
			$msg        = $h['message'] ?? '';
			$configured = $h['configured'] ?? false;
			$badge      = ( 'unconfigured' === $status ) ? 'UNCONFIGURED' : strtoupper( $status );
			?>
			<div class="tc-card tc-health-card tc-status-card-<?php echo esc_attr( $status ); ?>" id="card-<?php echo esc_attr( $slug ); ?>">
				<div class="tc-card-header">
					<h4><?php echo esc_html( $prov->get_name() ); ?></h4>
					<span class="tc-indicator-badge tc-status-<?php echo esc_attr( $status ); ?>">
						<span class="tc-indicator-dot <?php echo esc_attr( $status ); ?>"></span>
						<?php echo esc_html( $badge ); ?>
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
						'openrouter' => 'OpenRouter (Claude, OpenAI, Mistral)',
						'gateway'    => 'OmniRoute Gateway (ai.vmstudio.digital / OpenAI-compatible)',
						'aipuffer'   => 'AI Puffer (AIPKit / AI Power Bot Bridge)',
						'gemini'     => 'Google Gemini (Native Generative Language API)',
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

				<!-- Provider 1: OpenRouter with live model sync -->
				<div class="tc-card">
					<h3>1. OpenRouter</h3>
					<?php $or_models = get_option( 'tc_agents_openrouter_models_cache', array() ); $or_synced = get_option( 'tc_agents_openrouter_models_synced_at', '' ); ?>
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
								<?php $or_current = get_option( 'tc_agents_openrouter_model', 'anthropic/claude-3.5-sonnet' ); ?>
								<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
									<select name="openrouter_model" id="openrouter_model" class="regular-text">
										<?php if ( ! empty( $or_models ) ) : ?>
											<?php foreach ( $or_models as $m ) : ?>
												<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $or_current, $m['id'] ); ?>><?php echo esc_html( $m['name'] ?? $m['id'] ); ?></option>
											<?php endforeach; ?>
										<?php else : ?>
											<option value="<?php echo esc_attr( $or_current ); ?>"><?php echo esc_html( $or_current ); ?></option>
										<?php endif; ?>
									</select>
									<button type="button" class="button button-secondary tc-sync-models-btn" data-provider="openrouter" id="tc-sync-openrouter-models">
										<span class="dashicons dashicons-update"></span> Sync Models
									</button>
								</div>
								<p class="description">Live catalogue syncs on demand<?php echo $or_synced ? ' (last: ' . esc_html( $or_synced ) . ')' : ''; ?>.</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 2: OmniRoute Gateway (ai.vmstudio.digital) -->
				<div class="tc-card">
					<h3>2. OmniRoute Gateway (ai.vmstudio.digital)</h3>
					<p class="description">Consolidated OpenAI-compatible Gateway for Omniroute / ai.vmstudio.digital.</p>
					<?php $gw_synced = get_option( 'tc_agents_gateway_models_synced_at', '' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="gateway_base_url">Gateway Base URL</label></th>
							<td>
								<input type="text" name="gateway_base_url" id="gateway_base_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_gateway_base_url', get_option( 'tc_agents_vmstudio_base_url', 'https://ai.vmstudio.digital/v1' ) ) ); ?>" />
								<p class="description">Default: <code>https://ai.vmstudio.digital/v1</code></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gateway_api_key">API Key</label></th>
							<td>
								<input type="password" name="gateway_api_key" id="gateway_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'gateway_api_key' ) ?: TC_Agents_Vault::hint( 'vmstudio_api_key' ) ?: '••••••••••••' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'gateway_api_key' ) || TC_Agents_Vault::has( 'vmstudio_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gateway_model">Model Name</label></th>
							<td>
								<?php $gw_models = get_option( 'tc_agents_gateway_models_cache', array() ); $gw_current = get_option( 'tc_agents_gateway_model', 'default' ); ?>
								<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
									<select name="gateway_model" id="gateway_model" class="regular-text">
										<?php if ( ! empty( $gw_models ) ) : ?>
											<?php foreach ( $gw_models as $m ) : ?>
												<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $gw_current, $m['id'] ); ?>><?php echo esc_html( $m['name'] ?? $m['id'] ); ?></option>
											<?php endforeach; ?>
										<?php else : ?>
											<option value="<?php echo esc_attr( $gw_current ); ?>"><?php echo esc_html( $gw_current ); ?></option>
										<?php endif; ?>
									</select>
									<button type="button" class="button button-secondary tc-sync-models-btn" data-provider="gateway" id="tc-sync-gateway-models">
										<span class="dashicons dashicons-update"></span> Sync Models
									</button>
								</div>
								<p class="description">Live /models catalogue from gateway<?php echo $gw_synced ? ' (last: ' . esc_html( $gw_synced ) . ')' : ''; ?>.</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 3: AI Puffer (AIPKit / AI Power Bot Bridge) -->
				<div class="tc-card">
					<h3>3. AI Puffer (AIPKit / AI Power)</h3>
					<p class="description">Autonomous Chatbot Bridge connecting to local or remote AI Power / AIPKit instances.</p>
					<?php 
					$ap_bots = TC_Provider_AIPuffer::get_cached_bots(); 
					$ap_synced = get_option( 'tc_agents_aipuffer_bots_synced_at', '' );
					$ap_current_bot = get_option( 'tc_agents_aipuffer_bot_id', get_option( 'vmai_aipuffer_bot_id', '' ) );
					?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="aipuffer_base_url">Bridge URL</label></th>
							<td>
								<input type="text" name="aipuffer_base_url" id="aipuffer_base_url" class="regular-text" value="<?php echo esc_attr( get_option( 'tc_agents_aipuffer_base_url', '' ) ); ?>" placeholder="<?php echo esc_attr( site_url() . ' (Leave empty for this local site)' ); ?>" />
								<p class="description">Target site URL where AI Power/AIPKit is installed. Leave blank for this local site.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aipuffer_api_key">API Key (Bearer Token)</label></th>
							<td>
								<input type="password" name="aipuffer_api_key" id="aipuffer_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'aipuffer_api_key' ) ?: 'Optional if local; required if remote' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'aipuffer_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="aipuffer_bot_id">Selected Chatbot</label></th>
							<td>
								<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
									<select name="aipuffer_bot_id" id="tc_agents_aipuffer_bot_id" class="regular-text" style="<?php echo ( empty( $ap_bots ) && ! empty( $ap_current_bot ) ) ? 'display:none;' : ''; ?>">
										<option value="">— Select Remote/Local Brain —</option>
										<?php if ( ! empty( $ap_current_bot ) ) : ?>
											<option value="<?php echo esc_attr( $ap_current_bot ); ?>" selected>Current Bot (ID: <?php echo esc_html( $ap_current_bot ); ?>)</option>
										<?php endif; ?>
										<?php foreach ( $ap_bots as $b ) : ?>
											<?php if ( (string) $b['id'] !== (string) $ap_current_bot ) : ?>
												<option value="<?php echo esc_attr( $b['id'] ); ?>"><?php echo esc_html( $b['name'] . ' [ID: ' . $b['id'] . ']' ); ?></option>
											<?php endif; ?>
										<?php endforeach; ?>
									</select>
									<input type="text" name="aipuffer_bot_id_manual" id="tc_agents_aipuffer_bot_id_manual" class="regular-text" value="<?php echo esc_attr( $ap_current_bot ); ?>" placeholder="e.g. 102 or my_bot_id" style="<?php echo ( empty( $ap_bots ) && ! empty( $ap_current_bot ) ) ? '' : 'display:none;'; ?>" />
									<button type="button" class="button button-secondary" id="tc-sync-aipuffer-bots">
										<span class="dashicons dashicons-update"></span> Sync Bots
									</button>
									<button type="button" class="button button-link" id="tc-toggle-bot-manual">
										Manual ID
									</button>
								</div>
								<p class="description">Discovers chatbots from local AI Power CPTs or remote <code>/wp-json/aipkit/v1/chat/list</code><?php echo $ap_synced ? ' (last synced: ' . esc_html( $ap_synced ) . ')' : ''; ?>.</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Provider 4: Google Gemini (Native) -->
				<div class="tc-card">
					<h3>4. Google Gemini (Native API)</h3>
					<p class="description">Direct Google AI Studio connection (Generative Language API).</p>
					<?php $gm_synced = get_option( 'tc_agents_gemini_models_synced_at', '' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="gemini_api_key">Gemini API Key</label></th>
							<td>
								<input type="password" name="gemini_api_key" id="gemini_api_key" class="regular-text" value="" placeholder="<?php echo esc_attr( TC_Agents_Vault::hint( 'gemini_api_key' ) ?: 'AIza...' ); ?>" autocomplete="new-password" />
								<?php if ( TC_Agents_Vault::has( 'gemini_api_key' ) ) : ?><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span> <small>Encrypted in Vault</small><?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gemini_model">Model</label></th>
							<td>
								<?php $gm_models = get_option( 'tc_agents_gemini_models_cache', array() ); $gm_current = get_option( 'tc_agents_gemini_model', 'gemini-2.0-flash' ); ?>
								<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
									<select name="gemini_model" id="gemini_model" class="regular-text">
										<?php if ( ! empty( $gm_models ) ) : ?>
											<?php foreach ( $gm_models as $m ) : ?>
												<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $gm_current, $m['id'] ); ?>><?php echo esc_html( $m['name'] ?? $m['id'] ); ?></option>
											<?php endforeach; ?>
										<?php else : ?>
											<option value="<?php echo esc_attr( $gm_current ); ?>"><?php echo esc_html( $gm_current ); ?></option>
										<?php endif; ?>
									</select>
									<button type="button" class="button button-secondary tc-sync-models-btn" data-provider="gemini" id="tc-sync-gemini-models">
										<span class="dashicons dashicons-update"></span> Sync Models
									</button>
								</div>
								<p class="description">Live Gemini catalogue<?php echo $gm_synced ? ' (last: ' . esc_html( $gm_synced ) . ')' : ''; ?>.</p>
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
