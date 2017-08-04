<?php

// Dashboard Widget
function content_audit_dashboard_widget() {
	$options = get_option( 'content_audit' );
	$alltables = '';
	foreach ( $options['post_types'] as $type ) {
		$table = '';		
		$oldposts = get_posts( apply_filters( 'content_audit_dashboard_get_posts_args', array(
				'posts_per_page' => 5,
				'post_type' => $type,
				'order'	=> 'ASC',
				'orderby' => 'modified',
				'content_audit' => 'outdated'
		)	)
		);
		$obj = get_post_type_object( $type );
		foreach ( $oldposts as $apost ) {
			$table .= sprintf( '<tr class="author-self"><td class="column-title"><a href="%s">%s</a></td>', get_permalink( $apost->ID ), $apost->post_title );
			$table .= sprintf( '<td class="column-date">%s</td></tr>', mysql2date( get_option( 'date_format' ), $apost->post_modified ) );
		}
		if ( !empty( $table ) ) {
			$alltables .= sprintf( '<table class="widefat fixed" id="content-audit-outdated">
					<thead>
						<tr>
						<th>%s</th>
						<th class="column-date">%s</th>
						</tr>
					</thead>
					<tbody>
					%s 
					<tr>
					<td class="column-title" colspan="2">
						<a href="edit.php?post_type=%s&content_audit=outdated">%s</a>
					</td>
					</tr>
					</tbody>
					</table>', 
					$obj->label, 
					__( 'Last Modified', 'content-audit' ), 
					$table,
					$type, 
					__( 'See all...', 'content-audit' ) 
			);
		}
	}
	$alltables = apply_filters( 'content_audit_dashboard_output', $alltables );
	if ( !empty( $alltables ) ) {
		echo $alltables;
	}
	else {
		$success = apply_filters( 'content_audit_dashboard_congrats', __( 'Congratulations! All your content is up to date.', 'content-audit' ) );
		printf( '<p>%s</p>', $success );
	}
	printf( '<p><a href="%s">%s</a></p>', 'index.php?page=content-audit', __( 'Content Audit Overview', 'content-audit' ) );
}

function content_audit_dashboard_widget_setup() {
    wp_add_dashboard_widget( 'dashboard_audit_widget_id', __( 'Outdated Content', 'content-audit' ), 'content_audit_dashboard_widget' );
}

add_action( 'wp_dashboard_setup', 'content_audit_dashboard_widget_setup' );