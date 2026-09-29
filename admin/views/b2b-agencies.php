<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'tc_agent_agencies';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
$rows   = array();
$total  = 0;

if ( $exists === $table ) {
	$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
	$rows      = $wpdb->get_results( "SELECT * FROM $table ORDER BY updated_at DESC LIMIT 100", ARRAY_A );
	$new       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='new'" );
	$in_crm    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='in_crm'" );
	$contacted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='contacted'" );
} else {
	$new = 0; $in_crm = 0; $contacted = 0;
}

// Scheduled rotation context
$cities_str = (string) get_option( 'tc_agents_b2b_import_cities', 'Varanasi, Mumbai, Ahmedabad, Surat, Delhi, Bengaluru, Hyderabad, Kolkata, Chennai, Jaipur, Lucknow, Pune' );
$cities     = array_filter( array_map( 'trim', explode( ',', $cities_str ) ) );
$city_idx   = (int) get_option( 'tc_agents_b2b_city_index', 0 );
$next_city  = $cities[ $city_idx ] ?? ( $cities[0] ?? 'Varanasi' );
$last_import = get_option( 'tc_agents_b2b_last_import', 'Never' );

$fluent_crm_url   = admin_url( 'admin.php?page=fluentcrm-admin#/email/templates' );
$fluent_funnel_url = admin_url( 'admin.php?page=fluentcrm-admin#/funnels' );
$fluent_list_url   = admin_url( 'admin.php?page=fluentcrm-admin#/subscribers?lists=8' );
?>
<div class="wrap tc-admin-wrap">
	<div class="tc-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
		<div>
			<h1 style="display:flex;align-items:center;gap:10px;">
				<span class="dashicons dashicons-building" style="font-size:28px;color:#f59e0b;"></span>
				B2B Travel Partner Outreach &amp; Marketing Engine
			</h1>
			<p class="description" style="font-size:13px;max-width:780px;line-height:1.5;">
				Promoting <strong>Dev Deepawali 2026 Varanasi</strong> &amp; <strong>Varanasi-Ayodhya-Prayagraj Spiritual Circuits</strong> to verified Indian travel agencies.
				Automated across FluentCRM email drip, WhatsApp sequences, and TwentyCRM.
			</p>
		</div>
		<div class="tc-header-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
			<a href="<?php echo esc_url( $fluent_crm_url ); ?>" target="_blank" class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;">
				<span class="dashicons dashicons-email-alt" style="color:#0284c7;"></span> 2x FluentCRM Templates
			</a>
			<a href="<?php echo esc_url( $fluent_funnel_url ); ?>" target="_blank" class="button button-secondary" style="display:inline-flex;align-items:center;gap:6px;">
				<span class="dashicons dashicons-update" style="color:#10b981;"></span> Active Funnel (Funnel 11)
			</a>
			<button type="button" class="button button-primary" id="tc-b2b-import-scheduled" style="background:#f59e0b;border-color:#d97706;color:#fff;">
				<span class="dashicons dashicons-download"></span> Run Scheduled Import Now (<?php echo esc_html( $next_city ); ?>)
			</button>
		</div>
	</div>

	<!-- Marketing Automation Overview Card -->
	<div style="background: linear-gradient(135deg, #091428 0%, #152744 100%); color: #ffffff; padding: 18px 24px; border-radius: 12px; margin: 16px 0; border: 1px solid rgba(245,158,11,0.3); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
		<div>
			<span style="background: rgba(245,158,11,0.2); color: #fcd34d; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 12px; letter-spacing: 0.5px; text-transform: uppercase;">
				Active 2-Step B2B Drip Funnel
			</span>
			<h3 style="margin: 8px 0 4px; color: #ffffff; font-size: 17px;">
				Email 1: Dev Deepawali 2026 Allocation &rarr; 48h Wait &rarr; Email 2: Complete Spiritual Circuit DMC Tariff
			</h3>
			<p style="margin: 0; color: #94a3b8; font-size: 12px;">
				Target List: <a href="<?php echo esc_url( $fluent_list_url ); ?>" target="_blank" style="color:#38bdf8;text-decoration:none;font-weight:700;">B2B Travel Agencies - Pan India (List 8)</a> • Tags: <code>b2b-travel-agency</code>, <code>dev-deepawali-2026-interested</code> • Quiet Hours: 9:00 PM – 8:30 AM IST (Enforced)
			</p>
		</div>
		<div>
			<button type="button" class="button button-hero" id="tc-b2b-sync-all" style="background:#10b981;border:none;color:#fff;font-weight:700;font-size:13px;padding:8px 18px;border-radius:8px;cursor:pointer;">
				⚡ Sync All to FluentCRM &amp; Enroll in Funnel
			</button>
		</div>
	</div>

	<!-- Metric Cards -->
	<div class="tc-health-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:14px;margin-bottom:20px;">
		<div class="tc-card" style="background:#fff;padding:16px 20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
			<h4 style="margin:0 0 6px;color:#64748b;font-size:12px;text-transform:uppercase;">Total Prospect Agencies</h4>
			<p style="font-size:28px;font-weight:800;margin:0;color:#0f172a;"><?php echo esc_html( $total ); ?></p>
		</div>
		<div class="tc-card" style="background:#fff;padding:16px 20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
			<h4 style="margin:0 0 6px;color:#64748b;font-size:12px;text-transform:uppercase;">Synced in FluentCRM</h4>
			<p style="font-size:28px;font-weight:800;margin:0;color:#10b981;"><?php echo esc_html( $in_crm ); ?></p>
		</div>
		<div class="tc-card" style="background:#fff;padding:16px 20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
			<h4 style="margin:0 0 6px;color:#64748b;font-size:12px;text-transform:uppercase;">Outreach Completed</h4>
			<p style="font-size:28px;font-weight:800;margin:0;color:#3b82f6;"><?php echo esc_html( $contacted ); ?></p>
		</div>
		<div class="tc-card" style="background:#fff;padding:16px 20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
			<h4 style="margin:0 0 6px;color:#64748b;font-size:12px;text-transform:uppercase;">Schedule Rotation</h4>
			<p style="margin:0;font-size:14px;font-weight:700;color:#d97706;">
				Next: <code><?php echo esc_html( $next_city ); ?></code>
			</p>
			<small style="color:#64748b;display:block;margin-top:4px;">Last: <?php echo esc_html( $last_import ); ?></small>
		</div>
	</div>

	<!-- Two Column Layout: Import Forms & Prospects Table -->
	<div class="tc-two-col-layout" style="display:grid;grid-template-columns:1fr;gap:20px;">
		<div class="tc-col-main">
			
			<!-- Row with 2 Import Cards -->
			<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:16px;margin-bottom:20px;">
				
				<!-- Card 1: Google Places Import -->
				<div class="tc-card" style="background:#fff;padding:20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="margin:0 0 8px;display:flex;align-items:center;gap:8px;font-size:16px;">
						<span class="dashicons dashicons-search" style="color:#3b82f6;"></span> 1. Search &amp; Import via Google Places
					</h3>
					<p class="description" style="margin-bottom:12px;font-size:12px;">
						Queries Google Places Text Search (New) for verified travel agencies &amp; tour operators. Auto-normalizes phone (+91) &amp; dedupes.
					</p>
					<form id="tc-b2b-city-form" style="display:flex;gap:8px;">
						<input type="text" id="tc-b2b-city" class="regular-text" style="flex:1;" placeholder="e.g. Mumbai, Ahmedabad, Surat, Delhi, Bengaluru" />
						<button class="button button-primary" type="submit">
							<span class="dashicons dashicons-download"></span> Import City
						</button>
					</form>
				</div>

				<!-- Card 2: Quick CSV / Raw Paste -->
				<div class="tc-card" style="background:#fff;padding:20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<h3 style="margin:0 0 8px;display:flex;align-items:center;gap:8px;font-size:16px;">
						<span class="dashicons dashicons-clipboard" style="color:#10b981;"></span> 2. Quick Paste Agencies (CSV / Excel)
					</h3>
					<p class="description" style="margin-bottom:8px;font-size:12px;">
						Format: <code>Agency Name, City, Phone/WhatsApp, Email</code>
					</p>
					<form id="tc-b2b-raw-form">
						<textarea id="tc-b2b-raw-list" rows="3" class="large-text code" style="font-size:12px;" placeholder="Shree Ram Tours, Ayodhya, +919876543210, info@shreeramtours.com&#10;Gujarat Darshan Travels, Ahmedabad, +919123456789, booking@gujaratdarshan.in"></textarea>
						<div style="margin-top:8px;display:flex;justify-content:flex-end;">
							<button class="button button-secondary" type="submit">
								<span class="dashicons dashicons-plus-alt"></span> Bulk Import Pasted Agencies
							</button>
						</div>
					</form>
				</div>

			</div>

			<!-- Agency Prospects Table -->
			<div class="tc-card" style="background:#fff;padding:20px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
				<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
					<h3 style="margin:0;font-size:17px;font-weight:700;">
						Target Agency Prospects (Latest <?php echo count( $rows ); ?>)
					</h3>
					<div style="display:flex;gap:8px;">
						<button type="button" class="button button-secondary" id="tc-b2b-outreach-batch">
							<span class="dashicons dashicons-whatsapp" style="color:#25d366;"></span> WhatsApp Batch (10)
						</button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
					<thead>
						<tr>
							<th style="width:30%;">Agency &amp; Location</th>
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
						$status_label = 'New';
						if ( 'new' === $r['status'] ) { $status_color = '#3b82f6'; $status_label = 'Pending CRM'; }
						if ( 'in_crm' === $r['status'] ) { $status_color = '#10b981'; $status_label = 'In Funnel'; }
						if ( 'contacted' === $r['status'] ) { $status_color = '#8b5cf6'; $status_label = 'Outreach Done'; }
						?>
						<tr>
							<td>
								<strong style="font-size:14px;color:#0f172a;"><?php echo esc_html( $r['name'] ); ?></strong><br />
								<span class="dashicons dashicons-location" style="font-size:13px;color:#f59e0b;vertical-align:text-bottom;"></span>
								<code style="font-size:11px;font-weight:600;"><?php echo esc_html( $r['city'] ?: 'India' ); ?></code><br />
								<small style="color:#64748b;line-height:1.3;display:block;margin-top:2px;"><?php echo esc_html( $r['address'] ); ?></small>
							</td>
							<td>
								<?php if ( ! empty( $r['phone'] ) ) : ?>
									<span class="dashicons dashicons-phone" style="font-size:13px;color:#10b981;vertical-align:text-bottom;"></span>
									<strong style="color:#0f172a;"><?php echo esc_html( $r['phone'] ); ?></strong><br />
								<?php endif; ?>
								<?php if ( ! empty( $r['email'] ) ) : ?>
									<span class="dashicons dashicons-email" style="font-size:13px;color:#0ea5e9;vertical-align:text-bottom;"></span>
									<span style="color:#334155;"><?php echo esc_html( $r['email'] ); ?></span><br />
								<?php endif; ?>
								<?php if ( ! empty( $r['website'] ) ) : ?>
									<small style="margin-top:2px;display:inline-block;"><a href="<?php echo esc_url( $r['website'] ); ?>" target="_blank" rel="noopener" style="color:#f59e0b;font-weight:600;text-decoration:none;">Visit Website &rarr;</a></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $r['rating'] ? '⭐ <strong style="color:#d97706;">' . esc_html( $r['rating'] ) . '</strong>' : '<span style="color:#94a3b8;">—</span>'; ?><br />
								<small style="color:#94a3b8;text-transform:uppercase;font-size:10px;font-weight:700;"><?php echo esc_html( $r['source'] ?? 'google' ); ?></small>
							</td>
							<td>
								<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase;color:#fff;background:<?php echo esc_attr( $status_color ); ?>;">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td>
								<div style="display:flex;gap:6px;flex-wrap:wrap;">
									<button class="button button-small button-primary tc-b2b-outreach-one" data-id="<?php echo esc_attr( $r['id'] ); ?>" title="Send WhatsApp B2B Pitch & Brevo Email" style="display:inline-flex;align-items:center;gap:3px;">
										<span class="dashicons dashicons-format-chat" style="font-size:13px;"></span> Pitch
									</button>
									<button class="button button-small button-secondary tc-b2b-crm-one" data-id="<?php echo esc_attr( $r['id'] ); ?>" title="Sync to FluentCRM (List 8) & Twenty CRM" style="display:inline-flex;align-items:center;gap:3px;">
										<span class="dashicons dashicons-cloud" style="font-size:13px;color:#10b981;"></span> CRM
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="5" style="text-align:center;padding:32px;color:#64748b;">
								<span class="dashicons dashicons-building" style="font-size:36px;color:#cbd5e1;display:block;margin-bottom:8px;"></span>
								No agencies imported yet.<br />
								Click <strong>"Run Scheduled Import Now"</strong> or enter a city above to import prospect agencies.
							</td>
						</tr>
					<?php endif; ?>
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
				alert(doneMsg || (res.message ? res.message : 'Operation completed successfully!'));
				location.reload();
			},
			error: function(xhr){
				var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : (xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : xhr.statusText);
				alert('Error: ' + msg);
				if(btn){ $(btn).prop('disabled', false); }
			}
		});
	}

	$('#tc-b2b-import-scheduled').on('click', function(){
		call(tcAgentsAdmin.restUrl + 'b2b/import', {run_scheduled_now: true}, this, 'Scheduled city import completed!');
	});

	$('#tc-b2b-sync-all').on('click', function(){
		if(!confirm('This will sync all pending agencies to FluentCRM (List 8: Pan India) and enroll them in the Dev Deepawali & Spiritual Circuit marketing funnels. Proceed?')) {
			return;
		}
		call(tcAgentsAdmin.restUrl + 'b2b/sync-crm', {sync_all: true}, this, 'Agencies synced to FluentCRM & enrolled in marketing funnels!');
	});

	$('#tc-b2b-city-form').on('submit', function(e){
		e.preventDefault();
		var c = $('#tc-b2b-city').val();
		if(!c){ alert('Please enter a city name.'); return; }
		call(tcAgentsAdmin.restUrl + 'b2b/import', {city: c}, $(this).find('button'), 'City agencies imported successfully!');
	});

	$('#tc-b2b-raw-form').on('submit', function(e){
		e.preventDefault();
		var raw = $('#tc-b2b-raw-list').val();
		if(!raw.trim()){ alert('Please paste one or more agency rows.'); return; }
		call(tcAgentsAdmin.restUrl + 'b2b/import', {raw_list: raw}, $(this).find('button'), 'Pasted agencies imported and parsed!');
	});

	$('#tc-b2b-outreach-batch').on('click', function(){
		call(tcAgentsAdmin.restUrl + 'b2b/outreach', {}, this, 'Batch outreach dispatched via WhatsApp/Email.');
	});

	$('.tc-b2b-outreach-one').on('click', function(){
		call(tcAgentsAdmin.restUrl + 'b2b/outreach', {agency_id: $(this).data('id')}, this, 'Partnership pitch dispatched via WhatsApp & Email!');
	});

	$('.tc-b2b-crm-one').on('click', function(){
		call(tcAgentsAdmin.restUrl + 'b2b/sync-crm', {agency_id: $(this).data('id')}, this, 'Agency synced to FluentCRM & Twenty CRM!');
	});
})(jQuery);
</script>
