<?php
class zenpost_post_api {

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
	public function __construct( $authenticator ) {
		$this->authenticator = $authenticator;		
	}

	
	/**
	 * Register API routes.
	 */
	public function register_routes() {
		register_rest_route( self::API_BASE, '/post', array('methods'  => 'POST','callback' => array( $this, 'handle_post' )));
		register_rest_route( self::API_BASE, '/verify', array('methods'  => 'POST','callback' => array( $this, 'verify_auth' )) );
		register_rest_route( self::API_BASE, '/authors', array('methods'  => 'GET','callback' => array( $this, 'get_authors' )));
		register_rest_route( self::API_BASE, '/new-secret', array('methods'  => 'GET','callback' => array( $this, 'generate_secret' )));
	}
	/**
	 * Handle the POST for post creation.
	 *
	 * This does the following:
	 *	 - decodes JSON payload
	 *	 - validates payload
	 *	 - downloads attachments to wp-content/uploads if provided
	 *	 - creates the post
	 *	 - links attachments to new post
	 *
	 * @param WP_REST_Request $request the request
	 *
	 * @return WP_REST_Response success and response details
	 */
	public function handle_post( WP_REST_Request $request ) {
		$payload = @json_decode( $request->get_body(), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_REST_Response(array('success'=>false,'error'=>'Could not parse JSON'),400);
		}
	###########	error_log(print_r($payload,__return_true()).' *** ');

		list( $valid, $error ) = self::validate_post_payload( $payload );
		if ( ! $valid ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => $error
			), 400 );
		}

		list( $allowed, $error ) = $this->check_authentication( $payload );
		if ( ! $allowed ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => $error
			), 403 );
		}

		if ( isset( $payload['attachments'] ) ) {
			$downloads = self::download_attachments( $payload['attachments'] );
			$to_attach = $downloads['succeeded'];
		} else {
			$downloads = array();
			$to_attach = array();
		}

		if ( count( $to_attach ) > 0 && isset( $payload['featured_image'] ) ) {
			$featured_id = $payload['featured_image'];
		} else {
			$featured_id = null;
		}

		list( $result, $errors ) = self::create_post( $payload, $to_attach );
		if ( $result > -1 && null === $errors ) {
			$attached = self::import_attachments( $result, $to_attach, $featured_id );

			return new WP_REST_Response( array(
 			'success'    => true,
 			'post_id'    => $result,
 			'post'       => $payload,
 			'downloaded' => $downloads,
 			'attached'   => $attached
			) );
		} else {
			return new WP_REST_Response( array(
				'success' => false,
				'errors'  => $errors
			), 400 );
		}
	}

	/**
	 * Test authentication of a request.
	 *
	 * @param WP_REST_Request $request the request
	 *
	 * @return WP_REST_Response success and response details
	 */
	public function verify_auth( WP_REST_Request $request ) {
		$payload = @json_decode( $request->get_body(), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => 'Could not parse JSON'
			), 400 );
		}

		list( $allowed, $error ) = $this->check_authentication( $payload );
		if ( ! $allowed ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => $error,
				'payload' => $payload
			), 403 );
		}

		return new WP_REST_Response( array(
			'success' => true
		) );
	}

	/**
	 * Get authors with permission to post with the API.
	 *
	 * @param WP_REST_Request $request the request
	 *
	 * @return WP_REST_Response success and response details
	 */
	public function get_authors( WP_REST_Request $request ) {
		$capabilities = array( 'edit_posts' );
		$authors = array_filter(
			get_users(),
			function ( $author ) use ( $capabilities ) {
				$caps = $author->allcaps;
				foreach ( $capabilities as $cap ) {
					if ( ! isset( $caps[$cap] ) || true !== $caps[$cap] ) {
						return false;
					}
				}

				return true;
			}
		);

		$capable_authors = array_map( function ( $author ) {
			return array(
				'nicename' => $author->user_nicename,
				'roles'    => $author->roles,
				'id'       => $author->ID
			);
		}, $authors );

		return new WP_REST_Response( array(
			'success' => true,
			'authors' => array_values( $capable_authors )
		) );
	}

	/**
	 * Get a new secret.
	 *
	 * @param WP_REST_Request $request the request
	 *
	 * @return WP_REST_Response success and response details
	 */
	public function generate_secret( WP_REST_Request $request ) {
		return new WP_REST_Response( array(
			'success' => true,
			'secret'  => $this->authenticator->generate_secret()
		) );
	}

	/**
	 * Validate the POST payload.
	 *
	 * @param array $payload the payload
	 *
	 * @return array the success and any errors if encountered
	 */
	public static function validate_post_payload( $payload ) {
		// Request must at least contain a post title and body.
		$required_post_keys = array( 'title', 'body' );
		foreach ( $required_post_keys as $k ) {
			if ( ! isset( $payload[$k] ) ) {
				return array( false, "Missing required key '$k'" );
			}

			if ( 0 === strlen( $payload[$k] ) ) {
				return array( false, "Empty string for '$k'" );
			}
		}

		// If provided, 'attachments' should be an array of IDs to URLs.
		if ( isset( $payload['attachments'] ) ) {
			if ( ! is_array( $payload['attachments'] ) ) {
				return array( false, 'Attachments must be a list' );
			}
		}

		// If provided, 'categories' should be an array of slugs.
		if ( isset( $payload['categories'] ) ) {
			if ( ! is_array( $payload['categories'] ) ) {
				return array( false, 'Categories must be a list' );
			}
		}

		return array( true, null );
	}

	/**
	 * Check that the current user can make this request.
	 *
	 * @param WP_REST_Request $request the request
	 *
	 * @return bool if the request is authenticated and an optional error
	 */
	public function check_authentication( $request ) {
		return $this->authenticator->authenticate( $request );
	}

	/**
	 * Create the post.
	 *
	 * @param array $payload the post payload
	 * @param array $images  an optional array of images to attach to this post
	 *
	 * If attachments are provided, the post content will also be
	 *	 altered to insert images into the body.
	 *
	 * @return array the post ID and null on success,
	 *	 -1 and errors on failure
	 */
	public static function create_post( $params, $images = array() ) {
		$status 	=	(isset($params['status'])) ? $params['status'] : 'publish';
		if(isset($params['publish_now'])){
			if($params['publish_now']==true || $params['publish_now']=='true'){
				$status 	=	'publish';
			}
		}
		$body		=	self::insert_images( $params['body'], $images);
		$categories = 	array();

		if(isset($params['categories'])){
			$categories 	=	array_reduce(
				$params['categories'],
				function ( $acc, $cat ) {
					$t = get_term_by( 'slug', $cat, 'category' );
					if ( false === $t ) {
						return array( 'error' => $cat );
					}
					else{
						$acc[] = $t->term_id;
						return $acc;
					}
				},
				array()
			);
			if ( isset( $categories['error'] ) ) {
				$cat = $categories['error'];
				return array( -1, array( "Cat $cat doesn't exist" ) );
			}
		}
		//post_name
		$tags = ( isset( $params['tags'] ) ) ? $params['tags'] : array();
		

		$seo_title 						=	$params['seo_title'];
		$seo_url 						=	$params['seo_url'];
		$seo_meta						=	$params['meta_description'];
		$search_engine_preview			=	$params['search_engine_preview'];

		$twitter_social_image			=	$params['twitter_social_image'];
		$twitter_social_title			=	$params['twitter_social_title'];
		$twitter_social_description		=	$params['twitter_social_description'];
		$facebook_social_image			=	$params['facebook_social_image'];
		$facebook_social_title			=	$params['facebook_social_title'];
		$facebook_social_description	=	$params['facebook_social_description'];

		$post_args = array(
			'post_name'		=>	$seo_url,
			'post_title'   	=> 	wp_strip_all_tags( $params['title'] ),
			'post_content' 	=> 	$body,
			'post_status'  	=> 	$status,
			'tags_input'   	=> 	$tags
		);
		if ( isset( $params['author'] ) ) {
			$post_args['post_author'] = intval( $params['author'] );
		}
		if ( count( $categories ) ) {
			$post_args['post_category'] = $categories;
		}
		$result = wp_insert_post( $post_args, true );

		if ( is_array( $result ) && isset( $result['errors'] ) ) {
			return array( -1, $result['errors'] );
		}
		/* Code by Codeflox [1] */
		else{
			$new_post_id 	= 	$result;
			$assignment_id 	=	$params['assignment_id'];
			$secret_key 	=	$params['secret_key'];
			$signature 		=	$params['signature'];
			$zenpost_env 	=	$params['zenpost_env'];
			$zenpost_url 	=	$params['zenpost_url'];


			###############update_post_meta($new_post_id,'thepostmeta',$params);
			
			// feature image url

			

			/* Yosta Meta Update */
			update_post_meta($new_post_id,'_yoast_wpseo_title',$seo_title);
			update_post_meta($new_post_id,'_yoast_wpseo_metadesc',$seo_meta);
			update_post_meta($new_post_id,'_yoast_wpseo_twitter-title',$twitter_social_title);
			update_post_meta($new_post_id,'_yoast_wpseo_twitter-description',$twitter_social_description);
			update_post_meta($new_post_id,'_yoast_wpseo_opengraph-title',$facebook_social_title);
			update_post_meta($new_post_id,'_yoast_wpseo_opengraph-description',$facebook_social_description);
			
			/********************************************************/
			$remote_fb_url 	=	$params['attachments']['facebookimage'];
			$remote_tw_url 	= 	$params['attachments']['twitterimage'];
			$remote_ft_url 	= 	$params['attachments']['featuredimage'];


			if($remote_ft_url){
				zenpost_post_api::update_social_image2($remote_ft_url,$new_post_id,'featuredimage');				
			}

			$featured_img_url =	get_the_post_thumbnail_url($new_post_id,'full');
			
			if($remote_tw_url){
				zenpost_post_api::update_social_image2($remote_tw_url,$new_post_id,'_yoast_wpseo_twitter-image');
			}
			else{
				if($featured_img_url){
					update_post_meta($new_post_id,'_yoast_wpseo_twitter-image',$featured_img_url);	
				}
				
			}
			if($remote_fb_url){
				zenpost_post_api::update_social_image2($remote_fb_url,$new_post_id,'_yoast_wpseo_opengraph-image');	
			}
			else{
				if($featured_img_url){
					update_post_meta($new_post_id,'_yoast_wpseo_opengraph-image',$featured_img_url);
				}
			}


			update_post_meta($new_post_id,'assignment_id',$assignment_id);
			update_post_meta($new_post_id,'secret_key',$secret_key);
			update_post_meta($new_post_id,'signature',$signature);
			update_post_meta($new_post_id,'zenpost_env',$zenpost_env);
			update_post_meta($new_post_id,'zenpost_url',$zenpost_url);
			update_post_meta($new_post_id,'zenpost_type','true');
			if($status=='publish'){
				Zenpost_Post_Publish::on_post_publish($new_post_id);
			}
		}
		/* Code by Codeflox [1] #End */
		return array( $result, null );
	}



	/*public function update_social_image($image_url,$post_id,$meta){
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$image_name       = basename( $image_url );
		$upload_dir       = wp_upload_dir(); // Set upload folder
		$image_data       = file_get_contents($image_url); // Get image data
		$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
		$filename         = basename( $unique_file_name ); // Create image file name

		if( wp_mkdir_p( $upload_dir['path'] ) ) {
			$file = $upload_dir['path'] . '/' . $filename;
		}
		else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}
		file_put_contents( $file, $image_data );
		$wp_filetype = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_type'			=> 'attachment',
	        'posts_per_page'	=> -1,
	        'post_status'		=> 'any',
	        'post_parent' 		=> $post_id
		);
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		


		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		$furl 	=	wp_get_attachment_url($attach_id);
		update_post_meta($post_id,$meta,$furl);
		

	}*/

	public function update_social_image2($image,$post_id,$meta){
		require_once(ABSPATH.'wp-admin/includes/media.php');
		require_once(ABSPATH.'wp-admin/includes/file.php');
		require_once(ABSPATH.'wp-admin/includes/image.php');
		$media 		=	media_sideload_image($image, $post_id);
		if(!empty($media) && !is_wp_error($media)){
		    $args = array(
		        'post_type'			=> 'attachment',
		        'posts_per_page'	=> -1,
		        'post_status'		=> 'any',
		        'post_parent' 		=> $post_id
		    );
			$attachments 	=	get_posts($args);
			if(isset($attachments) && is_array($attachments)){
				foreach($attachments as $attachment){
					$image 	= 	wp_get_attachment_image_src($attachment->ID, 'full');
					update_post_meta($post_id,$meta,$image[0]);
					if(strpos($media, $image[0]) !== false){
						
						if($meta=='featuredimage'){
							set_post_thumbnail($post_id, $attachment->ID);
						}
						//update_post_meta($post_id,$meta,$image[0]);
						break;
					}
				}
			}
		}
	}

	/**
	 * Download attachments to uploads directory.
	 *
	 * @param array $attachments the attachments keyed by ID
	 *
	 * @return array successful and failed downloads
	 */
	public static function download_attachments( $attachments ) {
		$upload_dir = wp_upload_dir();
			$upload_url = $upload_dir['url'];
			$upload_dir = $upload_dir['path'];

			$files = array_map(
				function ( $id ) use ( $attachments, $upload_dir, $upload_url ) {
					$url = $attachments[$id];
					$filename = array_pop( explode( '/', $url ) );
					return array(
						'id'          => $id,
						'source_url'  => $url,
						'filename'    => $filename,
						'upload_path' => implode( '/', array( $upload_dir, $filename ) ),
						'upload_url'  => implode( '/', array( $upload_url, $filename ) )
					);
				},
				array_keys( $attachments )
			);
			return array_reduce(
				$files,
				function ( $acc, $file ) use ( $upload_dir ) {
					if ( copy( $file['source_url'], $file['upload_path'] ) ) {
						$acc['succeeded'][] = $file;
					} else {
						$acc['failed'][] = $file['id'];
					}
					return $acc;
				},
				array( 'succeeded' => array(), 'failed' => array() )
			);	
	
	}

	/**
	 * Insert images into post body.
	 *
	 * If no images are provided, the body is obviously not altered.
	 *
	 * @param string $body	 the post body
	 * @param array  $images the images to insert
	 *
	 * @return string altered post body with images
	 */
	public static function insert_images( $body, $images ) {
		return array_reduce(
			$images,
			function ( $result, $image ) {
				$id = $image['id'];
				$url = $image['upload_url'];

				$re = "/{{img (${id})}}/";
				$tag = sprintf( '<img src="%s">', $url );

				return preg_replace( $re, $tag, $result );
			},
			$body
		);
	}

	/**
	 * Import downloaded attachments and attach to the new post.
	 *
	 * @param int		$post_id	 the new post ID
	 * @param array $to_attach the attachments
	 */
	public static function import_attachments($post_id,$to_attach,$featured_id = null) {
		return array_reduce(
			$to_attach,
			function ( $acc, $attachment ) use ( $post_id, $featured_id ) {
				$path = $attachment['upload_path'];
				$id = $attachment['id'];

				$filename = basename( $path );
				$type = wp_check_filetype( $filename, null );
				$title = $filename;

				$attachment_params = array(
					'guid'           => $attachment['upload_url'],
					'post_mime_type' => $type['type'],
					'post_title'     => $title,
					'post_status'    => 'inherit',
					'post_content'   => ''
				);

				$attachment_id = wp_insert_attachment($attachment_params,$path,$post_id);
				
				/*
				$featured_img_url =	wp_get_attachment_url($attachment_id);
		update_post_meta($post_id,'zenpostfimage',$attachment_id.'***'.$featured_img_url);

		$twimg		=	get_post_meta($post_id,'_yoast_wpseo_twitter-image',true);
		$fbimg 		=	get_post_meta($post_id,'_yoast_wpseo_opengraph-image',true);


		if($twimg){
				
			}
			else{
				if($featured_img_url){
					update_post_meta($post_id,'_yoast_wpseo_twitter-image',$featured_img_url);	
				}
				
			}
		if($fbimg){
			
		}
		else{
			if($featured_img_url){
				update_post_meta($post_id,'_yoast_wpseo_opengraph-image',$featured_img_url);
			}
		}
				*/


				if ( $attachment_id > 0 ) {
					self::set_attachment_metadata( $attachment_id, $path );
					if ( null !== $featured_id && $featured_id === $id ) {
						$set_featured = set_post_thumbnail( $post_id, $attachment_id );
					} else {
						$set_featured = false;
					}

					$acc['succeeded'][] = array(
						'id'            => $id,
						'attachment_id' => $attachment_id,
						'set_featured'  => $set_featured
					);
				} else {
					$acc['failed'][] = $id;
				}

				return $acc;
			},
			array( 'succeeded' => array(), 'failed' => array() )
		);
	}

	/**
	 * Set attachment metadata.
	 *
	 * @param int		 $attachment_id the new attachment ID
	 * @param string $path					the path to the image
	 */
	public static function set_attachment_metadata( $attachment_id, $path ) {
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		$metadata = wp_generate_attachment_metadata( $attachment_id, $path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
}