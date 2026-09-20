<?php
/**
 * Admin Knowledge Base & FAQ Repository View.
 * Inspired by VM Sales OS Knowledge Module.
 *
 * @package TripCosmos_Agents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_kb = $wpdb->prefix . 'tc_agent_knowledge';

$search_query      = sanitize_text_field( $_GET['s'] ?? '' );
$selected_category = sanitize_text_field( $_GET['category'] ?? '' );
$edit_id           = absint( $_GET['edit'] ?? 0 );

$where = array( '1=1' );
if ( ! empty( $selected_category ) ) {
	$where[] = $wpdb->prepare( 'category = %s', $selected_category );
}
if ( ! empty( $search_query ) ) {
	$like = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where[] = $wpdb->prepare( '(title LIKE %s OR content LIKE %s OR tags LIKE %s)', $like, $like, $like );
}

$where_sql = implode( ' AND ', $where );
$docs = $wpdb->get_results( "SELECT * FROM $table_kb WHERE $where_sql ORDER BY id DESC", ARRAY_A ) ?: array();

// If editing, load the doc
$edit_doc = null;
if ( $edit_id > 0 ) {
	$edit_doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_kb WHERE id = %d", $edit_id ), ARRAY_A );
}

$categories = array(
	'faq'           => __( 'Frequently Asked Questions (FAQ)', 'tripcosmos-agents' ),
	'policy'        => __( 'Booking & Cancellation Policies', 'tripcosmos-agents' ),
	'tour_circuits' => __( 'Tour Circuits & Itineraries (Varanasi, Ayodhya, Bodhgaya)', 'tripcosmos-agents' ),
	'cabs_hotels'   => __( 'Outstation Cab Rates & Ghat Hotel Stays', 'tripcosmos-agents' ),
	'darshan_aarti' => __( 'Kashi Vishwanath, Ram Mandir & Ganga Aarti Logistics', 'tripcosmos-agents' ),
);
?>

<div class="wrap tc-admin-wrap">
	<div class="tc-header">
		<div>
			<h1><span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Knowledge Base & FAQ Engine', 'tripcosmos-agents' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Feed packages, temple darshan guidelines, cab rate charts, and hotel policies directly into the AI agent to guarantee accurate answers.', 'tripcosmos-agents' ); ?></p>
		</div>
		<div class="tc-header-actions">
			<button type="button" id="tc-sync-catalog-btn" class="button button-secondary" style="margin-right: 8px;">
				<span class="dashicons dashicons-database-import" style="vertical-align: middle;"></span> <?php esc_html_e( 'Sync Tours & Pages to RAG', 'tripcosmos-agents' ); ?>
			</button>
			<a href="#new-doc" class="button button-primary" onclick="document.getElementById('tc-kb-form-card').scrollIntoView({behavior: 'smooth'});">
				<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'New Document', 'tripcosmos-agents' ); ?>
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Knowledge base entry saved.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Document removed.', 'tripcosmos-agents' ); ?></p></div>
	<?php endif; ?>

	<div class="tc-two-col-layout">
		<!-- Left: Knowledge Documents List -->
		<div>
			<div class="tc-card" style="padding: 14px 20px; margin-bottom: 20px;">
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
					<input type="hidden" name="page" value="tc-agents-knowledge" />
					<div style="flex: 1; min-width: 180px;">
						<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Search documents & FAQs...', 'tripcosmos-agents' ); ?>" style="width: 100%;" />
					</div>
					<div>
						<select name="category" onchange="this.form.submit();">
							<option value=""><?php esc_html_e( 'All Categories', 'tripcosmos-agents' ); ?></option>
							<?php foreach ( $categories as $ckey => $clabel ) : ?>
								<option value="<?php echo esc_attr( $ckey ); ?>" <?php selected( $selected_category, $ckey ); ?>><?php echo esc_html( $clabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'tripcosmos-agents' ); ?></button>
					<?php if ( ! empty( $search_query ) || ! empty( $selected_category ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge' ) ); ?>" class="button-link"><?php esc_html_e( 'Reset', 'tripcosmos-agents' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<div class="tc-card">
				<div class="tc-card-header">
					<h3><?php esc_html_e( 'Active Knowledge Items', 'tripcosmos-agents' ); ?> (<?php echo count( $docs ); ?>)</h3>
				</div>

				<?php if ( empty( $docs ) ) : ?>
					<div style="text-align: center; color: #64748b; padding: 40px;">
						<span class="dashicons dashicons-media-document" style="font-size: 36px; width: 36px; height: 36px; margin-bottom: 8px;"></span>
						<p><?php esc_html_e( 'No knowledge entries found. Add your first policy or FAQ using the form on the right.', 'tripcosmos-agents' ); ?></p>
					</div>
				<?php else : ?>
					<div class="tc-kb-list">
						<?php foreach ( $docs as $d ) : ?>
							<div class="tc-kb-item <?php echo empty( $d['is_active'] ) ? 'inactive' : ''; ?>">
								<div class="tc-kb-item-header">
									<div>
										<span class="tc-tag tc-tag-category"><?php echo esc_html( $categories[ $d['category'] ] ?? $d['category'] ); ?></span>
										<?php if ( empty( $d['is_active'] ) ) : ?>
											<span class="tc-badge tc-badge-danger" style="padding: 2px 6px; font-size: 10px;"><?php esc_html_e( 'Inactive', 'tripcosmos-agents' ); ?></span>
										<?php endif; ?>
										<h4 style="margin: 6px 0 2px 0; font-size: 15px; color: #0f172a;"><?php echo esc_html( $d['title'] ); ?></h4>
									</div>
									<div class="tc-kb-actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge&edit=' . $d['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'tripcosmos-agents' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Permanently delete this knowledge item?', 'tripcosmos-agents' ); ?>');">
											<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
											<input type="hidden" name="tc_agents_action" value="delete_knowledge" />
											<input type="hidden" name="doc_id" value="<?php echo esc_attr( $d['id'] ); ?>" />
											<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'tripcosmos-agents' ); ?></button>
										</form>
									</div>
								</div>

								<div class="tc-kb-content-preview">
									<?php echo esc_html( wp_trim_words( $d['content'], 35 ) ); ?>
								</div>

								<?php if ( ! empty( $d['tags'] ) ) : ?>
									<div class="tc-kb-tags" style="margin-top: 8px;">
										<?php
										$tag_list = explode( ',', $d['tags'] );
										foreach ( $tag_list as $t ) :
											?>
											<span class="tc-tag"><?php echo esc_html( trim( $t ) ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right: Add / Edit Knowledge Item Form -->
		<div id="tc-kb-form-card">
			<div class="tc-card" style="position: sticky; top: 32px;">
				<div class="tc-card-header">
					<h3><?php echo $edit_doc ? esc_html__( 'Edit Knowledge Document', 'tripcosmos-agents' ) : esc_html__( 'Add Knowledge Document', 'tripcosmos-agents' ); ?></h3>
					<?php if ( $edit_doc ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-agents-knowledge' ) ); ?>" class="button button-small"><?php esc_html_e( 'Cancel Edit', 'tripcosmos-agents' ); ?></a>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<?php wp_nonce_field( 'tc_agents_admin_save', 'tc_agents_nonce' ); ?>
					<input type="hidden" name="tc_agents_action" value="save_knowledge" />
					<input type="hidden" name="doc_id" value="<?php echo esc_attr( $edit_doc['id'] ?? 0 ); ?>" />

					<p>
						<label for="tc_kb_title"><strong><?php esc_html_e( 'Document / Question Title', 'tripcosmos-agents' ); ?></strong></label><br>
						<input type="text" id="tc_kb_title" name="title" value="<?php echo esc_attr( $edit_doc['title'] ?? '' ); ?>" class="regular-text" required style="width: 100%;" placeholder="e.g. Kashi Vishwanath Mangala Aarti Darshan & Protocol Guidelines" />
					</p>

					<p>
						<label for="tc_kb_category"><strong><?php esc_html_e( 'Category', 'tripcosmos-agents' ); ?></strong></label><br>
						<select id="tc_kb_category" name="category" style="width: 100%;">
							<?php foreach ( $categories as $ckey => $clabel ) : ?>
								<option value="<?php echo esc_attr( $ckey ); ?>" <?php selected( $edit_doc['category'] ?? 'faq', $ckey ); ?>><?php echo esc_html( $clabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p>
						<label for="tc_kb_tags"><strong><?php esc_html_e( 'Keywords / Search Tags', 'tripcosmos-agents' ); ?></strong></label><br>
						<input type="text" id="tc_kb_tags" name="tags" value="<?php echo esc_attr( $edit_doc['tags'] ?? '' ); ?>" class="regular-text" style="width: 100%;" placeholder="e.g. kedarkantha, refund, permits, altitude" />
						<span class="description" style="font-size: 11px;"><?php esc_html_e( 'Comma-separated keywords to help semantic and prompt matching.', 'tripcosmos-agents' ); ?></span>
					</p>

					<p>
						<label for="tc_kb_content"><strong><?php esc_html_e( 'Answer / Policy Content', 'tripcosmos-agents' ); ?></strong></label><br>
						<textarea id="tc_kb_content" name="content" rows="9" style="width: 100%; font-family: monospace; font-size: 13px;" required placeholder="<?php esc_attr_e( 'Provide exact rules, steps, and traveler guidelines. The AI will cite this verbatim when asked relevant questions.', 'tripcosmos-agents' ); ?>"><?php echo esc_textarea( $edit_doc['content'] ?? '' ); ?></textarea>
					</p>

					<p>
						<label>
							<input type="checkbox" name="is_active" value="1" <?php checked( $edit_doc ? (bool) $edit_doc['is_active'] : true ); ?> />
							<strong><?php esc_html_e( 'Active for Prompt Injection', 'tripcosmos-agents' ); ?></strong>
						</label>
					</p>

					<p>
						<button type="submit" class="button button-primary" style="width: 100%;">
							<?php echo $edit_doc ? esc_html__( 'Update Document', 'tripcosmos-agents' ) : esc_html__( 'Save Document to AI Memory', 'tripcosmos-agents' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
