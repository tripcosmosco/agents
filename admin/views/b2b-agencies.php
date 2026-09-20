<?php
/**
 * B2B Agencies admin view: imported Indian travel agencies + outreach.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
global $wpdb;
$table = $wpdb->prefix . 'tc_agent_agencies';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
$rows = array(); $total = 0;
if ( $exists === $table ) {
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY updated_at DESC LIMIT 100", ARRAY_A );
}
$new = $exists === $table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='new'" ) : 0;
$contacted = $exists === $table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='contacted'" ) : 0;
?>
<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-building"></span> B2B Agencies — India Import & Outreach</h1>
			<p class="description">Google Business import of travel agencies, then WhatsApp (Evolution) + Brevo email partnership outreach. Every outreach logs to FluentCRM + TwentyCRM.</p>
		</div>
		<div class="tc-header-actions">
			<button type="button" class="button button-secondary" id="tc-b2b-import-all">Import All Cities (cron now)</button>
			<button type="button" class="button button-primary" id="tc-b2b-outreach-batch">Outreach Batch (10)</button>
		</div>
	</div>
	<div class="tc-health-grid">
		<div class="tc-card"><h4>Total Agencies</h4><p style="font-size:28px;font-weight:800;"><?php echo esc_html( $total ); ?></p></div>
		<div class="tc-card"><h4>New (to contact)</h4><p style="font-size:28px;font-weight:800;"><?php echo esc_html( $new ); ?></p></div>
		<div class="tc-card"><h4>Contacted</h4><p style="font-size:28px;font-weight:800;"><?php echo esc_html( $contacted ); ?></p></div>
		<div class="tc-card"><h4>Last Import</h4><p><code><?php echo esc_html( get_option( 'tc_agents_b2b_last_import', 'never' ) ); ?></code></p></div>
	</div>
	<div class="tc-two-col-layout">
		<div class="tc-col-main">
			<div class="tc-card">
				<h3><span class="dashicons dashicons-search"></span> 1. Search & Import via Google Places</h3>
				<form id="tc-b2b-city-form" style="display:flex;gap:8px;margin-bottom:8px;">
					<input type="text" id="tc-b2b-city" class="regular-text" placeholder="e.g. Varanasi, Mumbai, Ahmedabad, Surat, Delhi" />
					<button class="button button-primary" type="submit"><span class="dashicons dashicons-download"></span> Import City</button>
				</form>
				<p class="description">Queries Google Places Text Search (New) for travel agencies and tour operators. Deduplicates automatically.</p>
			</div>

			<div class="tc-card">
				<h3><span class="dashicons dashicons-clipboard"></span> 2. Quick Paste Agencies (CSV / Tab-Separated)</h3>
				<p class="description">Paste list from Google Sheets or Excel (one per line): <code>Agency Name, City, Phone/WhatsApp, Email</code></p>
				<form id="tc-b2b-raw-form">
					<textarea id="tc-b2b-raw-list" rows="4" class="large-text code" placeholder="Shree Ram Tours, Ayodhya, +919876543210, info@shreeramtours.com&#10;Gujarat Darshan Travels, Ahmedabad, +919123456789, booking@gujaratdarshan.in"></textarea>
					<div style="margin-top:8px;">
						<button class="button button-secondary" type="submit"><span class="dashicons dashicons-plus-alt"></span> Bulk Import Pasted Agencies</button>
					</div>
				</form>
			</div>

			<div class="tc-card">
				<h3>Target Agency Prospects (Latest 100)</h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:30%;">Agency & Location</th>
							<th style="width:25%;">Contact Details</th>
							<th style="width:12%;">Rating / Source</th>
							<th style="width:13%;">Status</th>
							<th style="width:20%;">Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<?php
						$status_color = '#64748b';
						if ( 'new' === $r['status'] ) $status_color = '#3b82f6';
						if ( 'in_crm' === $r['status'] ) $status_color = '#8b5cf6';
						if ( 'contacted' === $r['status'] ) $status_color = '#10b981';
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $r['name'] ); ?></strong><br />
								<span class="dashicons dashicons-location" style="font-size:14px;color:#64748b;"></span> <code><?php echo esc_html( $r['city'] ?: 'India' ); ?></code><br />
								<small style="color:#64748b;"><?php echo esc_html( $r['address'] ); ?></small>
							</td>
							<td>
								<?php if ( ! empty( $r['phone'] ) ) : ?>
									<span class="dashicons dashicons-phone" style="font-size:14px;color:#10b981;"></span> <strong><?php echo esc_html( $r['phone'] ); ?></strong><br />
								<?php endif; ?>
								<?php if ( ! empty( $r['email'] ) ) : ?>
									<span class="dashicons dashicons-email" style="font-size:14px;color:#0ea5e9;"></span> <?php echo esc_html( $r['email'] ); ?><br />
								<?php endif; ?>
								<?php if ( ! empty( $r['website'] ) ) : ?>
									<small><a href="<?php echo esc_url( $r['website'] ); ?>" target="_blank" rel="noopener">Website &rarr;</a></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $r['rating'] ? '⭐ ' . esc_html( $r['rating'] ) : '—'; ?><br />
								<small style="color:#94a3b8;"><?php echo esc_html( $r['source'] ?? 'google' ); ?></small>
							</td>
							<td>
								<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff;background:<?php echo esc_attr( $status_color ); ?>;">
									<?php echo esc_html( $r['status'] ); ?>
								</span>
							</td>
							<td>
								<div style="display:flex;gap:4px;flex-wrap:wrap;">
									<button class="button button-small button-primary tc-b2b-outreach-one" data-id="<?php echo esc_attr( $r['id'] ); ?>" title="Send WhatsApp B2B Pitch & Brevo Email">
										<span class="dashicons dashicons-format-chat"></span> Pitch
									</button>
									<button class="button button-small button-secondary tc-b2b-crm-one" data-id="<?php echo esc_attr( $r['id'] ); ?>" title="Sync to FluentCRM & Twenty CRM">
										<span class="dashicons dashicons-cloud"></span> CRM
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $rows ) ) : ?><tr><td colspan="5">No agencies yet. Enter a city above or paste agency rows to import.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<script>
(function($){
	function call(url, data, btn, doneMsg){
		if(btn){ $(btn).prop('disabled', true); }
		$.ajax({
			url: url,
			method: 'POST',
			headers: { 'X-WP-Nonce': tcAgentsAdmin.nonce },
			contentType: 'application/json',
			data: JSON.stringify(data||{}),
			success: function(res){
				alert(doneMsg || (res.message ? res.message : 'Done'));
				location.reload();
			},
			error: function(xhr){
				alert('Error: ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : xhr.statusText));
				if(btn){ $(btn).prop('disabled', false); }
			}
		});
	}

	$('#tc-b2b-import-all').on('click', function(){ call(tcAgentsAdmin.restUrl + 'b2b/import', {}, this, 'All-city import queued.'); });
	$('#tc-b2b-city-form').on('submit', function(e){
		e.preventDefault();
		var c = $('#tc-b2b-city').val();
		if(!c){ alert('Please enter a city name.'); return; }
		call(tcAgentsAdmin.restUrl + 'b2b/import', {city: c}, $(this).find('button'), 'City imported successfully!');
	});
	$('#tc-b2b-raw-form').on('submit', function(e){
		e.preventDefault();
		var raw = $('#tc-b2b-raw-list').val();
		if(!raw.trim()){ alert('Please paste one or more agency rows.'); return; }
		call(tcAgentsAdmin.restUrl + 'b2b/import', {raw_list: raw}, $(this).find('button'), 'Pasted agencies imported!');
	});
	$('#tc-b2b-outreach-batch').on('click', function(){ call(tcAgentsAdmin.restUrl + 'b2b/outreach', {}, this, 'Batch outreach sent.'); });
	$('.tc-b2b-outreach-one').on('click', function(){ call(tcAgentsAdmin.restUrl + 'b2b/outreach', {agency_id: $(this).data('id')}, this, 'Partnership outreach dispatched via WhatsApp & Email!'); });
	$('.tc-b2b-crm-one').on('click', function(){ call(tcAgentsAdmin.restUrl + 'b2b/sync-crm', {agency_id: $(this).data('id')}, this, 'Agency synced to FluentCRM & Twenty CRM!'); });
})(jQuery);
</script>
