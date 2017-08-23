<?php
/*
Plugin Name: BuddyPress Automatic Followers
Plugin URI: http://www.stevenword.com/bp-automatic-followers/
Description: Automatically create and accept followers for specified users upon new user registration. * Requires BuddyPress
Text Domain: bp-automatic-followers
Version: 2.0.7
Author: Steven Word
Author URI: http://stevenword.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2013 Steven K. Word

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * BuddPress Automatic Followers Core
 */
class BPAFollowersCore {

	const VERSION        = '2.0.7';
	const REVISION       = '20141120';
	const METAKEY        = 'bpaf_global_follower';
	const LEGACY_OPTION  = 'skw_bpaf_options';
	const NONCE          = 'bpaf_nonce';
	const NONCE_FAIL_MSG = 'Cheatin&#8217; huh?';
	const TEXT_DOMAIN    = 'bp-automatic-followers';

	/* Define and register singleton */
	private static $instance = false;
	public static function instance() {
		if( ! self::$instance ) {
			self::$instance = new self;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Constructor
     *
	 * @since 2.0.0
	 */
	private function __construct() { }

	/**
	 * Clone
     *
	 * @since 2.0.0
	 */
	private function __clone() { }

	/**
	 * Add actions and filters
	 *
	 * @uses add_action, add_filter
	 * @since 2.0.0
	 */
	function setup() {
		$plugin_dir = basename( dirname( __FILE__ ) );
		load_plugin_textdomain( self::TEXT_DOMAIN, false, $plugin_dir . '/languages/' );

		add_action( 'bp_loaded', array( $this, 'action_bp_loaded' ) );
	}


	/**
	 * Loader function only fires if BuddyPress exists.
	 *
	 * @uses is_admin, add_action
	 * @action bp_loaded
	 * @return null
	 */
	function action_bp_loaded(){

		/* Load the admin */
		if ( is_admin() ) {
			if( class_exists( 'BP_Follow_Component' ) ) {
				require_once( dirname(__FILE__) . '/inc/admin.php' );
			} else {
				add_action('admin_notices', array( $this, 'admin_notice' ) );
			}
		}

		/* Do this the first time a new user logs in */
		add_action( 'wp', array( $this, 'first_login' ) );
	}

	/**
	 * New method for creating followers at first login.
	 *
	 * Prevents conflict with plugins such as "Disable Activation" that bypass the activation process.
	 *
	 * Hook into the 'wp' action and check if the user is logged in
	 * and if get_user_meta( $bp->loggedin_user->id, 'last_activity' ) is false.
	 * http://buddypress.trac.wordpress.org/ticket/3003
	 */
	function first_login() {

		if( ! is_user_logged_in() ) {
			return;
		}

		global $bp;

		$last_login = bp_get_user_last_activity( $bp->loggedin_user->id );

		// This needs to be re-added after debugging
		if( ! isset( $last_login ) || empty( $last_login ) )
			$this->create_followers( $bp->loggedin_user->id );

	}

	/**
	 * Get Global Followers
	 *
	 * @return array|bool
	 */
	public static function get_global_followers() {
		global $bp;

		// The Query
		$user_query = new WP_User_Query( array(
			'meta_key' => BPAFollowersCore::METAKEY,
			'meta_value' => true,
			'fields' => 'ID'
		) );

		if ( ! empty( $user_query->results ) ) {
			return $user_query->results;
		} else {
			return false;
		}
	}

	/**
	 * Create followers automatically
	 *
	 * When a initiator user registers for the blog, create initiator followership with the specified user(s) and autoaccept those followerhips.
	 * @global bp
	 * @param initiator_user_id
	 * @uses get_userdata, get_option, explode, followers_add_follower, get_follower_user_ids, total_follower_count
	 * @return null
	 */
	public static function create_followers( $initiator_user_id ) {

		global $bp;

		// Disable email notifications.  In situations with hundreds of users, this can get SPAMMY fast
		remove_action( 'followers_followership_requested', 'followers_notification_new_request', 10 );
		remove_action( 'followers_followership_accepted', 'followers_notification_accepted_request', 10 );

		/* Get the user data for the initiatorly registered user. */
		$initiator_user_info = get_userdata( $initiator_user_id );

		/* Get the follower users id(s) */
		//$options = get_option( BPAFollowersCore::LEGACY_OPTION );
		//$global_follower_user_ids = $options['s8d_bpaf_user_ids'];

		$global_follower_user_ids = self::get_global_followers();

		/* Check to see if the admin options are set*/
		if ( isset( $global_follower_user_ids ) && ! empty( $global_follower_user_ids ) ){

			// @legacy
			//$follower_user_ids = explode( ',', $global_follower_user_ids );

			$follower_user_ids = $global_follower_user_ids;

			foreach ( $follower_user_ids as $follower_user_id ){
				bp_follow_start_following( array(
					'leader_id'   => $initiator_user_id,
					'follower_id' => $follower_user_id
				) );
			}

		}

	}

	/**
	 * Destroy Followerships
	 *
	 * @global bp
	 * @param initiator_user_id
	 * @uses get_userdata, get_option, explode, followers_add_follower, get_follower_user_ids, total_follower_count
	 * @return null
	 */
	public static function destroy_followers( $initiator_user_id ) {
				bp_follow_start_following( array(
					'leader_id'   => $initiator_user_id,
					'follower_id' => $follower_user_id
				) );
	}

	/**
	 * Update Followership Counts
	 *
	 * @return null
	 */
	public static function update_followership_counts( $initiator_user_id ) {

		$followers = bp_follow_get_followers( $initiator_user_id );
		var_dump( $followers );
		die( 'asdf' );
		return count( $followers );

		return;

		/* Get followers of $user_id */
		$follower_ids = BP_Followers_Followership::get_follower_user_ids( $initiator_user_id );

		/* Loop through the initiator's followers and update their follower counts */
		foreach ( (array) $follower_ids as $follower_id ) {
			BP_Followers_Followership::total_follower_count( $follower_id );
		}

		/* Update initiator follower counts */
		BP_Followers_Followership::total_follower_count( $initiator_user_id );
	}

	/**
	 * Notify the admin of why we can't load the plugin.
	 *
	 * @since 2.0.0
	 */
	function admin_notice() {
		echo '<div class="error"><p>BuddyPress Automatic Followers cannot be loaded because BuddyPress Follow is not isntalled. <a href="' . admin_url('plugin-install.php?tab=plugin-information&plugin=buddypress-followers') . '">Click Here</a> to install BuddyPress Follow.</p></div>';
	}

}
BPAFollowersCore::instance();

/* Wrappers */
if ( ! function_exists( 'bpaf_get_global_followers' ) ) {
	function bpaf_get_global_followers() {
		return BPAFollowersCore::get_global_followers();
	}
}
if ( ! function_exists( 'bpaf_create_followers' ) ) {
	function bpaf_create_followers( $initiator_user_id ) {
		BPAFollowersCore::create_followers( $initiator_user_id );
	}
}
if ( ! function_exists( 'bpaf_destroy_followers' ) ) {
	function bpaf_destroy_followers( $initiator_user_id ) {
		BPAFollowersCore::destroy_followers( $initiator_user_id );
	}
}
if ( ! function_exists( 'bpaf_update_followership_counts' ) ) {
	function bpaf_update_followership_counts( $initiator_user_id ) {
		BPAFollowersCore::update_followership_counts( $initiator_user_id );
	}
}
