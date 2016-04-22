<?php

// set up scheduled jobs
register_activation_hook( dirname ( __FILE__ )."/content-audit.php", 'content_audit_cron_activate' );
register_deactivation_hook( dirname ( __FILE__ )."/content-audit.php", 'content_audit_cron_deactivate' );

// add custom time to cron
function content_audit_cron_schedules( $param ) {
	return array( 'weekly' => array( 
								'interval' => 60*60*24*7, 
								'display'  => __( 'Once a Week', 'content-audit' )
							 ) ,
				  'monthly' => array( 
								'interval' => 60*60*24*7*4, 
								'display'  => __( 'Once a Month', 'content-audit' )
							 ) 
				 );
}
add_filter( 'cron_schedules', 'content_audit_cron_schedules' );

add_action( 'content_audit_outdated_report', 'content_audit_mark_outdated' );
add_action( 'content_audit_outdated_email', 'content_audit_notify_owners' );

function content_audit_cron_activate() {
	$options = get_option( 'content_audit' );
	if ( !wp_next_scheduled( 'content_audit_outdated_report' ) ) {
		wp_schedule_event( time(), 'daily', 'content_audit_outdated_report' );
	}
	
	// prevent this from happening immediately
	switch ( $options['interval'] ) {
		case 'daily': $start = strtotime( '+1 day' ); break;
		case 'weekly': $start = strtotime( '+1 week' ); break;
		default: $start = strtotime( '+1 month' );
	}
	
	if ( !wp_next_scheduled( 'content_audit_outdated_email' ) ) {
		wp_schedule_event( $start, $options['interval'], 'content_audit_outdated_email' );
		if ( isset( $options['notify_now'] ) && $options['notify_now'] )
			wp_schedule_event( time(), $options['interval'], 'content_audit_outdated_email' );
	}
}

function content_audit_cron_deactivate() {
	wp_clear_scheduled_hook( 'content_audit_outdated_report' );
	wp_clear_scheduled_hook( 'content_audit_outdated_email' );
}

function content_audit_mark_outdated() {
	$options = get_option( 'content_audit' );
	// handle auto-outdated content
	if ( $options['mark_outdated'] ) {
		$oldposts = content_audit_get_outdated();
		if ( !empty( $oldposts ) ) {
			foreach ( $oldposts as $oldpost ) {
				wp_set_object_terms( $oldpost->ID, 'outdated', 'content_audit', true );
			}
		} 
	} 
	
	// handle manually set expiration dates
	$date = time();
	$expired = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => -1, 
		'meta_key' => '_content_audit_expiration_date', 'meta_value' => $date, 'meta_compare' => '<=' ) );
	if ( !empty( $expired ) ) {
		foreach ( $expired as $oldpost ) {
			wp_set_object_terms( $oldpost->ID, 'outdated', 'content_audit', true );
		}
	}
} 

function content_audit_notify_owners() {
	$options = get_option( 'content_audit' );
	if ( $options['notify'] ) {	
		$userposts = array();
		$from = get_option( 'admin_email' );
		// get all types we're auditing
		foreach ( $options['post_types'] as $type ) {
			// get all outdated posts of this type ( published status only )
			$oldposts = get_posts( 'numberposts=-1&post_type='.$type.'&content_audit=outdated&order=ASC&orderby=modified' );
			foreach ( $oldposts as $apost ) {
				// 	if it has a content owner, assign to owner's ID
				$owner = get_post_meta( $apost->ID, "_content_audit_owner", true );
				//	otherwise, if we're notifying authors, add to author's ID
				if ( empty( $owner ) && ( $options['notify_authors'] ) ) {
					$owner = $apost->post_author;
				}
				// store the list of posts by owner, then by type
				if ( $owner > 0 )
					$userposts[$owner][$type][$apost->ID] = '<li><a href="' . get_permalink( $apost->ID ) . '">' . $apost->post_title . '</a></li>';
			}
		}
		// update_option( 'content-audit-status', $userposts );  // debug

		// now send the emails
		$headers = "MIME-Version: 1.0\n"
			. 'From: '.$from. "\r\n" 
			. 'sendmail_from: '.$from. "\r\n" 
			. "Content-Type: text/html; charset=\"" . get_option( 'blog_charset' ) . "\"\n";

		foreach ( $userposts as $owner => $theirposts ) {
			$postlist = '';
			foreach ( $theirposts as $type => $postdata ) {
				$obj = get_post_type_object( $type );
				$postlist .= '<p>'.$obj->label.'</p><ul>';
				$postlist .= implode( $postdata, "\n" );
				$postlist .= '</ul>';
			}
			$userinfo = get_userdata( $owner );
			$subject = __( 'Outdated content report:', 'content-audit' ) .' '. get_bloginfo( 'name' );
			$message = '<p>'.__( 'The following articles are outdated. Please review them:', 'content-audit' ).'</p>'.$postlist.
					   '<p>'.__( 'If the article does not need to be updated, just uncheck the "Outdated" box and press the "Update" button.', 'content-audit' ) .'</p>';
			wp_mail( $userinfo->user_email, $subject, $message, $headers );
		}
	} // if ( $options['notify'] )	
}


function content_audit_get_outdated() {
	global $wpdb;
	$options = get_option( 'content_audit' );
	
	if ( empty( $options['post_types'] ) ) 
		return false;

	$safe_posttypes = array();
	foreach( $options['post_types'] as $type )
		$safe_posttypes[] = $wpdb->prepare( '%s', $type );
	$types = implode( ',', $safe_posttypes );
	$longago = date( 'Y-m-d', strtotime( '-'.$options['outdate'].' '.$options['outdate_unit'] ) );
	$oldposts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_author, post_type, post_modified 
			FROM $wpdb->posts WHERE post_type IN ( {$types} ) AND post_modified <= %s
			ORDER BY post_type, post_modified ASC", $longago ) );

	return $oldposts;
}