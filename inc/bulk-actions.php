<?php

function register_content_audit_bulk_actions( $bulk_actions ) {
	$bulk_actions['mark_outdated'] = __( 'Mark as Outdated', 'content-audit' );
	$bulk_actions['mark_audited'] = __( 'Mark as Audited', 'content-audit' );
	return $bulk_actions;
}
add_filter( 'bulk_actions-upload', 'register_content_audit_bulk_actions' );
add_filter( 'bulk_actions-edit-post', 'register_content_audit_bulk_actions' );
add_filter( 'bulk_actions-edit-page', 'register_content_audit_bulk_actions' );

function content_audit_bulk_audit_handler( $redirect_to, $action, $post_ids ) {
	if ( $action !== 'mark_audited' && $action !== 'mark_outdated' ) {
		return $redirect_to;
	}
	
	$taxonomy = 'content_audit';
	
	if ( $action == 'mark_audited' ) {
		$slug = 'audited';
		$append = false;
	}
		
	if ( $action == 'mark_outdated' ) {
		$slug = 'outdated';	
		$append = true;
	}
	
	$term = get_term_by( 'slug', $slug, $taxonomy );

	foreach ( $post_ids as $post_id ) {
		wp_set_post_terms( $post_id, $term->term_id, $taxonomy, $append );
	}

	$redirect_to = add_query_arg( 'bulk_'.$action, count( $post_ids ), $redirect_to );

	return $redirect_to;
}
add_filter( 'handle_bulk_actions-upload', 'content_audit_bulk_audit_handler', 10, 3 );

function content_audit_bulk_action_admin_notice() {
	if ( ! empty( $_REQUEST['bulk_mark_audited'] ) ) {
		$drafts_count = intval( $_REQUEST['bulk_mark_audited'] );

		printf(
			'<div id="message" class="updated fade">' .
			_n( '%s post marked as Audited.', '%s posts marked as Audited.', $drafts_count, 'content-audit' )
			. '</div>',
			$drafts_count
		);
	}
	if ( ! empty( $_REQUEST['bulk_mark_outdated'] ) ) {
		$drafts_count = intval( $_REQUEST['bulk_mark_outdated'] );

		printf(
			'<div id="message" class="updated fade">' .
			_n( '%s post marked as Outdated.', '%s posts marked as Outdated.', $drafts_count, 'content-audit' )
			. '</div>',
			$drafts_count
		);
	}
}
add_action( 'admin_notices', 'content_audit_bulk_action_admin_notice' );