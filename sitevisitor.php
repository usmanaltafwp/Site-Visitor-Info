<?php
/**
 * Plugin Name: Site Visitor Info  
  *Author:Usman Altaf
 * Version:1.0
 * License:GPL2
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cannot access pages directly.' );
}
class visitor
{ 

	public function __construct(){
		
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		$this->hooks();
	}

	public function add_plugin_page() {

		add_menu_page(__('Site Visitor Info'), __("Site Visitor Info"), 'administrator', "site_visitor",  array( $this, 'site_visitor_html_page' ));
	}

	public function hooks(){
		
		// activating  database table
		register_activation_hook( __FILE__, array($this, 'site_visitor_info_database_table') );
		 // Register style
		add_action( 'wp_enqueue_scripts', array($this, 'register_plugin_styles') );
		add_action( 'init', array($this, 'init') );

	}

	public function site_visitor_info_database_table() {
		
		global $wpdb; 
		$table_name = $wpdb->prefix . 'visitor_details'; 
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
			ip varchar(255),
			browser varchar(255),
			os varchar(255),
			device varchar(255),
			source varchar(255),
			action varchar(255),
			PRIMARY KEY  (id)
			);";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
	}

	public function site_visitor_html_page() {
		
		global $wpdb; 
		$table_name = $wpdb->prefix . 'visitor_details'; 
		if(isset($_POST['redirect'])){
			$redirect = $_POST['redirect'];
			add_site_option( 'redirect', $redirect );
			$updated =  update_option('redirect', $redirect);
		}
		if(isset($_POST['block'])){

			$user_ip = $_POST['user_ip'];
			$clicked = 'banned'; 
			$wpdb->query('UPDATE '.$table_name.' SET action = "'.$clicked.'" WHERE ip = "'.$user_ip.'"' );
		}
		elseif(isset($_POST['unblock'])){
			
			$user_ip = $_POST['user_ip'];
			$unbanned = 'unbanned'; 
			$wpdb->query('UPDATE '.$table_name.' SET action = "'.$unbanned.'" WHERE ip = "'.$user_ip.'"' );
		}
		
		$redirect_val = get_option( 'redirect' );	
		$return_output = '';
		
		$return_output .='
			<form class="site_visited" method="post" action=""><input class="redirect" name="redirect" type="text" placeholder="Enter the Url" value="'.$redirect_val.'"><input class="button button-primary unblock" name="submit" type="submit" value="send"></form>
			<table class="widefat fixed" cellspacing="0">
			<tr>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="12%">IP Address</th>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="20%">Browser</th>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="12%">OS</th>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="12%">Device</th>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="12%">Source/Host</th>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="10%">Status</th>
			<th id="columnname" class="manage-column column-columnname" scope="col" width="22%">Action</th>
			</tr>';
		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
		$limit = 5; // number of rows in page
		$offset = ( $pagenum - 1 ) * $limit;
		$total = $wpdb->get_var( "SELECT COUNT(`id`) FROM $table_name " );
		$num_of_pages = ceil( $total / $limit );
		$result = $wpdb->get_results( "SELECT * FROM $table_name LIMIT $offset, $limit" );
		$user_action = '';
		foreach($result as $row)
		{
			$user_action = $row->action;
			if($user_action == 'banned'){
				$actioned = 'sign-ban.png';
				$action_input = '<input class="button button-primary" name="unblock" type="submit" value="Un Block IP">';
			}
			else{
				$actioned = 'sign-check.png';
				$action_input = '<input class="button button-primary block" name="block" type="submit" value="Block IP">';
			}
			$return_output .='<tr class="alternate"><td class="column-columnname">'.$row->ip.'</td>
			<td class="column-columnname"> '.$row->browser.' </td>
			<td class="column-columnname"> '.$row->os.' </td>
			<td class="column-columnname"> '.$row->device.' </td>
			<td class="column-columnname"> '.$row->source.' </td>
			<td class="column-columnname"><img src=" '.plugins_url( 'images/'.$actioned,  __FILE__ ).' " /> </td>
			<td class="column-columnname"><form  method="post" action=""><input type="hidden" name="user_ip" value="'.$row->ip.'" />'.$action_input.'</form>
			</td>';
		}
		$return_output .='</tr></table>';
		
		echo $return_output;
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'prev_text' => __( '&laquo;', 'aag' ),
			'next_text' => __( '&raquo;', 'aag' ),
			'total' => $num_of_pages,
			'current' => $pagenum
			) );
		
		if ( $page_links ) {
			echo '<div class="tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . 
			$page_links . '</div></div>';
		}
	}
	
	public function register_plugin_styles() {
		
		wp_register_style( 'sitevisitor', plugins_url( 'site_visitor_info/css/style.css' ) );
		wp_enqueue_style( 'sitevisitor' );

	}

	public function init(){

		$User_info = $this->Check_User_info();

		/* check if user already exist*/

		if($User_info){

			/* check if user is banned  or not*/
			$User_action= $User_info['0']->action;
			if($User_action == 'banned'){
				$this->site_redirect();
			}
		}

		else{ 

			$browser	= $this->browser_data();
			global $wpdb; 
		$table_name = $wpdb->prefix . 'visitor_details'; 
		$userip = $_SERVER['REMOTE_ADDR'];
		$ref = $_SERVER[HTTP_REFERER];
		$sitevisited = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		$sitehome = get_site_url ();
		$direct = 'Direct';
		$realurl = $sitevisited;

		if (empty($realurl)){
			$realurl = $direct;
		}
		if ( wp_is_mobile() ) {
			$device = 'Mobile Device';
		}else{ 
			$device = 'Desktop';
		}
		$resultcheck = $wpdb->get_results ("SELECT ip FROM $table_name WHERE ip = '".$userip."'");
			if (count ($resultcheck) == 0) {
				$wpdb->insert($table_name, array(
				'ip' => $userip ,
				'browser' => $browser['name'],
				'os' => $browser['platform'],
				'device' => $device,
				'source' => $realurl,
				'action' => 'unbanned', 
				));
			}
		}
	}

	public function Check_User_info(){
		global $wpdb; 
		$table_name = $wpdb->prefix . 'visitor_details'; 
		$userip = $_SERVER['REMOTE_ADDR'];
		$resultcheck = $wpdb->get_results ("SELECT ip, action FROM $table_name WHERE ip = '".$userip."'");
		if (count ($resultcheck) == 0) {
			return false;
		}else{
			return $resultcheck;
		}
	}


	/**
	 * helper function to get browser data at user comes into the site
	 *
	 * @return WP_visitor
	 */

	private function browser_data() {

		// grab base user agent and parse out
	    $u_agent	= $_SERVER['HTTP_USER_AGENT'];
	    $bname		= 'Unknown';
	    $platform	= 'Unknown';
	    $version	= '';
		$up			= '';
		
	    // determine platform
	    if (preg_match('/linux/i', $u_agent))
	        $platform = 'linux';

	    if (preg_match('/macintosh|mac os x/i', $u_agent))
	        $platform = 'mac';

	    if (preg_match('/windows|win32/i', $u_agent))
	        $platform = 'windows';


	    // get browser info
	    if(preg_match('/MSIE/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) {
	        $bname	= 'Internet Explorer';
	        $ub		= 'MSIE';
	    }

	    if(preg_match('/Firefox/i',$u_agent)) {
	        $bname	= 'Mozilla Firefox';
	        $ub		= 'Firefox';
	    }

	    if(preg_match('/Chrome/i',$u_agent)) {
	        $bname	= 'Google Chrome';
	        $ub		= 'Chrome';
	    }

		if(preg_match('/Safari/i',$u_agent) && !preg_match('/Chrome/i',$u_agent)) {
	        $bname	= 'Apple Safari';
	        $ub		= 'Safari';
	    }

	    if(preg_match('/Opera/i',$u_agent)) {
	        $bname	= 'Opera';
	        $ub		= 'Opera';
	    }

	    if(preg_match('/Netscape/i',$u_agent)) {
	        $bname	= 'Netscape';
	        $ub		= 'Netscape';
	    }

	    // finally get the correct version number
	    $known		= array('Version', $ub, 'other');
	    $pattern	= '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

	    if (!preg_match_all($pattern, $u_agent, $matches)) {
	        // we have no matching number just continue
	    }

	    // see how many we have
	    $i = count( $matches['browser'] );
	    if ($i != 1) {
	        //we will have two since we are not using 'other' argument yet
	        //see if version is before or after the name
	        if (strripos( $u_agent, 'Version' ) < strripos($u_agent,$ub)){
	            $version= $matches['version'][0];
	        }
	        else {
	            $version= $matches['version'][1];
	        }
	    }
	    else {
	        $version= $matches['version'][0];
	    }

	    // check if we have a number
	    if ($version == null || $version == '' )
	    	$version = '?';

	    return array(
	        'userAgent'	=> $u_agent,
	        'name'		=> $bname,
	        'version'	=> $version,
	        'platform'	=> $platform,
	        'pattern'	=> $pattern
	    );
	}

	public function site_redirect(){
		$redirect_val = get_option( 'redirect' );
		wp_redirect( $redirect_val);
		exit;
	}
}

// Instantiate our class
$WP_Bouncer = new visitor();