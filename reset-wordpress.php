<?php
/*
Plugin Name: TRL Reset Wordpress
Plugin URI: http://www.trlit.com
Description: Resets the Wordpress database to the fresh wordpress installation. Delets all contents and custom changes. It will resets only database not modify or delete any files.
Version: 1.0.1
Author: Trlit
Author URI: http://www.trlit.com
Text Domain: reset wordpress,wordpress reset,wordpress,clean,cleaner,database,mysql,wp-admin,admin,plugin
*/

if ( ! defined( 'ABSPATH' ) ){
	exit; // Exit if accessed this file directly
} 

if ( is_admin() ) {

define( 'REACTIVATE_THE_TRL_RESET_WP', true );

class Trl_ResetWP {
	
	function Trl_ResetWP() {
		add_action( 'admin_menu', array( &$this, 'Trl_add_page' ) );
		add_action( 'admin_init', array( &$this, 'Trl_admin_init' ) );
		add_filter( 'favorite_actions', array( &$this, 'Trl_add_favorite' ), 100 );
		add_action( 'wp_before_admin_bar_render', array( &$this, 'Trl_admin_bar_link' ) );
	}
	
	// Checks reset_wp post value and performs an installation, adding the users previous password also
	function Trl_admin_init() {
		global $current_user;

		$reset_wp = ( isset( $_POST['trl_reset_wp'] ) && $_POST['trl_reset_wp'] == 'true' ) ? true : false;
		$reset_wp_confirm = ( isset( $_POST['trl_reset_wp_confirm'] ) && $_POST['trl_reset_wp_confirm'] == 'yes' ) ? true : false;
		$valid_nonce = ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'trl_reset_wp' ) ) ? true : false;

		if ( $reset_wp && $reset_wp_confirm && $valid_nonce ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );

			$blogname = get_option( 'blogname' );
			$admin_email = get_option( 'admin_email' );
			$blog_public = get_option( 'blog_public' );

			if ( $current_user->user_login != 'admin' )
				$user = get_user_by( 'login', 'admin' );

			if ( empty( $user->user_level ) || $user->user_level < 10 )
				$user = $current_user;

			global $wpdb;

			$prefix = str_replace( '_', '\_', $wpdb->prefix );
			$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
			foreach ( $tables as $table ) {
				$wpdb->query( "DROP TABLE $table" );
			}

			$result = wp_install( $blogname, $user->user_login, $user->user_email, $blog_public );
			extract( $result, EXTR_SKIP );

			$query = $wpdb->prepare( "UPDATE $wpdb->users SET user_pass = '".$user->user_pass."', user_activation_key = '' WHERE ID =  '".$user_id."' ");
			$wpdb->query( $query );

			$get_user_meta = function_exists( 'get_user_meta' ) ? 'get_user_meta' : 'get_usermeta';
			$update_user_meta = function_exists( 'update_user_meta' ) ? 'update_user_meta' : 'update_usermeta';

			if ( $get_user_meta( $user_id, 'default_password_nag' ) )
				$update_user_meta( $user_id, 'default_password_nag', false );

			if ( $get_user_meta( $user_id, $wpdb->prefix . 'default_password_nag' ) )
				$update_user_meta( $user_id, $wpdb->prefix . 'default_password_nag', false );

			
			if ( defined( 'REACTIVATE_THE_TRL_RESET_WP' ) && REACTIVATE_THE_TRL_RESET_WP === true )
				@activate_plugin( plugin_basename( __FILE__ ) );
			

			wp_clear_auth_cookie();
			
			wp_set_auth_cookie( $user_id );

			wp_redirect( admin_url()."?trl_reset-wp=trl-reset-wp" );
			exit();
		}

		if ( array_key_exists( 'trl-reset-wp', $_GET ) && stristr( $_SERVER['HTTP_REFERER'], 'trl-reset-wp' ) )
			add_action( 'admin_notices', array( &$this, 'admin_notices_successfully_reset' ) );
	}
	
	// admin_menu action hook operations & Add the settings page
	function Trl_add_page() {
		if ( current_user_can( 'level_10' ) && function_exists( 'add_management_page' ) )
			$hook = add_management_page( 'Reset Wordpress', 'Reset Wordpress', 'level_10', 'trl-reset-wp', array( &$this, 'Trl_admin_page' ) );
			add_action( "admin_print_scripts-{$hook}", array( &$this, 'Trl_admin_javascript' ) );
			add_action( "admin_footer-{$hook}", array( &$this, 'Trl_footer_javascript' ) );
	}
	
	function Trl_add_favorite( $actions ) {
		$reset['tools.php?page=trl-reset-wp'] = array( 'Reset Wordpress', 'level_10' );
		return array_merge( $reset, $actions );
	}

	function Trl_admin_bar_link() {
		global $wp_admin_bar;
		$wp_admin_bar->add_menu(
			array(
				'parent' => 'site-name',
				'id'     => 'trl-reset-wp',
				'title'  => 'Reset Wordpress',
				'href'   => admin_url( 'tools.php?page=trl-reset-wp' )
			)
		);
	}
	
	// Inform the user that WordPress has been successfully reset
	function Trl_admin_notices_successfully_reset() {
		$user = get_user_by( 'id', 1 );
		echo '<div id="message" class="updated"><p><strong>WordPress has been reset successfully. The user "' . $user->user_login . '" is recreated with its current login password.</strong></p></div>';
		do_action( 'reset_wp_post', $user );
	}
	
	function Trl_admin_javascript() {
		wp_enqueue_script( 'jquery' );
	}

	function Trl_footer_javascript() {
	?>
	<script type="text/javascript">
		jQuery('#trl_reset_wp_submit').click(function(){
			if ( jQuery('#trl_reset_wp_confirm').val() == 'yes' || jQuery('#trl_reset_wp_confirm').val() == 'YES' ) {
				var message = 'This action can not be Undo.\n\nClicking "OK" will reset your database to the default installation.\n\nIt deletes all content and custom changes.\nIt will only reset the database.\nIt will not modify or delete any files.\n\n Click "Cancel" to stop this operation.'
				var reset = confirm(message);
				if ( reset ) {
					jQuery('#trl_reset_wp_form').submit();
				} else {
					jQuery('#trl_reset_wp').val('false');
					return false;
				}
			} else {
				alert('Invalid confirmation. Please type \'yes\' in the confirmation field.');
				return false;
			}
		});
	</script>	
	<?php
	}

	// add_option_page callback operations
	function Trl_admin_page() {
		global $current_user;
		if ( isset( $_POST['trl_reset_wp_confirm'] ) && $_POST['trl_reset_wp_confirm'] != 'reset-wordpress' )
			echo '<div class="error fade"><p><strong>Invalid confirmation. Please type \'yes\' in the confirmation field.</strong></p></div>';
		elseif ( isset( $_POST['_wpnonce'] ) )
			echo '<div class="error fade"><p><strong>Invalid wpnonce. Please try again.</strong></p></div>';
			
	?>
	<div class="wrap">
		<div id="icon-tools" class="icon32"><br /></div>
		<h2>Reset Wordpress</h2>
		<p><strong>Once completing this reset process, you will be respectively redirected to the wp-admin dashboard page. </strong></p>
		
		<?php $admin = get_user_by( 'login', 'admin' ); ?>
		<?php if ( ! isset( $admin->user_login ) || $admin->user_level < 10 ) : $user = $current_user; ?>
		<p>The 'admin' user does not exist. The user '<strong><?php echo esc_html( $user->user_login ); ?></strong>' will be recreated with its <strong>current password</strong> with user level 10.</p>
		<?php else : ?>
		
		<p>During this operation, the '<strong>admin</strong>' user remains untouched and will be recreated with <strong>  existing credentials </strong> only.</p>
		<?php endif; ?>

		<p>Further, plugin <strong>would reactivate</strong> once process to get completed successfully.</p>
		
		<hr/>
		
		<p>To proceed with reset function, type '<strong>yes</strong>' in the below text box and then click the 
		"Submit" button</p>
		<form id="trl_reset_wp_form" action="" method="post" autocomplete="off">
			<?php wp_nonce_field( 'trl_reset_wp' ); ?>
			<lable>Do you want to reset wordpress?</lable>
			<input id="trl_reset_wp" type="hidden" name="trl_reset_wp" value="true" />
			<input id="trl_reset_wp_confirm" type="text" name="trl_reset_wp_confirm" value="" maxlength="15" />
			<input id="trl_reset_wp_submit" style="width: 130px;" type="submit" name="Submit" class="button-primary" value="Submit" />
			
		</form>
	</div>
	<?php
	}
}

$Trl_ResetWP = new Trl_ResetWP();

}