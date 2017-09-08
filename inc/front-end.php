<?php

// Prints the content status, notes, and owner on the front end
function content_audit_front_end_display( $content ) {
	if ( !is_user_logged_in() )
		return $content;
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
		
	if ( !empty( $options['display_switch'] ) && in_array( $role, $allowed ) ) {
		$out = content_audit_notes( false );		
		if ( $options['display'] == 'above' ) 
			return $out.$content;
		elseif ( $options['display'] == 'below' ) 
			return $content.$out;
		
		return $content;
	}
	
	return $content;
}

add_filter( 'the_content', 'content_audit_front_end_display' );

// template tag: content_audit_notes( $echo );
function content_audit_notes( $echo = true ) {
	$post_id = get_the_ID();
	$out = '<p class="content-status">'.get_the_term_list( $post_id, 'content_audit', __( 'Content status: ', 'content-audit' ), ', ','' ).'</p>';
	$ownerID = absint( get_post_meta( $post_id, "_content_audit_owner", true ) );
	if ( !empty( $ownerID ) ) {
		$out .= '<p class="content-owner">'.__( "Assigned to: ", 'content-audit' ).get_the_author_meta( 'display_name', $ownerID ).'</p>';
	}
	$out .= '<p class="content-notes">'.sanitize_text_field( get_post_meta( $post_id, "_content_audit_notes", true ) ).'</p>';
	$out = apply_filters( 'content_audit_notes', '<div class="content-audit">'.$out.'</div>' );
	if ( $echo ) 
		echo $out;
	else 
		return $out;	
}

// Prints the CSS for the front end
function content_audit_front_end_css() {
	if ( !is_user_logged_in() )
		return;
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
		
	if ( !empty( $options['display'] ) && in_array( $role, $allowed ) ) {	
		echo '<style type="text/css">'.$options['css'].'</style>';
	}
}
add_action( 'wp_head', 'content_audit_front_end_css' );