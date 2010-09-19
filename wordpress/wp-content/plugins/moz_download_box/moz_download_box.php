<?php
/*
Plugin Name: Mozilla Download Box
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 0.0.1
Author: Tomer Cohen
Author URI: http://tomercohen.com
License: A "Slug" license name e.g. GPL2
*/


/// Activation functions 
register_activation_hook(__FILE__, 'moz_download_box_activation');
add_action('moz_download_box_hourly_event', 'moz_download_box_prepare_tables');


/// Shortcode hooks
add_shortcode('moz-download-box', 'moz_download_box_button_shortcode');


function moz_download_box_activation() {
	add_option('moz_download_box_last_fetch', '0');

	moz_download_box_construct_table();
//	moz_download_box_update_table();

	wp_schedule_event(time(), 'hourly', 'moz_download_box_hourly_event');
}

function moz_download_box_prepare_tables() {
	// do something every hour
	update_option('moz_download_box_last_fetch', time());
//	moz_download_box_update_table();
	
}

/// Deactivation functions 
register_deactivation_hook(__FILE__, 'moz_download_box_deactivation');

function moz_download_box_deactivation() {
	wp_clear_scheduled_hook('moz_download_box_hourly_event');
	delete_option('moz_download_box_last_fetch');
	moz_download_box_destruct_table();
}

/// Admin menus 
add_action('admin_menu', 'moz_download_box_plugin_menu');

function moz_download_box_plugin_menu() {
  add_options_page('Moz Download Box Plugin Options', 'MozDownloadBox', 'manage_options', 'Moz_Download_Box', 'moz_download_box_plugin_options');
}

function moz_download_box_plugin_options() {

  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }
  
    moz_download_box_update_table('http://www.mozilla.com/includes/product-details/json/firefox_primary_builds.json', 'http://www.mozilla.com/includes/product-details/json/firefox_versions.json');
    
    
//  $timezone_format = _x('Y-m-d G:i:s', 'timezone date format');

  echo '<div class="wrap">';
  echo '<p dir="ltr">Last fetch of product-details: '. date_i18n(get_option( 'time_format') /*__('F j, Y g:i a')*/, get_option('moz_download_box_last_fetch'), true) .'</p>';
  
echo (moz_download_box_draw_buttons('LATEST_FIREFOX_VERSION', 'he'));
  
  echo ('<link rel="stylesheet" type="text/css" href="http://127.0.0.1/~tomer/moz-wp3/wp-content/plugins/moz_download_box/style.css" />');
  
  echo '</div>';
  

}

function moz_download_box_draw_buttons ($tag = NULL, $locale = NULL, $os = NULL) {
	$list  = moz_download_box_query($tag, $locale, $os);
	
	add_action('wp_print_styles', 'moz_download_box_add_stylesheet');

	/* TBD: if there is items */
	$out =  count($list) ." items ";
	if (count($list) > 0)
		foreach ($list as $item) {
			if (!$item['unavailable'] == 1)
				$out .= moz_download_box_draw_button($item['tag'], $item['locale'], $item['platform'], $item['version'], $item['filesize']);
		}
	else $out .= __('Unable to locate download links...');
	
	$out .= 'end';
	return $out;
}

    function moz_download_box_add_stylesheet() {
        $myStyleUrl  = WP_PLUGIN_URL . '/moz_download_box/style.css';
        $myStyleFile = WP_PLUGIN_DIR . '/moz_download_box/style.css';
        echo ("$myStyleUrl $myStyleFile ");
        if ( file_exists($myStyleFile) ) {
	        echo ("exists");
            wp_register_style('moz_download_box_stylesheet', $myStyleUrl);
            wp_enqueue_style( 'moz_download_box_stylesheet');
        }
}

function moz_download_box_draw_button($tag = NULL, $locale = NULL, $os = NULL, $version = NULL, $filesize = NULL) {
  $class = 'mozilla-product-download ';
  if ($tag) $class    .= $tag . ' ';
  if ($locale) $class .= "locale-$locale ";
  if ($os) $class     .= "os-$os ";
  if ($version) $class .= "version-$version ";

  $out = "<p class='$class'><a href='#'><strong>". __('Download') ." ". __('Firefox') ."</strong> <em>";
  if ($version) $out  .= __('version') ." ". $version .", ";
  if ($locale) $out   .= "$locale, ";
  if ($os) $out       .= "$os, ";
  if ($filesize) $out .= "$filesize". __('MB');
  $out .= "</em></a></p>";

  return $out;
    
}

function moz_download_box_construct_table() {
  global $wpdb;
  
  $sql = "CREATE TABLE " . $wpdb->prefix . "moz_download_box (
  	  tag         VARCHAR(40) NOT NULL,
	  locale      VARCHAR(7) NOT NULL,
	  version     VARCHAR(7) NOT NULL,
	  platform    VARCHAR(10) NOT NULL,
	  filesize    VARCHAR(10) NOT NULL,
	  unavailable tinyint(1)	);";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);

}

function moz_download_box_destruct_table() {
  global $wpdb;
  
  $wpdb->query("DROP TABLE {$wpdb->prefix}moz_download_box};");

}


function moz_download_box_fetch_tag_information($url = 'http://www.mozilla.com/includes/product-details/json/firefox_versions.json') {
  return json_decode(file_get_contents($url), true);
}

function moz_download_box_fetch_version_information($url = 'http://www.mozilla.com/includes/product-details/json/firefox_primary_builds.json') {
  return json_decode (file_get_contents($url), true);
}


/*
function moz_download_box_update_table() {
  global $wpdb;
  
  
//  if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}moz_download_box'") != $wpdb->prefix . 'moz_download_box') 
    moz_download_box_construct_table();

  
  $version_tag = array_flip(moz_download_box_fetch_tag_information());
  $data        =            moz_download_box_fetch_version_information();
  

  
  foreach ($data as $locale => $locale_data) {
  //  if ($locale == 'he') //var_dump ($locale_data);
    foreach ($locale_data as $version => $version_data) {
      if (isset($version_tag[$version]))
        foreach ($version_data as $platform => $platform_data) {
//          echo ("<td>TAG={$version_tag[$version]}  <td>LOCALE=$locale  <td>VERSION=$version  <td>PLATFORM=$platform  <td>FILESIZE={$platform_data['filesize']}");
//          if (isset($platform_data['unavailable']) && $platform_data['unavailable'] == 'true') echo ("<td>UNAVAILABLE");
  
           if (isset($platform_data['unavailable']) && $platform_data['unavailable'] == 'true') $unavailable = true; 
           else $unavailable = false; 
           
           
           
           
           $prep = $wpdb->prepare( "
	INSERT INTO {$wpdb->prefix}moz_download_box
	      ( tag, locale, version, platform, filesize, unavailable )
	VALUES ( %s,   %s,     %s,       %s,        %s,         %d)", 
        $version_tag[$version], $locale, $version, $platform, $platform_data['filesize'], $unavailable );
          $wpdb->query( $prep );

          
//          if (isset($platform_data['unavailable']) && $platform_data['unavailable'] == 'true') echo ("<td>UNAVAILABLE");
        }
    }
  }
}*/

function moz_download_box_update_table($primary_builds_url, $versions_url) {
	global $wpdb;
	
	if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}moz_download_box'") != $wpdb->prefix . 'moz_download_box') 
		moz_download_box_construct_table();

	$version_tags = array_flip(moz_download_box_fetch_tag_information($versions_url));
	$data         =            moz_download_box_fetch_version_information($primary_builds_url);
	
	$items = moz_download_box_fetch_json ($version_tags, $data);
	unset ($data);
	
	if (count($items) > 0) 	foreach ($items as $tag => $items) {
//		$sql = "DELETE FROM {$wpdb->prefix}moz_download_box WHERE tag='$tag'; "; 
//		$wpdb->query($sql);
		
		$sql = "INSERT INTO {$wpdb->prefix}moz_download_box ( tag, locale, version, platform, filesize, unavailable )	VALUES ";
		
		foreach ($items as $item) {

// Single queries are evil
//			$sql = $wpdb->prepare(
//				"INSERT INTO {$wpdb->prefix}moz_download_box ( tag, locale, version, platform, filesize, unavailable )
//									VALUES ( %s,   %s,     %s,       %s,        %s,         %d);", 
//				$item['tag'], $item['locale'], $item['version'], $item['os'], $item['filesize'], $item['unavailable']);
//				$wpdb->query($sql);

			$sql .= $wpdb->prepare ("( %s,   %s,     %s,       %s,        %s,         %d),",$item['tag'], $item['locale'], $item['version'], $item['os'], $item['filesize'], $item['unavailable']);
		}

		$wpdb->query("DELETE FROM {$wpdb->prefix}moz_download_box WHERE tag='$tag';");
		$sql[strlen($sql)-1] = ';'; // Replace last comma with semicolon. 
		$wpdb->query($sql);
	}
	else echo ("nothing has been fetched. Sorry... :(\n");	
}
	
function moz_download_box_fetch_json($version_tag = array(), $data = array()) {

	$fetch = array();
	
	foreach ($data as $locale=>$item) {
		foreach ($item as $version=>$item) {
			foreach ($item as $platform=>$item) {
				if (isset($item['unavailable']) && $item['unavailable'] == 'true') $unavailable = 1; 
				else $unavailable = 0; 
				
				if (isset($version_tag[$version])) // Make sure we keep only data which has up-to-date tag.
					$fetch[$version_tag[$version]][] = array ('tag' => $version_tag[$version], 'version' => $version, 'locale' =>$locale, 'os'=>$platform, 
						  'filesize' => $item['filesize'], 'unavailable' => $unavailable);
//				echo ($version_tag[$version] ."	$locale	$version	$platform	". $item['filesize'] .'	'. $unavailable ."\n");
			}
		}
	}

//	print_r($fetch);
	return ($fetch);
}

function moz_download_box_query ($tag = NULL, $locale = NULL, $os = NULL) {
    global $wpdb;

    $query = "SELECT * FROM {$wpdb->prefix}moz_download_box WHERE ";
    if ($tag) $query .= "tag='$tag' AND ";
    if ($locale) $query .= "locale='$locale' AND ";
    if ($os) $query .= "os='$os' AND ";
    $query .= '1=1;';
    
    echo $query;

	return $wpdb->get_results($query, ARRAY_A); 
}




function moz_download_box_button_shortcode($atts, $content=null, $code="") {
   // $atts    ::= array of attributes
   // $content ::= text within enclosing form of shortcode element
   // $code    ::= the shortcode found, when == callback name
   // examples: [my-shortcode]
   //           [my-shortcode/]
   //           [my-shortcode foo='bar']
   //           [my-shortcode foo='bar'/]
   //           [my-shortcode]content[/my-shortcode]
   //           [my-shortcode foo='bar']content[/my-shortcode]
  
  extract(shortcode_atts(array(
    'os' => NULL,
    'locale' => NULL,
    'tag' => 'LATEST_FIREFOX_VERSION',
  ), $atts));
  
  echo ("  moz_download_box_draw_buttons($tag, $locale, $os);");
  
  $out = moz_download_box_draw_buttons($tag, $locale, $os);
//  $out = moz_download_box_draw_buttons();
  
  return $out;
}

