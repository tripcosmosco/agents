<?php
/**
 * Booking create/edit form. Expects $tc_booking (array, empty for new), $c (contact row), $tc_nonce (closure).
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$b = wp_parse_args( (array) $tc_booking, array( 'id' => 0, 'title' => '', 'start_date' => '', 'end_date' => '', 'pax' => 1, 'vehicle' => '', 'driver' => '', 'quote_amount' => '', 'advance_paid' => '', 'status' => 'quoted', 'notes' => '' ) );
?>
<form method="post" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:8px; margin-top:10px;">
	<?php $tc_nonce(); ?>
	<input type="hidden" name="tc_contacts_action" value="save_booking" />
	<input type="hidden" name="contact_id" value="<?php echo (int) $c['id']; ?>" />
	<input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>" />
	<label style="grid-column: 1 / -1;"><?php esc_html_e( 'Trip / package', 'tripcosmos-agents' ); ?><br /><input name="title" value="<?php echo esc_attr( $b['title'] ); ?>" style="width:100%;" placeholder="Kashi Vishwanath VIP darshan + Ayodhya, 3D/2N" required /></label>
	<label><?php esc_html_e( 'Start', 'tripcosmos-agents' ); ?><br /><input type="date" name="start_date" value="<?php echo esc_attr( (string) $b['start_date'] ); ?>" /></label>
	<label><?php esc_html_e( 'End', 'tripcosmos-agents' ); ?><br /><input type="date" name="end_date" value="<?php echo esc_attr( (string) $b['end_date'] ); ?>" /></label>
	<label><?php esc_html_e( 'Travellers', 'tripcosmos-agents' ); ?><br /><input type="number" min="1" name="pax" value="<?php echo esc_attr( $b['pax'] ); ?>" style="width:80px;" /></label>
	<label><?php esc_html_e( 'Vehicle', 'tripcosmos-agents' ); ?><br /><input name="vehicle" value="<?php echo esc_attr( $b['vehicle'] ); ?>" placeholder="Innova Crysta" /></label>
	<label><?php esc_html_e( 'Driver', 'tripcosmos-agents' ); ?><br /><input name="driver" value="<?php echo esc_attr( $b['driver'] ); ?>" /></label>
	<label><?php esc_html_e( 'Quote (₹)', 'tripcosmos-agents' ); ?><br /><input type="number" step="1" min="0" name="quote_amount" value="<?php echo esc_attr( $b['quote_amount'] ); ?>" /></label>
	<label><?php esc_html_e( 'Advance paid (₹)', 'tripcosmos-agents' ); ?><br /><input type="number" step="1" min="0" name="advance_paid" value="<?php echo esc_attr( $b['advance_paid'] ); ?>" /></label>
	<label><?php esc_html_e( 'Status', 'tripcosmos-agents' ); ?><br />
		<select name="status"><?php foreach ( TC_Contacts_Admin::BOOKING_STATUSES as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $b['status'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
	</label>
	<label style="grid-column: 1 / -1;"><?php esc_html_e( 'Notes', 'tripcosmos-agents' ); ?><br /><textarea name="notes" rows="2" style="width:100%;"><?php echo esc_textarea( (string) $b['notes'] ); ?></textarea></label>
	<div style="grid-column: 1 / -1;"><button class="button button-primary"><?php echo $b['id'] ? esc_html__( 'Update booking', 'tripcosmos-agents' ) : esc_html__( 'Create booking', 'tripcosmos-agents' ); ?></button></div>
</form>
