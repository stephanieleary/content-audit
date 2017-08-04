<?php
function content_audit_sanitize_options( $input ) {
	
	$options = array();
	
	// valid post types only, please
	foreach ( $input['post_types'] as $post_type ) {
		if ( post_type_exists( $post_type ) )
			$options['post_types'][] = $post_type;
	}
	
	// valid roles only, please
	foreach ( $input['rolenames'] as $role ) {
		if ( get_role( $role ) )
			$options['rolenames'][] = $role;
	}
	
	// do not save injected options
	if ( in_array( $input['interval'], array( 'daily', 'weekly', 'monthly' ) ) )
		$options['interval'] = $input['interval'];
		
	if ( in_array( $input['outdate_unit'], array( 'days', 'weeks', 'months', 'years' ) ) )
		$options['outdate_unit'] = $input['outdate_unit'];
		
	if ( in_array( $input['display'], array( 'above', 'below' ) ) )
		$options['display'] = $input['display'];
	
	// these should all be zero or one
	$options['display_switch'] = absint( $input['display_switch'] );
	if ( $options['display_switch'] > 1 ) $options['display_switch'] = 0;

	$options['mark_outdated'] = absint( $input['mark_outdated'] );
	if ( $options['mark_outdated'] > 1 ) $options['mark_outdated'] = 0;

	$options['notify'] = absint( $input['notify'] );
	if ( $options['notify'] > 1 ) $options['notify'] = 0;

	$options['notify_now'] = absint( $input['notify_now'] );
	if ( $options['notify_now'] > 1 ) $options['notify_now'] = 0;

	$options['notify_authors'] = absint( $input['notify_authors'] );
	if ( $options['notify_authors'] > 1 ) $options['notify_authors'] = 0;
	
	// this can be any integer
	$options['outdate'] = absint( $input['outdate'] );
	
	// sanitize css
	$options['css'] = wp_filter_nohtml_kses( $input['css'] );
	
	/*
	// testing
	var_dump( $input );
	var_dump( $options ); exit;
	/**/
	
	return $options;
}

// displays the options page content
function content_audit_options() {
	// clear and redo the schedules now in case they changed
	content_audit_cron_deactivate();
	content_audit_cron_activate();
	?>	
    <div class="wrap">
	
	<?php
	// nuclear option, step 1: ask if we really want to delete
	if ( isset( $_GET['content_audit_nonce'] ) && wp_verify_nonce( $_GET['content_audit_nonce'], 'erase_audit' ) ) :
		
		?>
		<h2><?php _e( 'Erase Content Audit', 'content-audit' ); ?></h2>
		<p><?php _e( 'Are you sure? This option will remove the following from every post/page:', 'content-audit' ); ?></p>
		<ol>
			<li><?php _e( 'Content audit attributes', 'content-audit' ); ?></li>
			<li><?php _e( 'Audit notes', 'content-audit' ); ?></li>
			<li><?php _e( 'Assigned content owners', 'content-audit' ); ?></li>
		</ol>
		<p><?php printf( __( 'Your <a href="%s">audit attributes</a> will be preserved so you can reuse them.', 'content-audit' ), 
		 			add_query_arg( array( 'taxonomy' => 'content_audit' ), admin_url( 'edit-tags.php' ) ) ); ?></p>
		
		<p class="nuclear-option">
		<?php
			$erase_link = add_query_arg( array( 'page' => 'content-audit' ), wp_nonce_url( admin_url( 'options-general.php' ), 'erase_audit_confirm', 'content_audit_nonce' ) );
			$options_link = add_query_arg( array( 'page' => 'content-audit' ), admin_url( 'options-general.php' ) );
			printf( '<a href="%s" class="button-nuclear">%s</a>', $erase_link, __( 'Yes, erase everything' ) );
			printf( '<a href="%s" class="button">%s</a>', $options_link , __( 'No, go back to Content Audit options' ) );
		?>
		</p>
		
	<?php  // nuclear option, step 2: actually delete things
	elseif ( isset( $_GET['content_audit_nonce'] ) && wp_verify_nonce( $_GET['content_audit_nonce'], 'erase_audit_confirm' ) ) : 
		
		// remove all Content Audit custom fields
		global $wpdb;
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_content_audit_owner' ), 			 array( '%s' ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_content_audit_expiration_date' ), array( '%s' ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_content_audit_notes' ), 			 array( '%s' ) );
		
		// remove Content Audit taxonomy terms
		$terms = get_terms( array('content_audit'), array( 'fields' => 'ids', 'hide_empty' => false ) );
		$audited_posts = get_objects_in_term( $terms, 'content_audit' );
		foreach( $audited_posts as $audited_post ) {
			wp_set_object_terms( $audited_post, NULL, 'content_audit' );
		}
		
		// remove option
		delete_option( 'content_audit' );
				
		?>
		<h2><?php _e( 'Content Audit Data Erased', 'content-audit' ); ?></h2>
		<p><?php _e( 'The following have been deleted:', 'content-audit' ); ?></p>
		<ol>
			<li><?php _e( 'Content audit attributes', 'content-audit' ); ?></li>
			<li><?php _e( 'Audit notes', 'content-audit' ); ?></li>
			<li><?php _e( 'Assigned content owners', 'content-audit' ); ?></li>
		</ol>
		<p><?php _e( 'You may begin your new content audit!', 'content-audit' ); ?></p>
		<p>
		<?php
			$options_link = add_query_arg( array( 'page' => 'content-audit' ), admin_url( 'options-general.php' ) );
			printf( '<a href="%s" class="button-primary">%s</a>', $options_link , __( 'Return to Content Audit options' ) );
			$terms_link = add_query_arg( array( 'taxonomy' => 'content_audit' ), admin_url( 'edit-tags.php' ) );
			printf( '<a href="%s" class="button">%s</a>', $options_link , __( 'Edit Content Audit attributes' ) );
		?>
		</p>
		<?php
		
	else : // regular old options form ?>
	
	<form method="post" id="content_audit_form" action="options.php">
		<?php settings_fields( 'content_audit' ); 
		$options = get_option( 'content_audit' );
		if ( empty( $options ) )
			$options = content_audit_default_options();
		// testing
		// var_dump( $options ); 
		
		// convert from old options
		if ( isset( $options['types'] ) && !isset( $options['post_types'] ) ) { 
			$options['post_types'] = array();
			foreach( $options['types'] as $type => $val ) {
				array_push( $options['post_types'], $type );
			}
			unset( $options['types'] );
		}
		if ( !is_array( $options['post_types'] ) )
			$options['post_types'] = array( $options['post_types'] );
		
		if ( isset( $options['roles'] ) && !isset( $options['rolenames'] ) ) {
			$options['rolenames'] = $options['roles'];
			unset( $options['roles'] );
		}
		
		if ( !isset( $options['display'] ) )
			$options['display'] = 0;
		
		global $wp_roles;
		?>

    <h2><?php _e( 'Content Audit Options', 'content-audit' ); ?></h2>
    
    <table class="form-table">
	    <tr>
	    <th scope="row"><?php _e( "Audited content types", 'content-audit' ); ?></th>
		    <td>
			    <ul id="content_audit_types">
			    <?php
			    $content_types = get_post_types( '', 'objects' );
			    $ignored = array( 'revision', 'nav_menu_item', 'deprecated_log' );
			    foreach ( $content_types as $content_type ) {
			    	if ( !in_array( $content_type->name, $ignored ) ) { ?>
			    		<li>
			    		<label>
			    		<input type="checkbox" name="content_audit[post_types][]" value="<?php echo $content_type->name; ?>" 
					<?php if ( in_array( $content_type->name, $options['post_types'] ) ) echo 'checked="checked"'; ?> />
			    		<?php echo $content_type->label; ?></label>
			    		</li>
			    	<?php }
			    }
			    ?>
			    </ul>
		    </td>
	    </tr>

	    <tr>
	    <th scope="row"><?php _e( "Users allowed to audit", 'content-audit' ); ?></th>
		    <td><ul id="content_audit_roles">
			<?php
			foreach ( $wp_roles->roles as $role ) { ?>
				<li><input type="checkbox" name="content_audit[rolenames][]" value="<?php echo strtolower( $role['name'] ); ?>" 
					<?php
					// check the box if this role is included in the new option
					if ( isset( $options['rolenames'] ) && in_array( strtolower( $role['name'] ), $options['rolenames'] ) )
							echo ' checked="checked"';
					?> /> <?php echo $role['name']; ?></li>
			<?php }
			?>
			</ul>
		    </td>
	    </tr>

		  	
		<tr>
	    <th scope="row"><?php _e( "Outdated content", 'content-audit' ); ?></th>
		    <td>
			   
				<input type="checkbox" name="content_audit[mark_outdated]" value="1" <?php if ( isset( $options['mark_outdated'] ) ) checked( '1', $options['mark_outdated'] ); ?> />
				<label><?php _e( "Automatically mark content as outdated if it has not been modified in", 'content-audit' ); ?></label> 
				<input type="text" name="content_audit[outdate]" size="3" value="<?php echo esc_attr( $options['outdate'] ); ?>" />
			    <select name="content_audit[outdate_unit]">
			    	<option value="days" <?php selected( 'days', $options['outdate_unit'] ); ?>><?php _e( "days", 'content-audit' ); ?></option>
			    	<option value="weeks" <?php selected( 'weeks', $options['outdate_unit'] ); ?>><?php _e( "weeks", 'content-audit' ); ?></option>
			    	<option value="months" <?php selected( 'months', $options['outdate_unit'] ); ?>><?php _e( "months", 'content-audit' ); ?></option>
			    	<option value="years" <?php selected( 'years', $options['outdate_unit'] ); ?>><?php _e( "years", 'content-audit' ); ?></option>
			    </select>
		
			    </td>
		    </tr>


			<tr>
		    <th scope="row"><?php _e( "Email notifications", 'content-audit' ); ?></th>
			    <td>
					
				<label>
	    		<input type="checkbox" name="content_audit[notify]" value="1" <?php if ( isset( $options['notify'] ) ) checked( '1', $options['notify'] ); ?> />
	    		<?php _e( "Notify content owners of outdated content", 'content-audit' ); ?> </label>
				<label class="hidden"><?php _e( "How often?", 'content-audit' ); ?> </label>
			    <select name="content_audit[interval]">
			    	<option value="daily" <?php selected( 'daily', $options['interval'] ); ?>><?php _e( "once a day", 'content-audit' ); ?></option>
			    	<option value="weekly" <?php selected( 'weekly', $options['interval'] ); ?>><?php _e( "once a week", 'content-audit' ); ?></option>
			    	<option value="monthly" <?php selected( 'monthly', $options['interval'] ); ?>><?php _e( "once a month", 'content-audit' ); ?></option>
			    </select>
				<br />
				<label class="indent">
	    		<input type="checkbox" name="content_audit[notify_now]" value="1" <?php if ( isset( $options['notify_now'] ) ) checked( '1', $options['notify_now'] ); ?> />
	    		<?php _e( "Send notifications now", 'content-audit' ); ?></label>
				<br />
				<label class="indent">
	    		<input type="checkbox" name="content_audit[notify_authors]" value="1" <?php if ( isset( $options['notify_authors'] ) ) checked( '1', $options['notify_authors'] ); ?> />
	    		<?php _e( "Notify original author if no owner is selected", 'content-audit' ); ?></label>
			 </td>
	    </tr>
	
		<tr>
	    <th scope="row"><?php _e( "Front end display" ); ?></th>
		    <td>
				<?php if ( $options['display'] == '0' ) $options['display_switch'] = '0'; // handling option from previous version ?>
				<label>
	    		<input type="checkbox" name="content_audit[display_switch]" value="1" <?php if ( isset( $options['display_switch'] ) ) checked( '1', $options['display_switch'] ); ?> />
	    		<?php _e( 'Display content status, notes, and owner to logged-in auditors ', 'content-audit' ); ?></label> 
				
				<label class="hidden"><?php _e( "Where?", 'content-audit' ); ?> </label>
				<select name="content_audit[display]">
					<option value="above" <?php selected( 'above', $options['display'] ); ?>><?php _e( "above content" ); ?></option>
					<option value="below" <?php selected( 'below', $options['display'] ); ?>><?php _e( "below content" ); ?></option>
				</select>
				<?php _e( "." ); ?>
		    </td>
	    </tr>
		<tr>
			<th scope="row"><?php _e( "CSS for front end display", 'content-audit' ); ?></th>
		    <td>
				<textarea name="content_audit[css]"><?php echo $options['css']; ?></textarea>
		    </td>
	    </tr>

	
		<tr>
	    <th scope="row"><?php _e( 'Content attributes', 'content-audit' ); ?></th>
		    <td>
			    <a href="edit-tags.php?taxonomy=content_audit"><?php _e( 'Edit content audit attributes', 'content-audit' ); ?></a>
		    </td>
	    </tr>
    </table>
    
	<p class="submit">
	<input type="submit" name="submit" class="button-primary" value="<?php _e( 'Update Options', 'content-audit' ); ?>" />
	</p>
	
	<p class="nuclear-option">
		<a class="button" href="<?php echo add_query_arg( array( 'page' => 'content-audit' ), wp_nonce_url( admin_url( 'options-general.php' ), 'erase_audit', 'content_audit_nonce' ) ) ?>">
			<?php _e('Clear old audits and start over', 'content-audit'); ?></a>
	</p>
	</form>
	
	<?php endif; ?>
	
	</div>
<?php 
} // end function content_audit_options() 