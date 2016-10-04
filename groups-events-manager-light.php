<?php
/**
 * groups-events-manager-light.php
 *
 * Copyright (c) 2016 www.itthinx.com
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This header and all notices must be kept intact.
 *
 * @author itthinx
 * @package groups-events-manager-light
 * @since 1.0.0
 *
 * Plugin Name: Groups Events Manager Light
 * Plugin URI: http://www.itthinx.com/plugins/groups-events-manager/
 * Description: Integrates Groups with Events Manager.
 * Author: itthinx
 * Author URI: http://www.itthinx.com/
 * Version: 1.0.0
 */

if ( !defined('ABSPATH' ) ) {
	exit;
}

define( 'GROUPS_EM_PLUGIN_URL', WP_PLUGIN_URL . '/groups-events-manager-light' );
define( 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN', 'groups-events-manager-light' );

/**
 * Groups Events Manager integration.
 */
class Groups_Events_Manager_Light {
	
	const PLUGIN_OPTIONS = 'groups_events_manager_light';
	const SET_ADMIN_OPTIONS = 'set_admin_options';
	const CHOSEN_GROUPS = 'chosen_groups';
	const DEFAULT_STRING = '';
	const GROUPS_EM_NONCE = 'groups_eml_nonce';
	const GROUPS_EM_ADMIN_OPTIONS = 'set_admin_options';
	
	// Events Manager uses magic numbers
	const BOOKING_STATUS_UNAPPROVED              = 0;
	const BOOKING_STATUS_APPROVED                = 1;
	const BOOKING_STATUS_REJECTED                = 2;
	const BOOKING_STATUS_CANCELLED               = 3;
	const BOOKING_STATUS_AWAITING_ONLINE_PAYMENT = 4;
	const BOOKING_STATUS_AWAITING_PAYMENT        = 5;
	
	private static $admin_messages = array();
	
	/**
	 * Prints admin notices.
	 */
	public static function admin_notices() {
		if ( !empty( self::$admin_messages ) ) {
			foreach ( self::$admin_messages as $msg ) {
				echo $msg;
			}
		}
	}
	
	/**
	 * Checks dependencies and sets up actions and filters.
	 */
	public static function init() {
	
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	
		$verified = true;
		$disable = false;
		$active_plugins = get_option( 'active_plugins', array() );
		$groups_is_active = in_array( 'groups/groups.php', $active_plugins );
		$events_manager_is_active = in_array( 'events-manager/events-manager.php', $active_plugins );
		
		if ( !$groups_is_active ) {
			self::$admin_messages[] = "<div class='error'>" . __( 'The <strong>Groups Events Manager Integration Light</strong> plugin requires the <a href="http://wordpress.org/plugins/groups/">Groups</a> plugin.', 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN' ) . "</div>";
		}
		if ( !$events_manager_is_active ) {
			self::$admin_messages[] = "<div class='error'>" . __( 'The <strong>Groups Events Manager Integration Light</strong> plugin requires the <a href="http://wordpress.org/plugins/woocommerce/">Events Manager</a> plugin to be activated.', 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN' ) . "</div>";
		}		
		if ( !$groups_is_active || !$events_manager_is_active ) {
			if ( $disable ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				deactivate_plugins( array( __FILE__ ) );
			}
			$verified = false;
		}
	
		if ( $verified ) {
			add_action( 'groups_admin_menu', array( __CLASS__, 'groups_admin_menu' ) );
			add_filter( 'em_booking_set_status', array( __CLASS__, 'em_booking_set_status' ), 10, 2 );
			add_filter( 'em_booking_delete', array( __CLASS__, 'em_booking_delete' ), 10, 2 );
		}
	}
	
	/**
	 * Adds a submenu item to the Groups menu for the Events Manager integration options.
	 */
	public static function groups_admin_menu() {
		$page = add_submenu_page(
				'groups-admin',
				__( 'Groups Events Manager Integration Light', 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN' ),
				__( 'Events Manager Integration Light', 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN' ),
				GROUPS_ADMINISTER_OPTIONS,
				'groups-events-manager-light',
				array( __CLASS__, 'groups_admin_em_light' )
		);
	}
	
	/**
	 * Groups Events Manager Integration Light : admin section.
	 */
	public static function groups_admin_em_light() {
		global $wpdb;
		
		$output = '';
		Groups_UIE::enqueue( 'select' );
		if ( !current_user_can( GROUPS_ADMINISTER_OPTIONS ) ) {
			wp_die( __( 'Access denied.', 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN' ) );
		}
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		if ( isset( $_POST['submit'] ) ) {
			if ( wp_verify_nonce( $_POST[self::GROUPS_EM_NONCE], self::GROUPS_EM_ADMIN_OPTIONS ) ) {
				$options[self::CHOSEN_GROUPS]  = $_POST[self::CHOSEN_GROUPS];
			}
			update_option( self::PLUGIN_OPTIONS, $options );
		}
	
		$chosen_groups = isset( $options[self::CHOSEN_GROUPS] ) ? $options[self::CHOSEN_GROUPS] : self::DEFAULT_STRING;
		
		$output .=
		'<div>' .
		'<h2>' .
		__( 'Groups Events Manager Integration Light', 'GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN' ) .
		'</h2>' .
		'</div>';		
		// @todo change this paragraph a bit
		$output .= '<p>' . __( 'Choose the group(s) where the users will be added after their booking is approved.', GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN ) . '</p>';		
		$output .= '<form action="" name="options" method="post">';
		$groups_table = _groups_get_tablename( 'group' );
		if ( $groups = $wpdb->get_results( "SELECT * FROM $groups_table ORDER BY name" ) ) {
			$output .= '<style type="text/css">';
			$output .= '.select_groups { width: 45%; }';
			$output .= '.groups .selectize-input { font-size: inherit; }';
			$output .= '</style>';
			$output .= '<label>';
			$output .= __( 'Select Groups', GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN );
			$output .= sprintf(
				'<select class="select_groups" name="chosen_groups[]" placeholder="%s" multiple="multiple">',
				__( 'Choose groups &hellip;', GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN )
			);
			foreach( $groups as $group ) {
					$output .= sprintf( '<option value="%d" %s>%s</option>', Groups_Utility::id( $group->group_id ), in_array( $group->group_id, $chosen_groups ) ? ' selected ' : '', wp_filter_nohtml_kses( $group->name ) );
			}
			$output .= '</select>';	
			$output .= '</label>';	
			$output .= Groups_UIE::render_select( '.select_groups' );
			$output .= '<p class="description">' . __( 'Users will be added to the selected Group(s) after their booking has been approved.', GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN ) . '</p>';
		}
		$output .= '<p class="manage" style="padding:1em;margin-right:1em;font-weight:bold;font-size:1em;line-height:1.62em">';
		$output .= wp_nonce_field( self::GROUPS_EM_ADMIN_OPTIONS, self::GROUPS_EM_NONCE, true, false );
		$output .= '<input type="submit" name="submit" value="' . __( 'Save', GROUPS_EVENTS_MANAGER_PLUGIN_DOMAIN ) . '"/>';
		$output .= '</form>';
		$output .= '</p>';
		
		echo $output;
	}
	
	/**
	 * Add/remove user to/from group based on the booking status.
	 *
	 * @param EM_Booking $em_booking
	 * @param int $status 
	 * @return boolean
	 */
	public static function em_booking_set_status( $status, $em_booking ) {
		$booking_status = $em_booking->booking_status;
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		$group_ids = $options[ self::CHOSEN_GROUPS ];
		if ( $person = $em_booking->get_person() ) {
			$user_id = $person->ID;
		}
		foreach ( $group_ids as $group_id ) {
			switch ( $booking_status ) {
				case   1:			
					if ( !Groups_User_Group::read( $user_id, $group_id ) ) {
						Groups_User_Group::create( array( 'user_id' => $user_id, 'group_id' => $group_id ) );
					}
					break;
				case   0:
				case   2:
				case   3:
					if ( Groups_User_Group::read( $user_id, $group_id ) ) {
						Groups_User_Group::delete( $user_id, $group_id );
					}				
					break;
			}
		}
		
		return $status;
	}	
	
	/**
	 * Remove user from group(s) when a booking is deleted
	 * 
	 * @param int $result
	 * @param object $em_booking
	 * @return int
	 */
	public static function em_booking_delete( $result, $em_booking ) {	
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		$group_ids = $options[ self::CHOSEN_GROUPS ];
		
		if ( $result !== false ) {
			if ( $person = $em_booking->get_person() ) {
				$user_id = $person->ID;
			}
			foreach ( $group_ids as $group_id ) {			
				if ( Groups_User_Group::read( $user_id, $group_id ) ) {
					Groups_User_Group::delete( $user_id, $group_id );
				}
			}
		}
		
		return $result;
	}
} Groups_Events_Manager_Light::init();
