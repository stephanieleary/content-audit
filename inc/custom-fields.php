<?php

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
	$notes = sanitize_text_field( get_post_meta( get_the_ID(), '_content_audit_notes', true ) );
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
	$options = get_option( 'content_audit' );
	$owner = absint( get_post_meta( get_the_ID(), '_content_audit_owner', true ) );
	if ( empty( $owner ) ) $owner = -1;
	if ( in_array( $role, $allowed ) ) {
		//add_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args', 10, 2 );
		wp_dropdown_users( array( 
			'selected' => $owner, 
			'name' => '_content_audit_owner', 
			'show_option_none' => __( 'Select a user','content-audit' ),
			'role__in' => $options['rolenames']
		 ) );
		//remove_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args' );
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
	$date = sanitize_text_field( get_post_meta( get_the_ID(), '_content_audit_expiration_date', true ) ); 
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
	
	if ( empty( $_POST['_content_audit_owner'] ) || $_POST['_content_audit_owner'] == '-1' ) {
		$storedfield = get_post_meta( $post_id, '_content_audit_owner', true );
		delete_post_meta( $post_id, '_content_audit_owner', $storedfield );
	}
	if ( $_POST['_content_audit_owner'] >= 0 ) // don't save -1 
		update_post_meta( $post_id, '_content_audit_owner', absint( $_POST['_content_audit_owner'] ) );
	
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
		update_post_meta( $post_id, '_content_audit_notes', sanitize_text_field( $_POST['_content_audit_notes'] ) );
	
}

function save_content_audit_media_meta( $post, $attachment ) {
	// in this filter, $post is an array of things being saved, not the usual $post object
		
	if ( isset( $attachment['_content_audit_owner'] ) && $attachment['_content_audit_owner'] > 0 ) 
		update_post_meta( $post['ID'], '_content_audit_owner', absint( $attachment['_content_audit_owner'] ) );
	else
		delete_post_meta( $post_id, '_content_audit_owner' );
	
	if ( isset( $attachment['audit_notes'] ) ) 
		update_post_meta( $post['ID'], '_content_audit_notes', sanitize_text_field( $attachment['audit_notes'] ) );
	
	if ( empty( $attachment['_content_audit_expiration_date'] ) )
		delete_post_meta( $post_id, '_content_audit_expiration_date' );
	elseif ( isset( $attachment['_content_audit_expiration_date'] ) ) {
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