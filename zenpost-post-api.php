<?php
/**
 * Plugin Name: Zenpost
 * Plugin URI:       https://wordpress.org/plugins/zenpost/
 * Description:       Zenpost delivers your assignment directly into your WordPress site automatically for you
 * Version:           1.1.3
 * Author:            ZenPost
 * Author URI:        https://zenpost.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       zenpost
 * Domain Path:       /languages
 */
require_once( dirname( __FILE__ ) . '/class-hmac-auth.php' );
require_once( dirname( __FILE__ ) . '/class-zenpost-post-api.php' );
require_once( dirname( __FILE__ ) . '/Options.class.php' );
require_once( dirname( __FILE__ ) . '/class-zenpost-post-publish.php' );
add_action( 'rest_api_init', function () {
	$secret 	= Zenpost_Options::get_secret();
	$auth 		= new Zenpost_HMAC_Auth( $secret );
	( new zenpost_post_api( $auth ) )->register_routes();
} );
new Zenpost_Options();

add_filter('plugin_action_links_'.plugin_basename(__FILE__),'zenpost_add_action_links' );
function zenpost_add_action_links ( $links ) {
	 $mylinks = array(
	 	'<a href="' . admin_url( 'options-general.php?page=zenpost' ) . '">Settings</a>',
	 );
	return array_merge( $links, $mylinks );
}

//admin!@#$%%$#@!
//add_shortcode( 'temp_meta_display','zen_debug_post_meta');
function zen_debug_post_meta(){
	ob_start();
	$new_post_id=3224;
	$meta = get_post_meta($new_post_id); 
	$att=get_attached_media('image',$new_post_id);
	$thepostmeta 	=	get_post_meta( $new_post_id,'thepostmeta',true);
	echo '<pre><code>';
	//print_r( $att);
	//print_r($meta);
	//
	echo $thepostmeta['attachments']['facebookimage'];
	echo $thepostmeta['attachments']['twitterimage'];
	print_r($thepostmeta);

	echo '</code></pre>';

	$o=ob_get_contents();
	ob_end_clean();
	return $o;
	}