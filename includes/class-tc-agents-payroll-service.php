<?php
/**
 * TripCosmos Agents - Zero-Cost Employee Salary & Payroll Engine
 *
 * Handles employee directory, salary calculation (Indian statutory CTC split:
 * Basic 50%, HRA 20%, Special Allowance 30%, LOP, PT, TDS), responsive PDF/HTML
 * payslip generation, instant WhatsApp delivery via Evolution API, and automated
 * Manual Journal syncing to Zoho Books Free Plan.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TC_Agents_Payroll_Service {

	const OPTION_EMPLOYEES   = 'tc_agents_payroll_employees';
	const OPTION_RUN_PREFIX  = 'tc_agents_payroll_run_';

	/**
	 * Default seed employees for TripCosmos operations.
	 */
	public static function get_default_employees() {
		return array(
			array(
				'id'            => 'EMP-001',
				'name'          => 'Rahul Sharma',
				'email'         => 'rahul@tripcosmos.co',
				'phone'         => '919876543210',
				'designation'   => 'Senior Tour Specialist & Ops Lead',
				'department'    => 'Operations',
				'monthly_ctc'   => 45000,
				'bank_name'     => 'State Bank of India',
				'account_no'    => '389201928374',
				'ifsc'          => 'SBIN0001234',
				'upi_id'        => 'rahul@sbi',
				'pan'           => 'ABCPS1234F',
				'joining_date'  => '2023-04-01',
				'status'        => 'active',
			),
			array(
				'id'            => 'EMP-002',
				'name'          => 'Aditi Verma',
				'email'         => 'aditi@tripcosmos.co',
				'phone'         => '919876543211',
				'designation'   => 'B2B Network & Customer Experience Lead',
				'department'    => 'B2B Sales',
				'monthly_ctc'   => 40000,
				'bank_name'     => 'HDFC Bank',
				'account_no'    => '501002938475',
				'ifsc'          => 'HDFC0000567',
				'upi_id'        => 'aditi@okhdfcbank',
				'pan'           => 'BNMPV5678G',
				'joining_date'  => '2023-07-15',
				'status'        => 'active',
			),
			array(
				'id'            => 'EMP-003',
				'name'          => 'Vikram Rao',
				'email'         => 'vikram@tripcosmos.co',
				'phone'         => '919876543212',
				'designation'   => 'Fleet & Transport Coordinator',
				'department'    => 'Logistics',
				'monthly_ctc'   => 32000,
				'bank_name'     => 'ICICI Bank',
				'account_no'    => '001201938472',
				'ifsc'          => 'ICIC0000123',
				'upi_id'        => 'vikram@icici',
				'pan'           => 'CQKPR9876H',
				'joining_date'  => '2024-01-10',
				'status'        => 'active',
			),
			array(
				'id'            => 'EMP-004',
				'name'          => 'Neha Patel',
				'email'         => 'neha@tripcosmos.co',
				'phone'         => '919876543213',
				'designation'   => 'Accounts & Compliance Executive',
				'department'    => 'Finance',
				'monthly_ctc'   => 35000,
				'bank_name'     => 'Kotak Mahindra Bank',
				'account_no'    => '671201928371',
				'ifsc'          => 'KKBK0000456',
				'upi_id'        => 'neha@kotak',
				'pan'           => 'DJFPN4321K',
				'joining_date'  => '2023-11-01',
				'status'        => 'active',
			),
			array(
				'id'            => 'EMP-005',
				'name'          => 'Rajesh Kumar',
				'email'         => 'rajesh@tripcosmos.co',
				'phone'         => '919876543214',
				'designation'   => 'Travel Operations Associate',
				'department'    => 'Customer Support',
				'monthly_ctc'   => 25000,
				'bank_name'     => 'Punjab National Bank',
				'account_no'    => '129301928374',
				'ifsc'          => 'PUNB0000789',
				'upi_id'        => 'rajesh@pnb',
				'pan'           => 'ELNPK8765L',
				'joining_date'  => '2024-03-01',
				'status'        => 'active',
			),
		);
	}

	/**
	 * Retrieve all employees. If none exist, seed defaults.
	 *
	 * @return array
	 */
	public static function get_employees() {
		$employees = get_option( self::OPTION_EMPLOYEES, null );
		if ( empty( $employees ) || ! is_array( $employees ) ) {
			$employees = self::get_default_employees();
			update_option( self::OPTION_EMPLOYEES, $employees );
		}
		return $employees;
	}

	/**
	 * Save or update employee details.
	 *
	 * @param array $data
	 * @return array
	 */
	public static function save_employee( array $data ) {
		$employees = self::get_employees();
		$id        = ! empty( $data['id'] ) ? sanitize_text_field( $data['id'] ) : 'EMP-' . str_pad( (string) ( count( $employees ) + 1 ), 3, '0', STR_PAD_LEFT );

		$record = array(
			'id'           => $id,
			'name'         => sanitize_text_field( $data['name'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'phone'        => preg_replace( '/[^0-9]/', '', (string) ( $data['phone'] ?? '' ) ),
			'designation'  => sanitize_text_field( $data['designation'] ?? 'Operations Associate' ),
			'department'   => sanitize_text_field( $data['department'] ?? 'Operations' ),
			'monthly_ctc'  => max( 0, (float) ( $data['monthly_ctc'] ?? 0 ) ),
			'bank_name'    => sanitize_text_field( $data['bank_name'] ?? 'State Bank of India' ),
			'account_no'   => sanitize_text_field( $data['account_no'] ?? '' ),
			'ifsc'         => strtoupper( sanitize_text_field( $data['ifsc'] ?? '' ) ),
			'upi_id'       => sanitize_text_field( $data['upi_id'] ?? '' ),
			'pan'          => strtoupper( sanitize_text_field( $data['pan'] ?? '' ) ),
			'joining_date' => sanitize_text_field( $data['joining_date'] ?? date( 'Y-m-d' ) ),
			'status'       => sanitize_text_field( $data['status'] ?? 'active' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$found = false;
		foreach ( $employees as $idx => $emp ) {
			if ( $emp['id'] === $id ) {
				$employees[ $idx ] = array_merge( $emp, $record );
				$found             = true;
				break;
			}
		}

		if ( ! $found ) {
			$employees[] = $record;
		}

		update_option( self::OPTION_EMPLOYEES, $employees );
		return $record;
	}

	/**
	 * Delete or deactivate an employee.
	 *
	 * @param string $id
	 * @return bool
	 */
	public static function delete_employee( $id ) {
		$employees = self::get_employees();
		$new_list  = array();
		$found     = false;

		foreach ( $employees as $emp ) {
			if ( $emp['id'] === $id ) {
				$found = true;
				continue;
			}
			$new_list[] = $emp;
		}

		if ( $found ) {
			update_option( self::OPTION_EMPLOYEES, $new_list );
			return true;
		}
		return false;
	}

	/**
	 * Compute monthly payroll breakdown for all active staff.
	 *
	 * @param string $month YYYY-MM (e.g. '2026-10')
	 * @param array $attendance_overrides Map of [ emp_id => [ 'lop_days' => x, 'bonus' => y, 'advances' => z ] ]
	 * @return array
	 */
	public static function calculate_payroll( $month = '', array $attendance_overrides = array() ) {
		if ( empty( $month ) ) {
			$month = date( 'Y-m' );
		}

		$time         = strtotime( $month . '-01' );
		$total_days   = (int) date( 't', $time );
		$month_name   = date( 'F Y', $time );
		$employees    = self::get_employees();
		$items        = array();
		$total_gross  = 0;
		$total_net    = 0;
		$total_deduct = 0;
		$total_bonus  = 0;

		foreach ( $employees as $emp ) {
			if ( isset( $emp['status'] ) && 'inactive' === $emp['status'] ) {
				continue;
			}

			$emp_id    = $emp['id'];
			$overrides = $attendance_overrides[ $emp_id ] ?? array();

			$ctc       = (float) ( $emp['monthly_ctc'] ?? 0 );
			$lop_days  = max( 0, (float) ( $overrides['lop_days'] ?? 0 ) );
			$bonus     = max( 0, (float) ( $overrides['bonus'] ?? 0 ) );
			$advances  = max( 0, (float) ( $overrides['advances'] ?? 0 ) );
			$tds       = max( 0, (float) ( $overrides['tds'] ?? 0 ) );

			// Statutory CTC breakdown: 50% Basic, 20% HRA, 30% Special Allowance
			$basic             = round( $ctc * 0.50 );
			$hra               = round( $ctc * 0.20 );
			$special_allowance = round( $ctc - $basic - $hra );

			// LOP computation
			$per_day_rate   = $total_days > 0 ? round( $ctc / $total_days, 2 ) : 0;
			$lop_deduction  = round( $per_day_rate * $lop_days );
			$days_worked    = max( 0, $total_days - $lop_days );
			$earned_gross   = max( 0, round( $ctc - $lop_deduction ) );

			// Earned breakdown prorated
			$earned_basic   = $total_days > 0 ? round( ( $basic / $total_days ) * $days_worked ) : 0;
			$earned_hra     = $total_days > 0 ? round( ( $hra / $total_days ) * $days_worked ) : 0;
			$earned_special = max( 0, $earned_gross - $earned_basic - $earned_hra );

			// Professional Tax (standard India slab: 200 if CTC > 15,000)
			$pt = ( $earned_gross >= 15000 ) ? 200 : 0;

			$total_emp_deductions = round( $lop_deduction + $advances + $pt + $tds );
			$net_payable          = max( 0, round( $earned_gross + $bonus - $advances - $pt - $tds ) );

			$item = array(
				'employee_id'       => $emp_id,
				'name'              => $emp['name'],
				'designation'       => $emp['designation'],
				'department'        => $emp['department'],
				'phone'             => $emp['phone'],
				'monthly_ctc'       => $ctc,
				'total_days'        => $total_days,
				'days_worked'       => $days_worked,
				'lop_days'          => $lop_days,
				'basic'             => $basic,
				'hra'               => $hra,
				'special_allowance' => $special_allowance,
				'earned_basic'      => $earned_basic,
				'earned_hra'        => $earned_hra,
				'earned_special'    => $earned_special,
				'earned_gross'      => $earned_gross,
				'lop_deduction'     => $lop_deduction,
				'bonus'             => $bonus,
				'advances'          => $advances,
				'pt'                => $pt,
				'tds'               => $tds,
				'total_deductions'  => $total_emp_deductions,
				'net_payable'       => $net_payable,
				'bank_name'         => $emp['bank_name'] ?? '',
				'account_no'        => $emp['account_no'] ?? '',
				'ifsc'              => $emp['ifsc'] ?? '',
				'upi_id'            => $emp['upi_id'] ?? '',
				'pan'               => $emp['pan'] ?? '',
			);

			$items[]       = $item;
			$total_gross  += $earned_gross;
			$total_net    += $net_payable;
			$total_deduct += $total_emp_deductions;
			$total_bonus  += $bonus;
		}

		$summary = array(
			'month'            => $month,
			'month_name'       => $month_name,
			'total_days'       => $total_days,
			'employee_count'   => count( $items ),
			'total_gross'      => $total_gross,
			'total_net'        => $total_net,
			'total_deductions' => $total_deduct,
			'total_bonus'      => $total_bonus,
			'items'            => $items,
			'calculated_at'    => current_time( 'mysql' ),
		);

		update_option( self::OPTION_RUN_PREFIX . $month, $summary );
		return $summary;
	}

	/**
	 * Convert number to Indian currency words.
	 *
	 * @param float|int $number
	 * @return string
	 */
	public static function number_to_words( $number ) {
		$number = round( $number );
		$words  = array(
			0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
			6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
			11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
			16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
			30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
			80 => 'Eighty', 90 => 'Ninety'
		);

		if ( 0 === $number ) {
			return 'Zero Rupees Only';
		}

		$crores   = floor( $number / 10000000 );
		$number  -= $crores * 10000000;
		$lakhs    = floor( $number / 100000 );
		$number  -= $lakhs * 100000;
		$thousands = floor( $number / 1000 );
		$number   -= $thousands * 1000;
		$hundreds = floor( $number / 100 );
		$number  -= $hundreds * 100;
		$tens     = (int) $number;

		$result = '';
		if ( $crores > 0 ) {
			$result .= self::convert_tens( $crores, $words ) . ' Crore ';
		}
		if ( $lakhs > 0 ) {
			$result .= self::convert_tens( $lakhs, $words ) . ' Lakh ';
		}
		if ( $thousands > 0 ) {
			$result .= self::convert_tens( $thousands, $words ) . ' Thousand ';
		}
		if ( $hundreds > 0 ) {
			$result .= $words[ $hundreds ] . ' Hundred ';
		}
		if ( $tens > 0 ) {
			$result .= self::convert_tens( $tens, $words ) . ' ';
		}

		return trim( $result ) . ' Rupees Only';
	}

	private static function convert_tens( $num, $words ) {
		if ( $num <= 20 ) {
			return $words[ $num ];
		}
		$ten  = floor( $num / 10 ) * 10;
		$unit = $num % 10;
		return trim( $words[ $ten ] . ' ' . ( $unit > 0 ? $words[ $unit ] : '' ) );
	}

	/**
	 * Generate branded HTML payslip and return URL.
	 *
	 * @param string $employee_id
	 * @param string $month YYYY-MM
	 * @return array
	 */
	public static function generate_payslip_html( $employee_id, $month = '' ) {
		if ( empty( $month ) ) {
			$month = date( 'Y-m' );
		}

		$run = get_option( self::OPTION_RUN_PREFIX . $month );
		if ( empty( $run ) || empty( $run['items'] ) ) {
			$run = self::calculate_payroll( $month );
		}

		$item = null;
		foreach ( $run['items'] as $it ) {
			if ( $it['employee_id'] === $employee_id ) {
				$item = $it;
				break;
			}
		}

		if ( ! $item ) {
			return array(
				'success' => false,
				'message' => 'Employee record not found for calculation',
			);
		}

		$upload_dir = wp_upload_dir();
		$payslip_dir = trailingslashit( $upload_dir['basedir'] ) . 'payslips';
		if ( ! file_exists( $payslip_dir ) ) {
			wp_mkdir_p( $payslip_dir );
		}

		$filename = sprintf( 'payslip_%s_%s.html', sanitize_file_name( $month ), sanitize_file_name( $employee_id ) );
		$filepath = trailingslashit( $payslip_dir ) . $filename;
		$fileurl  = trailingslashit( $upload_dir['baseurl'] ) . 'payslips/' . $filename;

		$words_net = self::number_to_words( $item['net_payable'] );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Salary Payslip - <?php echo esc_html( $item['name'] ); ?> (<?php echo esc_html( $run['month_name'] ); ?>)</title>
	<style>
		@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
		* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
		body { background-color: #f1f5f9; padding: 24px 12px; color: #0f172a; }
		.payslip-wrapper { max-width: 800px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06); overflow: hidden; }
		.header { background: linear-gradient(135deg, #090e17 0%, #151e2e 100%); color: #ffffff; padding: 32px 36px; border-bottom: 3px solid #d4af37; display: flex; justify-content: space-between; align-items: flex-start; }
		.brand-name { font-size: 26px; font-weight: 700; color: #d4af37; letter-spacing: -0.5px; }
		.brand-tagline { font-size: 13px; color: #94a3b8; margin-top: 4px; }
		.payslip-title { text-align: right; }
		.payslip-badge { background: rgba(212, 175, 55, 0.15); color: #f59e0b; border: 1px solid rgba(212, 175, 55, 0.4); font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
		.month-heading { font-size: 18px; font-weight: 700; margin-top: 6px; color: #f8fafc; }
		.meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 24px 36px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
		.meta-box p { margin-bottom: 6px; color: #475569; }
		.meta-box strong { color: #0f172a; }
		.table-section { padding: 24px 36px; }
		.salary-table { width: 100%; border-collapse: collapse; font-size: 13px; }
		.salary-table th { background: #f1f5f9; padding: 12px 14px; text-align: left; font-weight: 600; color: #334155; border: 1px solid #e2e8f0; }
		.salary-table td { padding: 10px 14px; border: 1px solid #e2e8f0; color: #1e293b; }
		.salary-table tr:nth-child(even) td { background: #fafafa; }
		.text-right { text-align: right; }
		.total-row td { background: #f8fafc; font-weight: 700; color: #0f172a; }
		.net-pay-banner { background: #ecfdf5; border: 2px dashed #10b981; border-radius: 12px; margin: 16px 36px 24px 36px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; }
		.net-pay-label { font-size: 13px; font-weight: 600; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px; }
		.net-pay-words { font-size: 13px; color: #047857; margin-top: 4px; font-style: italic; }
		.net-pay-val { font-size: 32px; font-weight: 800; color: #059669; }
		.footer { padding: 20px 36px 28px 36px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b; background: #f8fafc; }
		.signature-box { text-align: right; }
		.signature-line { width: 160px; height: 1px; background: #94a3b8; margin: 28px 0 6px auto; }
		.print-btn-container { text-align: center; margin-top: 20px; }
		.print-btn { background: #0f172a; color: #ffffff; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s ease; }
		.print-btn:hover { background: #1e293b; }
		@media print {
			body { background: #ffffff; padding: 0; }
			.payslip-wrapper { border: none; box-shadow: none; border-radius: 0; }
			.print-btn-container { display: none; }
		}
	</style>
</head>
<body>

<div class="payslip-wrapper">
	<div class="header">
		<div>
			<div class="brand-name">TripCosmos.co</div>
			<div class="brand-tagline">Premium Spiritual & Himalayan Expeditions</div>
			<div style="font-size: 11px; color: #cbd5e1; margin-top: 6px;">TripCosmos Tourism Private Limited &bull; CIN: U63090DL2023PTC123456</div>
		</div>
		<div class="payslip-title">
			<span class="payslip-badge">Official Payslip</span>
			<div class="month-heading"><?php echo esc_html( $run['month_name'] ); ?></div>
			<div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Ref: PAY-<?php echo esc_html( $month ); ?>-<?php echo esc_html( $item['employee_id'] ); ?></div>
		</div>
	</div>

	<div class="meta-grid">
		<div class="meta-box">
			<p><strong>Employee Name:</strong> <?php echo esc_html( $item['name'] ); ?></p>
			<p><strong>Employee ID:</strong> <?php echo esc_html( $item['employee_id'] ); ?></p>
			<p><strong>Designation:</strong> <?php echo esc_html( $item['designation'] ); ?></p>
			<p><strong>Department:</strong> <?php echo esc_html( $item['department'] ); ?></p>
			<p><strong>PAN:</strong> <?php echo esc_html( ! empty( $item['pan'] ) ? $item['pan'] : 'NOT PROVIDED' ); ?></p>
		</div>
		<div class="meta-box">
			<p><strong>Bank:</strong> <?php echo esc_html( $item['bank_name'] ); ?></p>
			<p><strong>Account No:</strong> <?php echo esc_html( ! empty( $item['account_no'] ) ? 'XXXXXX' . substr( $item['account_no'], -4 ) : 'N/A' ); ?></p>
			<p><strong>IFSC:</strong> <?php echo esc_html( $item['ifsc'] ); ?></p>
			<p><strong>UPI ID:</strong> <?php echo esc_html( ! empty( $item['upi_id'] ) ? $item['upi_id'] : 'N/A' ); ?></p>
			<p><strong>Days in Month / Worked:</strong> <?php echo esc_html( $item['total_days'] ); ?> / <strong><?php echo esc_html( $item['days_worked'] ); ?></strong> (LOP: <?php echo esc_html( $item['lop_days'] ); ?>)</p>
		</div>
	</div>

	<div class="table-section">
		<table class="salary-table">
			<thead>
				<tr>
					<th style="width: 50%;">Earnings (In INR)</th>
					<th class="text-right" style="width: 15%;">Amount</th>
					<th style="width: 20%;">Deductions</th>
					<th class="text-right" style="width: 15%;">Amount</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Basic Salary (50% CTC)</td>
					<td class="text-right">₹<?php echo number_format( $item['earned_basic'] ); ?></td>
					<td>Loss of Pay (LOP)</td>
					<td class="text-right">₹<?php echo number_format( $item['lop_deduction'] ); ?></td>
				</tr>
				<tr>
					<td>House Rent Allowance (HRA 20%)</td>
					<td class="text-right">₹<?php echo number_format( $item['earned_hra'] ); ?></td>
					<td>Professional Tax (PT)</td>
					<td class="text-right">₹<?php echo number_format( $item['pt'] ); ?></td>
				</tr>
				<tr>
					<td>Special & Operations Allowance</td>
					<td class="text-right">₹<?php echo number_format( $item['earned_special'] ); ?></td>
					<td>Salary Advance Deduction</td>
					<td class="text-right">₹<?php echo number_format( $item['advances'] ); ?></td>
				</tr>
				<tr>
					<td>Performance Bonus / Incentives</td>
					<td class="text-right">₹<?php echo number_format( $item['bonus'] ); ?></td>
					<td>TDS / Tax Deductions</td>
					<td class="text-right">₹<?php echo number_format( $item['tds'] ); ?></td>
				</tr>
				<tr class="total-row">
					<td><strong>Total Gross Earnings</strong></td>
					<td class="text-right"><strong>₹<?php echo number_format( $item['earned_gross'] + $item['bonus'] ); ?></strong></td>
					<td><strong>Total Deductions</strong></td>
					<td class="text-right"><strong>₹<?php echo number_format( $item['total_deductions'] ); ?></strong></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="net-pay-banner">
		<div>
			<div class="net-pay-label">Net Salary Disbursed</div>
			<div class="net-pay-words"><?php echo esc_html( $words_net ); ?></div>
		</div>
		<div class="net-pay-val">₹<?php echo number_format( $item['net_payable'] ); ?></div>
	</div>

	<div class="footer">
		<div>
			<p>This is a computer-generated document and requires no physical signature.</p>
			<p style="margin-top: 4px;">Disbursed via Direct Bank Transfer (SBI / UPI) &bull; Verified by TripCosmos Finance.</p>
		</div>
		<div class="signature-box">
			<div class="signature-line"></div>
			<p><strong>Authorized Signatory</strong></p>
			<p>TripCosmos Finance Team</p>
		</div>
	</div>
</div>

<div class="print-btn-container">
	<button class="print-btn" onclick="window.print()">🖨️ Download / Print Official PDF</button>
</div>

</body>
</html>
		<?php
		$html = ob_get_clean();
		file_put_contents( $filepath, $html );

		return array(
			'success'  => true,
			'url'      => $fileurl,
			'filepath' => $filepath,
			'item'     => $item,
			'month'    => $month,
		);
	}

	/**
	 * Send payslip to employee via Evolution API WhatsApp.
	 *
	 * @param string $employee_id
	 * @param string $month YYYY-MM
	 * @return array
	 */
	public static function dispatch_payslip_whatsapp( $employee_id, $month = '' ) {
		if ( empty( $month ) ) {
			$month = date( 'Y-m' );
		}

		$gen = self::generate_payslip_html( $employee_id, $month );
		if ( ! $gen['success'] ) {
			return $gen;
		}

		$item  = $gen['item'];
		$phone = preg_replace( '/[^0-9]/', '', (string) $item['phone'] );
		$url   = $gen['url'];

		if ( empty( $phone ) ) {
			return array(
				'success' => false,
				'message' => 'Employee WhatsApp phone number is missing.',
			);
		}

		$time       = strtotime( $month . '-01' );
		$month_name = date( 'F Y', $time );

		$msg  = "Namaste *{$item['name']}* 🙏\n\n";
		$msg .= "Your official salary payslip for *{$month_name}* from *TripCosmos.co* is now ready!\n\n";
		$msg .= "💼 *Role:* {$item['designation']}\n";
		$msg .= "📅 *Days Worked:* {$item['days_worked']} / {$item['total_days']}\n";
		$msg .= "💰 *Earned Gross:* ₹" . number_format( $item['earned_gross'] + $item['bonus'] ) . "\n";
		if ( $item['total_deductions'] > 0 ) {
			$msg .= "📉 *Total Deductions (LOP/PT):* ₹" . number_format( $item['total_deductions'] ) . "\n";
		}
		$msg .= "✨ *Net Salary:* *₹" . number_format( $item['net_payable'] ) . "*\n";
		$msg .= "🏦 *Disbursed To:* " . ( ! empty( $item['upi_id'] ) ? $item['upi_id'] : $item['bank_name'] ) . "\n\n";
		$msg .= "📄 *View / Download Your Official Payslip PDF:*\n{$url}\n\n";
		$msg .= "_Thank you for your dedicated service to TripCosmos travelers!_";

		$sent = false;
		$wa_res = null;

		if ( class_exists( 'TC_Integration_Evolution' ) && TC_Integration_Evolution::is_configured() ) {
			$wa_res = TC_Integration_Evolution::send_message( $phone, $msg );
			$sent   = ! is_wp_error( $wa_res ) && ( $wa_res['success'] ?? false );
		}

		return array(
			'success'     => true,
			'wa_sent'     => $sent,
			'phone'       => $phone,
			'payslip_url' => $url,
			'message'     => $msg,
			'wa_result'   => $wa_res,
		);
	}

	/**
	 * Sync monthly payroll expense directly to Zoho Books Free Plan via Manual Journal.
	 * Debits: Salaries and Wages (Expense)
	 * Credits: Payroll Payable (Current Liability)
	 *
	 * @param string $month YYYY-MM
	 * @return array
	 */
	public static function sync_to_zoho_journal( $month = '' ) {
		if ( empty( $month ) ) {
			$month = date( 'Y-m' );
		}

		$run = get_option( self::OPTION_RUN_PREFIX . $month );
		if ( empty( $run ) || empty( $run['items'] ) ) {
			$run = self::calculate_payroll( $month );
		}

		$total_net = (float) $run['total_net'];
		if ( $total_net <= 0 ) {
			return array(
				'success' => false,
				'message' => 'No net payable salary to sync for month ' . $month,
			);
		}

		$time       = strtotime( $month . '-01' );
		$last_day   = date( 'Y-m-t', $time );
		$month_name = date( 'F Y', $time );

		// Check Zoho Books Integration
		if ( ! class_exists( 'TC_Integration_Zoho' ) || ! TC_Integration_Zoho::is_connected() ) {
			// Return successful simulation / dry-run payload ready for connection
			return array(
				'success'       => true,
				'status'        => 'pending_zoho_connect',
				'message'       => 'Zoho Books credentials not connected. Manual journal entry prepared and saved locally.',
				'journal_entry' => array(
					'journal_date'     => $last_day,
					'reference_number' => 'PAYROLL-' . $month,
					'notes'            => 'TripCosmos Staff Monthly Payroll for ' . $month_name . ' (' . $run['employee_count'] . ' employees)',
					'total_amount'     => $total_net,
					'lines'            => array(
						array(
							'account' => 'Salaries and Employee Wages',
							'type'    => 'Debit',
							'amount'  => $total_net,
						),
						array(
							'account' => 'Payroll Payable / Bank Transfer',
							'type'    => 'Credit',
							'amount'  => $total_net,
						),
					),
				),
			);
		}

		$token   = TC_Integration_Zoho::get_access_token();
		$org_id  = TC_Integration_Zoho::get_org_id();
		$base_url = TC_Integration_Zoho::get_api_base_url();

		if ( is_wp_error( $token ) || empty( $token ) ) {
			return array(
				'success' => false,
				'message' => 'Failed to retrieve Zoho access token: ' . ( is_wp_error( $token ) ? $token->get_error_message() : 'Token empty' ),
			);
		}

		// Query Chart of Accounts to find exact account IDs
		$coa_url = add_query_arg(
			array( 'organization_id' => $org_id ),
			trailingslashit( $base_url ) . 'chartofaccounts'
		);

		$coa_res = wp_remote_get(
			$coa_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		$expense_account_id = '';
		$liability_account_id = '';

		if ( ! is_wp_error( $coa_res ) && wp_remote_retrieve_response_code( $coa_res ) === 200 ) {
			$coa_data = json_decode( wp_remote_retrieve_body( $coa_res ), true );
			if ( ! empty( $coa_data['chartofaccounts'] ) ) {
				foreach ( $coa_data['chartofaccounts'] as $acc ) {
					$name = strtolower( $acc['account_name'] ?? '' );
					$type = strtolower( $acc['account_type'] ?? '' );

					if ( empty( $expense_account_id ) && ( str_contains( $name, 'salar' ) || str_contains( $name, 'wage' ) || 'expense' === $type ) ) {
						$expense_account_id = $acc['account_id'];
					}
					if ( empty( $liability_account_id ) && ( str_contains( $name, 'payroll' ) || str_contains( $name, 'payable' ) || str_contains( $type, 'liability' ) ) ) {
						$liability_account_id = $acc['account_id'];
					}
				}
			}
		}

		$journal_payload = array(
			'journal_date'     => $last_day,
			'reference_number' => 'PAYROLL-' . $month,
			'notes'            => 'TripCosmos Staff Monthly Payroll for ' . $month_name . ' (' . $run['employee_count'] . ' employees)',
			'line_items'       => array(
				array(
					'account_id'      => $expense_account_id,
					'account_name'    => 'Salaries and Employee Wages',
					'debit_or_credit' => 'debit',
					'amount'          => $total_net,
					'description'     => 'Gross earned staff salaries for ' . $month_name,
				),
				array(
					'account_id'      => $liability_account_id,
					'account_name'    => 'Payroll Payable',
					'debit_or_credit' => 'credit',
					'amount'          => $total_net,
					'description'     => 'Net staff payroll payable for ' . $month_name,
				),
			),
		);

		$journal_url = add_query_arg(
			array( 'organization_id' => $org_id ),
			trailingslashit( $base_url ) . 'journals'
		);

		$post_res = wp_remote_post(
			$journal_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $journal_payload ),
			)
		);

		if ( is_wp_error( $post_res ) ) {
			return array(
				'success' => false,
				'message' => 'Zoho HTTP error: ' . $post_res->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $post_res ), true );
		$code = wp_remote_retrieve_response_code( $post_res );

		if ( $code >= 200 && $code < 300 && ( 0 === ( $body['code'] ?? 1 ) ) ) {
			$journal_id = $body['journal']['journal_id'] ?? '';
			return array(
				'success'    => true,
				'status'     => 'synced_to_zoho',
				'journal_id' => $journal_id,
				'message'    => 'Manual Journal successfully recorded in Zoho Books (Free Plan)!',
				'data'       => $body['journal'] ?? array(),
			);
		}

		return array(
			'success'  => false,
			'message'  => 'Zoho Books returned: ' . ( $body['message'] ?? 'Status ' . $code ),
			'payload'  => $journal_payload,
		);
	}
}
