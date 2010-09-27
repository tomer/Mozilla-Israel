<?php
/*
Plugin Name: RSS Shortcode
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 0.0.1
Author: Tomer Cohen
Author URI: http://tomercohen.com
License: A "Slug" license name e.g. GPL2
*/


add_shortcode('feed', 'post_feed_handler');


function post_feed_handler($atts, $content=null, $code="") {
   // $atts    ::= array of attributes
   // $content ::= text within enclosing form of shortcode element
   // $code    ::= the shortcode found, when == callback name
   // examples: [my-shortcode]
   //           [my-shortcode/]
   //           [my-shortcode foo='bar']
   //           [my-shortcode foo='bar'/]
   //           [my-shortcode]content[/my-shortcode]
   //           [my-shortcode foo='bar']content[/my-shortcode]

  $out .= ("You have just added \"$code\" to the post body. My content is <em>$content</em> and I got the following parameters - \n");
  var_dump ($atts);
  
  extract(shortcode_atts(array(
    'url' => '',
    'max_items' => '50',
    'title' => '',
    'summary' => false,     // false - don't display, true - display, integer number - max length
    'description' => false, // false - don't display, true - display, integer number - max length
  ), $atts));

	//return "foo = {$foo}";

  
  // Get RSS Feed(s)
  include_once(ABSPATH . WPINC . '/feed.php');

  // Get a SimplePie feed object from the specified feed source.
  $rss = fetch_feed($url);
  if (!is_wp_error( $rss ) ) { // Checks that the object is created correctly 
    // Figure out how many total items there are, but limit it to 5. 
    $maxitems = $rss->get_item_quantity($max_items); 

    // Build an array of all the items, starting with element 0 (first element).
    $rss_items = $rss->get_items(0, $maxitems); 
  }

  $out .= '<div class="widget-container widget_rss">';
  if ($title != '') {
    if ($title == '%') $out .= "<h3 class='widget-title'>{$rss->get_title()}</h3>";
    else               $out .= '<h3 class="widget-title">$title</h3>';
  }
  $out .= '<ul>';
  if ($maxitems == 0) $out .= '<li>No items.</li>';
  else
    // Loop through each feed item and display each item as a hyperlink.
    foreach ( $rss_items as $item ) { 
      $out .= '<li>';
      $out .= "<a href='{$item->get_permalink()}' class='rsswidget' title='{$item->get_date('j F Y | g:i a')}'>{$item->get_title()}</a>";
      $out .= '</li>';
    }
  $out .= '</ul></div>';
  
  
  return $out;
}
