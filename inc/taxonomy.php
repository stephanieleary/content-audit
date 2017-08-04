<?php

add_action( 'init', 'create_content_audit_tax' );

function activate_content_audit_tax() {
	create_content_audit_tax();
	activate_content_audit_terms();
	flush_rewrite_rules();
}

function create_content_audit_tax() {
	$options = get_option( 'content_audit' );
	if ( isset( $options['post_types'] ) )
		$types = $options['post_types'];
	else
		$types = 'page';
	
	register_taxonomy( 
		'content_audit',
		$types,
		array( 
			'label' => __( 'Content Audit Attributes', 'content-audit' ),
			'show_admin_column' => true,
			'hierarchical' => true,
			'show_tagcloud' => false,
			'update_count_callback' => 'content_audit_term_count',
			'helps' => __('Enter content attributes separated by commas.', 'content-audit'),
		 )
	 );
}

function content_audit_term_count( $terms, $taxonomy ) {
	// based on _update_post_term_count(), plus per-post-type counts stored as options
	global $wpdb;
	
	$object_types = ( array ) $taxonomy->object_type;

		foreach ( $object_types as &$object_type )
			list( $object_type ) = explode( ':', $object_type );

		$object_types = array_unique( $object_types );

		if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) ) {
			unset( $object_types[ $check_attachments ] );
			$check_attachments = true;
		}

		if ( $object_types )
			$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
		
		foreach ( ( array ) $terms as $term ) {
			$count = 0;

			// Attachments can be 'inherit' status, we need to base count off the parent's status if so
			if ( $check_attachments )
				$count += ( int ) $wpdb->get_var( $wpdb->prepare( 
					"SELECT COUNT( * ) FROM $wpdb->term_relationships, $wpdb->posts p1 
					WHERE p1.ID = $wpdb->term_relationships.object_id 
					AND ( post_status = 'publish' 
					OR ( post_status = 'inherit' 
					AND post_parent > 0 
					AND ( SELECT post_status 
						FROM $wpdb->posts 
						WHERE ID = p1.post_parent ) = 'publish' ) ) 
						AND post_type = 'attachment' 
						AND term_taxonomy_id = %d", $term ) );

			// add to the total count for all non-attachment post types
			if ( $object_types )
				$count += ( int ) $wpdb->get_var( $wpdb->prepare( 
				"SELECT COUNT( * ) FROM $wpdb->term_relationships, $wpdb->posts 
				WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id 
				AND post_status = 'publish' 
				AND post_type 
				IN ( '" . implode( "', '", $object_types ) . "' ) 
				AND term_taxonomy_id = %d", $term ) );

			do_action( 'edit_term_taxonomy', $term, $taxonomy );

			// get the per-post-type counts and store in options
			foreach ( $object_types as $post_type ) {
				$option = get_option( '_audit_term_count_'.$post_type );
				
				$typecount = ( int ) $wpdb->get_var( $wpdb->prepare( 
				"SELECT COUNT( * ) FROM $wpdb->term_relationships, $wpdb->posts 
				WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id 
				AND post_status = 'publish' 
				AND post_type = %s
				AND term_taxonomy_id = %d", $post_type, $term ) );
				
				// update per-post-type count
				if ( isset( $typecount ) ) {
					$option[$term] = $typecount;
					update_option( '_audit_term_count_'.$post_type, $option );
				}	
			}
			
			// update total count for all post types
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
}

function activate_content_audit_terms() {
	if ( !term_exists( 'Outdated', 'content-audit' ) &&  !term_exists( 'outdated', 'content-audit' ) )
		wp_insert_term( 
			__( 'Outdated', 'content-audit' ), 
			'content_audit', 
			array( 'description' => __( 'This information is old and should be updated.', 'content-audit' ) ) 
		);
	if ( !term_exists( 'Redundant', 'content-audit' ) &&  !term_exists( 'redundant', 'content-audit' ) )	
		wp_insert_term( 
			__( 'Redundant', 'content-audit' ), 
			'content_audit', 
			array( 'description' => __( 'This information is duplicated elsewhere.', 'content-audit' ) ) 
		 );
	if ( !term_exists( 'Trivial', 'content-audit' ) &&  !term_exists( 'trivial', 'content-audit' ) )
		wp_insert_term( 
			__( 'Trivial', 'content-audit' ), 
			'content_audit', 
			array( 'description' => __( 'This page is unnecessary.', 'content-audit' ) ) 
		 );
	if ( !term_exists( 'Review SEO', 'content-audit' ) &&  !term_exists( 'review-seo', 'content-audit' ) )
		wp_insert_term( 
			__( 'Review SEO', 'content-audit' ), 
			'content_audit', 
			array( 'description' => __( 'The title, metadata, and/or content are not aligned with our target keywords.', 'content-audit' ) ) 
		 );
	if ( !term_exists( 'Review Style', 'content-audit' ) &&  !term_exists( 'review-style', 'content-audit' ) )
		wp_insert_term( 
			__( 'Review Style', 'content-audit' ), 
			'content_audit', 
			array( 'description' => __( 'The title and/or content were not written according to our editorial guidelines.', 'content-audit' ) ) 
		 );
	if ( !term_exists( 'Audited', 'content-audit' ) &&  !term_exists( 'audited', 'content-audit' ) )
		wp_insert_term( 
			__( 'Audited', 'content-audit' ), 
			'content_audit', 
			array( 'description' => __( 'This page has been reviewed. No further changes are needed.', 'content-audit' ) ) 
		 );
}

add_action( 'admin_init', 'content_audit_taxonomies' );

function content_audit_taxonomies() {
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	if ( !is_array( $options['post_types'] ) )
		$options['post_types'] = array( $options['post_types'] );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	foreach ( $options['post_types'] as $content_type ) {
		if ( in_array( $role, $allowed ) )
			register_taxonomy_for_object_type( 'content_audit', $content_type );
	}
}