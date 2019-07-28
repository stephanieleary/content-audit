<?php

// Handle custom count columns on edit term list screens
add_filter( 'manage_edit-content_audit_columns', 'content_audit_term_count_columns' );

function content_audit_term_count_columns( $columns ) {
	if ( !isset( $_GET['post_type'] ) || !post_type_exists( $_GET['post_type'] ) )
		$post_type = 'post';
	else
		$post_type = $_GET['post_type'];
		
	$obj = get_post_type_object( $post_type );
	
    $columns = array( 
        'cb' => '<input type="checkbox" />',
        'name' => __( 'Name' ),
        'slug' => __( 'Slug' ),
		'audit_term_count_'.$post_type => $obj->labels->name,
        );
    return $columns;
}

add_action( 'manage_content_audit_custom_column',  'content_audit_column_contents', 10, 3  );

function content_audit_column_contents( $out, $column_name, $term_id ) {
	if ( !isset( $_GET['post_type'] ) || !post_type_exists( $_GET['post_type'] ) )
		$post_type = 'post';
	else
		$post_type = $_GET['post_type'];
	
	$term = get_term( $term_id, 'content_audit' );
	
    switch ( $column_name ) {
        case 'audit_term_count_'.$post_type:
            $count = get_option( '_audit_term_count_'.$post_type );
			isset( $count[$term_id] ) ? $num = ( int ) $count[$term_id] : $num = 0;
			$link = add_query_arg( array( 'post_type' => $post_type, 'content_audit' => $term->slug ) , admin_url( 'edit.php' ) );
	        $out .= sprintf( '<a href="%s">%d</a>', $link, $num );
            break;
 
        default:
            break;
    }
    return $out;   
}


// Handle custom columns on edit post list screens

add_action( 'admin_init', 'content_audit_column_setup' );

function content_audit_column_setup() {
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	if ( !is_array( $options['post_types'] ) )
		$options['post_types'] = array( $options['post_types'] );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );

	if ( in_array( $role, $allowed ) ) {
		foreach ( $options['post_types'] as $type ) {
			
			switch ( $type ) {
				case 'post': 
						add_filter( 'manage_posts_columns', 'content_audit_columns' );
						add_action( 'manage_posts_custom_column', 'content_audit_custom_column', 10, 2 );
						add_filter( 'manage_edit-post_sortable_columns', 'content_audit_register_sortable' );
					break;
				case 'page': 
						add_filter( 'manage_pages_columns', 'content_audit_columns' );
						add_action( 'manage_pages_custom_column', 'content_audit_custom_column', 10, 2 );
						add_filter( 'manage_edit-page_sortable_columns', 'content_audit_register_sortable' );
					break;
				case 'attachment': 
						add_filter( 'manage_media_columns', 'content_audit_columns' );
						add_action( 'manage_media_custom_column', 'content_audit_custom_column', 10, 2 );
						add_filter( 'manage_edit-media_sortable_columns', 'content_audit_register_sortable' );
					break;
				default:
				 	if ( post_type_exists( $type ) && in_array( $type, $options['post_types'] ) ) {
						add_filter( 'manage_'.$type.'_posts_columns', 'content_audit_columns' ); 
						add_filter( 'manage_edit-'.$type.'_sortable_columns', 'content_audit_register_sortable' );
						if ( is_post_type_hierarchical( $type ) == true )
							add_action( 'manage_pages_custom_column', 'content_audit_custom_column', 10, 2 );
						else
							add_action( 'manage_posts_custom_column', 'content_audit_custom_column', 10, 2 );
					}
			}

			// add filter dropdowns
			add_action( 'restrict_manage_posts', 'content_audit_restrict_content_authors' );
			add_action( 'restrict_manage_posts', 'content_audit_restrict_content_owners' );
			add_action( 'restrict_manage_posts', 'content_audit_restrict_content_status' );
				
			// modify edit screens' query when dropdown option is chosen
			add_filter( 'posts_where', 'content_audit_posts_where' );
		
			// Add author field to quick edit
//			add_action( 'quick_edit_custom_box', 'add_quickedit_content_owner' );
		}
	}	
}

// rearrange the columns on the Edit screens
function content_audit_columns( $defaults ) {
	// make sure we rearrange columns only on custom post types we're auditing, and only for users who can audit
	$role = wp_get_current_user()->roles[0];
	$options = get_option( 'content_audit' );
	if ( isset( $_REQUEST['post_type'] ) )
		$type = $_REQUEST['post_type'];
	else 
		$type = 'post';
	if ( !in_array( $type, $options['post_types'] ) || !in_array( $role, $options['rolenames'] ) )
		return $defaults;
	
	// preserve the original column headings and remove ( unset ) default columns
	if ( isset( $defaults['comments'] ) ) {
		$original['comments'] = $defaults['comments'];
		unset( $defaults['comments'] );
	}
	$original['date'] = $defaults['date'];
	unset( $defaults['date'] );
	$original['cb'] = $defaults['cb'];
	unset( $defaults['cb'] );
	if ( isset( $defaults['categories'] ) ) {
		$original['categories'] = $defaults['categories'];
		unset( $defaults['categories'] );
	}
	if ( isset( $defaults['tags'] ) ) {
		$original['tags'] = $defaults['tags'];
		unset( $defaults['tags'] );
	}
	// insert content owner and status taxonomy columns
	$defaults['content_owner'] = __( 'Content Owner', 'content-audit' );
    $defaults['content_status'] = __( 'Content Status', 'content-audit' );
	$defaults['content_notes'] = __( 'Notes', 'content-audit' );
	// restore default columns
	if ( isset( $original['categories'] ) ) $defaults['categories'] = $original['categories'];
	if ( isset( $original['tags'] ) ) $defaults['tags'] = $original['tags'];
	if ( isset( $original['comments'] ) ) $defaults['comments'] = $original['comments'];
	$defaults['date'] = $original['date'];
	// set expiration date column
	$defaults['expiration'] = __( 'Expiration', 'content-audit' );
	if ( isset( $original['analytics'] ) ) $defaults['analytics'] = $original['analytics'];
	// restore checkbox, add ID as the second column, then add the rest
    return array( 'cb' => $original['cb'], 'ID' => __( 'ID' ) ) + $defaults;
}

// print the contents of the new Content Audit columns
function content_audit_custom_column( $column_name, $id ) {
	if ( isset( $_REQUEST['post_type'] ) && !empty( $_REQUEST['post_type'] ) ) 
		$type = 'post_type='.$_REQUEST['post_type'].'&';
	else 
		$type = '';

	switch ( $column_name ) {
		case 'ID':
			echo $id;
		break;
		
		case 'content_owner': 
			$ownerID = absint( get_post_meta( $id, "_content_audit_owner", true ) );
			if ( !empty( $ownerID ) && $ownerID > 0 ) {
				$url = 'edit.php?'.$type.'content_owner='.$ownerID;
				echo '<a id="_content_audit_owner-'.$id.'" title="'.$ownerID.'" href="'.admin_url( $url ).'">'.get_the_author_meta( 'display_name', $ownerID ).'</a>';
			}
		break;
		
		case 'content_status':
			$termlist = array();
			$terms = wp_get_object_terms( $id, 'content_audit', array( 'fields' => 'all' ) );
			foreach ( $terms as $term ) {
				if ( !empty( $term->name ) ) {
					$url = 'edit.php?'.$type.'content_audit='.$term->slug;
					$termlist[] .= '<a href="'.admin_url( $url ).'">'.$term->name.'</a>';
				}
			}
			if ( !empty( $termlist ) ) 
				echo implode( ', ', $termlist );
		break;
		
		case 'content_notes': 
			echo '<p id="_content_audit_notes-'.$id.'">'.sanitize_text_field( get_post_meta( $id, "_content_audit_notes", true ) ).'</p>';
		break;
		
		case 'expiration':
			$date = sanitize_text_field( get_post_meta( $id, '_content_audit_expiration_date', true ) );
			$datecontent = $datetitle = '';
			// convert from timestamp to date string
			if ( !empty( $date ) ) {
				$datecontent = date( 'Y/m/d', $date );
				// different format for the datepicker
				$datetitle = esc_attr( date( 'n/j/Y', $date ) );
			}
			echo '<span id="_content_audit_expiration_date-'.$id.'" title="'.$datetitle.'">' . $datecontent .'</span>';
		break;
		
		default: break;
	}
}

// make expiration date column sortable
// filters are added for each post type in the init function
function content_audit_register_sortable( $columns ) {
	$columns['expiration'] = 'expiration';
	return $columns;
}

function content_audit_column_orderby( $vars ) {
	if ( isset( $vars['orderby'] ) && 'expiration' == $vars['orderby'] ) {
		$vars = array_merge( $vars, array( 
			'meta_key' => '_content_audit_expiration_date',
			'orderby' => 'meta_value_num'
		 ) );
	}
 
	return $vars;
}
add_filter( 'request', 'content_audit_column_orderby' );

// print the dropdown box to filter posts by content status
function content_audit_restrict_content_status() {
	$options = get_option( 'content_audit' );
	if ( isset( $_GET['content_audit'] ) ) 
		$content_status = $_GET['content_audit'];
	else 
		$content_status = ''; 
	if ( isset( $_REQUEST['post_type'] ) ) 
		$type = $_REQUEST['post_type'];
	else 
		$type = 'post';
	
	if ( in_array( $type, $options['post_types'] ) ) {
		?>
		<select name="content_audit" id="content_audit" class="postform">
		<option value="0"><?php _e( "Show all statuses", 'content-audit' ); ?></option>
	
		<?php
		$terms = get_terms( 'content_audit', '' );
		foreach ( $terms as $term ) { ?>
			<option value="<?php echo $term->slug; ?>" <?php selected( $term->slug, $content_status ) ?>><?php echo $term->name; ?></option>
<?php	}
		?>
		</select>
	<?php
	}
}

// print the dropdown box to filter posts by content owner
function content_audit_restrict_content_owners() {
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( isset( $_GET['content_owner'] ) ) 
		$owner = $_GET['content_owner'];
	else 
		$owner = '0'; 
	if ( isset( $_REQUEST['post_type'] ) ) 
		$type = $_REQUEST['post_type'];
	else 
		$type = 'post';
	
	if ( in_array( $type, $options['post_types'] ) ) {
		add_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args', 10, 2 );
		wp_dropdown_users( 
			array( 
				'show_option_all' => __( 'Show all owners', 'content-audit' ),
				'name' => 'content_owner',
				'selected' => isset( $_GET['content_owner'] ) ? $_GET['content_owner'] : 0,
			 )
		 );
		remove_filter( 'wp_dropdown_users_args', 'content_audit_dropdown_users_args' );
	}
}

// print a dropdown to filter posts by author
function content_audit_restrict_content_authors() {
	wp_dropdown_users( 
		array( 
			'show_option_all' => __( 'Show all authors', 'content-audit' ),
			'name' => 'author',
			'selected' => isset( $_GET['author'] ) ? $_GET['author'] : 0
		 )
	 );
}

// amend the db query based on content owner dropdown selection
function content_audit_posts_where( $where ) {
	global $wpdb;
	if ( isset( $_REQUEST['content_owner'] ) ) {
		$owner = absint( $_REQUEST['content_owner'] );
		if ( !empty( $owner ) ) {
			$where .= " AND ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_content_audit_owner' AND meta_value='{$_GET['content_owner']}' )";
		}	
	}
	return $where;
}