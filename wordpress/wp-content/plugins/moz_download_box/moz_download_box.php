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
//add_action('moz_download_box_hourly_event', 'moz_download_box_prepare_tables');
add_action('moz_download_box_fetch_feeds', 'moz_download_box_update_table');
add_action('wp_print_styles', 'moz_download_box_add_stylesheet');
    
/// Shortcode hooks
add_shortcode('moz-download-box', 'moz_download_box_button_shortcode');


function moz_download_box_activation() {
	//add_option('moz_download_box_last_fetch', '0');

	$options = array (
		'thunderbird' => array(
			'download_url' => '//http://www.mozillamessaging.com/thunderbird/download/?product={product}-{version}&os={platform}&lang={locale}',
			'tags_url' => 'http://www.mozilla.com/includes/product-details/json/thunderbird_versions.json',
			'builds_url' => 'http://www.mozilla.com/includes/product-details/json/thunderbird_primary_builds.json',
			'timestamp' => 0),
		'firefox' => array(
			'download_url' => 'http://www.mozilla.com/products/download.html?product={product}-{version}&os={platform}&lang={locale}',
//			'enabled' => false, 
			'tags_url' => 'http://www.mozilla.com/includes/product-details/json/firefox_versions.json',
			'builds_url' => 'http://www.mozilla.com/includes/product-details/json/firefox_primary_builds.json',
			'timestamp' => 0));
			
	
	
//add_option('moz_download_box_feeds', serialize($options));  // Default configuration
	add_option('moz_download_box_feeds', $options);  // Default configuration

	
	moz_download_box_construct_table();
//	moz_download_box_update_table();

//	wp_schedule_event(time(), 'hourly', 'moz_download_box_hourly_event');
//	wp_schedule_event(time(), 'hourly', 'moz_download_box_fetch_feeds');

//	moz_download_box_reschedule_fetch();
}

/*function moz_download_box_prepare_tables() {
	// do something every hour
	update_option('moz_download_box_last_fetch', time());
//	moz_download_box_update_table();
	
}*/

function moz_download_box_show_schedules() {
//	$list = unserialize(get_option('moz_download_box_feeds'));
	$list = get_option('moz_download_box_feeds');

		
	if (count($list) > 0) {

		echo ("<table>");
		echo ("  <tr>\n    <th>Feed</th>\n    <th>Last</th>\n    <th>Next</th>\n  </tr>");
		foreach ($list as $feed=>$item) {
			echo ("  <tr>\n    <td>$feed</td>\n    <td>". date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $item['timestamp']) ."</td>\n");
			echo ("    <td>". date_i18n(get_option('date_format') . ' ' . get_option('time_format'), wp_next_scheduled('moz_download_box_fetch_feeds', array($feed))) ."</td>\n");
			echo ("  </tr>");
		}
		echo ("</table>");
	}
	else {
		echo ("<p>No data been found.</p>");
	}
}

function moz_download_box_reschedule_fetch() {
	//wp_clear_scheduled_hook('moz_download_box_hourly_event');
//	$options = unserialize(get_option('moz_download_box_feeds'));
	$options = get_option('moz_download_box_feeds');
	wp_clear_scheduled_hook('moz_download_box_fetch_feeds');
	
	foreach ($options as $name=>$feed) {
		if (isset($feed['enabled']) && $feed['enabled'] == true) {
			if (isset($feed['recurrance'])) {
				switch ($feed['recurrance']) {
					case 'daily':
						moz_download_box_schedule($name, 'daily', time() + rand(0, 60 * 60 * 24)); break;
					case 'twicedaily':
						moz_download_box_schedule($name, 'twicedaily', time() + rand(0, 60 * 60 * 12)); break;
					case 'hourly':
					default: 
						moz_download_box_schedule($name, 'hourly', time() + rand(0, 60 * 60)); break;
				}
			}
			else	moz_download_box_schedule($name, 'hourly', time() + rand(0, 60 * 60)); break;
		}
	}
}

function moz_download_box_schedule($feed, $recurrance = 'daily', $timestamp) {
	//wp_schedule_event($timestamp, $recurrance, 'moz_download_box_fetch_json_feeds', array($feed));
	wp_schedule_event($timestamp, $recurrance, 'moz_download_box_fetch_feeds', array($feed));
}


/// Deactivation functions 
register_deactivation_hook(__FILE__, 'moz_download_box_deactivation');

function moz_download_box_deactivation() {
	//wp_clear_scheduled_hook('moz_download_box_hourly_event');
	wp_clear_scheduled_hook('moz_download_box_fetch_feeds');
	delete_option('moz_download_box_last_fetch'); // TBD: Remove me
	delete_option('moz_download_box_feeds');
	moz_download_box_destruct_table();
}

/// Admin menus 
add_action('admin_menu', 'moz_download_box_plugin_menu');

function moz_download_box_plugin_menu() {
  add_options_page('Moz Download Box Plugin Options', 'MozDownloadBox', 'manage_options', 'Moz_Download_Box', 'moz_download_box_plugin_options');
}

function moz_download_box_show_feeds_form() {
	if (isset($_POST['manage_feed']) && isset($_POST['feed'])) moz_download_box_admin_manage_feeds();
	else {
	
//		$list = unserialize(get_option('moz_download_box_feeds'));
		$list = get_option('moz_download_box_feeds');

		//echo ("list = ". get_option('moz_download_box_feeds') ." dump "); var_dump($list);
	
		//echo ('<form method="post" action="http://localhost/~tomer/1/form.php" name="manage_feeds" id="manage_feeds">');
		echo ('<form method="post" action="" name="manage_feeds" id="manage_feeds">');
		if (count($list) > 0) foreach ($list as $feed=>$item) {
			echo ("<fieldset><legend>Feed: $feed</legend>");
			echo ("<label for='feed[$feed][tags]'>Tags URL:</label    ><input name='feed[$feed][tags]'   id='feed[$feed][tags]' type='text' value='{$item['tags_url']}' />");
			echo ("<label for='feed[$feed][builds]'>Builds URL:</label    ><input name='feed[$feed][builds]'   id='feed[$feed][builds]' type='text' value='{$item['builds_url']}' />");
			
			echo ("<label for='feed[$feed][download_url]'>Template for download URL:</label    ><input name='feed[$feed][download_url]'   id='feed[$feed][download_url]' type='text' value='{$item['download_url']}' />");
			
	//		echo ("<label for='feed[$feed][builds]'>Builds URL:</label><input   name=\"feed[$feed][builds]\" id=\"feed[$feed .'][builds]\" type="'text' value="'. $item['builds_url'] .'" />');

			//echo ("<input name='feed[$feed][delete]' type='button' onclick='confirm(\"Are you sure?\");' value='Delete' />");
			
			echo ("<p><input type='checkbox' name='feed[$feed][enabled]' id='feed[$feed][enabled]' "); 
			if (wp_next_scheduled('moz_download_box_fetch_feeds', array($feed))) echo ('checked=\'checked\'');
			echo (' />');

			echo ("<label for='feed[$feed][enabled]'>Enabled?</label> (Next fetch: ". wp_next_scheduled('moz_download_box_fetch_feeds', array($feed)) .") </p>");

			echo ("<p><input type='checkbox' name='feed[$feed][delete]' id='feed[$feed][delete]' /><label for='feed[$feed][delete]'>Delete?</label> Warning: There is no undo.</p>");


			echo ('</fieldset>');
		}
		echo ('<fieldset><legend>Add new feed</legend>');
		echo ('<label for="feed[new][name]">Feed name:</label   ><input name="feed[new][name]"   id="feed[new][name]"   type="text" value="" />');
		echo ('<label for="feed[new][tags]">Tags URL:</label    ><input name="feed[new][tags]"   id="feed[new][tags]"   type="text" value="" />');
		echo ('<label for="feed[new][builds]">Builds URL:</label><input name="feed[new][builds]" id="feed[new][builds]" type="text" value="" />');
		echo ('<label for="feed[new][download_url]">Builds URL:</label><input name="feed[new][download_url]" id="feed[new][download_url]" type="text" value="" />');
		echo ('</fieldset>');
		
		echo ('<input type="submit" name="manage_feed" /></from>');
	}
}

function moz_download_box_admin_manage_feeds() {
//	$list = unserialize(get_option('moz_download_box_feeds'));
	$list = get_option('moz_download_box_feeds');

	//var_dump ($_POST);
	
	if (isset($_POST['manage_feed']) && isset($_POST['feed'])) {
		foreach ($_POST['feed'] as $tag=>$item) {
			if ($tag == 'new') {
				if (isset($item['tags']) && $item['tags'] != '' && $item['builds'] != '' && $item['name'] != '') {
					echo ("<p>Creating new feed {$item['name']}...</p>");
					$list[$item['name']]['tags_url'] = $item['tags'];
					$list[$item['name']]['builds_url'] = $item['builds'];
					$list[$item['name']]['download_url'] = $item['download_url'];
					$list[$item['name']]['timestamp'] = 0;
				}
			}
			else {
				if (isset($item['delete'])) {
					echo ("<p>Unscheduling $tag...</p>");
					wp_unschedule_event(wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)), 'moz_download_box_fetch_feeds', array($tag));
					echo ("<p>Deleting <em>$tag</em>... :(</p>");
					unset ($list[$tag]);
				}
				else {
					if ($list[$tag]['tags_url'] != $item['tags']) {
						echo ("<p>Updating <em>$tag</em> tags url...</p>");
						$list[$tag]['tags_url'] = $item['tags'];
					}
					if ($list[$tag]['builds_url'] != $item['builds']) {
						echo ("<p>Updating <em>$tag</em> builds url...</p>");
						$list[$tag]['builds_url'] = $item['builds'];
					}
					
					if ($list[$tag]['download_url'] != $item['download_url']) {
						echo ("<p>Updating <em>$tag</em> download url...</p>");
						$list[$tag]['download_url'] = $item['download_url'];
					}
					// If is scheduled and not enabled
					if (!isset($item['enabled']) && wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)) > 0) {
//						echo ("<p>(". wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)) .")</p>"); var_dump (wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)));
						echo ("<p>Unscheduling $tag...</p>");
						//wp_clear_scheduled_hook('moz_download_box_hourly_event', array($tag));
						wp_unschedule_event(wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)), 'moz_download_box_fetch_feeds', array($tag));
//						echo ("<p>(". wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)) .")</p>"); var_dump (wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)));
					}
					else if (isset($item['enabled']) && !wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)) > 0) {
						moz_download_box_schedule($tag, 'hourly', time() + rand(0, 60 * 60));
						echo ("<p>Scheduling $tag (". wp_next_scheduled('moz_download_box_fetch_feeds', array($tag)) .")...</p>");
					}
				}
			}
			
		}
//	var_dump ($list);
//	update_option('moz_download_box_feeds', serialize($list));
	update_option('moz_download_box_feeds', $list);

	}
	else echo ("Nothing to do.");
}

function moz_download_box_plugin_options() {

  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }
  
  if (isset($_REQUEST['action'])) $action = $_REQUEST['action'];
  else $action=NULL;
  
  switch ($action) {
	  case 'reschedule': 
		echo ("<p>Rescheduling all feeds...</p>");
		moz_download_box_reschedule_fetch();   
		moz_download_box_show_schedules();
		break;
	  case 'forcefetch':
		echo ("<p>Fetching all feeds...</p>");
		moz_download_box_force_fetch_all();
		break;
	  case 'destroy':
	    echo ("<p>Destroying all feeds...</p>");
	    moz_download_box_deactivation();
	    moz_download_box_activation();
	    break;
	  default: 
		moz_download_box_admin_display();
		break;
  }
}

function moz_download_box_admin_display() {
    //moz_download_box_update_table('http://www.mozilla.com/includes/product-details/json/firefox_primary_builds.json', 'http://www.mozilla.com/includes/product-details/json/firefox_versions.json');
    
    //moz_download_box_update_table('firefox');
    
    
//  $timezone_format = _x('Y-m-d G:i:s', 'timezone date format');

  echo '<div class="wrap" dir="ltr">';
  
  echo ('<fieldset><legend>Schedules</legend>');
  moz_download_box_show_schedules();
  echo ('<form name="reschedule" method="post"><button name="action" value="reschedule">Reschedule All feeds</button></form></fieldset>');
  echo ('<form name="force-fetch" method="post"><button name="action" value="forcefetch">Force fetch</button></form></fieldset>');
  echo ('<form name="destroy-feeds" method="post"><button name="action" value="destroy">Destoy data</button></form></fieldset>');
 
//  echo '<p dir="ltr">Last fetch of product-details: '. date_i18n(get_option( 'time_format') /*__('F j, Y g:i a')*/, get_option('moz_download_box_last_fetch'), true) .'</p>';

  moz_download_box_show_feeds_form();

  if ((isset($_REQUEST['action'])) && ($_REQUEST['action'] == 'debug')) {
	  echo ("$print => <pre>");
	  $print = get_option('moz_download_box_feeds');
	  //var_dump(unserialize($print));
	  echo ("</pre>");
  }
  
/*  var_dump(moz_download_box_get_feed_details('firefox'));
  echo ("</pre>");*
  

/*  echo (moz_download_box_draw_buttons('LATEST_FIREFOX_VERSION', 'he'));
  echo ('<link rel="stylesheet" type="text/css" href="http://127.0.0.1/~tomer/moz-wp3/wp-content/plugins/moz_download_box/style.css" />');*/
  
  moz_download_box_show_tags();
  
  echo ("</div>");
  

}

function moz_download_box_platform_keyword($platform) {
	switch ($platform) {
		case 'Linux': return 'linux'; break;
		case 'OS X': return 'osx'; break;
		case 'Windows': return 'win'; break;
		default: return false;
	}
	return false;
}

function moz_download_box_tag2product ($tag) {
	$options = get_option('moz_download_box_feeds');
	foreach ($options as $feed=>$item) {
		foreach ($item['tags'] as $feed_tag => $version) {
			if ($feed_tag == $tag) return $feed; 
		}
	}
	return false;
}

function moz_download_box_draw_buttons ($tag = NULL, $locale = NULL, $os = NULL) {
	$list  = moz_download_box_query($tag, $locale, $os);
	$options = get_option('moz_download_box_feeds');

	add_action('wp_print_styles', 'moz_download_box_add_stylesheet');
	
	$out = '';
	
	if (count($list) > 0)
		foreach ($list as $key=>$item) {
			if (!$item['unavailable'] == 1) {
				$product = moz_download_box_tag2product($tag);
				$template_url = $options[$product]['download_url']; 
				
				$platform = moz_download_box_platform_keyword($item['platform']);
				$download_url = moz_download_box_download_link($template_url, $item['locale'], $platform, $item['version'], $product);
				
				$out .= moz_download_box_draw_button($item['tag'], $item['locale'], $item['platform'], $item['version'], $item['filesize'], $product, $download_url);
			}
		}
	else $out .= __('Unable to locate download links...');
	
//	$out .= 'end';
	return $out;
}

    function moz_download_box_add_stylesheet() {
        $myStyleUrl  = WP_PLUGIN_URL . '/moz_download_box/style.css';
        $myStyleFile = WP_PLUGIN_DIR . '/moz_download_box/style.css';
        echo ("$myStyleUrl $myStyleFile ");
        if ( file_exists($myStyleFile) ) {
            wp_register_style('moz_download_box_stylesheet', $myStyleUrl);
            wp_enqueue_style( 'moz_download_box_stylesheet');
        }
}

//				$download_url = moz_download_box_download_link($template_url, $item['locale'], $item['platform'], $item['version'], $item);
function moz_download_box_download_link ($template = 'http://download.mozilla.org/?product={product}-{version}&os={platform}&lang={locale}',  $locale = 'en-US', $os='linux', $version = '3.6.10', $product = 'firefox') {
//http://www.mozillamessaging.com/thunderbird/download/?product=thunderbird-3.1.4&os=linux&lang=en-US
//http://www.mozilla.com/products/download.html?product=firefox-3.6.10&os=linux&lang=en-US
	$keywords =     array('{platform}', '{locale}', '{product}', '{version}');
	$replacements = array($os,          $locale,    $product,    $version);
	
	return (str_replace($keywords, $replacements, $template));
}


function moz_download_box_draw_button($tag = NULL, $locale = NULL, $os = NULL, $version = NULL, $filesize = NULL, $product = NULL, $download_url = '#') {	
  $class = 'mozilla-product-download ';
  if ($tag) $class     .= $tag . ' ';
  if ($locale) $class  .= "locale-$locale ";
  if ($os) $class      .= 'os-'. moz_download_box_platform_keyword($os)  .' ';
  if ($version) $class .= "version-$version ";
  if ($product) $class .= "product-$product ";

  $out = "<p class='$class'><a href='$download_url'><strong>". __('Download') ." ". __('Firefox') ."</strong> <em>";
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
  
  $wpdb->query("DROP TABLE {$wpdb->prefix}moz_download_box;");

}


function moz_download_box_fetch_tag_information($url = 'http://www.mozilla.com/includes/product-details/json/firefox_versions.json') {

//	echo ("moz_download_box_fetch_tag_information($url);");//REMOVE ME
	
  return json_decode(file_get_contents($url), true);
}

function moz_download_box_fetch_version_information($url = 'http://www.mozilla.com/includes/product-details/json/firefox_primary_builds.json') {

//	echo ("moz_download_box_fetch_version_information($url);"); //REMOVE ME

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

function moz_download_box_get_feed_details($feed) {
//	$out = unserialize(get_option('moz_download_box_feeds'));
	$out = get_option('moz_download_box_feeds');
	
	if (isset($out[$feed])) return $out[$feed];
	else return false;
}
function moz_download_box_update_feed_timestamp($feed, $timestamp) {
	$out = get_option('moz_download_box_feeds');
//	$out = unserialize(get_option('moz_download_box_feeds'));

//	echo ("==="); var_dump(get_option('moz_download_box_feeds')); // debug 

	if (isset($out[$feed])) { 
		$out[$feed]['timestamp'] = $timestamp;
		update_option('moz_download_box_feeds', $out);
//		update_option('moz_download_box_feeds', serialize($out));
	}
	else return false; 
}

function moz_download_box_show_tags() {
//	$list = unserialize(get_option('moz_download_box_feeds'));
	$list = get_option('moz_download_box_feeds');
	
	$tags = array();
	
	foreach ($list as $item) {
		if (isset($item['tags']) && count($item['tags']) > 0) $tags = array_merge($item['tags'], $tags);
	}
	
	if (count($tags) > 0) {
		echo ("<table><tr><th>Feed</th><th>Tag</th><th>Version</th></tr>");
		foreach ($list as $feed=>$item) {
			if (isset($item['tags']) && count($item['tags']) > 0)
				foreach ($item['tags'] as $tag=>$version) {
					echo ("<tr><td>$feed</td><td>$tag</td><td>$version</td></tr>");
			}
		}
		echo ("</table>");
	}
	else 
		echo ("No tags has been fetched... yet.");
}

function moz_download_box_update_feed_tags($feed, $tags) {
//	$options = unserialize(get_option('moz_download_box_feeds'));
	$options = get_option('moz_download_box_feeds');
	
	$options[$feed]['tags'] = $tags; 
	
//	update_option('moz_download_box_feeds', serialize($options));
	update_option('moz_download_box_feeds', $options);

}

function moz_download_box_force_fetch_all() {
//	$list = unserialize(get_option('moz_download_box_feeds'));
	$list = get_option('moz_download_box_feeds');

	
	foreach ($list as $feed=>$data) 
		moz_download_box_update_table($feed);
}

function moz_download_box_update_table($feed) {
	global $wpdb;

	$feed_info = moz_download_box_get_feed_details($feed);
	
	if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}moz_download_box'") != $wpdb->prefix . 'moz_download_box') 
		moz_download_box_construct_table();

//print("==="); var_dump($feed_info); //debug

	$tags = moz_download_box_fetch_tag_information($feed_info['tags_url']);
	$data         =            moz_download_box_fetch_version_information($feed_info['builds_url']);
	
	if (count($tags) < 1 || count($data) < 1) {
		echo ("unable to fetch feed data!");
	}
	else {
		$version_tags = array_flip($tags);

		moz_download_box_update_feed_tags($feed, $tags);
	
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
		
			moz_download_box_update_feed_timestamp($feed, time());
		}
		else echo ("nothing has been fetched. Sorry... :(\n");
	}
}
	
function moz_download_box_fetch_json($version_tag = array(), $data = array()) {

	$fetch = array();
	
	foreach ($data as $locale => $item) {
		foreach ($item as $version => $item) {
			foreach ($item as $platform => $item) {
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
    
//    echo $query;

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
  
//  echo ("  moz_download_box_draw_buttons($tag, $locale, $os);");
  
  $out = moz_download_box_draw_buttons($tag, $locale, $os);
//  $out = moz_download_box_draw_buttons();
  
  return $out;
}


