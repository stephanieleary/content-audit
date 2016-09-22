<?php
/* Custom Taxonomy */

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



/* Custom Fields */

add_action( 'admin_init', 'content_audit_boxes' );
function content_audit_boxes() {
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	if ( !is_array( $options['post_types'] ) )
		$options['post_types'] = array( $options['post_types'] );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	foreach ( $options['post_types'] as $content_type ) {
		add_meta_box( 'content_audit_meta', __( 'Content Audit Notes','content-audit' ), 'content_audit_notes_meta_box', $content_type, 'normal', 'high' );
		add_meta_box( 'content_audit_owner', __( 'Content Owner','content-audit' ), 'content_audit_owner_meta_box', $content_type, 'side', 'low' );
		add_meta_box( 'content_audit_exp_date', __( 'Expiration Date','content-audit' ), 'content_audit_exp_date_meta_box', $content_type, 'side', 'low' );
		if ( $content_type == 'attachment' ) {
			//add_filter( 'attachment_fields_to_edit', 'content_audit_media_fields', 10, 2 );
			add_filter( 'attachment_fields_to_save', 'save_content_audit_media_meta', 10, 2 );
		}
		// let non-auditors see a read-only version of the taxonomy
		if ( !in_array( $role, $allowed ) )  {
			add_meta_box( 'content_audit_taxes', __( 'Content Audit','content-audit' ), 'content_audit_taxes_meta_box', $content_type, 'side', 'low' );
		}
	}
	add_action( 'save_post', 'save_content_audit_meta_data' );
	// This hook is needed if only the custom meta boxes' data was saved
	// ( save_post does not fire if no fields changed in the built-in post form )
	add_action( 'pre_post_update', 'save_content_audit_meta_data' );
	
	// don't show taxonomy checkboxes to non-auditors
	if ( !in_array( $role, $allowed ) )  {
		add_action( 'admin_menu', 'remove_audit_taxonomy_boxes' );
	}
}

function remove_audit_taxonomy_boxes()
{
	$options = get_option( 'content_audit' );
	foreach ( $options['post_types'] as $content_type ) {
		remove_meta_box( 'content_auditdiv', $content_type, 'side' );
	}
}

function content_audit_notes_meta_box() {
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	$notes = get_post_meta( get_the_ID(), '_content_audit_notes', true );
	if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'content_audit_notes_nonce', '_content_audit_notes_nonce' ); 
?>
<div id="audit-notes">
	<?php if ( in_array( $role, $allowed ) ) { ?>
	<textarea name="_content_audit_notes"><?php echo esc_textarea( $notes ); ?></textarea>
	<?php }
	// let non-auditors read the notes. Same HTML that's allowed in posts. 
	else echo wp_kses_post( $notes ); 
	?>
</div>
<?php
}

function content_audit_dropdown_users_args( $query_args, $r ) {
	$options = get_option( 'content_audit' );
	$query_args['role__in'] = $options['rolenames'];
	return $query_args;
}

function content_audit_owner_meta_box() {
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'content_audit_owner_nonce', '_content_audit_owner_nonce' ); 
?>
<div id="audit-owner">
	<?php
	$owner = get_post_meta( get_the_ID(), '_content_audit_owner', true );
	if ( empty( $owner ) ) $owner = -1;
	if ( in_array( $role, $allowed ) ) {
		add_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args', 10, 2 );
		wp_dropdown_users( array( 
			'selected' => $owner, 
			'name' => '_content_audit_owner', 
			'show_option_none' => __( 'Select a user','content-audit' ),
		 ) );	
		remove_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args' );
	}
	else {
		// let non-auditors see the owner
		if ( $owner > 0 ) the_author_meta( 'display_name', $owner );
	}
	?>
</div>
<?php
}

function content_audit_exp_date_meta_box() {
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( 'content_audit_exp_date_nonce', 'content_audit_exp_date_nonce' ); 
?>
<div id="audit-exp-date">
	<?php 
	$date = get_post_meta( get_the_ID(), '_content_audit_expiration_date', true ); 
	// convert from timestamp to date string
	if ( !empty( $date ) )
		$date = date( 'm/d/y', $date );
	if ( in_array( $role, $allowed ) ) { ?>
		<input type="text" class="widefat datepicker" name="_content_audit_expiration_date" value="<?php esc_attr_e( $date ); ?>" />
	<?php }
	else
		// let non-auditors see the expiration date
		echo $date; ?>
</div>
<?php
}

// this is a display-only version of the Content Audit taxonomy
function content_audit_taxes_meta_box() { ?>
	<div id="audit-taxes">
		<ul>
		<?php wp_list_categories( 'title_li=&taxonomy=content_audit' ); ?>
		</ul>
	</div>
	<?php
}

function save_content_audit_meta_data( $post_id ) {
	// check post types
	// reject this quickly
	if ( !isset( $_POST['post_type'] ) )
		return $post_id;
		
	if ( 'nav_menu_item ' == $_POST['post_type'] )
		return $post_id;
	
	// reject the ones we aren't auditing
	$options = get_option( 'content_audit' );
	if ( !in_array( $_POST['post_type'], $options['post_types'] ) )
		return $post_id;
	
	// check regular edit nonces
	if ( defined( 'DOING_AJAX' ) && !DOING_AJAX ) {
		check_admin_referer( 'content_audit_notes_nonce', '_content_audit_notes_nonce' );
		check_admin_referer( 'content_audit_owner_nonce', '_content_audit_owner_nonce' );
		check_admin_referer( 'content_audit_exp_date_nonce', 'content_audit_exp_date_nonce' );
	}

	// check quickedit nonces
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		check_ajax_referer( 'inlineeditnonce', '_inline_edit' );
	}
	
	// check capabilites
	$role = wp_get_current_user()->roles[0];
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	if ( !in_array( $role, $allowed ) )
		return $post_id;
			
	// save fields	
	// ignore autosaves
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;

	// for revisions, save using parent ID
	if ( wp_is_post_revision( $post_id ) ) $post_id = wp_is_post_revision( $post_id ); 
	
	if ( empty( $_POST['_content_audit_owner'] ) ) {
		$storedfield = get_post_meta( $post_id, '_content_audit_owner', true );
		delete_post_meta( $post_id, '_content_audit_owner', $storedfield );
	}
	elseif ( $_POST['_content_audit_owner'] >= 0 ) // don't save -1 
		update_post_meta( $post_id, '_content_audit_owner', $_POST['_content_audit_owner'] );
	
	if ( empty( $_POST['_content_audit_expiration_date'] ) ) {
		$storedfield = get_post_meta( $post_id, '_content_audit_expiration_date', true );
		delete_post_meta( $post_id, '_content_audit_expiration_date', $storedfield );
	}
	else {
		// convert displayed date string to timestamp for storage
		$date = strtotime( $_POST['_content_audit_expiration_date'] );
		update_post_meta( $post_id, '_content_audit_expiration_date', $date );
	}
	
	if ( empty( $_POST['_content_audit_notes'] ) ) {
		$storedfield = get_post_meta( $post_id, '_content_audit_notes', true );
		delete_post_meta( $post_id, '_content_audit_notes', $storedfield );
	}
	else 
		update_post_meta( $post_id, '_content_audit_notes', $_POST['_content_audit_notes'] );
	
}

function save_content_audit_media_meta( $post, $attachment ) {
	// in this filter, $post is an array of things being saved, not the usual $post object
		
	if ( isset( $attachment['_content_audit_owner'] ) ) 
		update_post_meta( $post['ID'], '_content_audit_owner', $attachment['_content_audit_owner'] );
	
	if ( isset( $attachment['audit_notes'] ) ) 
		update_post_meta( $post['ID'], '_content_audit_notes', $attachment['audit_notes'] );
	
	if ( isset( $attachment['_content_audit_expiration_date'] ) ) {
		// convert displayed date string to timestamp for storage
		$date = strtotime( $attachment['_content_audit_expiration_date'] );
		update_post_meta( $post['ID'], '_content_audit_expiration_date', $date );
	}
		
	return $post;
}

function content_audit_media_fields( $form_fields, $post ) {
	
	$notes = esc_textarea( get_post_meta( $post->ID, '_content_audit_notes', true ) );
	
	$owner = get_post_meta( $post->ID, '_content_audit_owner', true );
	if ( empty( $owner ) ) $owner = -1;
	
	$date = get_post_meta( $post->ID, '_content_audit_expiration_date', true );
	$date = strtotime( $date );
	
	add_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args', 10, 2 );
	$owner_dropdown = wp_dropdown_users( array( 
		'selected' => $owner, 
		'name' => "attachments[$post->ID][_content_audit_owner]", 
		'show_option_none' => __( 'Select a user', 'content-audit' ),
		'echo' => 0,
	 ) );
	remove_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args' );
	
	$form_fields['audit_owner'] = array( 
			'label' => __( 'Content Audit Owner', 'content-audit' ),
			'input' => 'select',
			'select' => $owner_dropdown,
		 );
		
	$form_fields['audit_notes'] = array( 
			'label' => __( 'Content Audit Notes', 'content-audit' ),
			'input' => 'textarea',
			'value' => $notes,
		 );
	
	$form_fields['audit_expiration'] = array( 
			'label' => __( 'Expiration Date', 'content-audit' ),
			'input' => 'text',
			'value' => $date,
			'class' => 'datepicker',
		 );
		
	return $form_fields;
	
}

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
			$wp_admin_bar->add_menu( array( 
				'parent' => 'content_audit',
				'id' => $term->slug,
				'title' => $term->name,
				'href' => $url,
			 ) );
		}
	
	
	if ( !empty( $current_object->post_type ) &&
		( $post_type_object = get_post_type_object( $current_object->post_type ) ) &&
		current_user_can( $post_type_object->cap->edit_post, $current_object->ID )
		 ) {
			$wp_admin_bar->add_menu( 
				array( 
					'parent' => 'content_audit',
					'id' => 'delete',
					'title' => __( 'Move to Trash', 'content-audit' ),
					'href' => add_query_arg( array( 'return' => admin_url( 'edit.php' ) ), get_delete_post_link( $current_object->term_id ) )
				 )
			 );
	}
	
	if ( is_home() || is_front_page() ) {
		$wp_admin_bar->add_menu( 
			array( 
				'parent' => 'content_audit',
				'id' => 'download',
				'title' => __( 'Download Audit Report', 'content-audit' ),
				'href' => add_query_arg( array ( 'format' => 'csv', 'post_type' => $current_object->post_type ), get_permalink( $current_object->ID ) )
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

	$set = wp_set_object_terms( $id, $term, $tax->name, true );
	
	if ( is_wp_error( $set ) )
	   echo $set->get_error_message();

	wp_redirect( esc_url( $_GET['return'] ) );
	
	exit;
}

// Bulk/Quick edit for custom fields

add_action( 'quick_edit_custom_box', 'content_audit_quickedit', 10, 2 );
add_action( 'bulk_edit_custom_box', 'content_audit_quickedit', 10, 2 );
function content_audit_quickedit( $column_name, $post_type ) {
    // if the column is not one of ours, quit
	if ( !in_array( $column_name, array( 'content_owner','content_notes','expiration' ) ) )
	    return;

	// if the user can't audit, quit
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
	if ( !in_array( $role, $allowed ) )
		return;
	
	// we're good to go	
	
	$post_id = get_the_ID();
		
	switch( $column_name ) {
	            case 'content_owner':
					$owner = get_post_meta( $post_id, '_content_audit_owner', true );
					if ( empty( $owner ) ) 
						$owner = -1;
	               ?>
					<fieldset class="inline-edit-col-left">
						<div class="inline-edit-col">
							<label class="alignleft">
								<span class="title"><?php _e( 'Content Owner' ); ?></span>
								<?php wp_nonce_field( 'content_audit_owner_nonce', '_content_audit_owner_nonce' ); ?>
								<?php
								add_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args', 10, 2 );
								wp_dropdown_users( array( 
									'selected' => $owner, 
									'name' => '_content_audit_owner', 
									'show_option_none' => __( 'Select a user','content-audit' ),
								 ) );	
								remove_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args' );	
								?>
							</label>
						</div>
					</fieldset>
					<?php
	            break;
				case 'content_notes':
					$notes = get_post_meta( $post_id, '_content_audit_notes', true );
					?>
					<fieldset class="inline-edit-col-right">
						<div class="inline-edit-col">
							<label class="alignleft">
								<span class="title"><?php _e( 'Content Audit Notes' ); ?></span>
								<?php wp_nonce_field( 'content_audit_notes_nonce', '_content_audit_notes_nonce' ); ?>
								<input name="_content_audit_notes" type="text" class="widefat"><?php echo esc_textarea( $notes ); ?></textarea>
							</label>
						</div>
					</fieldset>
					<?php
				break;
				case 'expiration': 
					$date = get_post_meta( $post_id, '_content_audit_expiration_date', true ); 
					// convert from timestamp to date string
					if ( !empty( $date ) )
						$date = date( 'm/d/y', $date );
					?>
					<fieldset class="inline-edit-col-right">
						<div class="inline-edit-col">
							<label class="alignleft">
								<span class="title"><?php _e( 'Content Audit Expiration' ); _e( ' ( m/d/y )' ) ?></span>
								<?php wp_nonce_field( 'content_audit_exp_date_nonce', 'content_audit_exp_date_nonce' ); ?>
								<input type="text" class="widefat datepicker" name="_content_audit_expiration_date" value="<?php esc_attr_e( $date ); ?>" />
							</label>
						</div>
					</fieldset>
					<?php
				break;
	}
}

// Save bulk/quick edit changes
add_action( 'wp_ajax_content_audit_save_bulk_edit', 'content_audit_save_bulk_edit' );
function content_audit_save_bulk_edit() {
	// get our variables
	$post_ids = ( isset( $_POST[ 'post_ids' ] ) && !empty( $_POST[ 'post_ids' ] ) ) ? $_POST[ 'post_ids' ] : array();
   
	$owner = ( isset( $_POST[ '_content_audit_owner' ] ) && !empty( $_POST[ '_content_audit_owner' ] ) ) ? $_POST[ '_content_audit_owner' ] : NULL;

	$expiration = ( isset( $_POST[ '_content_audit_expiration_date' ] ) && !empty( $_POST[ '_content_audit_expiration_date' ] ) ) ? $_POST[ '_content_audit_expiration_date' ] : NULL;

	$notes = ( isset( $_POST[ '_content_audit_notes' ] ) && !empty( $_POST[ '_content_audit_notes' ] ) ) ? $_POST[ '_content_audit_notes' ] : NULL;
   
	// if everything is in order
	if ( !empty( $post_ids ) && is_array( $post_ids ) && !empty( $owner ) ) {	
		foreach( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_content_audit_expiration_date', $expiration );
			update_post_meta( $post_id, '_content_audit_owner', $owner );
			update_post_meta( $post_id, '_content_audit_notes', $notes );
		}
	}
}