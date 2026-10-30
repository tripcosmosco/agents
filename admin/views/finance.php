<?php
/**
 * Financial Intelligence Hub View.
 * Unified multi-channel financial ledger:
 * 1. State Bank of India (SBI) Statement Reconciliation
 * 2. Zoho Books API v3 Invoicing & Receivables
 * 3. Marketing ROI, Unit Economics, & ROAS Attribution
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_bank = $wpdb->prefix . 'tcfp_bank_transactions';
$table_bk   = $wpdb->prefix . 'tc_agent_bookings';

// Handle Statement CSV Upload if posted
$upload_notice = '';
if ( ! empty( $_POST['tc_action'] ) && 'upload_sbi_csv' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	$csv_content = '';
	if ( ! empty( $_FILES['sbi_csv_file']['tmp_name'] ) ) {
		$csv_content = file_get_contents( $_FILES['sbi_csv_file']['tmp_name'] );
	} elseif ( ! empty( $_POST['sbi_csv_raw'] ) ) {
		$csv_content = wp_unslash( $_POST['sbi_csv_raw'] );
	}

	if ( ! empty( trim( $csv_content ) ) ) {
		$req = new WP_REST_Request( 'POST', '/tc-agents/v1/mobile/bank-statement/upload' );
		$req->set_body_params( array(
			'csv_data'   => $csv_content,
			'bank_name'  => 'State Bank of India (SBI)',
			'account_no' => sanitize_text_field( $_POST['account_no'] ?? '00000044078696102' ),
		) );
		$res = TC_Agents_Mobile_CRM_API::handle_bank_statement_upload( $req );
		$data = $res->get_data();
		if ( ! empty( $data['ok'] ) ) {
			$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Statement Imported Successfully:</strong> ' . esc_html( $data['message'] ?? 'Transactions parsed.' ) . '</p></div>';
		} else {
			$upload_notice = '<div class="notice notice-error is-dismissible"><p><strong>Import Error:</strong> ' . esc_html( $data['error'] ?? 'Could not parse CSV.' ) . '</p></div>';
		}
	}
}

// Handle Ad Spend Update
if ( ! empty( $_POST['tc_action'] ) && 'update_ad_spend' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	$spend = floatval( $_POST['monthly_ad_spend'] ?? 0 );
	update_option( 'tc_agents_monthly_ad_spend', $spend );
	$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Marketing Budget Saved:</strong> Updated monthly ad spend to ₹' . number_format( $spend, 2 ) . '</p></div>';
}

// Handle Google Ads Live API Sync
if ( ! empty( $_POST['tc_action'] ) && 'sync_gads_live' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	if ( class_exists( 'TC_Integration_Google_Ads' ) ) {
		$res = TC_Integration_Google_Ads::sync_campaigns();
		if ( ! empty( $res['ok'] ) ) {
			$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Google Ads Live Sync Complete:</strong> Retrieved ' . count( $res['campaigns'] ) . ' campaigns. Total 30-day spend: ₹' . number_format( $res['total_spend'], 2 ) . '.</p></div>';
		} else {
			$upload_notice = '<div class="notice notice-error is-dismissible"><p><strong>Google Ads Sync Notice:</strong> ' . esc_html( $res['error'] ?? 'API connection failed.' ) . '</p></div>';
		}
	}
}

// Handle Google Ads CSV Report Upload
if ( ! empty( $_POST['tc_action'] ) && 'upload_gads_csv' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	$csv_content = '';
	if ( ! empty( $_FILES['gads_csv_file']['tmp_name'] ) ) {
		$csv_content = file_get_contents( $_FILES['gads_csv_file']['tmp_name'] );
	} elseif ( ! empty( $_POST['gads_csv_raw'] ) ) {
		$csv_content = wp_unslash( $_POST['gads_csv_raw'] );
	}

	if ( class_exists( 'TC_Integration_Google_Ads' ) && ! empty( trim( $csv_content ) ) ) {
		$res = TC_Integration_Google_Ads::import_campaigns_csv( $csv_content );
		if ( ! empty( $res['ok'] ) ) {
			$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Google Ads Report Imported:</strong> ' . esc_html( $res['message'] ) . '</p></div>';
		} else {
			$upload_notice = '<div class="notice notice-error is-dismissible"><p><strong>Google Ads Import Error:</strong> ' . esc_html( $res['error'] ?? 'Could not parse CSV.' ) . '</p></div>';
		}
	}
}

// Handle Offline Conversion Upload (OCT) Test
if ( ! empty( $_POST['tc_action'] ) && 'upload_oct_conversion' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	if ( class_exists( 'TC_Integration_Google_Ads' ) ) {
		$test_gclid = sanitize_text_field( $_POST['oct_gclid'] ?? '' );
		$test_val   = floatval( $_POST['oct_value'] ?? 0 );
		$test_order = sanitize_text_field( $_POST['oct_order_id'] ?? ( 'TEST-' . time() ) );

		$res = TC_Integration_Google_Ads::upload_offline_conversion( $test_gclid, $test_val, $test_order );
		if ( ! empty( $res['ok'] ) ) {
			$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Conversion Uploaded to Google Ads:</strong> ' . esc_html( $res['message'] ) . '</p></div>';
		} else {
			$upload_notice = '<div class="notice notice-error is-dismissible"><p><strong>Google Ads OCT Notice:</strong> ' . esc_html( $res['error'] ?? 'Upload failed.' ) . '</p></div>';
		}
	}
}

// Handle Zoho Live Sync
if ( ! empty( $_POST['tc_action'] ) && 'sync_zoho_live' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	if ( class_exists( 'TC_Integration_Zoho' ) ) {
		$res = TC_Integration_Zoho::sync();
		if ( ! empty( $res['ok'] ) ) {
			$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Zoho Books Synced:</strong> Updated organization ledger and customer invoices.</p></div>';
		} else {
			$upload_notice = '<div class="notice notice-error is-dismissible"><p><strong>Zoho Books Notice:</strong> ' . esc_html( $res['error'] ?? 'Sync failed.' ) . '</p></div>';
		}
	}
}

// Handle Add Customer Direct Expense
if ( ! empty( $_POST['tc_action'] ) && 'add_customer_expense' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	$inv_no      = sanitize_text_field( $_POST['exp_invoice_no'] ?? '' );
	$cust_name   = sanitize_text_field( $_POST['exp_customer_name'] ?? '' );
	$category    = sanitize_text_field( $_POST['exp_category'] ?? 'other' );
	$vendor_name = sanitize_text_field( $_POST['exp_vendor_name'] ?? '' );
	$amount      = floatval( $_POST['exp_amount'] ?? 0 );
	$exp_date    = sanitize_text_field( $_POST['exp_date'] ?? current_time( 'Y-m-d' ) );
	$method      = sanitize_text_field( $_POST['exp_payment_method'] ?? 'bank_debit' );
	$notes       = sanitize_textarea_field( $_POST['exp_notes'] ?? '' );

	if ( ! empty( $inv_no ) && $amount > 0 ) {
		$table_cust_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		$wpdb->insert(
			$table_cust_exp,
			array(
				'invoice_no'     => $inv_no,
				'customer_name'  => $cust_name ?: 'Traveler',
				'category'       => $category,
				'vendor_name'    => $vendor_name,
				'amount'         => $amount,
				'expense_date'   => $exp_date,
				'payment_method' => $method,
				'notes'          => $notes,
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Customer Expense Added:</strong> Recorded ₹' . number_format( $amount, 2 ) . ' (' . esc_html( $category ) . ') for ' . esc_html( $inv_no ) . '.</p></div>';
	}
}

// Handle Link Bank Debit to Customer Invoice
if ( ! empty( $_POST['tc_action'] ) && 'link_bank_debit' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	$tx_id       = absint( $_POST['link_txn_id'] ?? 0 );
	$inv_no      = sanitize_text_field( $_POST['link_invoice_no'] ?? '' );
	$cust_name   = sanitize_text_field( $_POST['link_customer_name'] ?? '' );
	$category    = sanitize_text_field( $_POST['link_category'] ?? 'cab' );
	$vendor_name = sanitize_text_field( $_POST['link_vendor_name'] ?? '' );
	$notes       = sanitize_textarea_field( $_POST['link_notes'] ?? '' );

	$table_bank = $wpdb->prefix . 'tcfp_bank_transactions';
	$tx = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_bank WHERE id = %d", $tx_id ), ARRAY_A );
	if ( $tx && ! empty( $inv_no ) ) {
		$table_cust_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
		$wpdb->insert(
			$table_cust_exp,
			array(
				'invoice_no'     => $inv_no,
				'customer_name'  => $cust_name ?: 'Traveler',
				'category'       => $category,
				'vendor_name'    => $vendor_name ?: ( $tx['description'] ?? '' ),
				'amount'         => (float) $tx['amount'],
				'expense_date'   => $tx['txn_date'],
				'payment_method' => 'bank_debit',
				'bank_txn_id'    => $tx_id,
				'notes'          => $notes ?: ( 'Linked from Bank Txn Ref: ' . ( $tx['ref_no'] ?? '' ) ),
				'created_at'     => current_time( 'mysql' ),
			)
		);
		$exp_id = $wpdb->insert_id;

		$cat_label = 'Vendor & Tour Expense';
		if ( 'hotel' === $category ) { $cat_label = 'Hotel & Stay'; }
		elseif ( 'cab' === $category ) { $cat_label = 'Cab & Transport'; }
		elseif ( 'aarti_boat' === $category ) { $cat_label = 'Boat & Pilgrimage'; }
		elseif ( 'guide_temple' === $category ) { $cat_label = 'Guide & Temple VIP'; }
		elseif ( 'meals' === $category ) { $cat_label = 'Meals & Refreshment'; }

		$wpdb->update(
			$table_bank,
			array(
				'reconciled'         => 1,
				'category'           => $cat_label,
				'matched_expense_id' => $exp_id,
			),
			array( 'id' => $tx_id )
		);

		$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Bank Debit Linked:</strong> Assigned ₹' . number_format( (float) $tx['amount'], 2 ) . ' to ' . esc_html( $inv_no ) . ' (' . esc_html( $cust_name ) . ').</p></div>';
	}
}

// Handle Delete Customer Expense
if ( ! empty( $_POST['tc_action'] ) && 'delete_customer_expense' === $_POST['tc_action'] && check_admin_referer( 'tc_finance_action', '_tc_nonce' ) ) {
	$exp_id = absint( $_POST['delete_exp_id'] ?? 0 );
	$table_cust_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_cust_exp WHERE id = %d", $exp_id ), ARRAY_A );
	if ( $row ) {
		if ( ! empty( $row['bank_txn_id'] ) ) {
			$table_bank = $wpdb->prefix . 'tcfp_bank_transactions';
			$wpdb->update( $table_bank, array( 'reconciled' => 0, 'matched_expense_id' => null ), array( 'id' => (int) $row['bank_txn_id'] ) );
		}
		$wpdb->delete( $table_cust_exp, array( 'id' => $exp_id ) );
		$upload_notice = '<div class="notice notice-success is-dismissible"><p><strong>✓ Expense Deleted:</strong> Removed entry and updated customer margins.</p></div>';
	}
}

// 1. Bank Statement Metrics
$total_credits = 0.0;
$total_debits  = 0.0;
$net_cashflow  = 0.0;
$credit_count  = 0;
$debit_count   = 0;
$total_count   = 0;
$txns          = array();
$expense_cats  = array();
$inflow_cats   = array();

// Filter parameters
$current_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : 'all';
$current_cat  = isset( $_GET['filter_cat'] ) ? sanitize_text_field( $_GET['filter_cat'] ) : 'all';
$search_q     = isset( $_GET['s_q'] ) ? sanitize_text_field( $_GET['s_q'] ) : '';
$limit_opt    = isset( $_GET['limit_opt'] ) ? sanitize_text_field( $_GET['limit_opt'] ) : '100';

if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_bank ) ) ) {
	$total_credits = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM $table_bank WHERE type = 'credit'" );
	$total_debits  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM $table_bank WHERE type = 'debit'" );
	$net_cashflow  = $total_credits - $total_debits;

	$credit_count  = (int) $wpdb->get_var( "SELECT COUNT(1) FROM $table_bank WHERE type = 'credit'" );
	$debit_count   = (int) $wpdb->get_var( "SELECT COUNT(1) FROM $table_bank WHERE type = 'debit'" );
	$total_count   = $credit_count + $debit_count;

	// Expense category breakdown
	$expense_cats = $wpdb->get_results( "
		SELECT category, COUNT(1) as cnt, SUM(amount) as total 
		FROM $table_bank 
		WHERE type = 'debit' 
		GROUP BY category 
		ORDER BY total DESC
	", ARRAY_A );

	// Build query with filters
	$where = array( '1=1' );
	if ( 'debit' === $current_type ) {
		$where[] = "type = 'debit'";
	} elseif ( 'credit' === $current_type ) {
		$where[] = "type = 'credit'";
	}

	if ( 'all' !== $current_cat && ! empty( $current_cat ) ) {
		$where[] = $wpdb->prepare( "category = %s", $current_cat );
	}

	if ( ! empty( $search_q ) ) {
		$like = '%' . $wpdb->esc_like( $search_q ) . '%';
		$where[] = $wpdb->prepare( "(description LIKE %s OR ref_no LIKE %s)", $like, $like );
	}

	$where_sql = implode( ' AND ', $where );
	$limit_sql = ( 'all' === $limit_opt ) ? '' : 'LIMIT ' . min( 500, max( 10, absint( $limit_opt ) ) );

	$txns = $wpdb->get_results( "
		SELECT * FROM $table_bank 
		WHERE $where_sql 
		ORDER BY txn_date DESC, id DESC 
		$limit_sql
	", ARRAY_A );
}

// 2. Zoho Books Metrics
$zoho_active  = class_exists( 'TC_Integration_Zoho' ) && TC_Integration_Zoho::is_configured();
$zoho_summary = class_exists( 'TC_Integration_Zoho' ) ? TC_Integration_Zoho::get_summary() : array();
$zoho_metrics = $zoho_summary['metrics'] ?? array(
	'total_invoiced' => 0.0,
	'total_paid'     => 0.0,
	'total_balance'  => 0.0,
	'overdue_count'  => 0,
);
$zoho_invoices = $zoho_summary['invoices'] ?? array();

// 3. Marketing ROI & Unit Economics
$monthly_ad_spend = (float) get_option( 'tc_agents_monthly_ad_spend', 45000.0 );
$gads_summary     = class_exists( 'TC_Integration_Google_Ads' ) ? TC_Integration_Google_Ads::get_summary() : array();
$gads_campaigns   = $gads_summary['campaigns'] ?? array();
$gads_configured  = ! empty( $gads_summary['configured'] );
$gads_auth_url    = $gads_summary['auth_url'] ?? '';
$gads_spend_api   = (float) ( $gads_summary['total_30d_spend'] ?? 0.0 );
if ( $gads_spend_api > 0 ) {
	$monthly_ad_spend = $gads_spend_api;
}
$gross_revenue    = max( $total_credits, (float) ( $zoho_metrics['total_invoiced'] ?? 0 ) );
$vendor_costs     = $total_debits;
$net_profit       = $gross_revenue - $monthly_ad_spend - $vendor_costs;
$roas             = $monthly_ad_spend > 0 ? ( $gross_revenue / $monthly_ad_spend ) : 0.0;
$profit_margin_pct= $gross_revenue > 0 ? ( ( $net_profit / $gross_revenue ) * 100 ) : 0.0;

// Total Bookings closed
$total_bookings = 0;
if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_bk ) ) ) {
	$total_bookings = (int) $wpdb->get_var( "SELECT COUNT(1) FROM $table_bk WHERE status IN ('confirmed','completed')" );
}
if ( $total_bookings === 0 && count( $zoho_invoices ) > 0 ) {
	$total_bookings = count( $zoho_invoices );
}
$cac = $total_bookings > 0 ? ( $monthly_ad_spend / $total_bookings ) : 0.0;

// 4. Customer Margin & Profit Calculations (Zoho Invoices vs Direct Tour Expenses)
$table_cust_exp = $wpdb->prefix . 'tc_agent_customer_expenses';
$has_exp_table  = (bool) $wpdb->get_var( "SHOW TABLES LIKE '$table_cust_exp'" );

$customer_expenses_map = array();
$all_expenses_raw      = array();
if ( $has_exp_table ) {
	$all_expenses_raw = $wpdb->get_results( "SELECT * FROM $table_cust_exp ORDER BY expense_date DESC, id DESC", ARRAY_A ) ?: array();
	foreach ( $all_expenses_raw as $er ) {
		$inv_k = trim( (string) $er['invoice_no'] );
		if ( ! isset( $customer_expenses_map[ $inv_k ] ) ) {
			$customer_expenses_map[ $inv_k ] = array();
		}
		$customer_expenses_map[ $inv_k ][] = $er;
	}
}

$cust_ledger_items = array();
$tot_cust_revenue  = 0.0;
$tot_cust_paid     = 0.0;
$tot_cust_cogs     = 0.0;
$tot_cust_hotel    = 0.0;
$tot_cust_cab      = 0.0;
$tot_cust_aarti    = 0.0;
$tot_cust_guide    = 0.0;
$tot_cust_other    = 0.0;

foreach ( $zoho_invoices as $zi ) {
	$inv_no    = trim( (string) ( $zi['invoice_number'] ?? '' ) );
	$tot_inv   = (float) ( $zi['total'] ?? 0 );
	$bal_inv   = (float) ( $zi['balance'] ?? 0 );
	$paid_inv  = max( 0.0, $tot_inv - $bal_inv );
	$cname     = (string) ( $zi['customer_name'] ?? 'Traveler' );

	$tot_cust_revenue += $tot_inv;
	$tot_cust_paid    += $paid_inv;

	$c_exps    = $customer_expenses_map[ $inv_no ] ?? array();
	$h_cost    = 0.0;
	$c_cost    = 0.0;
	$a_cost    = 0.0;
	$g_cost    = 0.0;
	$m_cost    = 0.0;
	$o_cost    = 0.0;

	foreach ( $c_exps as $ce ) {
		$amt = (float) ( $ce['amount'] ?? 0 );
		$cat = strtolower( (string) ( $ce['category'] ?? 'other' ) );
		if ( 'hotel' === $cat ) { $h_cost += $amt; }
		elseif ( 'cab' === $cat ) { $c_cost += $amt; }
		elseif ( 'aarti_boat' === $cat ) { $a_cost += $amt; }
		elseif ( 'guide_temple' === $cat ) { $g_cost += $amt; }
		elseif ( 'meals' === $cat ) { $m_cost += $amt; }
		else { $o_cost += $amt; }
	}

	$direct_cogs = $h_cost + $c_cost + $a_cost + $g_cost + $m_cost + $o_cost;
	$profit      = $tot_inv - $direct_cogs;
	$margin_pct  = $tot_inv > 0 ? round( ( $profit / $tot_inv ) * 100, 1 ) : 0.0;

	$tot_cust_cogs  += $direct_cogs;
	$tot_cust_hotel += $h_cost;
	$tot_cust_cab   += $c_cost;
	$tot_cust_aarti += $a_cost;
	$tot_cust_guide += $g_cost;
	$tot_cust_other += ( $m_cost + $o_cost );

	$cust_ledger_items[] = array(
		'invoice_number' => $inv_no,
		'customer_name'  => $cname,
		'date'           => (string) ( $zi['date'] ?? '' ),
		'due_date'       => (string) ( $zi['due_date'] ?? '' ),
		'status'         => (string) ( $zi['status'] ?? 'draft' ),
		'total'          => $tot_inv,
		'paid'           => $paid_inv,
		'balance'        => $bal_inv,
		'hotel'          => $h_cost,
		'cab'            => $c_cost,
		'aarti_boat'     => $a_cost,
		'guide_temple'   => $g_cost,
		'other'          => ( $m_cost + $o_cost ),
		'total_cogs'     => $direct_cogs,
		'profit'         => $profit,
		'margin_pct'     => $margin_pct,
		'expenses'       => $c_exps,
	);
}

$tot_cust_profit = $tot_cust_revenue - $tot_cust_cogs;
$overall_cust_margin_pct = $tot_cust_revenue > 0 ? round( ( $tot_cust_profit / $tot_cust_revenue ) * 100, 1 ) : 0.0;
?>

<div class="wrap tc-int-wrap">
	<?php echo $upload_notice; ?>

	<!-- Hero Header -->
	<div class="tc-int-hero">
		<div>
			<h1>
				<span>💰</span>
				Financial Intelligence Hub
			</h1>
			<p>
				Unified multi-source financial control center: SBI Bank Statement reconciliation, Zoho Books GST Invoicing, and marketing Return On Ad Spend (ROAS) analytics.
			</p>
		</div>
		<div style="text-align: right;">
			<span class="tc-badge-gold">Enterprise Finance</span>
			<div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">
				Account: <strong>GALLEXIA VOYAGE OPC PVT LTD</strong>
			</div>
		</div>
	</div>

	<!-- Top Aggregate KPI Cards -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
		<div class="tc-card-lux" style="margin: 0; padding: 20px; border-left: 4px solid #10b981;">
			<div style="font-size: 11.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Gross Revenue (Credited)</div>
			<div style="font-size: 24px; font-weight: 800; color: #10b981; margin: 8px 0;">₹<?php echo number_format( $total_credits, 2 ); ?></div>
			<div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo (int) $credit_count; ?> Inflow Transactions</div>
		</div>
		<div class="tc-card-lux" style="margin: 0; padding: 20px; border-left: 4px solid #ef4444;">
			<div style="font-size: 11.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Total Expenses (Debited)</div>
			<div style="font-size: 24px; font-weight: 800; color: #ef4444; margin: 8px 0;">₹<?php echo number_format( $total_debits, 2 ); ?></div>
			<div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo (int) $debit_count; ?> Debited Expense Rows</div>
		</div>
		<div class="tc-card-lux" style="margin: 0; padding: 20px; border-left: 4px solid #3b82f6;">
			<div style="font-size: 11.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Net Closing Balance</div>
			<div style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 8px 0;">₹<?php echo number_format( $net_cashflow, 2 ); ?></div>
			<div style="font-size: 11px; color: #3b82f6; font-weight: 600;">SBI A/C: 00000044078696102</div>
		</div>
		<div class="tc-card-lux" style="margin: 0; padding: 20px; border-left: 4px solid #f59e0b;">
			<div style="font-size: 11.5px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Marketing ROAS (Google Ads)</div>
			<div style="font-size: 24px; font-weight: 800; color: #f59e0b; margin: 8px 0;"><?php echo number_format( $roas, 2 ); ?>x</div>
			<div style="font-size: 11px; color: #64748b; font-weight: 600;">Budget: ₹<?php echo number_format( $monthly_ad_spend, 0 ); ?>/mo</div>
		</div>
	</div>

	<!-- TAB NAVIGATION -->
	<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<a href="#tab-margins" class="nav-tab nav-tab-active" onclick="switchFinanceTab(event, 'tab-margins');">🎯 1. Customer Profit &amp; Margin Ledger</a>
		<a href="#tab-sbi" class="nav-tab" onclick="switchFinanceTab(event, 'tab-sbi');">🏛️ 2. SBI Bank Reconciliation &amp; Debits</a>
		<a href="#tab-zoho" class="nav-tab" onclick="switchFinanceTab(event, 'tab-zoho');">📚 3. Zoho Books Ledger</a>
		<a href="#tab-roi" class="nav-tab" onclick="switchFinanceTab(event, 'tab-roi');">📈 4. Google Ads ROI &amp; Unit Economics</a>
	</h2>

	<!-- TAB 1: CUSTOMER PROFIT & MARGIN LEDGER -->
	<div id="tab-margins" class="tc-tab-content">
		<div class="tc-card-lux">
			<div class="tc-card-header-flex">
				<div>
					<div class="tc-card-title">
						<span>🎯</span>
						Per-Customer Profitability &amp; Direct Tour Expense Ledger
					</div>
					<p class="tc-card-desc" style="margin: 4px 0 0;">
						Real-time unit economics per customer: <strong>Zoho Invoiced Revenue</strong> minus direct tour payouts for Hotels, Cabs, Ganga Aarti Boats, and Temple Guides.
					</p>
				</div>
				<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<button type="button" class="button button-primary" onclick="openAddExpenseModal('', '')" style="background: #0f172a; border-color: #0f172a; font-weight: 700; height: 32px;">
						+ Record Customer Expense
					</button>
					<form method="post" style="display: inline; margin: 0;">
						<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
						<input type="hidden" name="tc_action" value="sync_zoho_live" />
						<button type="submit" class="button" title="Sync live invoices from Zoho Books" style="height: 32px;">🔄 Sync Zoho</button>
					</form>
				</div>
			</div>

			<!-- Customer Margin Summary KPI Ribbon -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 20px 0 24px;">
				<div style="background: #f8fafc; border-radius: 12px; padding: 14px; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6;">
					<div style="font-size: 11px; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Invoiced</div>
					<div style="font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 4px;">₹<?php echo number_format( $tot_cust_revenue, 2 ); ?></div>
					<div style="font-size: 11px; color: #10b981; font-weight: 600; margin-top: 2px;">Collected: ₹<?php echo number_format( $tot_cust_paid, 2 ); ?></div>
				</div>

				<div style="background: #fdf2f2; border-radius: 12px; padding: 14px; border: 1px solid #fee2e2; border-left: 4px solid #ef4444;">
					<div style="font-size: 11px; color: #991b1b; font-weight: 700; text-transform: uppercase;">Direct Tour Costs (COGS)</div>
					<div style="font-size: 22px; font-weight: 800; color: #ef4444; margin-top: 4px;">₹<?php echo number_format( $tot_cust_cogs, 2 ); ?></div>
					<div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">
						🏨 ₹<?php echo number_format( $tot_cust_hotel, 0 ); ?> &bull; 🚕 ₹<?php echo number_format( $tot_cust_cab, 0 ); ?> &bull; 🪔 ₹<?php echo number_format( $tot_cust_aarti, 0 ); ?>
					</div>
				</div>

				<div style="background: #f0fdf4; border-radius: 12px; padding: 14px; border: 1px solid #dcfce7; border-left: 4px solid #10b981;">
					<div style="font-size: 11px; color: #166534; font-weight: 700; text-transform: uppercase;">Net Customer Profit</div>
					<div style="font-size: 22px; font-weight: 800; color: #166534; margin-top: 4px;">₹<?php echo number_format( $tot_cust_profit, 2 ); ?></div>
					<div style="font-size: 11px; color: #166534; font-weight: 600; margin-top: 2px;">Gross Margin Across Trips</div>
				</div>

				<div style="background: #fffbeb; border-radius: 12px; padding: 14px; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b;">
					<div style="font-size: 11px; color: #92400e; font-weight: 700; text-transform: uppercase;">Average Margin %</div>
					<div style="font-size: 22px; font-weight: 800; color: #d97706; margin-top: 4px;"><?php echo number_format( $overall_cust_margin_pct, 1 ); ?>%</div>
					<div style="font-size: 11px; color: #92400e; font-weight: 600; margin-top: 2px;"><?php echo count( $cust_ledger_items ); ?> Customer Invoices Tracked</div>
				</div>
			</div>

			<!-- Search filter for Customer Table -->
			<div style="margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
				<div style="display: flex; gap: 8px; align-items: center;">
					<input type="text" id="tc-cust-search" placeholder="🔍 Search traveler name or invoice #..." onkeyup="filterCustomerMargins()" style="width: 280px; height: 34px; font-size: 12.5px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 10px;" />
					<span style="font-size: 12px; color: #64748b;" id="tc-cust-match-count"><?php echo count( $cust_ledger_items ); ?> customers</span>
				</div>
				<div style="font-size: 12px; color: #64748b;">
					<span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10b981; margin-right: 4px;"></span> &gt;30% High Margin
					<span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #f59e0b; margin-left: 12px; margin-right: 4px;"></span> 15-30% Moderate
					<span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #ef4444; margin-left: 12px; margin-right: 4px;"></span> &lt;15% Low
				</div>
			</div>

			<!-- Customer Profit Table -->
			<table class="widefat striped" id="tc-customer-margins-table">
				<thead>
					<tr>
						<th>Customer / Traveler</th>
						<th>Invoice # &amp; Date</th>
						<th style="text-align: right;">Invoiced (₹)</th>
						<th style="text-align: right; background: #fff5f5; color: #ef4444;">🏨 Hotel</th>
						<th style="text-align: right; background: #fff5f5; color: #ef4444;">🚕 Cab</th>
						<th style="text-align: right; background: #fff5f5; color: #ef4444;">🪔 Aarti / Boat</th>
						<th style="text-align: right; background: #fff5f5; color: #ef4444;">🛕 Guide / Other</th>
						<th style="text-align: right; background: #fee2e2; color: #991b1b;">Total Costs</th>
						<th style="text-align: right; background: #f0fdf4; color: #166534;">Net Profit</th>
						<th style="text-align: center;">Margin %</th>
						<th style="text-align: center; width: 130px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $cust_ledger_items ) ) : ?>
						<tr>
							<td colspan="11" style="text-align: center; padding: 28px; color: #64748b;">
								No Zoho invoices found. Click <strong>Sync Zoho</strong> above or connect your Zoho Books credentials in Integrations.
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $cust_ledger_items as $cli ) : ?>
							<?php
							$badge_bg = '#dcfce7';
							$badge_color = '#15803d';
							if ( $cli['total'] <= 0 || $cli['margin_pct'] < 15 ) {
								$badge_bg = '#fee2e2';
								$badge_color = '#b91c1c';
							} elseif ( $cli['margin_pct'] <= 30 ) {
								$badge_bg = '#fef3c7';
								$badge_color = '#b45309';
							}
							$exp_json = htmlspecialchars( wp_json_encode( $cli['expenses'] ), ENT_QUOTES, 'UTF-8' );
							?>
							<tr class="tc-cust-row">
								<td>
									<strong><?php echo esc_html( $cli['customer_name'] ); ?></strong>
									<div style="font-size: 11px; color: #64748b; margin-top: 2px;">
										Paid: ₹<?php echo number_format( $cli['paid'], 0 ); ?> &bull; Bal: ₹<?php echo number_format( $cli['balance'], 0 ); ?>
									</div>
								</td>
								<td>
									<code><?php echo esc_html( $cli['invoice_number'] ); ?></code>
									<div style="font-size: 11px; color: #94a3b8;"><?php echo esc_html( $cli['date'] ); ?></div>
								</td>
								<td style="text-align: right; font-weight: 700; font-family: monospace; font-size: 13px;">
									₹<?php echo number_format( $cli['total'], 2 ); ?>
								</td>
								<td style="text-align: right; color: <?php echo $cli['hotel'] > 0 ? '#ef4444' : '#94a3b8'; ?>; font-weight: <?php echo $cli['hotel'] > 0 ? '700' : 'normal'; ?>; font-family: monospace;">
									<?php echo $cli['hotel'] > 0 ? '₹' . number_format( $cli['hotel'], 0 ) : '—'; ?>
								</td>
								<td style="text-align: right; color: <?php echo $cli['cab'] > 0 ? '#ef4444' : '#94a3b8'; ?>; font-weight: <?php echo $cli['cab'] > 0 ? '700' : 'normal'; ?>; font-family: monospace;">
									<?php echo $cli['cab'] > 0 ? '₹' . number_format( $cli['cab'], 0 ) : '—'; ?>
								</td>
								<td style="text-align: right; color: <?php echo $cli['aarti_boat'] > 0 ? '#ef4444' : '#94a3b8'; ?>; font-weight: <?php echo $cli['aarti_boat'] > 0 ? '700' : 'normal'; ?>; font-family: monospace;">
									<?php echo $cli['aarti_boat'] > 0 ? '₹' . number_format( $cli['aarti_boat'], 0 ) : '—'; ?>
								</td>
								<td style="text-align: right; color: <?php echo $cli['other'] > 0 ? '#ef4444' : '#94a3b8'; ?>; font-weight: <?php echo $cli['other'] > 0 ? '700' : 'normal'; ?>; font-family: monospace;">
									<?php echo $cli['other'] > 0 ? '₹' . number_format( $cli['other'], 0 ) : '—'; ?>
								</td>
								<td style="text-align: right; font-weight: 800; color: #991b1b; background: #fff5f5; font-family: monospace; font-size: 13px;">
									₹<?php echo number_format( $cli['total_cogs'], 2 ); ?>
								</td>
								<td style="text-align: right; font-weight: 800; color: <?php echo $cli['profit'] >= 0 ? '#15803d' : '#b91c1c'; ?>; background: <?php echo $cli['profit'] >= 0 ? '#f0fdf4' : '#fff5f5'; ?>; font-family: monospace; font-size: 13.5px;">
									₹<?php echo number_format( $cli['profit'], 2 ); ?>
								</td>
								<td style="text-align: center;">
									<span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-weight: 800; font-size: 11px; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>;">
										<?php echo number_format( $cli['margin_pct'], 1 ); ?>%
									</span>
								</td>
								<td style="text-align: center;">
									<div style="display: flex; gap: 4px; justify-content: center;">
										<button type="button" class="button button-small" onclick="openAddExpenseModal('<?php echo esc_js( $cli['invoice_number'] ); ?>', '<?php echo esc_js( $cli['customer_name'] ); ?>')" title="Add Expense for this traveler" style="font-size: 11px; padding: 0 6px;">
											➕ Cost
										</button>
										<?php if ( ! empty( $cli['expenses'] ) ) : ?>
											<button type="button" class="button button-small" onclick="openViewExpenseModal('<?php echo esc_js( $cli['invoice_number'] ); ?>', '<?php echo esc_js( $cli['customer_name'] ); ?>', '<?php echo $exp_json; ?>')" title="View attached receipts" style="font-size: 11px; padding: 0 6px;">
												👁️ (<?php echo count( $cli['expenses'] ); ?>)
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 2: SBI BANK RECONCILIATION -->
	<div id="tab-sbi" class="tc-tab-content" style="display: none;">
		<div class="tc-card-lux">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>🏦</span>
					State Bank of India (SBI) Statement Feed &amp; Debits / Expense Calculator
				</div>
				<button type="button" class="button button-primary" onclick="document.getElementById('sbi-import-modal').style.display='block';">
					+ Import SBI Statement (CSV/XLS)
				</button>
			</div>
			<p class="tc-card-desc">
				Complete account ledger for <strong>GALLEXIA VOYAGE OPC PRIVATE LIMITED</strong> (Current Account <code>00000044078696102</code>). Both debited expenses and credited inflows are tracked with exact balances and category classification.
			</p>

			<!-- Quick upload box hidden by default -->
			<div id="sbi-import-modal" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
				<h4 style="margin: 0 0 10px 0;">Import SBI NetBanking Statement (CSV / TSV)</h4>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
					<input type="hidden" name="tc_action" value="upload_sbi_csv" />
					<div style="display: flex; gap: 16px; margin-bottom: 12px; flex-wrap: wrap;">
						<div>
							<label style="font-weight: 600; font-size: 12px;">Account Number</label><br />
							<input type="text" name="account_no" value="00000044078696102" class="regular-text" style="margin-top: 4px;" />
						</div>
						<div>
							<label style="font-weight: 600; font-size: 12px;">Select CSV / XLS File</label><br />
							<input type="file" name="sbi_csv_file" accept=".csv,.txt,.xls,.tsv" style="margin-top: 4px;" />
						</div>
					</div>
					<div>
						<label style="font-weight: 600; font-size: 12px;">Or Paste Statement Lines Directly:</label><br />
						<textarea name="sbi_csv_raw" rows="4" class="large-text" placeholder="Txn Date, Value Date, Description, Ref No, Debit, Credit, Balance..." style="margin-top: 4px; font-family: monospace; font-size: 11px;"></textarea>
					</div>
					<div style="margin-top: 12px;">
						<button type="submit" class="button button-primary">Parse &amp; Upload Statement</button>
						<button type="button" class="button" onclick="document.getElementById('sbi-import-modal').style.display='none';">Cancel</button>
					</div>
				</form>
			</div>

			<!-- DEBITS & EXPENSES CATEGORY BREAKDOWN -->
			<div style="background: #fdf2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 18px; margin: 18px 0;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
					<div>
						<strong style="color: #991b1b; font-size: 14px;">📉 Debited Expenses Breakdown (Total: ₹<?php echo number_format( $total_debits, 2 ); ?>)</strong>
						<span style="font-size: 11.5px; color: #b91c1c; margin-left: 8px;">(<?php echo (int) $debit_count; ?> total outflow entries)</span>
					</div>
					<div style="font-size: 12px; font-weight: 700; color: #991b1b;">
						Operating Expense Ratio: <?php echo $total_credits > 0 ? number_format( ( $total_debits / $total_credits ) * 100, 1 ) : 0; ?>% of Inflows
					</div>
				</div>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
					<?php foreach ( $expense_cats as $ec ) : ?>
						<?php 
						$cat_pct = $total_debits > 0 ? ( ( (float) $ec['total'] / $total_debits ) * 100 ) : 0; 
						?>
						<div style="background: #ffffff; border: 1px solid #fee2e2; border-radius: 8px; padding: 10px 14px;">
							<div style="display: flex; justify-content: space-between; align-items: center;">
								<span style="font-weight: 700; font-size: 12px; color: #1e293b;"><?php echo esc_html( $ec['category'] ); ?></span>
								<span style="font-size: 10.5px; color: #64748b; font-weight: 600;"><?php echo (int) $ec['cnt']; ?> txns</span>
							</div>
							<div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 4px;">
								<span style="font-size: 15px; font-weight: 800; color: #ef4444;">₹<?php echo number_format( (float) $ec['total'], 2 ); ?></span>
								<span style="font-size: 11px; font-weight: 600; color: #94a3b8;"><?php echo number_format( $cat_pct, 1 ); ?>%</span>
							</div>
							<div style="background: #f1f5f9; height: 4px; border-radius: 2px; margin-top: 6px; overflow: hidden;">
								<div style="background: #ef4444; height: 100%; width: <?php echo esc_attr( min( 100, $cat_pct ) ); ?>%;"></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- INTERACTIVE FILTER BAR -->
			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px; display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap;">
				<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<span style="font-weight: 700; font-size: 12px; color: #475569; margin-right: 4px;">View:</span>
					<a href="<?php echo esc_url( add_query_arg( array( 'filter_type' => 'all', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'all' === $current_type ? 'button-primary' : ''; ?>" style="font-weight: 600; font-size: 12px;">
						All (<?php echo (int) $total_count; ?>)
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'filter_type' => 'debit', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'debit' === $current_type ? 'button-primary' : ''; ?>" style="font-weight: 600; font-size: 12px; color: <?php echo 'debit' === $current_type ? '#fff' : '#ef4444'; ?>;">
						📉 Debits / Expenses (<?php echo (int) $debit_count; ?> • ₹<?php echo number_format( $total_debits, 0 ); ?>)
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'filter_type' => 'credit', 'paged' => 1 ) ) ); ?>" class="button <?php echo 'credit' === $current_type ? 'button-primary' : ''; ?>" style="font-weight: 600; font-size: 12px; color: <?php echo 'credit' === $current_type ? '#fff' : '#10b981'; ?>;">
						📈 Credits / Inflows (<?php echo (int) $credit_count; ?> • ₹<?php echo number_format( $total_credits, 0 ); ?>)
					</a>
				</div>

				<form method="get" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 0;">
					<input type="hidden" name="page" value="tc-agents-finance" />
					<input type="hidden" name="filter_type" value="<?php echo esc_attr( $current_type ); ?>" />

					<select name="filter_cat" style="font-size: 12px; height: 32px;" onchange="this.form.submit()">
						<option value="all">All Categories</option>
						<?php foreach ( $expense_cats as $ec ) : ?>
							<option value="<?php echo esc_attr( $ec['category'] ); ?>" <?php selected( $current_cat, $ec['category'] ); ?>>
								<?php echo esc_html( $ec['category'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<input type="text" name="s_q" value="<?php echo esc_attr( $search_q ); ?>" placeholder="Search description/ref..." style="font-size: 12px; height: 32px; width: 170px;" />

					<select name="limit_opt" style="font-size: 12px; height: 32px;" onchange="this.form.submit()">
						<option value="50" <?php selected( $limit_opt, '50' ); ?>>Show 50</option>
						<option value="100" <?php selected( $limit_opt, '100' ); ?>>Show 100</option>
						<option value="200" <?php selected( $limit_opt, '200' ); ?>>Show 200</option>
						<option value="all" <?php selected( $limit_opt, 'all' ); ?>>Show All (<?php echo (int) $total_count; ?>)</option>
					</select>

					<button type="submit" class="button" style="height: 32px; font-size: 12px;">Filter</button>
					<?php if ( $current_type !== 'all' || $current_cat !== 'all' || ! empty( $search_q ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-finance' ) ); ?>" class="button" style="height: 32px; font-size: 12px; color: #64748b;">Reset</a>
					<?php endif; ?>
				</form>
			</div>

			<!-- Transactions Table with Explicit Debited & Credited Columns -->
			<table class="widefat striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 105px;">Date</th>
						<th>Description / Narration</th>
						<th style="width: 140px;">Ref No / UTR</th>
						<th style="width: 150px;">Category</th>
						<th style="text-align: right; width: 120px; color: #ef4444; background: #fff5f5;">Debited (Expense)</th>
						<th style="text-align: right; width: 120px; color: #10b981; background: #f0fdf4;">Credited (Inflow)</th>
						<th style="text-align: right; width: 120px;">Balance (₹)</th>
						<th style="text-align: center; width: 110px;">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $txns ) ) : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 32px; color: #64748b;">
								No matching transactions found for the selected filter.
							</td>
						</tr>
					<?php else : ?>
						<?php 
						$page_debits  = 0.0;
						$page_credits = 0.0;
						foreach ( $txns as $t ) : 
							$is_cr  = ( $t['type'] === 'credit' );
							$amt    = (float) $t['amount'];
							if ( $is_cr ) {
								$page_credits += $amt;
							} else {
								$page_debits += $amt;
							}
						?>
							<tr>
								<td><strong><?php echo esc_html( $t['txn_date'] ); ?></strong></td>
								<td style="max-width: 320px; font-size: 12.5px; line-height: 1.4;">
									<?php echo esc_html( $t['description'] ); ?>
								</td>
								<td>
									<code style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html( $t['ref_no'] ?: '—' ); ?></code>
								</td>
								<td>
									<span class="tc-badge-gold" style="font-size: 10.5px; padding: 2px 8px; white-space: nowrap;">
										<?php echo esc_html( $t['category'] ?: 'Uncategorized' ); ?>
									</span>
								</td>
								<!-- DEBITED COLUMN (EXPENSE) -->
								<td style="text-align: right; font-weight: 800; color: #ef4444; background: #fffdfd; font-family: monospace; font-size: 13px;">
									<?php if ( ! $is_cr ) : ?>
										-₹<?php echo number_format( $amt, 2 ); ?>
									<?php else : ?>
										<span style="color: #cbd5e1; font-weight: normal;">—</span>
									<?php endif; ?>
								</td>
								<!-- CREDITED COLUMN (INFLOW) -->
								<td style="text-align: right; font-weight: 800; color: #10b981; background: #fdfffd; font-family: monospace; font-size: 13px;">
									<?php if ( $is_cr ) : ?>
										+₹<?php echo number_format( $amt, 2 ); ?>
									<?php else : ?>
										<span style="color: #cbd5e1; font-weight: normal;">—</span>
									<?php endif; ?>
								</td>
								<!-- RUNNING BALANCE COLUMN -->
								<td style="text-align: right; font-weight: 700; color: #0f172a; font-family: monospace; font-size: 12.5px;">
									<?php echo isset( $t['balance'] ) && $t['balance'] !== null ? '₹' . number_format( (float) $t['balance'], 2 ) : '—'; ?>
								</td>
								<td style="text-align: center;">
									<?php if ( ! empty( $t['reconciled'] ) ) : ?>
										<span style="color: #10b981; font-weight: 700; font-size: 11.5px;">✓ Linked</span>
									<?php else : ?>
										<?php if ( ! $is_cr ) : ?>
											<button type="button" class="button button-small" onclick="openLinkDebitModal(<?php echo (int) $t['id']; ?>, <?php echo esc_js( json_encode( $t['description'] ) ); ?>, <?php echo (float) $t['amount']; ?>)" style="font-size: 11px; padding: 0 8px; height: 26px; color: #b91c1c; border-color: #fca5a5; font-weight: 600;" title="Assign this debit as customer expense">
												🏷️ Assign
											</button>
										<?php else : ?>
											<span style="color: #f59e0b; font-weight: 600; font-size: 11px; background: #fef3c7; padding: 2px 6px; border-radius: 4px;">Pending</span>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				<tfoot style="background: #f8fafc; font-weight: 800; font-size: 13px; border-top: 2px solid #cbd5e1;">
					<tr>
						<th colspan="4" style="text-align: right; padding: 12px 10px;">
							Showing <?php echo count( $txns ); ?> rows &bull; Page Total / Overall Total:
						</th>
						<th style="text-align: right; color: #ef4444; background: #fee2e2; font-family: monospace; font-size: 13.5px;">
							-₹<?php echo number_format( $total_debits, 2 ); ?>
						</th>
						<th style="text-align: right; color: #10b981; background: #dcfce7; font-family: monospace; font-size: 13.5px;">
							+₹<?php echo number_format( $total_credits, 2 ); ?>
						</th>
						<th style="text-align: right; color: #0f172a; font-family: monospace; font-size: 13.5px;">
							₹<?php echo number_format( $net_cashflow, 2 ); ?>
						</th>
						<th style="text-align: center; font-size: 11px; color: #64748b;">
							Balance Net
						</th>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>

	<!-- TAB 2: ZOHO BOOKS LEDGER -->
	<div id="tab-zoho" class="tc-tab-content" style="display: none;">
		<div class="tc-card-lux">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>📚</span>
					Zoho Books API v3 Invoicing &amp; Receivables
				</div>
				<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<?php if ( $zoho_active ) : ?>
						<span style="color: #10b981; font-weight: 700; margin-right: 6px;">● Connected &amp; Token Active</span>
						<form method="post" style="display: inline; margin: 0;">
							<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
							<input type="hidden" name="tc_action" value="sync_zoho_live" />
							<button type="submit" class="button" style="font-size: 12px; height: 32px;">🔄 Sync Invoices Now</button>
						</form>
					<?php else : ?>
						<a href="https://accounts.zoho.in/oauth/v2/auth?scope=ZohoBooks.fullaccess.all&client_id=1000.FE6DWV166VF44XWJMWOT6S4L0BMPHV&response_type=code&redirect_uri=https://tripcosmos.co&access_type=offline&prompt=consent" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #16a34a; border-color: #15803d; font-weight: 700;">
							⚡ 1-Click Connect Zoho Books (Authorize)
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>" class="button">Credentials</a>
					<?php endif; ?>
				</div>
			</div>
			<p class="tc-card-desc">
				Official GST invoices generated in Zoho Books are mirrored in real-time. Unpaid traveler balances and overdue alerts sync directly with the sales agents' phones.
			</p>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 16px 0;">
				<div style="background: #f8fafc; border-radius: 12px; padding: 14px; border: 1px solid #e2e8f0;">
					<div style="font-size: 11px; color: #64748b; font-weight: 600;">Total Invoiced</div>
					<div style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">₹<?php echo number_format( (float) $zoho_metrics['total_invoiced'], 2 ); ?></div>
				</div>
				<div style="background: #f8fafc; border-radius: 12px; padding: 14px; border: 1px solid #e2e8f0;">
					<div style="font-size: 11px; color: #64748b; font-weight: 600;">Collected (Paid)</div>
					<div style="font-size: 20px; font-weight: 800; color: #10b981; margin-top: 4px;">₹<?php echo number_format( (float) $zoho_metrics['total_paid'], 2 ); ?></div>
				</div>
				<div style="background: #f8fafc; border-radius: 12px; padding: 14px; border: 1px solid #e2e8f0;">
					<div style="font-size: 11px; color: #64748b; font-weight: 600;">Pending Receivables</div>
					<div style="font-size: 20px; font-weight: 800; color: #f59e0b; margin-top: 4px;">₹<?php echo number_format( (float) $zoho_metrics['total_balance'], 2 ); ?></div>
				</div>
				<div style="background: #f8fafc; border-radius: 12px; padding: 14px; border: 1px solid #e2e8f0;">
					<div style="font-size: 11px; color: #64748b; font-weight: 600;">Overdue Invoices</div>
					<div style="font-size: 20px; font-weight: 800; color: #ef4444; margin-top: 4px;"><?php echo (int) $zoho_metrics['overdue_count']; ?> Invoices</div>
				</div>
			</div>

			<table class="widefat striped" style="margin-top: 14px;">
				<thead>
					<tr>
						<th>Invoice #</th>
						<th>Customer / Traveler</th>
						<th>Date</th>
						<th>Due Date</th>
						<th style="text-align: right;">Total (₹)</th>
						<th style="text-align: right;">Balance (₹)</th>
						<th style="text-align: center;">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $zoho_invoices ) ) : ?>
						<tr>
							<td colspan="7" style="text-align: center; padding: 24px; color: #64748b;">
								No Zoho invoices synced yet. Configure credentials in <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>">Integrations</a></strong> or tap Connect in the X-Agents app.
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $zoho_invoices as $inv ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $inv['invoice_number'] ); ?></strong></td>
								<td><?php echo esc_html( $inv['customer_name'] ); ?></td>
								<td><?php echo esc_html( $inv['date'] ); ?></td>
								<td><?php echo esc_html( $inv['due_date'] ); ?></td>
								<td style="text-align: right; font-weight: 700;">₹<?php echo number_format( (float) $inv['total'], 2 ); ?></td>
								<td style="text-align: right; font-weight: 700; color: <?php echo (float) $inv['balance'] > 0 ? '#ef4444' : '#10b981'; ?>;">
									₹<?php echo number_format( (float) $inv['balance'], 2 ); ?>
								</td>
								<td style="text-align: center;">
									<span class="tc-badge-gold" style="font-size: 10px;"><?php echo esc_html( strtoupper( $inv['status'] ) ); ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 3: MARKETING ROI & GOOGLE ADS UNIT ECONOMICS -->
	<div id="tab-roi" class="tc-tab-content" style="display: none;">
		<!-- Google Ads API v17 Live Status Card -->
		<div class="tc-card-lux" style="border-left: 5px solid #4285f4; margin-bottom: 20px;">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>🎯</span>
					Google Ads API v17 &amp; Target ROAS Engine
				</div>
				<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
					<?php if ( $gads_configured ) : ?>
						<span style="color: #10b981; font-weight: 700; margin-right: 6px;">● Google Ads API Active (ID: <?php echo esc_html( $gads_summary['customer_id'] ?? '' ); ?>)</span>
						<form method="post" style="display: inline; margin: 0;">
							<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
							<input type="hidden" name="tc_action" value="sync_gads_live" />
							<button type="submit" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 32px; font-size: 12px;">
								🔄 Sync Live Campaigns from Google Ads
							</button>
						</form>
					<?php else : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-integrations' ) ); ?>" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700;">
							Configure Google Ads Credentials
						</a>
						<?php if ( ! empty( $gads_auth_url ) ) : ?>
							<a href="<?php echo esc_url( $gads_auth_url ); ?>" class="button">⚡ 1-Click Authorize</a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
			<p class="tc-card-desc">
				Syncs live campaign expenditure directly from the Google Ads API via GAQL, calculates real-time ROAS against traveler inflows, and uploads verified offline conversion values for Smart Bidding optimization.
			</p>

			<!-- Live Campaign Table (if available) -->
			<?php if ( ! empty( $gads_campaigns ) ) : ?>
				<h4 style="margin: 16px 0 8px;">Live Google Ads Campaign Performance (Last 30 Days)</h4>
				<table class="widefat striped" style="margin-bottom: 16px;">
					<thead>
						<tr>
							<th>Campaign Name</th>
							<th>Status</th>
							<th style="text-align: right;">Spend (₹)</th>
							<th style="text-align: right;">Impressions</th>
							<th style="text-align: right;">Clicks</th>
							<th style="text-align: right;">CTR</th>
							<th style="text-align: right;">Avg CPC</th>
							<th style="text-align: right;">Conversions</th>
							<th style="text-align: right;">ROAS</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $gads_campaigns as $camp ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $camp['name'] ); ?></strong></td>
								<td><span class="tc-badge-gold" style="font-size: 10px;"><?php echo esc_html( $camp['status'] ); ?></span></td>
								<td style="text-align: right; font-weight: 700; color: #ef4444;">₹<?php echo number_format( (float) $camp['cost_inr'], 2 ); ?></td>
								<td style="text-align: right;"><?php echo number_format( (int) $camp['impressions'] ); ?></td>
								<td style="text-align: right;"><?php echo number_format( (int) $camp['clicks'] ); ?></td>
								<td style="text-align: right;"><?php echo number_format( (float) $camp['ctr'], 2 ); ?>%</td>
								<td style="text-align: right;">₹<?php echo number_format( (float) $camp['avg_cpc'], 2 ); ?></td>
								<td style="text-align: right; font-weight: 700; color: #10b981;"><?php echo number_format( (float) $camp['conversions'], 1 ); ?></td>
								<td style="text-align: right; font-weight: 800; color: #f59e0b;"><?php echo number_format( (float) $camp['roas'], 2 ); ?>x</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- ROAS & Unit Economics Engine -->
		<div class="tc-card-lux">
			<div class="tc-card-header-flex">
				<div class="tc-card-title">
					<span>📈</span>
					Marketing ROI &amp; Unit Economics Engine
				</div>
			</div>
			<p class="tc-card-desc">
				Calculates genuine marketing return by combining Google Ads expenditure with closed booking revenue and vendor fulfillment costs.
			</p>

			<div style="background: #f8fafc; border-radius: 12px; padding: 18px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
				<form method="post" style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
					<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
					<input type="hidden" name="tc_action" value="update_ad_spend" />
					<div>
						<label style="font-weight: 700; font-size: 13px; color: #0f172a;">
							Monthly Google Ads Spend (₹):
							<?php if ( $gads_spend_api > 0 ) : ?>
								<span style="color: #2563eb; font-size: 11px; font-weight: normal;">(Auto-synced from Google Ads)</span>
							<?php endif; ?>
						</label><br />
						<input type="number" step="500" name="monthly_ad_spend" value="<?php echo esc_attr( $monthly_ad_spend ); ?>" class="regular-text" style="margin-top: 6px; font-size: 15px; font-weight: 700;" />
					</div>
					<button type="submit" class="button button-primary" style="height: 38px;">Save Budget &amp; Recalculate ROI</button>
					<button type="button" class="button" onclick="document.getElementById('gads-csv-modal').style.display = (document.getElementById('gads-csv-modal').style.display === 'none' ? 'block' : 'none');" style="height: 38px;">
						📂 Import Google Ads CSV Report
					</button>
				</form>
			</div>

			<!-- Google Ads CSV Report Upload Modal -->
			<div id="gads-csv-modal" style="display: none; background: #f0f7ff; border: 1.5px solid #bfdbfe; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
				<h4 style="margin: 0 0 8px 0; color: #1e40af;">📂 Import Google Ads Campaign Report (CSV)</h4>
				<p style="font-size: 12.5px; color: #2563eb; margin: 0 0 14px 0; line-height: 1.4;">
					Download your campaign report directly from <a href="https://ads.google.com/aw/campaigns" target="_blank" rel="noopener noreferrer" style="font-weight: 700;">Google Ads &rarr; Campaigns &rarr; Download &rarr; CSV ↗</a>. All campaign names, exact rupee spend, impressions, clicks, and conversions will be parsed automatically. <strong>No Developer Token required!</strong>
				</p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
					<input type="hidden" name="tc_action" value="upload_gads_csv" />
					<div style="margin-bottom: 12px;">
						<label style="font-weight: 600; font-size: 12px;">Select Downloaded Google Ads CSV:</label><br />
						<input type="file" name="gads_csv_file" accept=".csv,.txt" style="margin-top: 4px;" />
					</div>
					<div>
						<label style="font-weight: 600; font-size: 12px;">Or Paste CSV Lines Directly:</label><br />
						<textarea name="gads_csv_raw" rows="4" class="large-text" placeholder="Campaign, Cost, Impressions, Clicks, Conversions..." style="margin-top: 4px; font-family: monospace; font-size: 11px;"></textarea>
					</div>
					<div style="margin-top: 12px;">
						<button type="submit" class="button button-primary">Parse &amp; Calculate Real ROAS</button>
						<button type="button" class="button" onclick="document.getElementById('gads-csv-modal').style.display='none';">Cancel</button>
					</div>
				</form>
			</div>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px;">
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
					<div style="font-size: 12px; color: #64748b; font-weight: 600;">Return On Ad Spend (ROAS)</div>
					<div style="font-size: 26px; font-weight: 800; color: #f59e0b; margin: 6px 0;"><?php echo number_format( $roas, 2 ); ?>x</div>
					<div style="font-size: 11px; color: #64748b;">₹<?php echo number_format( $gross_revenue, 0 ); ?> revenue / ₹<?php echo number_format( $monthly_ad_spend, 0 ); ?> spend</div>
				</div>
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
					<div style="font-size: 12px; color: #64748b; font-weight: 600;">Customer Acquisition Cost (CAC)</div>
					<div style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 6px 0;">₹<?php echo number_format( $cac, 2 ); ?></div>
					<div style="font-size: 11px; color: #64748b;">Per converted traveler</div>
				</div>
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
					<div style="font-size: 12px; color: #64748b; font-weight: 600;">Net Operating Profit Margin</div>
					<div style="font-size: 26px; font-weight: 800; color: <?php echo $net_profit >= 0 ? '#10b981' : '#ef4444'; ?>; margin: 6px 0;">
						<?php echo number_format( $profit_margin_pct, 1 ); ?>%
					</div>
					<div style="font-size: 11px; color: #64748b;">After deducting ads + hotels + transport</div>
				</div>
			</div>

			<h4 style="margin: 20px 0 10px;">Unit Economics Breakdown Formula</h4>
			<table class="widefat striped" style="max-width: 650px;">
				<tbody>
					<tr>
						<td><strong>Gross Deal Revenue</strong></td>
						<td style="text-align: right; font-weight: 700; color: #10b981;">+ ₹<?php echo number_format( $gross_revenue, 2 ); ?></td>
					</tr>
					<tr>
						<td><strong>Google Ads Marketing Spend</strong></td>
						<td style="text-align: right; font-weight: 700; color: #ef4444;">- ₹<?php echo number_format( $monthly_ad_spend, 2 ); ?></td>
					</tr>
					<tr>
						<td><strong>Vendor Fulfillment Costs (Hotels &amp; Cabs)</strong></td>
						<td style="text-align: right; font-weight: 700; color: #ef4444;">- ₹<?php echo number_format( $vendor_costs, 2 ); ?></td>
					</tr>
					<tr style="background: #f1f5f9;">
						<td><strong>Net Realized Profit</strong></td>
						<td style="text-align: right; font-weight: 800; font-size: 15px; color: <?php echo $net_profit >= 0 ? '#10b981' : '#ef4444'; ?>;">
							₹<?php echo number_format( $net_profit, 2 ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Offline Conversion Tracking (OCT) Card -->
		<div class="tc-card-lux" style="margin-top: 20px;">
			<div class="tc-card-title">
				<span>⚡</span>
				Offline Conversion Tracking (OCT) Test &amp; Upload
			</div>
			<p class="tc-card-desc">
				Upload closed booking values with a visitor's Google Click ID (GCLID) to train Google Ads Target ROAS Smart Bidding.
			</p>

			<form method="post" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
				<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
				<input type="hidden" name="tc_action" value="upload_oct_conversion" />
				<div style="display: flex; gap: 16px; flex-wrap: wrap;">
					<div>
						<label style="font-weight: 600; font-size: 12px;">Visitor GCLID</label><br />
						<input type="text" name="oct_gclid" placeholder="EAIaIQobChMI..." class="regular-text" style="margin-top: 4px;" required />
					</div>
					<div>
						<label style="font-weight: 600; font-size: 12px;">Payment Value (INR ₹)</label><br />
						<input type="number" step="100" name="oct_value" value="25000" class="regular-text" style="margin-top: 4px; width: 140px;" required />
					</div>
					<div>
						<label style="font-weight: 600; font-size: 12px;">Order / Booking Reference</label><br />
						<input type="text" name="oct_order_id" value="TC-OCT-<?php echo time(); ?>" class="regular-text" style="margin-top: 4px; width: 180px;" />
					</div>
					<div style="display: flex; align-items: flex-end;">
						<button type="submit" class="button button-primary" style="height: 38px;">Upload Conversion to Google Ads</button>
					</div>
				</div>
			</form>

			<?php
			$recent_oct = $wpdb->get_results( "
				SELECT * FROM {$wpdb->prefix}tc_agent_conversion_log 
				ORDER BY id DESC LIMIT 10
			", ARRAY_A );
			?>
			<h4 style="margin: 16px 0 8px;">Recent Conversion Logs</h4>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Event</th>
						<th>Channel</th>
						<th>Source</th>
						<th>GCLID</th>
						<th>Value (₹)</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent_oct ) ) : ?>
						<tr><td colspan="6" style="text-align: center; color: #64748b; padding: 18px;">No conversion events recorded yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $recent_oct as $oc ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $oc['event_type'] ); ?></strong></td>
								<td><?php echo esc_html( $oc['channel'] ); ?></td>
								<td><?php echo esc_html( $oc['source'] ); ?></td>
								<td><code style="font-size: 11px;"><?php echo esc_html( $oc['gclid'] ? substr( $oc['gclid'], 0, 18 ) . '...' : '—' ); ?></code></td>
								<td style="font-weight: 700; color: #10b981;">₹<?php echo number_format( (float) $oc['value'], 2 ); ?></td>
								<td><?php echo esc_html( $oc['created_at'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- ===================================================================== -->
<!-- MODAL 1: ADD CUSTOMER DIRECT EXPENSE -->
<!-- ===================================================================== -->
<div id="tc-add-expense-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 100000; overflow-y: auto; backdrop-filter: blur(4px);">
	<div style="max-width: 520px; margin: 60px auto; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden;">
		<div style="background: #0f172a; color: #ffffff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
			<h3 style="margin: 0; color: #f8fafc; font-size: 16px; font-weight: 700;">➕ Record Customer Tour Expense</h3>
			<button type="button" onclick="closeTcModal('tc-add-expense-modal')" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
		</div>
		<form method="post" style="padding: 24px;">
			<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
			<input type="hidden" name="tc_action" value="add_customer_expense" />
			<input type="hidden" id="modal-exp-customer-name" name="exp_customer_name" value="" />

			<div style="margin-bottom: 14px;">
				<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Select Customer / Zoho Invoice *</label>
				<select id="modal-exp-invoice-no" name="exp_invoice_no" required onchange="updateModalCustomerName(this)" style="width: 100%; height: 36px; font-size: 13px;">
					<option value="">-- Choose Customer Invoice --</option>
					<?php foreach ( $zoho_invoices as $zi ) : ?>
						<option value="<?php echo esc_attr( $zi['invoice_number'] ); ?>" data-name="<?php echo esc_attr( $zi['customer_name'] ); ?>">
							<?php echo esc_html( $zi['invoice_number'] . ' — ' . $zi['customer_name'] . ' (₹' . number_format( (float) $zi['total'], 0 ) . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
				<div>
					<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Expense Category *</label>
					<select name="exp_category" required style="width: 100%; height: 36px; font-size: 13px;">
						<option value="hotel">🏨 Hotel &amp; Stay</option>
						<option value="cab" selected>🚕 Cab &amp; Transport</option>
						<option value="aarti_boat">🪔 Ganga Aarti &amp; Boat</option>
						<option value="guide_temple">🛕 Guide &amp; Temple VIP</option>
						<option value="meals">🍲 Meals &amp; Refreshment</option>
						<option value="other">📦 Other Tour Cost</option>
					</select>
				</div>
				<div>
					<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Amount (₹) *</label>
					<input type="number" step="0.01" name="exp_amount" required placeholder="5000" style="width: 100%; height: 36px; font-size: 13px;" />
				</div>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
				<div>
					<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Vendor / Payee</label>
					<input type="text" name="exp_vendor_name" placeholder="e.g. Hotel Surya / Driver Ramesh" style="width: 100%; height: 36px; font-size: 13px;" />
				</div>
				<div>
					<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Payment Method</label>
					<select name="exp_payment_method" style="width: 100%; height: 36px; font-size: 13px;">
						<option value="upi" selected>UPI Transfer (GPay / PhonePe)</option>
						<option value="bank_debit">SBI NetBanking / IMPS</option>
						<option value="cash">Cash Payment</option>
					</select>
				</div>
			</div>

			<div style="margin-bottom: 14px;">
				<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Expense Date</label>
				<input type="date" name="exp_date" value="<?php echo current_time( 'Y-m-d' ); ?>" style="width: 100%; height: 36px; font-size: 13px;" />
			</div>

			<div style="margin-bottom: 18px;">
				<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Notes / Remarks</label>
				<textarea name="exp_notes" rows="2" placeholder="e.g. Innova airport pickup + 2 days Sarnath tour" style="width: 100%; font-size: 12.5px;"></textarea>
			</div>

			<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
				<button type="button" class="button" onclick="closeTcModal('tc-add-expense-modal')" style="height: 34px;">Cancel</button>
				<button type="submit" class="button button-primary" style="background: #10b981; border-color: #059669; font-weight: 700; height: 34px;">Save &amp; Recalculate Margin</button>
			</div>
		</form>
	</div>
</div>

<!-- ===================================================================== -->
<!-- MODAL 2: LINK BANK DEBIT TRANSACTION TO CUSTOMER -->
<!-- ===================================================================== -->
<div id="tc-link-debit-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 100000; overflow-y: auto; backdrop-filter: blur(4px);">
	<div style="max-width: 520px; margin: 60px auto; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden;">
		<div style="background: #991b1b; color: #ffffff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
			<h3 style="margin: 0; color: #ffffff; font-size: 16px; font-weight: 700;">🏷️ Assign Bank Debit to Customer</h3>
			<button type="button" onclick="closeTcModal('tc-link-debit-modal')" style="background: none; border: none; color: #fecaca; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
		</div>
		<form method="post" style="padding: 24px;">
			<?php wp_nonce_field( 'tc_finance_action', '_tc_nonce' ); ?>
			<input type="hidden" name="tc_action" value="link_bank_debit" />
			<input type="hidden" id="link-txn-id" name="link_txn_id" value="" />
			<input type="hidden" id="link-customer-name" name="link_customer_name" value="" />

			<!-- Transaction preview card -->
			<div style="background: #fdf2f2; border: 1px solid #fee2e2; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;">
				<div style="font-size: 11px; color: #991b1b; font-weight: 700; text-transform: uppercase;">SBI Debited Transaction</div>
				<div id="link-txn-desc" style="font-size: 13px; font-weight: 600; color: #1e293b; margin: 4px 0;">—</div>
				<div id="link-txn-amt" style="font-size: 18px; font-weight: 800; color: #ef4444; font-family: monospace;">—</div>
			</div>

			<div style="margin-bottom: 14px;">
				<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Assign to Customer / Zoho Invoice *</label>
				<select id="link-invoice-no" name="link_invoice_no" required onchange="updateLinkCustomerName(this)" style="width: 100%; height: 36px; font-size: 13px;">
					<option value="">-- Choose Customer Invoice --</option>
					<?php foreach ( $zoho_invoices as $zi ) : ?>
						<option value="<?php echo esc_attr( $zi['invoice_number'] ); ?>" data-name="<?php echo esc_attr( $zi['customer_name'] ); ?>">
							<?php echo esc_html( $zi['invoice_number'] . ' — ' . $zi['customer_name'] . ' (₹' . number_format( (float) $zi['total'], 0 ) . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
				<div>
					<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Cost Category *</label>
					<select name="link_category" required style="width: 100%; height: 36px; font-size: 13px;">
						<option value="cab" selected>🚕 Cab &amp; Transport</option>
						<option value="hotel">🏨 Hotel &amp; Stay</option>
						<option value="aarti_boat">🪔 Ganga Aarti &amp; Boat</option>
						<option value="guide_temple">🛕 Guide &amp; Temple VIP</option>
						<option value="meals">🍲 Meals &amp; Food</option>
						<option value="other">📦 Other Vendor Cost</option>
					</select>
				</div>
				<div>
					<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Payee / Vendor Name</label>
					<input type="text" id="link-vendor-name" name="link_vendor_name" placeholder="Driver / Hotel name" style="width: 100%; height: 36px; font-size: 13px;" />
				</div>
			</div>

			<div style="margin-bottom: 18px;">
				<label style="font-weight: 700; font-size: 12px; display: block; margin-bottom: 4px;">Remarks (Optional)</label>
				<textarea name="link_notes" rows="2" placeholder="e.g. Airport transfer for arrival day" style="width: 100%; font-size: 12.5px;"></textarea>
			</div>

			<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
				<button type="button" class="button" onclick="closeTcModal('tc-link-debit-modal')" style="height: 34px;">Cancel</button>
				<button type="submit" class="button button-primary" style="background: #991b1b; border-color: #7f1d1d; font-weight: 700; height: 34px;">Link Debit &amp; Deduct from Margin</button>
			</div>
		</form>
	</div>
</div>

<!-- ===================================================================== -->
<!-- MODAL 3: VIEW ATTACHED EXPENSES DETAIL MODAL -->
<!-- ===================================================================== -->
<div id="tc-view-expense-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); z-index: 100000; overflow-y: auto; backdrop-filter: blur(4px);">
	<div style="max-width: 650px; margin: 60px auto; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden;">
		<div style="background: #0f172a; color: #ffffff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
			<div>
				<h3 id="view-exp-title" style="margin: 0; color: #f8fafc; font-size: 16px; font-weight: 700;">Customer Expense Details</h3>
				<div id="view-exp-subtitle" style="font-size: 12px; color: #94a3b8; margin-top: 2px;">—</div>
			</div>
			<button type="button" onclick="closeTcModal('tc-view-expense-modal')" style="background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; line-height: 1;">&times;</button>
		</div>
		<div style="padding: 20px;">
			<div id="view-exp-list" style="margin-bottom: 16px;"></div>
			<div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 14px;">
				<button type="button" id="view-exp-add-btn" class="button button-primary" style="font-weight: 700;">+ Add Another Expense</button>
				<button type="button" class="button" onclick="closeTcModal('tc-view-expense-modal')">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
function switchFinanceTab(evt, tabId) {
	if (evt) { evt.preventDefault(); }
	var contents = document.querySelectorAll('.tc-tab-content');
	for (var i = 0; i < contents.length; i++) {
		contents[i].style.display = 'none';
	}
	var tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
	for (var j = 0; j < tabs.length; j++) {
		tabs[j].classList.remove('nav-tab-active');
	}
	var targetEl = document.getElementById(tabId);
	if (targetEl) {
		targetEl.style.display = 'block';
	}
	if (evt && evt.currentTarget) {
		evt.currentTarget.classList.add('nav-tab-active');
	} else {
		var activeLink = document.querySelector('.nav-tab-wrapper a[href="#' + tabId + '"]');
		if (activeLink) activeLink.classList.add('nav-tab-active');
	}
	if (history.replaceState) {
		history.replaceState(null, null, '#' + tabId);
	}
}

// Restore tab from URL hash if present
window.addEventListener('DOMContentLoaded', function() {
	if (window.location.hash) {
		var hash = window.location.hash.substring(1);
		if (document.getElementById(hash)) {
			switchFinanceTab(null, hash);
		}
	}
});

function openAddExpenseModal(invoiceNo, customerName) {
	var modal = document.getElementById('tc-add-expense-modal');
	var invSelect = document.getElementById('modal-exp-invoice-no');
	var nameInput = document.getElementById('modal-exp-customer-name');

	if (invoiceNo) {
		invSelect.value = invoiceNo;
	}
	nameInput.value = customerName || '';
	modal.style.display = 'block';
}

function updateModalCustomerName(selectEl) {
	var opt = selectEl.options[selectEl.selectedIndex];
	var name = opt ? opt.getAttribute('data-name') : '';
	document.getElementById('modal-exp-customer-name').value = name || '';
}

function openLinkDebitModal(txnId, desc, amt) {
	var modal = document.getElementById('tc-link-debit-modal');
	document.getElementById('link-txn-id').value = txnId;
	document.getElementById('link-txn-desc').textContent = desc || 'Bank Debit';
	document.getElementById('link-txn-amt').textContent = '-₹' + Number(amt).toLocaleString('en-IN', {minimumFractionDigits: 2});
	document.getElementById('link-vendor-name').value = (desc && desc.length < 50) ? desc : '';
	modal.style.display = 'block';
}

function updateLinkCustomerName(selectEl) {
	var opt = selectEl.options[selectEl.selectedIndex];
	var name = opt ? opt.getAttribute('data-name') : '';
	document.getElementById('link-customer-name').value = name || '';
}

function openViewExpenseModal(invoiceNo, customerName, expensesJsonStr) {
	var modal = document.getElementById('tc-view-expense-modal');
	document.getElementById('view-exp-title').textContent = customerName + ' — ' + invoiceNo;
	
	var expenses = [];
	try {
		expenses = JSON.parse(expensesJsonStr || '[]');
	} catch(e) { expenses = []; }

	var container = document.getElementById('view-exp-list');
	if (!expenses || expenses.length === 0) {
		container.innerHTML = '<p style="text-align:center; color:#64748b; padding:20px;">No expenses attached yet.</p>';
	} else {
		var html = '<table class="widefat striped"><thead><tr><th>Category</th><th>Vendor / Description</th><th>Date</th><th style="text-align:right;">Amount (₹)</th><th style="text-align:center;">Action</th></tr></thead><tbody>';
		var total = 0;
		for (var i = 0; i < expenses.length; i++) {
			var e = expenses[i];
			var amt = Number(e.amount || 0);
			total += amt;
			var catIcons = {hotel:'🏨 Hotel', cab:'🚕 Cab', aarti_boat:'🪔 Aarti/Boat', guide_temple:'🛕 Guide', meals:'🍲 Food', other:'📦 Other'};
			var catLabel = catIcons[e.category] || e.category;
			html += '<tr>' +
				'<td><strong>' + catLabel + '</strong></td>' +
				'<td>' + (e.vendor_name || '—') + (e.notes ? '<div style="font-size:11px;color:#94a3b8;">' + e.notes + '</div>' : '') + '</td>' +
				'<td style="font-size:12px;">' + (e.expense_date || '—') + '</td>' +
				'<td style="text-align:right; font-weight:700; font-family:monospace; color:#ef4444;">-₹' + amt.toLocaleString('en-IN', {minimumFractionDigits:2}) + '</td>' +
				'<td style="text-align:center;">' +
					'<form method="post" style="display:inline;" onsubmit="return confirm(\'Delete this expense item?\');">' +
					'<?php echo wp_nonce_field( 'tc_finance_action', '_tc_nonce', true, false ); ?>' +
					'<input type="hidden" name="tc_action" value="delete_customer_expense" />' +
					'<input type="hidden" name="delete_exp_id" value="' + e.id + '" />' +
					'<button type="submit" class="button button-small" style="color:#ef4444; border-color:#fca5a5;">Delete</button>' +
					'</form>' +
				'</td>' +
				'</tr>';
		}
		html += '</tbody><tfoot><tr><th colspan="3" style="text-align:right;">Total Attached Expenses:</th><th style="text-align:right; font-weight:800; color:#ef4444; font-family:monospace;">-₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2}) + '</th><th></th></tr></tfoot></table>';
		container.innerHTML = html;
	}

	document.getElementById('view-exp-add-btn').onclick = function() {
		closeTcModal('tc-view-expense-modal');
		openAddExpenseModal(invoiceNo, customerName);
	};

	modal.style.display = 'block';
}

function closeTcModal(modalId) {
	var el = document.getElementById(modalId);
	if (el) el.style.display = 'none';
}

function filterCustomerMargins() {
	var input = document.getElementById('tc-cust-search');
	var filter = input.value.toLowerCase();
	var rows = document.querySelectorAll('#tc-customer-margins-table tbody tr.tc-cust-row');
	var matched = 0;
	for (var i = 0; i < rows.length; i++) {
		var text = rows[i].textContent.toLowerCase();
		if (text.indexOf(filter) > -1) {
			rows[i].style.display = '';
			matched++;
		} else {
			rows[i].style.display = 'none';
		}
	}
	document.getElementById('tc-cust-match-count').textContent = matched + ' customers';
}
</script>
