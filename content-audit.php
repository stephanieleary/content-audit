<?php
/*
Plugin Name: Content Audit
Plugin URI: http://stephanieleary.com/code/wordpress/content-audit/
Description: Lets you create a content inventory and notify the responsible parties about their outdated content. 
Version: 2.0a
Author: Stephanie Leary
Author URI: http://stephanieleary.com

Copyright 2010  Stephanie Leary  ( email : steph@sillybean.net )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    ( at your option ) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
TODO:
* Eliminate JS for custom fields in quick edit
* when this is fixed: http://core.trac.wordpress.org/ticket/16392
*/

// load stuff
include( 'inc/admin-bar.php' );
include( 'inc/bulk-actions.php' );
include( 'inc/bulk-quick-edit.php' );
include( 'inc/custom-fields.php' );
include( 'inc/cron.php' );
include( 'inc/dashboard-overview.php' );
include( 'inc/dashboard-widget.php' );
include( 'inc/edit-list-columns-filters.php' );
include( 'inc/front-end.php' );
include( 'inc/options.php' );
include( 'inc/taxonomy.php' );

// when activated, add option and create taxonomy terms
register_activation_hook( __FILE__, 'activate_content_audit_tax' );
register_activation_hook( __FILE__, 'content_audit_activation' );
function content_audit_activation() {
	add_option( 'content_audit', content_audit_default_options(), '', 'yes' );
}

// register options
add_action( 'admin_init', 'register_content_audit_options' );
function register_content_audit_options(){
	register_setting( 'content_audit', 'content_audit', 'content_audit_sanitize_options' );
}

// when uninstalled, remove option
register_uninstall_hook( __FILE__, 'content_audit_delete_options' );
function content_audit_delete_options() {
	delete_option( 'content_audit' );
}
// testing only
//register_deactivation_hook( __FILE__, 'content_audit_delete_options' );

// Add plugin CSS
add_action( 'admin_enqueue_scripts', 'content_audit_enqueue_scripts' );

function content_audit_enqueue_scripts( $hook ) {
	
	wp_register_style( 'content-audit-css', plugins_url( 'css/content-audit.css', __FILE__ ) );
	wp_register_style( 'wp-jquery-ui', plugins_url( 'css/wp-jquery-ui.css', __FILE__ ) );
	wp_register_script( 'content-audit-quickedit', plugins_url( 'js/quickedit.js', __FILE__ ), array( 'jquery', 'inline-edit-post' ), '', true );
	wp_register_script( 'content-audit-datepicker', plugins_url( 'js/initialize-datepicker.js', __FILE__ ), array( 'jquery', 'inline-edit-post' ), '', true );
	
	// Add CSS to some specific admin pages
	switch ( $hook ) {
		case 'post.php':
		case 'post-new.php':
		case 'media.php':
		case 'settings_page_content-audit':
		case 'dashboard_page_content-audit':
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script( 'content-audit-datepicker' );
			wp_enqueue_style( 'wp-jquery-ui' );
			// and keep going; we want the CSS too
		case 'index.php':
		case 'edit-tags.php':
			wp_enqueue_style( 'content-audit-css' );
			break;
		case 'edit.php':
			wp_enqueue_style( 'content-audit-css' );
			wp_enqueue_script( 'content-audit-quickedit' );
			break;
	}
	
}

// add the pages to the navigation menu
add_action( 'admin_menu', 'content_audit_add_pages' );

function content_audit_add_pages() {
    // Add a new submenu under Settings:
	add_options_page( __( 'Content Audit Options', 'content-audit' ), __( 'Content Audit', 'content-audit' ), 'manage_options', 'content-audit', 'content_audit_options' );
	// Add the boss view under the Dashboard:
	add_dashboard_page( __( 'Content Audit Overview', 'content-audit' ), __( 'Content Audit Overview', 'content-audit' ), 'manage_options', 'content-audit', 'content_audit_overview' );
}

// i18n
load_plugin_textdomain( 'content-audit', '', plugin_dir_path( __FILE__ ) . '/languages' );