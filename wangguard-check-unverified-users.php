<?php
/*
Plugin Name: WangGuard - Check Unverified Users Tool
Plugin URI:  http://ipassion.it
Description: This plugin adds the ability to bulk check unverified users using the WangGuard API - Requires WangGuard Plug-in. <a href="tools.php?page=WangGuardCUU">Use it now</a>.
Version:     0.1
Author:      Mauro Dalu
Author URI:  http://linkedin.com/in/maurodalu
Domain Path: /languages
Text Domain: wangguard
*/

class WangGuardCUU_Page
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        //add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
    	$parent_slug = 'tools.php';
    	$page_title = 'WangGuard Check Unverified Users';
    	$menu_title = $page_title;
    	$capability = 'manage_options';
    	$menu_slug = 'WangGuardCUU';
    	$function = array($this, 'create_admin_page');
    	add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        ?>
        <div class="wrap">
        	<h2>Check Unverified Users with WangGuard</h2>
        	
       <?php
       $wangguard_api_key = get_site_option('wangguard_api_key');
       if (empty($wangguard_api_key)) {
       		echo '<p>The WangGuard plug-in or API Key are missing.</p>';
       } else {
	       $action = NULL;
	       $limit = $batch = '';
	       if (isset($_POST['action'])) {
	       	$action = $_POST['action'];
	       	$limit = intval($_POST['limit']);
	       	$batch = $_POST['batch'];
	       }
	       if ($action == 'doit') {
	       	if ($limit == 0) { 
	       		$class = 'error';
	       		$message = '<p>Error: you should be checking more then 0 users, also make sure you are entering a number.</p>';
	       	}
	       	else {
	       		$log = $this->check_unverified_users($limit);
	       		$class = "update-nag";
	       		$message = "<p>We just verified {$log['verified']} users. {$log['reported']} users where reported as sploggers.</p>";
	       		$body = "</ul>";
	       		foreach ($log['activity'] as $userid => $result) {
	       			$body .= "<li>User <b>$userid</b> was marked as <b>$result</b></li>";	
	       		}
	       		$body .= "</ul>"; 
	       		$message .= $this->collapsable_section('activityLog', 'Activity Log', 'dashicons-list-view', $body);
	       	}
	       	echo "<div class=\"$class\">$message</div>";
	       } else { $limit = 50; }
	       $unverified_users = $this->unchecked_users_count();
	       ?>
	        	<p>You currently have <?php echo intval($unverified_users); ?> Unverified Users.</p>
	        	<form name="theForm" id="WangGuardForm" action="tools.php?page=WangGuardCUU" method="POST" />
	        		<input type="hidden" name="action" value="doit" />
	        		<table class="form-table">
	        		<tbody>
	        			<tr>
	        				<th scope="row">
	        					<label for="limit">How many users you want to check?</label>
	        				</th>
	        				<td>
		         				<input type="text" name="limit" value="<?php echo $limit; ?>" />
		         			</td>
		         		</tr>
		         		<tr>
		         			<th scope="row">
		         				<label for="batch">Batch process all users</label>
		         			</th>
		         			<td>
				         		<select name="batch">
				         			<option value="no-no" <?php if ($batch != 'active') { echo 'selected'; } ?>>Disabled</option>
				         			<option value="active" <?php if ($batch == 'active') { echo 'selected'; } ?>>Active</option>
				         		</select>
				         	</td>
				         </tr>
				         <tr>
				         	<th scope="row"></th>
				         	<td>
				         		<input type="submit" name="WangGuardSubmit" value="Go Check'em" class="button button-primary" />
				         	</td>
		         		</tr>
		         	</tbody>
		         	</table>
		         </form>
		 		 <?php
		 			if (($batch == 'active') and ($unverified_users > 10)) {
		        		$spinner_url = admin_url( 'images/spinner.gif', 'http' );
		         		echo "<img src='$spinner_url' align='absmiddle' /> Batch processing is active. Checking $limit of $unverified_users. Working...";
		          ?>	
	         		<script type="text/javascript">
	         		jQuery(document).ready(function () {
		         		//document.forms["WangGuardForm"].submit();
		         		//document.forms["theForm"].submit();
		         		//document.forms[0].submit();
		         		jQuery("#WangGuardForm").submit();
		         		print("Submitting...");
	         		});
	         		</script>
		         <?php
		         } ?>
	        </div>
	        <?php
		}
    }

    /**
     * Register and add settings
     */
    // public function page_init() { }
    
    public function unchecked_users_count() {
    	// This code is forked from wangguard-class-wp-users.php line 70
    	global $wpdb;   	
    	$table_name = $wpdb->base_prefix . "wangguarduserstatus";
    	$Count = $wpdb->get_col( "select count(*) as q from $wpdb->users where  (not EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID) OR EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID and $table_name.user_status IN ( '', 'not-checked' )))");
    	return $Count[0]; 
    }
    
    public function check_unverified_users($limit) {
        global $wpdb;
		// code forked from wangguard-wizard.php line 8
		if (wangguard_is_multisite()) {
			$spamFieldName = "spam";
		}
		else {
			$spamFieldName = "user_status";
		}
		// code forked from wangguard-class-wp-users.php line 70
        $table_name = $wpdb->base_prefix . "wangguarduserstatus";
        $users_to_check = $wpdb->get_results( "select ID from $wpdb->users where  (not EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID) OR EXISTS (select user_status from $table_name where $table_name.ID = {$wpdb->users}.ID and $table_name.user_status IN ( '', 'not-checked' ))) LIMIT $limit", ARRAY_A);
                
        $verified = 0;
        $reported = 0;       
        // code forked from wangguard-wizard.php line 156
    	foreach ($users_to_check as $key => $user) {
    		$userid = $user['ID'];
    		//get the WangGuard user status, if status is force-checked or buyer then ignore the user
    		$table_name = $wpdb->base_prefix . "wangguarduserstatus";
    		$user_status = $wpdb->get_var( $wpdb->prepare("select user_status from $table_name where ID = %d" , $userid));
    		if ( $user_status == 'force-checked' || $user_status == 'buyer' || $user_status == 'whitelisted' )
    			continue;
    		$user_object = new WP_User($userid);
    		set_time_limit(300);
    		$user_check_status = wangguard_verify_user($user_object);
    		$checked_users[$userid] = $user_check_status;
    		if ($user_check_status == "reported") {
    				$reported++;
    				do_action('wangguard_pre_mark_user_spam_wizard');
    				if (function_exists("update_user_status"))
    					update_user_status($userid, $spamFieldName, 1); //when flagging the user as spam, the wangguard hook is called to report the user
    				else
    					$wpdb->query( $wpdb->prepare("update $wpdb->users set $spamFieldName = 1 where ID = %d" , $userid ) );
    				}
    		$verified++;
    	}
    	
    	$log = array('verified' => $verified, 'reported' => $reported, 'activity' => $checked_users);
    	return $log;
    }
    
    /* ! Create a Collapsable Section */
    /***
    * Parameters:
    * $id = string, the css id of the item to collapse
    * $title = string, the text to display as a title
    * $icon (defaults to ) = string, the css class for the dashicon to use
    ***/
    public function collapsable_section($id, $title, $icon = 'dashicons-admin-settings', $body = NULL) {
    	$result = '
    	<h3>
    		<span onclick="jQuery(\'#'.$id.'\').toggle();jQuery(\'.'.$id.'-disclosure\').toggle();" style="cursor: pointer;">
    			<span class="dashicons dashicons-arrow-down '.$id.'-disclosure" style="display:none;color:#555;"></span>
    			<span class="dashicons dashicons-arrow-right '.$id.'-disclosure" style="color:#555;"></span>&nbsp;
    			<span class="dashicons '.$icon.'" style="color:#555;"></span>&nbsp;
    			'.$title.'
    		</span>
    	</h3>
    	<div id="'.$id.'" style="display:none;margin-left:10px;padding-left:15px;padding-right:20px;border-left:2px solid silver;">';
    	if (!empty($body)) {
    		$result .= $body.'</div>';
    	}
    	return $result;
    }
}

if( is_admin() ) {
	$wangguard_api_key = get_site_option('wangguard_api_key');
	
	if (empty($wangguard_api_key))  {
		function WangGuardCUU_admin_error_notice() {
			$class = 'error';
			$message = '<p><b>Sorry!</b> WangGuard - Check Unverified Users Tool cannot be initialized because WangGuard plug-in is either not installed, not active or not configured.<br/><i>If you just entered your API Key, please reload the page to initialize the tool.</i></p>';
			echo "<div class=\"$class\">$message</div>"; 
		}
		add_action( 'admin_notices', 'WangGuardCUU_admin_error_notice' ); 
	}
	
	$WangGuardCUU_Page = new WangGuardCUU_Page();
}