<?php
// displays the overview page content
function content_audit_overview() { ?>
	<div class="wrap">
	<?php 
	$options = get_option( 'content_audit' );
	$printsquares = '';
	$types = $editors = $tables = array();
	// get post types we're auditing
	$cpts = get_post_types( array( 'public' => true ), 'objects' );
	foreach ( $cpts as $cpt ) {
		if ( in_array( $cpt->name, $options['post_types'] ) )
			$types[$cpt->name] = $cpt->label;
	}
	
	$roles = $options['rolenames'];	
	foreach ( $roles as $role ) :
        $users_query = new WP_User_Query( array( 
            'fields' => 'all_with_meta', 
            'role' => $role, 
            'orderby' => 'display_name'
            ) );
        $results = $users_query->get_results();
        if ( $results ) 
			$editors = array_merge( $editors, $results );
    endforeach;
	//var_dump( $editors );
	?>

    <h2><?php _e( 'Content Audit Overview', 'content-audit' ); ?></h2>
	<p><a class="button secondary" href="<?php echo add_query_arg( array( 'format' => 'csv' ), home_url() ); ?>"><?php __('Download as CSV', 'content-audit'); ?></a></p>
	<?php
	// for each term in the audit taxonomy, print a box with a big number for the count
	$terms = get_terms( 'content_audit', array( 'hide_empty' => 0 ) );
	$count = count( $terms );
	if ( $count > 0 ){
		// doing some math to space the boxes out evenly...
		$squares = $count + 1; 
		$width = 100 / $squares;
		$margin = 100 / ( $count * $squares );
		$i = 0;
	    
	    foreach ( $terms as $term ) {
			$i++;
			if ( $i == $count ) $margin = 0;
	    	$printsquares .= '<li style="width: '.$width.'%; margin-right: '.$margin.'%;"><a href="'.admin_url( 'index.php?page=content-audit#'.$term->slug ).'"><h3>' . $term->count . '</h3><p>' . $term->name . '</p></a></li>';
	
			if ( $term->count > 0 ) {
				// then print a table where each row contains an owner/author, 
				// with a column for each content type containing the count for each of their assigned items
				$tables[$term->slug] = '<h3 id="'.$term->slug.'">'. $term->name .'</h3>';
				$tables[$term->slug] .= '<table class="wp-list-table widefat fixed boss-view" cellspacing="0">';
				$tables[$term->slug] .= "<thead> \n <tr> \n <th>". __( "Content Owner", 'content-audit' ). '</th>';
				foreach ( $types as $label ) { 
					$tables[$term->slug] .= '<th>'. $label .'</th>';
				}
				$tables[$term->slug] .= "\n </tr> \n </thead> \n <tbody> \n";
				
				foreach ( $editors as $editor ) {
					$userinfo = get_userdata( $editor->ID );
					
					$tables[$term->slug] .= '<tr><td>'. $userinfo->display_name .'</td>';
				
					foreach ( $types as $type => $label ) { 
						if ( $type == 'attachment' )
							$url = admin_url( 'upload.php' );
						else
							$url = admin_url( 'edit.php?post_type='.$type );
						$posts_with_owner = get_content_audit_posts( $term->slug, $type, 'publish', '_content_audit_owner', $editor->ID );
						$posts_with_author = get_content_audit_posts( $term->slug, $type, 'publish', 'author', $editor->ID );
						$num = count( array_merge( $posts_with_owner, $posts_with_author ) );
						$url = add_query_arg( array( '_content_audit_owner' => $editor->ID, 'content_audit' => $term->slug ), $url );

						$tables[$term->slug] .= '<td><a href="'. $url .'">'. $num .'</a></td>';
					} // foreach type
					$tables[$term->slug] .= '</tr>';
				}  // foreach post
				$tables[$term->slug] .= '</tbody></table>';
			} // if $term->count > 0
	    }  // foreach term
	
		echo '<ul id="boss-squares">'.$printsquares.'</ul>';
		if ( !empty( $tables ) )
			echo implode( '', $tables );			
	} // if $count > 0
	
	echo '</div> <!-- .wrap -->'; 
}

function get_content_audit_posts( $term, $types = 'page', $status = 'publish', $key = '', $val = '' ) {
	$args = array( 
		'post_type' => $types,
		'post_status' => $status,
		'tax_query' => array( 
			array( 
				'taxonomy' => 'content_audit',
				'field' => 'slug',
				'terms' => $term
			 )
		 ),
	 );
	if ( !empty( $key ) ) {
		if ( $key == 'author' ) {
			$args['author'] = $val;
		}
		else {
			$args['meta_key'] = $key;
			$args['meta_value'] = $val;
		}
	}
	return get_posts( $args );
}

function get_content_audit_meta_values( $key = '', $types = 'page', $status = 'publish' ) {
    global $wpdb;
	$query = $wpdb->prepare( "
        SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '%s' 
        AND p.post_status = '%s' 
        AND p.post_type IN ( '@FOO@' )
		AND pm.meta_value > 0
    ", $key, $status );
	// can't let prepare() handle this because it escapes the single quotes in between each post type, ARGH
	$query = str_replace( '@FOO@', $types, $query );
    $r = $wpdb->get_results( $query ); 
	return $r;
}

// Export to CSV

add_filter( 'template_include', 'content_audit_download_template_include' );
function content_audit_download_template_include( $template ) {
	if ( !isset( $_REQUEST['format'] ) || 'csv' !== $_REQUEST['format'] )
		return $template;
	
	global $wpdb;

	$tableheaders = array( 
		__( 'ID', 'content-audit' ), 
		__( 'Title', 'content-audit' ), 
		__( 'Author', 'content-audit' ), 
		__( 'Content Owner', 'content-audit' ), 
		__( 'Status', 'content-audit' ), 
		__( 'Notes', 'content-audit' ), 
		__( 'Type', 'content-audit' ), 
		__( 'Created', 'content-audit' ), 
		__( 'Updated', 'content-audit' ), 
		__( 'Expires', 'content-audit' ) 
	);
	$date_format = get_option( 'date_format' );
	$options = get_option( 'content_audit' ); 
	$types = $options['post_types'];
	
	$fileName = apply_filters( 'content_audit_csv_filename', 'content-audit-'.date( 'Ymdu' ).'.csv' );

	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header('Content-Description: File Transfer');
	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename={$fileName}");
	header("Expires: 0");
	header("Pragma: public");

	$file = @fopen( 'php://output', 'w' );
	
	
	fputcsv( $file, apply_filters( 'content_audit_csv_header_data', $tableheaders ) );
	
	$results = get_posts( array( 
		'posts_per_page' => -1,
		'post_type' => $types,
		'post_status' => 'publish,inherit',
		'orderby' => 'menu_order',
		'order' => 'ASC'
	 ) );

	foreach ( $results as $post ) {
		$term_list = '';
		$terms = get_the_terms( $post->ID, 'content_audit' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$term_links = array();
			foreach ( $terms as $term )
				$term_links[] = $term->name;
			$term_list = implode( ', ', $term_links );
		}
		
		$author = get_userdata( $post->post_author );
		$owner = get_post_meta( $post->ID, '_content_audit_owner', true );
		if ( !empty( $owner ) ) {
			$owner = get_userdata( $owner );
			$owner = $owner->display_name;
		}
		$expiration = get_post_meta( $post->ID, '_content_audit_expiration_date', true );
	
		$row = array( 
			$post->ID ,
			$post->post_title,
			$author->display_name,
			$owner,
			$term_list,
			get_post_meta( $post->ID, '_content_audit_notes', true ) ,
			$post->post_type ,
			get_the_date( $date_format ) ,
			the_modified_date( $date_format, '', '', false ) ,
			$expiration,
		);
		fputcsv( $file, apply_filters( 'content_audit_csv_row_data', $row ) );
	}
	fclose( $file );
	exit; 
}