<?php
/**
 * Nota Rest
 * This class adds and removes WP Rest actions
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nota Rest main class
 */
class Nota_WP_Rest {


	/**
	 * Nota API instance
	 * 
	 * @var Nota_Api
	 */
	private $api;

	/**
	 * Nota_Rest constructor
	 * 
	 * @param Nota_Api $api An instance of Nota_Api.
	 */
	public function __construct( $api ) {
		$this->api = $api;

		add_action( 'wp_ajax_nota_action', array( $this, 'handle_action' ) );
	}

	/**
	 * Converts HTML to text and trims to acceptable length
	 * 
	 * @param string $html string HTML to trim.
	 */
	private function trim_html( $html ) {
		// Replace <br> tags with a space before stripping tags to prevent words from sticking together.
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );

		// strip HTML tags from text.
		$text = wp_strip_all_tags( $html );
		$text = substr( $text, 0, 12000 );
	
		// Decode HTML entities to their corresponding characters.
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	
		// Normalize line breaks.
		$text = preg_replace( '/\n+/', "\n", $text );
	
		return $text;
	}

	/**
	 * Sanitizes core request properties.
	 *
	 * @param array $request A $_REQUEST superglobal.
	 */
	private function sanitize_nota_request( $request ) {
		$safe_request = $request['nota'];
		if ( ! isset( $request['nota'] ) || ! is_array( $request['nota'] ) ) {
			return new WP_Error( 'Request is missing required property "nota"' );
		}

		// sanitize action.
		$safe_request['nota_action'] = isset( $request['nota']['nota_action'] ) ? sanitize_key( $request['nota']['nota_action'] ) : '';

		// sanitize post HTML.
		$valid_post_html          = isset( $request['nota']['postHTML'] ) && is_string( $request['nota']['postHTML'] );
		$safe_request['postHTML'] = $valid_post_html ? (string) $request['nota']['postHTML'] : '';
		$safe_request['postText'] = $this->trim_html( $safe_request['postHTML'] );
		$safe_request['postId']   = isset( $_SERVER['HTTP_NOTA_POST_ID'] ) ? (int) $_SERVER['HTTP_NOTA_POST_ID'] : 0;

		// sanitize count.
		// count needs to be an integer, but we'll cast any strings to an integer if need be.
		$valid_count           = isset( $request['nota']['count'] ) && ( is_int( $request['nota']['count'] ) || is_string( $request['nota']['count'] ) );
		$safe_request['count'] = $valid_count ? (int) $request['nota']['count'] : null;

		// sanitize regenerate.
		// regenerate needs to be a boolean, but we'll cast any strings to a boolean if need be.
		$valid_regenerate           = isset( $request['nota']['regenerate'] ) && ( is_bool( $request['nota']['regenerate'] ) || is_string( $request['nota']['regenerate'] ) );
		$safe_request['regenerate'] = $valid_regenerate ? (bool) $request['nota']['regenerate'] : false;

		return $safe_request;
	}

	/**
	 * Handles actions
	 */
	public function handle_action() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['nonce'] ), NOTA_PLUGIN_NONCE ) ) {
			exit( 'Invalid nonce' );
		}

		if ( ! isset( $_REQUEST['nota'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing Nota data' ) );
			return;
		}

		$payload = $this->sanitize_nota_request( $_REQUEST );

		$actions = array(
			'get_current_user'           => array( $this, 'get_current_user' ),
			'get_text_hashtags'          => array( $this, 'get_text_hashtags' ),
			'get_text_summary'           => array( $this, 'get_text_summary' ),
			'get_text_headlines'         => array( $this, 'get_text_headlines' ),
			'get_text_slugs'             => array( $this, 'get_text_slugs' ),
			'get_text_keywords'          => array( $this, 'get_text_keywords' ),
			'get_text_entities'          => array( $this, 'get_text_entities' ),
			'get_text_meta_descriptions' => array( $this, 'get_text_meta_descriptions' ),
			'get_text_meta_titles'       => array( $this, 'get_text_meta_titles' ),
			'get_text_quotes'            => array( $this, 'get_text_quotes' ),
			'get_text_social_posts'      => array( $this, 'get_text_social_posts' ),
			'get_text_sms_messages'      => array( $this, 'get_text_sms_messages' ),
			'get_brand_tones'            => array( $this, 'get_brand_tones' ),
			'get_grade_scores'           => array( $this, 'get_grade_scores' ),
			'get_urls_score'             => array( $this, 'get_urls_score' ),
			'get_user_brands'            => array( $this, 'get_user_brands' ),
			'get_job'                    => array( $this, 'get_job' ),
			'get_public_config'          => array( $this, 'get_public_config' ),
			'save_events'                => array( $this, 'save_events' ),
			'adjust_text_tone'           => array( $this, 'adjust_text_tone' ),
			'get_related_keywords'       => array( $this, 'get_related_keywords' ),
			'get_related_keywords_batch' => array( $this, 'get_related_keywords_batch' ),
		);
		if ( ! $payload['nota_action'] || ! isset( $actions[ $payload['nota_action'] ] ) ) {
			wp_send_json_error( array( 'message' => 'invalid action' ), 400 );
			return;
		}

		$action   = sanitize_key( $payload['nota_action'] );
		$response = $actions[ $action ]( $payload );
		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			// Detect a 429 error specifically and send a custom message.
			if ( 429 === $error_code ) {
				wp_send_json_error(
					array(
						array(
							'code'    => 'nota_error',
							'message' => 'Rate limit error: API rate limit exceeded. Please try again later.',
						),
					),
					429
				);
			} else {
				wp_send_json_error( $response, 400 );
			}
		} else {
			wp_send_json( $response );
		}
	}

	/**
	 * Gets the current user
	 */
	private function get_current_user() {
		return $this->api->get_current_user();
	}

	/**
	 * Gets text summary
	 * 
	 * @param array $data Data sent with the request.
	 */
	private function get_text_summary( $data ) {
		if ( ! $data['postText'] ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text          = $data['postText'];
		$length_option = isset( $data['length_option'] ) && is_string( $data['length_option'] ) ? $data['length_option'] : '1-sentence';
		$regenerate    = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$brand_id      = isset( $data['brandId'] ) ? $data['brandId'] : null;
		$queue         = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		return $this->api->get_text_summary( $text, $length_option, $regenerate, $brand_id, $queue, $data['postId'] );
	}

	/**
	 *  Gets hashtags
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_hashtags( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;

		return $this->api->get_text_hashtags( $text, $regenerate, $data['postId'] );
	}

	/**
	 *  Gets headlines
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_headlines( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 3;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$brand_id   = isset( $data['brandId'] ) ? $data['brandId'] : null;
		$queue      = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		return $this->api->get_text_headlines( $text, $count, $regenerate, $brand_id, $queue, $data['postId'] );
	}

	/**
	 *  Gets slugs
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_slugs( $data ) {
		if ( ! isset( $data['postHTML'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		// strip HTML tags from text.
		$text       = $data['postText'];
		$count      = isset( $data['count'] ) ? $data['count'] : 3;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;

		return $this->api->get_text_slugs( $text, $count, $regenerate, $data['postId'] );
	}

	/**
	 *  Gets keywords
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_keywords( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 10;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$queue      = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		// maybe we'll expose this as a setting at some point.
		$variability = 0.3;

		return $this->api->get_text_keywords( $text, $count, $variability, $regenerate, $data['seoRanking'], $queue, $data['postId'] );
	}


	/**
	 *  Gets entities
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_entities( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$queue      = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		// maybe we'll expose this as a setting at some point.
		$variability = 0.3;

		return $this->api->get_text_entities( $text, $variability, $regenerate, $queue, $data['postId'] );
	}

	/**
	 *  Gets meta descriptions
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_meta_descriptions( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 10;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$brand_id   = isset( $data['brandId'] ) ? $data['brandId'] : null;
		$queue      = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		// maybe we'll expose this as a setting at some point.
		$variability = 0.3;

		return $this->api->get_text_meta_descriptions( $text, $count, $variability, $regenerate, $brand_id, $queue, $data['postId'] );
	}

	/**
	 *  Gets meta titles
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_meta_titles( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 10;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$brand_id   = isset( $data['brandId'] ) ? $data['brandId'] : null;
		$queue      = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		// maybe we'll expose this as a setting at some point.
		$variability = 0.3;

		return $this->api->get_text_meta_titles( $text, $count, $variability, $regenerate, $brand_id, $queue, $data['postId'] );
	}

	/**
	 *  Gets social posts for the given platform
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_social_posts( $data ) {
		if ( ! isset( $data['postText'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		if ( ! isset( $data['platform'] ) || ! is_string( $data['platform'] ) ) {
			wp_send_json_error( array( 'message' => 'platform is required' ), 400 );
			return;
		}

		$text       = $data['postText'];
		$platform   = $data['platform'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 10;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$queue      = (bool) isset( $data['queue'] ) ? $data['queue'] : false;
		$brand_id   = isset( $data['brandId'] ) ? $data['brandId'] : null;

		return $this->api->get_text_social_posts( $text, $platform, $count, $regenerate, $queue, $brand_id, $data['postId'] );
	}

	/**
	 *  Gets SMS messages
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_sms_messages( $data ) {
		if ( ! isset( $data['postHTML'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		// strip HTML tags from text.
		$text       = $data['postText'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 1;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;

		return $this->api->get_text_sms_messages( $text, $count, $regenerate, $data['postId'] );
	}
	
	/**
	 *  Gets quotes
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_text_quotes( $data ) {
		if ( ! isset( $data['postHTML'] ) ) {
			wp_send_json_error( array( 'message' => 'HTML is required' ), 400 );
			return;
		}

		// strip HTML tags from text.
		$text       = $data['postText'];
		$count      = ! is_null( $data['count'] ) ? $data['count'] : 1;
		$regenerate = isset( $data['regenerate'] ) ? $data['regenerate'] : false;
		$exclude    = isset( $data['exclude'] ) && is_array( $data['exclude'] ) ? $data['exclude'] : array();
		// we need to ensure that each item in the array is a string.
		// no funny business please!
		$sanitised_exclude = array_map( 'strval', $exclude );

		return $this->api->get_text_quotes( $text, $count, $regenerate, $sanitised_exclude, $data['postId'] );
	}

	/**
	 * Gets grade scores
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_grade_scores( $data ) {
		if ( ! isset( $data['postHTML'] ) ) {
			wp_send_json_error( array( 'message' => 'Content is required' ), 400 );
			return;
		}
		return $this->api->get_grade_scores( $data['postText'], $data['headline'], $data['meta_title'], $data['meta_description'], $data['slug'], $data['keywords'], $data['queue'] );
	}

	/**
	 * Gets URLs score
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_urls_score( $data ) {  
		return $this->api->get_urls_score( $data['urlsList'] );
	}

	/**
	 * Get user brands
	 */
	private function get_user_brands() {
		return $this->api->get_user_brands();
	}

	/** Save events
	 *
	 * @param array $data Data sent with the request.
	 * @return mixed The response from the API or a WP_Error.
	 */
	private function save_events( $data ) {
		$current_user = wp_get_current_user();

		$required_fields = [ 'postId', 'event', 'field', 'content' ];
		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
				wp_send_json_error( array( 'message' => "Missing required event data: $field" ), 400 );
				return;
			}
		}
	
		$event_data = array(
			'site'     => get_site_url(),
			'wpUserId' => $current_user->ID,
			'postId'   => sanitize_text_field( $data['postId'] ),
			'event'    => sanitize_text_field( $data['event'] ),
			'field'    => sanitize_text_field( $data['field'] ),
			'content'  => $data['content'],
		);
	
		return $this->api->save_events( $event_data );
	}

	/**
	 * Get brand tones
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_brand_tones( $data ) {
		if ( ! isset( $data['brandId'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required brand ID' ), 400 );
			return;
		}

		$brand_id = $data['brandId'];

		return $this->api->get_brand_tones( $brand_id );
	}

	/**
	 * Get a job
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_job( $data ) {
		if ( ! isset( $data['jobId'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required job ID' ), 400 );
			return;
		}

		$job_id = sanitize_text_field( $data['jobId'] );
		return $this->api->get_job( $job_id );
	}

	/**
	 * Returns the public config.
	 */
	private function get_public_config() {
		return $this->api->get_public_config();
	}

	/**
	 * Adjust text tone
	 *
	 * @param array $data Data sent with the request.
	 */
	private function adjust_text_tone( $data ) {
		if ( ! isset( $data['organizationBrandId'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required user brand ID' ), 400 );
			return;
		}
		if ( ! isset( $data['toneId'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required tone ID' ), 400 );
			return;
		}
		if ( ! isset( $data['text'] ) || ! is_array( $data['text'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required text or text is not an array' ), 400 );
			return;
		}
		$tone_id         = $data['toneId'];
		$text            = $data['text'];
		$brand_id        = $data['organizationBrandId'];
		$task            = $data['task'];
		$queue           = (bool) isset( $data['queue'] ) ? $data['queue'] : false;
		$prompt_category = isset( $data['promptCategory'] ) ? $data['promptCategory'] : 'general';

		return $this->api->adjust_text_tone( $tone_id, $brand_id, $text, $task, $queue, $prompt_category );
	}

	/**
	 * Get related keywords.
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_related_keywords( $data ) {
		if ( ! isset( $data['keyword'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required keyword' ), 400 );
			return;
		}
		$keyword = sanitize_text_field( $data['keyword'] );
		$text    = $data['postText'];
		$queue   = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		return $this->api->get_related_keywords( $keyword, $text, $queue );
	}

	/**
	 * Gets a batch of related keywords.
	 *
	 * @param array $data Data sent with the request.
	 */
	private function get_related_keywords_batch( $data ) {
		if ( ! isset( $data['keywords'] ) || ! is_array( $data['keywords'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required keywords' ), 400 );
			return;
		}

		$keywords = array_map( 'sanitize_text_field', $data['keywords'] );
		$text     = $data['postText'];
		$queue    = (bool) isset( $data['queue'] ) ? $data['queue'] : false;

		return $this->api->get_related_keywords_batch( $keywords, $text, $queue );
	}
}
