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
//      'posts' => __( 'Posts' ),
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
	global $current_user;
	get_currentuserinfo();
	$role = $current_user->roles[0];
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
	global $current_user;
	get_currentuserinfo();
	$role = $current_user->roles[0];
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
	if ( isset( $defaults['analytics'] ) ) {
		$original['analytics'] = $defaults['analytics'];
		unset( $defaults['analytics'] );
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
			$ownerID = get_post_meta( $id, "_content_audit_owner", true );
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
			echo '<p id="_content_audit_notes-'.$id.'">'.get_post_meta( $id, "_content_audit_notes", true ).'</p>';
		break;
		
		case 'expiration':
			$date = get_post_meta( $id, '_content_audit_expiration_date', true );
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
	if ( isset( $_GET['content_audit'] ) ) $content_status = $_GET['content_audit'];
	else $content_status = ''; 
	if ( isset( $_REQUEST['post_type'] ) ) $type = $_REQUEST['post_type'];
	else $type = 'post';
	
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
	global $user_ID;
	$options = get_option( 'content_audit' );
	if ( isset( $_GET['content_owner'] ) ) $owner = $_GET['content_owner'];
	else $owner = '0'; 
	if ( isset( $_REQUEST['post_type'] ) ) $type = $_REQUEST['post_type'];
	else $type = 'post';
	
	if ( in_array( $type, $options['post_types'] ) ) {
		wp_dropdown_users( 
			array( 
				'show_option_all' => __( 'Show all owners', 'content-audit' ),
				'name' => 'content_owner',
				'selected' => isset( $_GET['content_owner'] ) ? $_GET['content_owner'] : 0
			 )
		 );
	}
}

// print a dropdown to filter posts by author
function content_audit_restrict_content_authors()
{
	wp_dropdown_users( 
		array( 
			'who' => 'authors',
			'show_option_all' => __( 'Show all authors', 'content-audit' ),
			'name' => 'author',
			'selected' => isset( $_GET['author'] ) ? $_GET['author'] : 0
		 )
	 );
}

// amend the db query based on content owner dropdown selection
function content_audit_posts_where( $where )
{
	global $wpdb;
	if ( isset( $_GET['content_owner'] ) && !empty( $_GET['content_owner'] ) ) { 
		$where .= " AND ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_content_audit_owner' AND meta_value='{$_GET['content_owner']}' )";
	}	
	return $where;
}

// Outputs the new custom Quick Edit field HTML
function add_quickedit_content_owner( $column_name, $type ) { 
	if ( $column_name == 'content_owner' ) {
		global $post;
		$owner = get_post_meta( $post->ID, '_content_audit_owner', true );		
		?>
	<fieldset class="inline-edit-col-right">
	    <div class="inline-edit-col">
		<label class="inline-edit-status alignleft">
			<span class="title"><?php _e( "Content Owner", 'content-audit' ); ?></span>
			<?php
			
			wp_dropdown_users( 
				array( 
					'who' => 'authors',
					'show_option_all' => __( 'None', 'content-audit' ),
					'name' => '_content_audit_owner',
					'selected' => $owner
				 )
			 );			
			?>
			</label>
		</div>
	</fieldset>		
<?php }
}

// Prints the content status, notes, and owner on the front end
function content_audit_front_end_display( $content ) {
	global $current_user;
	get_currentuserinfo();
	$role = $current_user->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
		
	if ( !empty( $options['display_switch'] ) && in_array( $role, $allowed ) ) {
		$out = content_audit_notes( false );		
		if ( $options['display'] == 'above' ) return $out.$content;
		elseif ( $options['display'] == 'below' ) return $content.$out;
		else return $content;
	}
	else return $content;
}

add_filter( 'the_content', 'content_audit_front_end_display' );

// template tag: content_audit_notes( $echo );
function content_audit_notes( $echo = true ) {
	global $post;
	$out = '<p class="content-status">'.get_the_term_list( $post->ID, 'content_audit', __( 'Content status: ', 'content-audit' ), ', ','' ).'</p>';
	$ownerID = get_post_meta( $post->ID, "_content_audit_owner", true );
	if ( !empty( $ownerID ) ) {
		$out .= '<p class="content-owner">'.__( "Assigned to: ", 'content-audit' ).get_the_author_meta( 'display_name', $ownerID ).'</p>';
	}
	$out .= '<p class="content-notes">'.get_post_meta( $post->ID, "_content_audit_notes", true ).'</p>';
	$out = '<div class="content-audit">'.$out.'</div>';
	if ( $echo ) echo $out;
	else return $out;	
}

// Prints the CSS for the front end
function content_audit_front_end_css() {
	global $current_user;
	get_currentuserinfo();
	$role = $current_user->roles[0];
	$options = get_option( 'content_audit' );
	$allowed = $options['rolenames'];
	if ( !is_array( $allowed ) )
		$allowed = array( $allowed );
		
	if ( !empty( $options['display'] ) && in_array( $role, $allowed ) ) {	
		echo '<style type="text/css">'.$options['css'].'</style>';
	}
}
add_action( 'wp_head', 'content_audit_front_end_css' );

// Dashboard Widget
function content_audit_dashboard_widget() {
	$options = get_option( 'content_audit' );
	$alltables = '';
	foreach ( $options['post_types'] as $type ) {
		$table = '';		
		$oldposts = get_posts( 'numberposts=5&post_type='.$type.'&content_audit=outdated&order=ASC&orderby=modified' );
		$obj = get_post_type_object( $type );
		foreach ( $oldposts as $apost ) {
			$table .= '<tr class="author-self"><td class="column-title"><a href="'.get_permalink( $apost->ID ).'">'.$apost->post_title.'</a></td>';
			$table .= '<td class="column-date">'. mysql2date( get_option( 'date_format' ), $apost->post_modified ).'</td></tr>';
		}
		if ( !empty( $table ) ) {
			$table = '<table class="widefat fixed" id="content-audit-outdated"><thead><tr><th>'.$obj->label.'</th><th  class="column-date">'.__( 'Last Modified', 'content-audit' ).'</th></tr></thead><tbody>' . $table;
			$table .= '<tr><td class="column-title" colspan="2"><a href="edit.php?post_type='.$type.'&content_audit=outdated">'.__( 'See all...', 'content-audit' ).'</a></td></tr></tbody></table>';
			$alltables .= $table;
		}
	}
	if ( !empty( $alltables ) ) echo $alltables;
	else echo '<p>'. __( 'Congratulations! All your content is up to date.', 'content-audit' ).'</p>';
	echo '<p>'. sprintf( __( '<a href="%s">Content Audit Overview</a>', 'content-audit' ), 'index.php?page=content-audit' ).'</p>';
}

function content_audit_dashboard_widget_setup() {
    wp_add_dashboard_widget( 'dashboard_audit_widget_id', __( 'Outdated Content', 'content-audit' ), 'content_audit_dashboard_widget' );
}

add_action( 'wp_dashboard_setup', 'content_audit_dashboard_widget_setup' );