<?php
class Zenpost_Post_Publish{
	/**
	 * Base path of this API controller.
	 *
	 * @const string
	 */
	const API_BASE = 'zenpost';
	/**
	 * The authenticator.
	 *
	 * @var object
	 */
	private $authenticator;
	/**
	 * Initialize a new zenpost_post_api.
	 *
	 * @param object $authenticator the authenticator
	 */
	public static function init(){
        $class = __CLASS__;
        new $class;
    }
	function __construct() {
		add_action( 'publish_post', array($this,'on_post_publish'), 10, 2 );
	}
	function on_post_publish($post_id){
	    global $wpdb;
		$post 				=	get_post($post_id);
		$assignment_id 		=	get_post_meta($post_id,'assignment_id',true);
		$secret_key 		=	get_post_meta($post_id,'secret_key',true);
		$signature 			=	get_post_meta($post_id,'signature',true);
		$zenpost_env 		=	get_post_meta($post_id,'zenpost_env',true);
		$zenpost_url 		=	get_post_meta($post_id,'zenpost_url',true);
		$assignmentId 		=	get_post_meta($post_id,'assignment_id',true);
		$secretKey 			=	Zenpost_Options::get_secret(); //get_post_meta($post_id,'secret_key',true);

		$publishedUrl 		=	get_permalink( $post_id);
		$publishedAt 		=	strtotime($post->post_date_gmt);
		$lastModifiedAt 	=	strtotime($post->post_modified_gmt);
		
		$authorId 			= 	$post->post_author;
		$categories 		=	wp_get_post_categories($post_id);
		$tags 				=	Zenpost_Post_Publish::get_tags($post_id);
		$metaDescription	=	get_post_meta($post_id,'_yoast_wpseo_metadesc',true);
		$pageViews 			=	get_post_meta($post_id,'pageViews',true);
		$twitterShares		=	get_post_meta($post_id,'twitterShares',true);
		$facebookShares 	=	get_post_meta($post_id,'facebookShares',true);
		$linkedinShares 	=	get_post_meta($post_id,'linkedinShares',true);
		$pinterestShares 	=	get_post_meta($post_id,'pinterestShares',true);

		$apiData 	=	array(
			"secretKey"			=>	$secret_key,
			"publishedUrl"		=>	$publishedUrl,			
			"publishedAt"		=>	$publishedAt,
			"lastModifiedAt" 	=>	$lastModifiedAt,
			"assignmentId"		=>	$assignment_id,
			"wordpressPostId"	=>	$post_id,
			"authorId"			=>	$authorId,
			"categoryIds"		=>	$categories,
			"tags"				=>	$tags,
			"metaDescription"	=>	$metaDescription,
			"pageViews"			=>  $pageViews,
			"twitterShares"		=>  $twitterShares,
			"facebookShares"	=>  $facebookShares,
			"linkedinShares"	=> 	$linkedinShares,
			"pinterestShares"	=>  $pinterestShares
		);
		$apiDataJSON 		=	json_encode($apiData);
		$env = $zenpost_env;
		if ($env == "prod") {			$base_url = "https://app.zenpost.com";			}
		else if ($env == "staging") {	$base_url = "https://app-staging.zenpost.com";	}
		else {							$base_url = $zenpost_url;						}

		$new_base_url = $base_url.'/api/v1/wordpress/update-assignment-information';
		//$result 	= wp_remote_post($new_base_url);
		$result 	=	wp_remote_post($new_base_url, array(
			'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
			'body'        => $apiDataJSON,
			'method'      => 'POST',
			'data_format' => 'body'
		));
		
		if($result){
		//	update_post_meta($post_id,'zen_temp_log',$result);
		}


/*
		$decoded 	= json_decode($result,true);
		if($decoded->status == 200){
			update_post_meta($post_id,'rp1','true');
		}
		else{
			update_post_meta($post_id,'rp1',$decoded->status);
		}
		$nd1 	=	json_encode($decoded);
		update_post_meta($post_id,'result_publish',$result);
		*/
	}
	public function to_timestamp($time){
		return strtotime($time);
	}
	public function get_permalink($post_id){
		return get_permalink( $post_id);
	}
	public function get_temestamp($post_id){
		$post 		=	get_post($post_id);
		$post_time 	=	$post->post_modified_gmt;
		return strtotime($post_time);
	}
	public function get_tags($post_id){
		$tags 		=	get_the_tags($post_id);
		$tags_name 	=	array();
		if(!empty($tags)){
			foreach($tags as $tag){
				$tag_name 	=	$tag->name;
				array_push($tags_name, $tag_name);
			}
			if(!empty($tags_name)){
				return $tags_name;
			}	
		}
		
	}
}
add_action('plugins_loaded',array('Zenpost_Post_Publish','init'));