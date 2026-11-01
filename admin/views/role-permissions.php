<?php
/**
 * Role Permissions & Feature Access Matrix (RBAC) View.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$roles_config = array(
	'administrator'    => array(
		'label'       => __( 'Administrator', 'tripcosmos-agents' ),
		'badge'       => 'Super Admin',
		'badge_color' => '#dc2626',
		'locked'      => true,
	),
	'tc_sales_manager' => array(
		'label'       => __( 'Sales Manager', 'tripcosmos-agents' ),
		'badge'       => 'Management',
		'badge_color' => '#f59e0b',
		'locked'      => false,
	),
	'tc_sales_agent'   => array(
		'label'       => __( 'Sales Representative', 'tripcosmos-agents' ),
		'badge'       => 'Frontline Rep',
		'badge_color' => '#10b981',
		'locked'      => false,
	),
	'tcfp_manager'     => array(
		'label'       => __( 'Finance Manager', 'tripcosmos-agents' ),
		'badge'       => 'Finance Desk',
		'badge_color' => '#3b82f6',
		'locked'      => false,
	),
	'tcfp_accountant'  => array(
		'label'       => __( 'Accountant', 'tripcosmos-agents' ),
		'badge'       => 'Accounts Desk',
		'badge_color' => '#6366f1',
		'locked'      => false,
	),
	'tc_dispatcher'    => array(
		'label'       => __( 'Dispatcher & Fleet Ops', 'tripcosmos-agents' ),
		'badge'       => 'Operations',
		'badge_color' => '#8b5cf6',
		'locked'      => false,
	),
);

$feature_defs = class_exists( 'TC_Agents_Mobile_CRM_API' ) ? TC_Agents_Mobile_CRM_API::get_all_feature_definitions() : array();

// Group features by category
$categories = array();
foreach ( $feature_defs as $f_key => $f_info ) {
	$cat = $f_info['category'] ?? __( 'General', 'tripcosmos-agents' );
	if ( ! isset( $categories[ $cat ] ) ) {
		$categories[ $cat ] = array();
	}
	$categories[ $cat ][ $f_key ] = $f_info;
}

$saved_matrix = get_option( 'tc_agents_role_permissions', array() );
$defaults     = class_exists( 'TC_Agents_Mobile_CRM_API' ) ? TC_Agents_Mobile_CRM_API::get_default_role_permissions() : array();

?>

<style>
.tc-rbac-wrap {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	max-width: 1240px;
	margin: 20px auto 40px;
	color: #0f172a;
}
.tc-rbac-hero {
	background: linear-gradient(135deg, #0a0f1d 0%, #111e38 60%, #1e1b4b 100%);
	border-radius: 20px;
	padding: 28px 36px;
	color: #ffffff;
	box-shadow: 0 10px 30px rgba(10, 15, 29, 0.25);
	margin-bottom: 24px;
}
.tc-rbac-hero h1 {
	font-size: 24px;
	font-weight: 800;
	color: #ffffff;
	margin: 0 0 8px 0;
	display: flex;
	align-items: center;
	gap: 12px;
}
.tc-rbac-pill {
	display: inline-block;
	font-size: 11px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	padding: 4px 10px;
	border-radius: 20px;
	background: rgba(217, 119, 6, 0.2);
	color: #fbbf24;
	border: 1px solid rgba(251, 191, 36, 0.35);
}
.tc-rbac-hero p {
	font-size: 13.5px;
	color: #cbd5e1;
	line-height: 1.5;
	margin: 0;
	max-width: 880px;
}
.tc-rbac-alert {
	background: #ecfdf5;
	border-left: 4px solid #10b981;
	padding: 12px 18px;
	border-radius: 8px;
	margin-bottom: 22px;
	font-size: 13.5px;
	color: #065f46;
	display: flex;
	align-items: center;
	gap: 10px;
}
.tc-rbac-security-note {
	background: #fffbeb;
	border: 1px solid #fde68a;
	border-left: 4px solid #f59e0b;
	padding: 14px 20px;
	border-radius: 10px;
	margin-bottom: 24px;
	font-size: 13px;
	color: #92400e;
	line-height: 1.5;
}
.tc-rbac-table-card {
	background: #ffffff;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
	overflow: hidden;
	margin-bottom: 24px;
}
.tc-rbac-table {
	width: 100%;
	border-collapse: collapse;
	text-align: left;
}
.tc-rbac-table th {
	background: #f8fafc;
	padding: 14px 16px;
	font-size: 12px;
	font-weight: 700;
	color: #475569;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	border-bottom: 2px solid #e2e8f0;
}
.tc-rbac-table td {
	padding: 14px 16px;
	font-size: 13px;
	border-bottom: 1px solid #f1f5f9;
	vertical-align: middle;
}
.tc-rbac-cat-row td {
	background: #f1f5f9;
	font-size: 11.5px;
	font-weight: 800;
	color: #334155;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	padding: 10px 16px;
}
.tc-rbac-feature-name {
	font-weight: 700;
	color: #0f172a;
	margin-bottom: 2px;
}
.tc-rbac-feature-desc {
	font-size: 11.5px;
	color: #64748b;
	line-height: 1.35;
}
.tc-rbac-checkbox {
	width: 18px;
	height: 18px;
	accent-color: #d97706;
	cursor: pointer;
}
.tc-rbac-checkbox:disabled {
	cursor: not-allowed;
	opacity: 0.7;
}
.tc-rbac-role-col {
	text-align: center !important;
}
.tc-rbac-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding-top: 10px;
}
.tc-rbac-btn-save {
	background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
	color: #ffffff;
	border: none;
	padding: 12px 28px;
	font-size: 14px;
	font-weight: 700;
	border-radius: 10px;
	cursor: pointer;
	box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35);
	transition: all 0.2s ease;
}
.tc-rbac-btn-save:hover {
	background: linear-gradient(135deg, #b45309 0%, #92400e 100%);
	box-shadow: 0 6px 16px rgba(217, 119, 6, 0.45);
	transform: translateY(-1px);
}
.tc-rbac-btn-reset {
	background: transparent;
	color: #64748b;
	border: 1px solid #cbd5e1;
	padding: 10px 20px;
	font-size: 13px;
	font-weight: 600;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.2s ease;
}
.tc-rbac-btn-reset:hover {
	background: #f1f5f9;
	color: #0f172a;
}
.tc-badge-micro {
	display: inline-block;
	font-size: 9.5px;
	font-weight: 700;
	text-transform: uppercase;
	padding: 2px 6px;
	border-radius: 6px;
	color: #ffffff;
	margin-top: 4px;
}
</style>

<div class="tc-rbac-wrap">
	<div class="tc-rbac-hero">
		<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
			<span class="tc-rbac-pill">Bank-Grade RBAC Security &amp; Isolation</span>
			<span style="font-size: 12px; color: #94a3b8;"><?php echo esc_html( gmdate( 'd M Y, H:i' ) . ' UTC' ); ?></span>
		</div>
		<h1>
			<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
			</svg>
			<?php esc_html_e( 'Role Permissions & Feature Access Matrix', 'tripcosmos-agents' ); ?>
		</h1>
		<p>
			<?php esc_html_e( 'Configure granular access permissions for every role across X-Agents mobile app and WordPress admin. Frontline Sales Agents are prevented by default from viewing confidential Financial Intelligence, Zoho Books Invoices, Customer Unit Margins, Bank Reconciliations, and Payroll ledgers.', 'tripcosmos-agents' ); ?>
		</p>
	</div>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="tc-rbac-alert">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
			<span><?php echo esc_html( $notice ); ?></span>
		</div>
	<?php endif; ?>

	<div class="tc-rbac-security-note">
		<strong>🔒 <?php esc_html_e( 'Financial Isolation Guarantee:', 'tripcosmos-agents' ); ?></strong>
		<?php esc_html_e( 'When "Financial Intelligence Hub" is unchecked for a role (e.g. Sales Representative), the Finance Hub tab is completely hidden from their mobile footer dock, and all backend financial REST endpoints immediately reject incoming requests with HTTP 403 Forbidden.', 'tripcosmos-agents' ); ?>
	</div>

	<form method="post" action="">
		<?php wp_nonce_field( 'tc_agents_save_roles_action', 'tc_agents_roles_nonce' ); ?>

		<div class="tc-rbac-table-card">
			<table class="tc-rbac-table">
				<thead>
					<tr>
						<th style="width: 32%;"><?php esc_html_e( 'Super-App Feature / Capability', 'tripcosmos-agents' ); ?></th>
						<?php foreach ( $roles_config as $r_key => $r_meta ) : ?>
							<th class="tc-rbac-role-col" style="width: <?php echo esc_attr( round( 68 / count( $roles_config ) ) ); ?>%;">
								<div><?php echo esc_html( $r_meta['label'] ); ?></div>
								<span class="tc-badge-micro" style="background-color: <?php echo esc_attr( $r_meta['badge_color'] ); ?>;">
									<?php echo esc_html( $r_meta['badge'] ); ?>
								</span>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $cat_title => $cat_features ) : ?>
						<tr class="tc-rbac-cat-row">
							<td colspan="<?php echo esc_attr( count( $roles_config ) + 1 ); ?>">
								<?php echo esc_html( $cat_title ); ?>
							</td>
						</tr>
						<?php foreach ( $cat_features as $f_key => $f_info ) : ?>
							<tr>
								<td>
									<div class="tc-rbac-feature-name">
										<?php if ( 'financial_hub' === $f_key ) : ?>
											<span style="color: #dc2626;">💳 </span>
										<?php endif; ?>
										<?php echo esc_html( $f_info['label'] ); ?>
									</div>
									<div class="tc-rbac-feature-desc"><?php echo esc_html( $f_info['description'] ); ?></div>
								</td>
								<?php foreach ( $roles_config as $r_key => $r_meta ) : ?>
									<?php
									$is_locked = ! empty( $r_meta['locked'] );
									$is_checked = false;

									if ( $is_locked ) {
										$is_checked = true;
									} elseif ( isset( $saved_matrix[ $r_key ] ) && is_array( $saved_matrix[ $r_key ] ) ) {
										$is_checked = in_array( $f_key, $saved_matrix[ $r_key ], true ) || in_array( 'all', $saved_matrix[ $r_key ], true );
									} elseif ( isset( $defaults[ $r_key ] ) && is_array( $defaults[ $r_key ] ) ) {
										$is_checked = in_array( $f_key, $defaults[ $r_key ], true ) || in_array( 'all', $defaults[ $r_key ], true );
									}
									?>
									<td class="tc-rbac-role-col">
										<?php if ( $is_locked ) : ?>
											<input type="checkbox" class="tc-rbac-checkbox" checked disabled title="<?php esc_attr_e( 'Administrator always has full access', 'tripcosmos-agents' ); ?>" />
											<input type="hidden" name="tc_agents_roles[<?php echo esc_attr( $r_key ); ?>][]" value="<?php echo esc_attr( $f_key ); ?>" />
										<?php else : ?>
											<input type="checkbox" class="tc-rbac-checkbox" name="tc_agents_roles[<?php echo esc_attr( $r_key ); ?>][]" value="<?php echo esc_attr( $f_key ); ?>" <?php checked( $is_checked ); ?> />
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="tc-rbac-actions">
			<button type="submit" name="tc_agents_reset_defaults" value="1" class="tc-rbac-btn-reset" onclick="return confirm('<?php esc_attr_e( 'Reset all role permissions to factory defaults? Frontline sales agents will have Finance Hub disabled.', 'tripcosmos-agents' ); ?>');">
				<?php esc_html_e( '↺ Reset to Secure Defaults', 'tripcosmos-agents' ); ?>
			</button>
			<button type="submit" class="tc-rbac-btn-save">
				<?php esc_html_e( 'Save Role Permissions', 'tripcosmos-agents' ); ?>
			</button>
		</div>
	</form>
</div>
