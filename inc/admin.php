<?php
/**
 * Admin Options
 *
 * All of the administrative functionality for BuddyPress Automatic Followers.
 *
 * @link http://wordpress.org/plugins/bp-automatic-followers/
 * @since 2.0.0
 *
 * @package BuddyPress Automatic Followers
 * @subpackage Admin
 */

/**
 * BuddPress Automatic Followers Admin
 */
class BPAFollowersAdmin {

	public $plugins_url;

	/* Define and register singleton */
	private static $instance = false;
	public static function instance() {
		if ( ! self::$instance ) {
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
		global $pagenow;

		// Setup
		$this->plugins_url = plugins_url( '/bp-automatic-followers' );

		// Admin Menu
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'action_admin_menu' ), 11 );

		// AJAX
		add_action( 'wp_ajax_bpaf_suggest_global_follower', array( $this, 'action_ajax_bpaf_suggest_global_follower' ) );
		add_action( 'wp_ajax_bpaf_add_global_follower', array( $this, 'action_ajax_bpaf_add_global_follower' ) );
		add_action( 'wp_ajax_bpaf_delete_global_follower', array( $this, 'action_ajax_bpaf_delete_global_follower' ) );

		// User options
		add_action( 'personal_options', array( $this, 'action_personal_options' )  );
		add_action( 'personal_options_update', array( $this, 'action_personal_options_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'action_personal_options_update' ) );

		/* We don't need any of these things in other places */
		if ( 'users.php' != $pagenow || ! isset( $_REQUEST['page'] ) || 's8d-bpafollowers-settings' != $_REQUEST['page'] ) {
			return;
		}

		// Init
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ), 11 );

	}

	/**
	 * Setup the Admin.
	 *
	 * @uses register_setting, add_settings_section, add_settings_field
	 * @action admin_init
	 * @return null
	 */
	function action_admin_init() {
		// Register Settings
		register_setting( BPAFollowersCore::LEGACY_OPTION, BPAFollowersCore::LEGACY_OPTION, array( $this, 's8d_bpaf_settings_validate_options' ) );
	}


	/**
	 * Enqueue necessary scripts.
	 *
	 * @uses wp_enqueue_script
	 * @return null
	 */
	public function action_admin_enqueue_scripts() {
		wp_enqueue_script( 'bpaf-admin', $this->plugins_url. '/js/admin.js', array( 'jquery', 'jquery-ui-autocomplete' ), BPAFollowersCore::REVISION, true );

		wp_enqueue_style( 'bpaf-genericons', $this->plugins_url . '/fonts/genericons/genericons.css', '', BPAFollowersCore::REVISION );
		wp_enqueue_style( 'bpaf-admin', $this->plugins_url . '/css/admin.css', array( 'bpaf-genericons' ), BPAFollowersCore::REVISION );
	}

	/**
	 * Setup Admin Menu Options & Settings.
	 *
	 * @uses is_super_admin, add_submenu_page
	 * @action network_admin_menu, admin_menu
	 * @return null
	 */
	function action_admin_menu() {
		if ( ! is_super_admin() )
			return false;

		add_users_page( __( 'Automatic Followers', BPAFollowersCore::TEXT_DOMAIN), __( 'Automatic Followers', BPAFollowersCore::TEXT_DOMAIN ), 'manage_options', 's8d-bpafollowers-settings', array( $this, 'settings_page' ) );
	}

	/**
	 * Display the followers automatically added in the admin options.
	 *
	 * @since 1.5.0
	 * @return null
	 */
	function render_global_follower_table() {

		// Legacy Support

		$options = get_option( BPAFollowersCore::LEGACY_OPTION );
		if ( isset( $options[ BPAFollowersCore::LEGACY_OPTION . '_ids'] ) ) {
			$s8d_bpaf_user_ids = $options[ BPAFollowersCore::LEGACY_OPTION . '_ids'];
			$follower_user_ids = explode( ',', $s8d_bpaf_user_ids );
		}

		// Modern
		$follower_user_ids = $global_follower_user_ids = bpaf_get_global_followers();
		?>
		<form id="global-followers-form">
		<?php wp_nonce_field( BPAFollowersCore::NONCE, BPAFollowersCore::NONCE, false ); ?>
		<table class="wp-list-table widefat fixed users" cellspacing="0" style="clear:left;">
			<thead>
				<tr>
				  <th scope="col" id="username" class="manage-column column-username sortable desc" style=""><a><span><?php _e( 'Username', BPAFollowersCore::TEXT_DOMAIN );?></span></a></th>
				  <th scope="col" id="name" class="manage-column column-name sortable desc" style=""><a><span><?php _e( 'Name', BPAFollowersCore::TEXT_DOMAIN );?></span></a></th>
				  <th scope="col" id="followers" class="manage-column column-followers sortable desc" style=""><a><span><?php _e( 'Followers', BPAFollowersCore::TEXT_DOMAIN );?></span></a></th>
				</tr>
			</thead>
			<?php
			if ( is_array( $follower_user_ids ) && 0 < count( $follower_user_ids ) ) {
				foreach ( $follower_user_ids as $i => $follower_user_id ) {
					$follower_userdata = get_userdata( $follower_user_id );
					if ( $follower_userdata ) {
						// Add a row to the table
						$this->render_global_follower_table_row( $follower_user_id, $i + 2 ); // Because i%2 of i=0 and i=1 is the same
					}
				}// foreach
				unset( $i );
			} else { ?>
				<tr class="bpaf-empty-table-row"><td colspan="3"><?php _e( 'No Global Followers found.', BPAFollowersCore::TEXT_DOMAIN ); ?></td></tr>;
				<?php
			}
			?>
			<tfoot>
				<tr>
				  <th scope="col" id="username" class="manage-column column-username sortable desc" style=""><a><span><?php _e( 'Username', BPAFollowersCore::TEXT_DOMAIN );?></span></a></th>
				  <th scope="col" id="name" class="manage-column column-name sortable desc" style=""><a><span><?php _e( 'Name', BPAFollowersCore::TEXT_DOMAIN );?></span></a></th>
				  <th scope="col" id="followers" class="manage-column column-followers sortable desc" style=""><a><span><?php _e( 'Followers', BPAFollowersCore::TEXT_DOMAIN );?></span></a></th>
				</tr>
			</tfoot>
		</table>
		</form>
		<?php
	}

	/**
	 * Settings Page.
	 *
	 * @uses get_admin_url, settings_fields, do_settings_sections
	 * @return null
	 */
	function settings_page() {
		?>
		<div class="wrap">
			<?php //screen_icon(); ?>
			<h2><?php _e( 'BuddyPress Automatic Followers', BPAFollowersCore::TEXT_DOMAIN );?></h2>
			<div id="poststuff" class="metabox-holder has-right-sidebar">
				<div class="inner-sidebar" id="side-info-column">
					<div id="side-sortables" class="meta-box-sortables ui-sortable">
						<div id="bpaf_display_option" class="postbox ">
							<h3 class="hndle"><span><?php _e( 'Help Improve BP Automatic Followers', BPAFollowersCore::TEXT_DOMAIN );?></span></h3>
							<div class="inside">
								<p><?php _e( 'We would really appreciate your input to help us continue to improve the product.', BPAFollowersCore::TEXT_DOMAIN );?></p>
								<p>
								<?php printf( __( 'Find us on %1$s or donate to the project using the button below.', BPAFollowersCore::TEXT_DOMAIN ), '<a href="https://github.com/stevenkword/BuddyPress-Automatic-Followers" target="_blank">GitHub</a>' ); ?>
								</p>
								<div style="width: 100%; text-align: center;">
									<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
										<input type="hidden" name="cmd" value="_s-xclick">
										<input type="hidden" name="hosted_button_id" value="DWK9EXNAHLZ42">
										<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
										<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
									</form>
								</div>
							</div>
						</div>
						<div id="bpaf_display_contact" class="postbox ">
							<h3 class="hndle"><span><?php _e( 'Contact BP Automatic Followers', BPAFollowersCore::TEXT_DOMAIN );?></span></h3>
							<div class="inside">
								<ul class="bpaf-contact-links">
									<li><a class="link-bpaf-forum" href="http://wordpress.org/support/plugin/bp-automatic-followers" target="_blank"><?php _e( 'Support Forums', BPAFollowersCore::TEXT_DOMAIN );?></a></li>
									<li><a class="link-bpaf-web" href="http://stevenword.com/plugins/bp-automatic-followers/" target="_blank"><?php _e( 'BP Automatic Followers on the Web', BPAFollowersCore::TEXT_DOMAIN );?></a></li>
									<li><a class="link-bpaf-github" href="https://github.com/stevenkword/BuddyPress-Automatic-Followers" target="_blank"><?php _e( 'GitHub Project', BPAFollowersCore::TEXT_DOMAIN );?></a></li>
									<li><a class="link-bpaf-review" href="http://wordpress.org/support/view/plugin-reviews/bp-automatic-followers" target="_blank"><?php _e( 'Review on WordPress.org', BPAFollowersCore::TEXT_DOMAIN );?></a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
				<div id="post-body-content">
					<p><?php _e( 'When new user accounts are registered, followers between the new user and each of the following global followers will be created automatically.', BPAFollowersCore::TEXT_DOMAIN );?></p>
					<h3 style="float: left; margin:1em 0;padding:0; line-height:2em;"><?php _e( 'Global Followers', BPAFollowersCore::TEXT_DOMAIN );?></h3>
					<div style="padding: 1em 0;">
						<?php $search_text = __('Search by Username', BPAFollowersCore::TEXT_DOMAIN );?>
						<input type="text" name="add-global-follower-field" id="add-global-follower-field" style="margin-left: 1em; color: #aaa;"value="<?php echo $search_text;?>" onfocus="if (this.value == '<?php echo $search_text;?>') {this.value = '';}" onblur="if (this.value == '') {this.value = '<?php echo $search_text;?>';}" size="40" maxlength="128">
						<button id="add-global-follower-button" class="button" disabled="disabled"><?php _e( 'Add User', BPAFollowersCore::TEXT_DOMAIN );?></button>
						<span class="spinner"></span>
					</div>
					<div id="global-follower-table-container">
						<?php $this->render_global_follower_table();?>
					</div>
				</div>
			</div>
		</div><!--/.wrap-->
		<?php
	}

	/**
	 * Personal Options.
	 *
	 * @return null
	 */
	function action_personal_options( $user ) {
		$meta_value = get_user_meta( $user->ID, BPAFollowersCore::METAKEY, true );
		?>
			</table>
			<table class="form-table">
			<h3><?php _e( 'BuddyPress Automatic Followers', BPAFollowersCore::TEXT_DOMAIN );?></h3>
			<tr>
				<th scope="row"><?php _e( 'Global Follower', BPAFollowersCore::TEXT_DOMAIN );?></th>
				<td>
					<label for="global-follower">
						<input type="checkbox" id="global-follower" name="global-follower" <?php checked( $meta_value ); ?> />
						<span> <?php _e( 'Automatically create followers with all new users', BPAFollowersCore::TEXT_DOMAIN );?></span>
						<?php wp_nonce_field( BPAFollowersCore::NONCE, BPAFollowersCore::NONCE, false ); ?>
					</label>
				</td>
			</tr>
		<?php
	}

	/**
	 * Update personal options.
	 *
	 * @since 2.0.0
	 */
	function action_personal_options_update( $user_id ) {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST[ BPAFollowersCore::NONCE ], BPAFollowersCore::NONCE ) || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( BPAFollowersCore::NONCE_FAIL_MSG );
		}

		$meta_value = isset( $_REQUEST['global-follower'] ) ? true : false;
		update_user_meta( $user_id, BPAFollowersCore::METAKEY, $meta_value );

		// Update the follower counts
		BP_Followers_Followership::total_follower_count( $user_id );
	}

	/**
	 * Admin Ajax for finding users.
	 *
	 * @since 2.0.0
	 */
	function action_ajax_bpaf_suggest_global_follower() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], BPAFollowersCore::NONCE ) ) {
			wp_die( BPAFollowersCore::NONCE_FAIL_MSG );
		}

		global $bp;
		$global_follower_user_ids = bpaf_get_global_followers();

		$user_query = new WP_User_Query( array(
			'search' => '*' . $_REQUEST[ 'search' ] . '*',
			'exclude' => $global_follower_user_ids,
		) );

		// Get the results from the query, returning the first user
  		$users = $user_query->get_results();

		$user_ids = array();
		foreach ( $users as $user ) {
			$user_ids[] = array(
				'ID'           => $user->data->ID,
				'label'        => $user->data->user_login,
				'display_name' => $user->data->display_name
			);
		}

		header( 'Content-Type: application/x-json' );
		echo $json = json_encode( $user_ids );
		die;
	}

	/**
	 * Admin Ajax for adding users.
	 *
	 * @since 2.0.0
	 */
	function action_ajax_bpaf_add_global_follower() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], BPAFollowersCore::NONCE ) ) {
			wp_die( BPAFollowersCore::NONCE_FAIL_MSG );
		}

		if ( ! isset( $_REQUEST['username'] ) && empty( $_REQUEST['username'] ) ) {
		 	die;
		}

		// Add Global Follower status
		$user = get_user_by( 'login', $_REQUEST['username'] );
		if ( isset( $user->data->ID ) ) {
			// Update the user and related followers
			update_user_meta( $user->data->ID, BPAFollowersCore::METAKEY, true );

			// This is wrong MMMMMKay! This is looking for a newly registred user, not a global follower
			bpaf_create_followers( $user->data->ID );

			// Add a new row to the table
			//$this->render_global_follower_table_row( $user->data->ID );
			//
			//Redraw the table
			$this->render_global_follower_table();
		}
		die;
	}

	/**
	 * Render the global followers table.
	 *
	 * @since 2.0.0
	 */
	function render_global_follower_table_row( $follower_user_id, $i = '' ) {

		if( ! isset( $i ) || '' == $i ) {
			$i = count( bpaf_get_global_followers() );
			//echo $i;
		}
		$follower_userdata = get_userdata( $follower_user_id );
		?>
		<tr <?php if( 0 == $i % 2 ) echo 'class="alternate"'; ?>>
			<td class="username column-username">
				<input class="bpaf-user-id" id="bpaf-user-<?php echo $follower_user_id;?>" type="hidden" value="<?php echo $follower_user_id; ?>"></input>
				<?php echo get_avatar( $follower_user_id, 32 ); ?>
				<strong><?php echo $follower_userdata->user_login;?></strong>
				<br>
				<div class="row-actions">
					<span class="edit"><a href="<?php echo get_edit_user_link( $follower_user_id ); ?>" title="Edit this item"><?php _e( 'Edit', BPAFollowersCore::TEXT_DOMAIN );?></a> | </span>
					<span id="remove-<?php echo $follower_userdata->user_login;?>" class="trash"><a class="submitdelete" title="Move this item to the Trash" href="javascript:void(0);"><?php _e( 'Remove', BPAFollowersCore::TEXT_DOMAIN );?></a></span>
				</div>
			</td>

			<td class="name column-name">
				<?php echo $follower_userdata->display_name; ?>
			</td>

			<td class="followers column-followers">
				<?php //echo BP_Followers_Followership::total_follower_count( $follower_user_id );
				echo count( bp_follow_get_following( array( 'user_id'     => $follower_user_id ) ) ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Admin Ajax for removing users.
	 *
	 * @since 2.0.0
	 */
	function action_ajax_bpaf_delete_global_follower() {
		// Nonce check
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], BPAFollowersCore::NONCE ) ) {
			wp_die( BPAFollowersCore::NONCE_FAIL_MSG );
		}

		if ( ! isset( $_REQUEST['ID'] ) && empty( $_REQUEST['ID'] ) ) {
		 	die;
		}

		// Remove Global Follower status
		update_user_meta( $_REQUEST['ID'], BPAFollowersCore::METAKEY, false );
		bpaf_destroy_followers( $_REQUEST['ID'] );

		// Return the number of followers remaning
		// echo $global_followers_remaining = count( bpaf_get_global_followers() );
		//
		// Redraw the table
		$this->render_global_follower_table();
		die;
	}

} // Class
BPAFollowersAdmin::instance();
