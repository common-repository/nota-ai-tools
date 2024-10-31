<?php
/**
 * Nota API
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nota API class
 */
class Nota_Api {
	/**
	 * Nota settings class.
	 * 
	 * @var Nota_Settings
	 */
	private $settings;

	/**
	 * Nota_Api constructor
	 * 
	 * @param Nota_Settings $settings An instance of Nota_Settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}   

	/**
	 * Returns the API url
	 */
	private function get_api_url() {
		$api_url = $this->settings->get_option( 'api_url' );
		if ( ! $api_url ) {
			return new WP_Error( 'nota_error', 'Missing API URL' );
		}
		return trailingslashit( $api_url );
	}

	/**
	 * Makes a request
	 * 
	 * @param string $method HTTP method to use in the request.
	 * @param string $endpoint Endpoint to make the request to.
	 * @param array  $args Any other info for the request.
	 */
	private function make_request( $method, $endpoint, $args = array() ) {
		// get any headers sent with the request, but default to an empty array.
		$headers = array_key_exists( 'headers', $args ) && is_array( $args['headers'] ) ? $args['headers'] : array();

		// Custom header for indicating the request source.
		$headers['nota-request-source'] = 'WordPress';

		// add in our authorization headers, these are always required.
		$default_headers = array(
			'nota-subscription-key' => $this->settings->get_option( 'api_key' ),
		);

		$request_args = array_merge(
			$args,
			array(
				'method'  => $method,
				'headers' => array_merge( $headers, $default_headers ),
			)
		);
		// Check for timeout setting, default to 30 seconds if not set or empty.
		$timeout                 = (int) $this->settings->get_option( 'request_timeout_seconds' );
		$timeout                 = empty( $timeout ) ? 30 : $timeout;
		$request_args['timeout'] = $timeout;

		$url = $this->get_api_url();

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$url      = $url . $endpoint;
		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$body_json   = json_decode( $body, false );

		if ( $status_code < 200 || $status_code > 299 ) {
			Nota_Logger::debug( $body );

			// if this is an auth error, return a known response.
			if ( 401 === $status_code ) {
				return new WP_Error(
					'nota_error',
					'Authentication error: Please verify your API key setting.'
				);
			}

			// The Nota API will return human readable errors with the "isNotaError" property.
			// Let's return these so that we can distingush them on the front-end from other generic errors.
			if ( ! is_null( $body_json ) && isset( $body_json->isNotaError ) && $body_json->isNotaError && $body_json->message ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return new WP_Error(
					'nota_error',
					$body_json->message 
				);
			}

			// The Nota API will return human readable validation errors with a 400 response and validationErrors property.
			// Let's return these so that we can distinguish them on teh front-end from other generic errors.
			if ( ! is_null( $body_json ) && isset( $body_json->validationErrors ) && $body_json->validationErrors && $body_json->message ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return new WP_Error(
					'nota_error',
					'Invalid request: ' . join( ', ', $body_json->validationErrors ) . '.' // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				);
			}

			return new WP_Error(
				'nota_api_error',
				'Non-200 status code returned from Nota API',
				[
					'body'        => $body_json || $body,
					'status_code' => $status_code,
				]
			);
		}

		if ( is_null( $body_json ) ) {
			return new WP_Error(
				'nota_api_error',
				'Could not parse response body',
				[
					'body' => $body,
				]
			);
		}
		return $body_json;
	}

	/**
	 * Gets the current user
	 */
	public function get_current_user() {
		return $this->make_request(
			'GET',
			'wordpress/v1/user'
		);
	}

	/**
	 * Returns the contentInputSource for the record.
	 *
	 * @param string $post_id The post ID.
	 */
	private function build_content_input_source( $post_id ) {
			// these are elements from the user-agent, see class-wp-http.php.
		return array(
			'type'        => 'cms',
			'cmsRecordId' => $post_id,
			'cmsProvider' => 'WordPress/' . get_bloginfo( 'version' ),
			'cmsName'     => get_bloginfo( 'url' ),
		);
	}

	/**
	 * Gets a text summary
	 * 
	 * @param string $text Text to summarise.
	 * @param string $length_option How long the summary should be.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $brand_id The brand ID to use.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $post_id The post ID.
	 */
	public function get_text_summary( $text, $length_option, $regenerate, $brand_id, $queue, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/summary',
			array(
				'body' => array(
					'text'               => $text,
					'lengthOption'       => $length_option,
					'regenerate'         => $regenerate,
					'brandId'            => $brand_id,
					'queue'              => $queue,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the hashtags from the text
	 * 
	 * @param string $text Text to get keywords from.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $post_id The post ID.
	 */
	public function get_text_hashtags( $text, $regenerate, $post_id ) {
		return $this->make_request(
			'POST',
			'social/v1/hashtags',
			array(
				'body' => array(
					'text'               => $text,
					'regenerate'         => $regenerate,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the grade scores for the text
	 * 
	 * @param string $content Content to get scores for.
	 * @param string $headline Headline to get scores for.
	 * @param string $meta_title Meta title to get scores for.
	 * @param string $meta_description Meta description to get scores for.
	 * @param string $slug Slug to get scores for.
	 * @param object $keywords Keywords to get scores for.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 */
	public function get_grade_scores( $content, $headline, $meta_title, $meta_description, $slug, $keywords, $queue ) {
		return $this->make_request(
			'POST',
			'grade/v1/score',
			array(
				'body' => array(
					'headline'        => $headline,
					'slug'            => $slug,
					'metaDescription' => $meta_description,
					'metaTitle'       => $meta_title,
					'content'         => $content,
					'keywords'        => (object) $keywords,
					'queue'           => $queue,
				),
			)
		);
	}

	/**
	 * Gets the urls score
	 * 
	 * @param array $urls_list List of URLs to get scores for.
	 */
	public function get_urls_score( $urls_list ) {
		return $this->make_request(
			'POST',
			'grade/v1/check-urls',
			array(
				'body' => array(
					'urlsList' => $urls_list,
				),
			)
		);
	}
	
	/**
	 * Gets the user brands
	 */
	public function get_user_brands() {
		return $this->make_request(
			'GET',
			'wordpress/v1/user/brands'
		);
	}

	/**
	 * Gets the headline from the text
	 * 
	 * @param string $text Text to get headlines from.
	 * @param int    $count Number of headlines to get.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $brand_id The brand ID to use.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $post_id The post ID.
	 */
	public function get_text_headlines( $text, $count, $regenerate, $brand_id, $queue, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/headlines',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'itemCharactersMax'  => ! empty( $this->settings->get_option( 'headline_max_characters' ) ) ? $this->settings->get_option( 'headline_max_characters' ) : null,
					'regenerate'         => $regenerate,
					'headlineCase'       => ! empty( $this->settings->get_option( 'headline_casing' ) ) ? $this->settings->get_option( 'headline_casing' ) : null,
					'brandId'            => $brand_id,
					'queue'              => $queue,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the slug from the text
	 * 
	 * @param string $text Text to get slugs from.
	 * @param int    $count Number of slugs to get.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $post_id The post ID.
	 */
	public function get_text_slugs( $text, $count, $regenerate, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/slugs',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'regenerate'         => $regenerate,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the keywords from the text
	 * 
	 * @param string $text Text to get keywords from.
	 * @param int    $count Number of keywords to get.
	 * @param float  $variability How much the keywords should vary.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param bool   $seo_ranking Enable SEO ranking.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $post_id The post ID.
	 */
	public function get_text_keywords( $text, $count, $variability, $regenerate, $seo_ranking, $queue, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/keywords',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'variability'        => $variability,
					'regenerate'         => $regenerate,
					'seoRanking'         => $seo_ranking,
					'queue'              => $queue,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the entities from the text
	 * 
	 * @param string $text Text to get entities from.
	 * @param float  $variability How much the entities should vary.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $post_id The post ID.
	 */
	public function get_text_entities( $text, $variability, $regenerate, $queue, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/entities',
			array(
				'body' => array(
					'text'               => $text,
					'variability'        => $variability,
					'regenerate'         => $regenerate,
					'queue'              => $queue,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the meta description from the text
	 * 
	 * @param string $text Text to get description from.
	 * @param int    $count Number of descriptions to get.
	 * @param float  $variability How much the descriptions should vary.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $brand_id The brand ID to use.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $post_id The post ID.
	 */
	public function get_text_meta_descriptions( $text, $count, $variability, $regenerate, $brand_id, $queue, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/meta/descriptions',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'variability'        => $variability,
					'regenerate'         => $regenerate,
					'brandId'            => $brand_id,
					'queue'              => $queue,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the meta title from the text
	 * 
	 * @param string $text Text to get title from.
	 * @param int    $count Number of titles to get.
	 * @param float  $variability How much the titles should vary.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $brand_id The brand ID to use.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $post_id The post ID.
	 */
	public function get_text_meta_titles( $text, $count, $variability, $regenerate, $brand_id, $queue, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/meta/titles',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'variability'        => $variability,
					'regenerate'         => $regenerate,
					'titleCase'          => ! empty( $this->settings->get_option( 'meta_title_casing' ) ) ? $this->settings->get_option( 'meta_title_casing' ) : null,
					'brandId'            => $brand_id,
					'queue'              => $queue,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets the social posts for the text
	 * 
	 * @param string $text Text to get title from.
	 * @param string $platform Platform to get posts for (facebook, twitter, instagram, ...).
	 * @param int    $count Number of titles to get.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $brand_id The brand ID to use.
	 * @param string $post_id The post ID.
	 */
	public function get_text_social_posts( $text, $platform, $count, $regenerate, $queue, $brand_id, $post_id ) {
		return $this->make_request(
			'POST',
			'social/v1/posts',
			array(
				'body' => array(
					'text'               => $text,
					'platform'           => $platform,
					'count'              => $count,
					'regenerate'         => $regenerate,
					'style'              => ! empty( $this->settings->get_option( 'social_post_style' ) ) ? $this->settings->get_option( 'social_post_style' ) : null,
					'queue'              => $queue,
					'brandId'            => $brand_id,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets SMS messages from the text
	 * 
	 * @param string $text Text to get title from.
	 * @param int    $count Number of titles to get.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param string $post_id The post ID.
	 */
	public function get_text_sms_messages( $text, $count, $regenerate, $post_id ) {
		return $this->make_request(
			'POST',
			'social/v1/sms',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'regenerate'         => $regenerate,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Gets quotes from the text
	 * 
	 * @param string $text Text to get title from.
	 * @param int    $count Number of titles to get.
	 * @param bool   $regenerate Whether to regenerate the value.
	 * @param array  $exclude The quotes to exclude.
	 * @param string $post_id The post ID.
	 */
	public function get_text_quotes( $text, $count, $regenerate, $exclude, $post_id ) {
		return $this->make_request(
			'POST',
			'sum/v1/quotes',
			array(
				'body' => array(
					'text'               => $text,
					'count'              => $count,
					'regenerate'         => $regenerate,
					'exclude'            => $exclude,
					'contentInputSource' => $this->build_content_input_source( $post_id ),
				),
			)
		);
	}

	/**
	 * Save events
	 * 
	 * @param array $event_data Data for the event to be saved.
	 * @return mixed The response from the API or a WP_Error.
	 */
	public function save_events( $event_data ) {
		return $this->make_request(
			'POST',
			'wordpress/v1/analytics',
			array(
				'body'    => json_encode( $event_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);
	}

	/**
	 * Get a job result.
	 *
	 * @param string $job_id The job ID to retrieve.
	 */
	public function get_job( $job_id ) {
		return $this->make_request(
			'GET',
			'jobs/v1/job/' . rawurlencode( $job_id )
		);
	}

	/**
	 * Gets the public config.
	 */
	public function get_public_config() {
		return $this->make_request(
			'GET',
			'wordpress/v1/public-config'
		);
	}

	/**
	 * Gets the brand tones.
	 * 
	 * @param string $brand_id The brand ID to use.
	 */
	public function get_brand_tones( $brand_id ) {
		return $this->make_request(
			'GET',
			'wordpress/v1/brand/' . $brand_id . '/tones'
		);
	}

	/**
	 * Adjusts the text to a custom tone.
	 * 
	 * @param string $tone_id The tone ID to use.
	 * @param string $brand_id The user brand ID to use.
	 * @param string $text The text to adjust.
	 * @param string $task The task to adjust for.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 * @param string $prompt_category The prompt category to use.
	 */
	public function adjust_text_tone( $tone_id, $brand_id, $text, $task, $queue, $prompt_category ) {
		return $this->make_request(
			'POST',
			'wordpress/v1/tones/adjust-text-tone',
			array(
				'body' => array(
					'toneId'              => $tone_id,
					'organizationBrandId' => $brand_id,
					'text'                => $text,
					'task'                => $task,
					'queue'               => $queue,
					'promptCategory'      => $prompt_category,
				),
			)
		);
	}

	/**
	 * Gets related keywords.
	 *
	 * @param string $keyword The keyword to compare.
	 * @param string $text The used used for context.
	 * @param bool   $queue Whether to queue the request or return immediately.
	 */
	public function get_related_keywords( $keyword, $text, $queue ) {
		return $this->make_request(
			'POST',
			'wordpress/v1/seo/related-keywords',
			array(
				'body' => array(
					'keyword' => $keyword,
					'text'    => $text,
					'queue'   => $queue,
				),
			)
		);
	}

	/**
	 * Gets a batch of related keywords.
	 *
	 * @param string[] $keywords The keyword to compare.
	 * @param string   $text The used used for context.
	 * @param bool     $queue Whether to queue the request or return immediately.
	 */
	public function get_related_keywords_batch( $keywords, $text, $queue ) {
		return $this->make_request(
			'POST',
			'wordpress/v1/seo/batch-related-keywords',
			array(
				'body' => array(
					'keywords' => $keywords,
					'text'     => $text,
					'queue'    => $queue,
				),
			)
		);
	}
}
