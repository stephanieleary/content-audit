<?php

function content_audit_admin_bar_links() {
	if ( !is_super_admin() || !is_admin_bar_showing() || !is_singular() )
		return;

	$current_object = get_queried_object();

	if ( empty( $current_object ) )
		return;
	
	global $wp_admin_bar, $post;
	
	$nonce = wp_create_nonce( 'content-audit-nonce' );
	
	$wp_admin_bar->add_menu( array( 
		'parent' => false, 
		'id' => 'content_audit',
		'title' => __( 'Audit', 'content-audit' ), 
		'href' => get_edit_post_link( $post->ID ), 
		'meta' => false 
	 ) );

	$auditterms = get_terms( 'content_audit', array( 'hide_empty' => false ) );
	if ( !empty( $auditterms ) )
		foreach ( $auditterms as $term ) {
			$url = add_query_arg( array( 'action' => 'content-audit-categorize',
										'term' => $term->slug,
										'post_id' => $post->ID,
										'return' => $_SERVER["REQUEST_URI"],
										'nonce' => $nonce,
									 ), admin_url( '/admin-ajax.php' ) );
									
			$title = $term->name;
			if ( has_term( $term->term_id, 'content_audit', $post->ID ) ) {
				$title = '&checkmark; ' . $title;
			}
			
			$wp_admin_bar->add_menu( array( 
				'parent' => 'content_audit',
				'id' => 'content_audit_' . $term->slug,
				'title' => $title,
				'href' => $url
			 ) );
		}
	
	
	if ( !empty( $current_object->post_type ) &&
		( $post_type_object = get_post_type_object( $current_object->post_type ) ) &&
		current_user_can( $post_type_object->cap->edit_post, $current_object->ID )
		 ) {
			$args = array( 
				'return' => admin_url( 'edit.php?post_type=' . $post->post_type ) 
			);
			$wp_admin_bar->add_menu( 
				array( 
					'parent' => 'content_audit',
					'id' => 'delete',
					'title' => __( 'Move to Trash', 'content-audit' ),
					'href' => add_query_arg( $args, get_delete_post_link( $post->ID ) )
				 )
			 );
	}

	
	if ( is_home() || is_front_page() ) {
		$args = array( 
			'format' => 'csv', 
			'post_type' => $current_object->post_type 
		);
		$wp_admin_bar->add_menu( 
			array( 
				'parent' => 'content_audit',
				'id' => 'download',
				'title' => __( 'Download Audit Report', 'content-audit' ),
				'href' => add_query_arg( $args, get_permalink( $current_object->ID ) )
			 )
		 );
		
	}

}
add_action( 'wp_before_admin_bar_render', 'content_audit_admin_bar_links' );

// admin-ajax hook
add_action( 'wp_ajax_content-audit-categorize', 'content_audit_ajax_categorize' );

function content_audit_ajax_categorize() {
	// check nonce
	if ( ! wp_verify_nonce( $_GET['nonce'], 'content-audit-nonce' ) )
		die ( 'Busted!' );
	
	// validate
	if ( isset( $_GET['term'] ) ) {
		$taxonomy = 'content_audit';
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax )
			die( '0' );
		$termok = term_exists( $_GET['term'], $tax->name );
		if ( $termok == 0 || $termok == null )
			die( '0' );
	} else {
		die( '0' );
	}

	$term = stripslashes( $_GET['term'] );
	$id = $_GET['post_id'];
	$append = true;
	
	if ( $term == 'audited' )
		$append = false;

	$set = wp_set_object_terms( $id, $term, $tax->name, $append );
	
	if ( is_wp_error( $set ) )
	   echo $set->get_error_message();

	wp_redirect( esc_url( $_GET['return'] ) );
	
	exit;
}
