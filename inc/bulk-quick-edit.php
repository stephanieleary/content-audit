<?php

// Outputs the new custom Quick Edit field HTML
function add_quickedit_content_owner( $column_name, $type ) { 
	$options = get_option( 'content_audit' );
	if ( $column_name == 'content_owner' ) { ?>
	<fieldset class="inline-edit-col-right">
	    <div class="inline-edit-col">
		<label class="inline-edit-status alignleft">
			<span class="title"><?php _e( "Content Owner", 'content-audit' ); ?></span>
			<?php
			add_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args', 10, 2 );
			wp_dropdown_users( 
				array( 
					'show_option_all' => __( 'None', 'content-audit' ),
					'name' => '_content_audit_owner',
					'selected' => get_post_meta( get_the_ID(), '_content_audit_owner', true ),
				 )
			 );	
			remove_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args' );		
			?>
			</label>
		</div>
	</fieldset>		
<?php }
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