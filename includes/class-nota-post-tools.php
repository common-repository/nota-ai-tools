<?php
/**
 * Nota Post Tools
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nota Post Tools class
 */
class Nota_Post_Tools {

	/**
	 * Nota settings class.
	 * 
	 * @var Nota_Settings
	 */
	private $settings;

	/**
	 * Public meta keys used to save WP Post data
	 * 
	 * @var array
	 */
	private static $public_meta_keys = [
		'seo_title' => 'nota_seo_page_title',
		'seo_desc'  => 'nota_seo_page_description',
	];

	/**
	 * Private meta keys used to save Nota data
	 * 
	 * @var array
	 */
	private static $private_meta_keys = [
		'social_hashtags_history'       => 'nota_hashtags_history',
		'headline_history'              => 'nota_headline_history',
		'slug_history'                  => 'nota_slug_history',
		'excerpt_history'               => 'nota_excerpt_history',
		'tag_history'                   => 'nota_tag_history',
		'quotes_history'                => 'nota_quotes_history',
		'selected_hashtags'             => 'nota_selected_hashtags', // 'selected_hashtags' is used in the frontend.
		'seo_title_history'             => 'nota_seo_title_history',
		'seo_desc_history'              => 'nota_seo_desc_history',
		'social_post_facebook_history'  => 'nota_social_post_facebook_history',
		'social_post_linkedin_history'  => 'nota_social_post_linkedin_history',
		'social_post_instagram_history' => 'nota_social_post_instagram_history',
		'social_post_threads_history'   => 'nota_social_post_threads_history',
		'social_post_tiktok_history'    => 'nota_social_post_tiktok_history',
		'social_post_twitter_history'   => 'nota_social_post_twitter_history',
		'sms_history'                   => 'nota_sms_history',
		'grade_scores_history'          => 'nota_grade_scores_history',
		'urls_score_history'            => 'nota_urls_score_history',
		'summary_history'               => 'nota_summary_history',
		'nota_published_elements'       => 'nota_published_elements',
		'keywords_synonyms_history'     => 'nota_keywords_synonyms_history',
	];

	/**
	 * Post tools constructor
	 * 
	 * @param Nota_Settings $settings The settings class.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'init', array( $this, 'register_seo_meta_fields' ) );
		add_filter( 'single_post_title', array( $this, 'single_post_title' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'add_meta_desc' ) );
		add_action( 'wp_head', array( $this, 'add_published_elements_meta' ) );
		add_action( 'wp_ajax_nota_save_metadata', array( $this, 'handle_save_post_metadata' ) );
		add_action( 'wp_ajax_nota_save_selected_brand_id', array( $this, 'handle_save_selected_brand_id' ) );
	}

	/**
	 * Handles AJAX request to save the selected brand for the current user.
	 */
	public function handle_save_selected_brand_id() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['nonce'] ), NOTA_PLUGIN_NONCE ) ) {
			exit( 'Invalid nonce' );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'User not logged in.', 401 );
			return;
		}

		$brand_id = isset( $_POST['nota']['brand_id'] ) ? sanitize_text_field( $_POST['nota']['brand_id'] ) : null;
		if ( ! $brand_id ) {
			wp_send_json_error( 'No brand ID provided.', 400 );
			return;
		}

		// Update the user meta with the new brand ID.
		$result = update_user_meta( $user_id, 'nota_selected_brand_id', $brand_id );

		if ( false === $result ) {
			wp_send_json_error( 'Failed to update the brand.', 500 );
		} else {
			wp_send_json_success( 'Brand updated successfully.' );
		}
	}

	/**
	 * Retrieves the selected brand for the current user.
	 */
	public function get_user_selected_brand_id() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			return get_user_meta( $user_id, 'nota_selected_brand_id', true );
		}
		return '';
	}


	/**
	 * Retrieves Nota metadata for a given post.
	 * 
	 * @param int $post_id The ID of the post to retrieve metadata for.
	 * @return array An array of metadata values.
	 */
	public function get_nota_metadata( $post_id ) {
		$metadata = [];
		foreach ( self::$private_meta_keys as $key => $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( ! empty( $value ) ) {
				$metadata[ $meta_key ] = maybe_unserialize( $value );
			}
		}
		return $metadata;
	}

	/**
	 * Handles saving of Nota post metadata.
	 * 
	 * Verifies the nonce and updates post metadata based on provided data.
	 * 
	 * @return void
	 */
	public function handle_save_post_metadata() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['nonce'] ), NOTA_PLUGIN_NONCE ) ) {
			exit( 'Invalid nonce' );
		}

		if ( ! isset( $_REQUEST['nota'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing Nota data' ) );
			return;
		}

		if ( isset( $_POST['nota'] ) && is_array( $_POST['nota'] ) ) {
			$data = $_POST['nota']; 
		} else {
			wp_send_json_error( 'Invalid or missing Nota data', 400 );
			return;
		}

		
		if ( ! isset( $data['post_id'] ) || ! isset( $data['metadata'] ) ) {
			wp_send_json_error( 'Missing required data', 400 );
			return;
		}

		$post_id  = intval( $data['post_id'] );
		$metadata = $data['metadata'];

		foreach ( self::$private_meta_keys as $key => $meta_key ) {
			if ( isset( $metadata[ $meta_key ] ) ) {
				$meta_value           = $metadata[ $meta_key ];
				$sanitized_meta_value = $this->sanitize_meta_callback( $meta_value, $meta_key, 'post' );
				update_post_meta( $post_id, $meta_key, $sanitized_meta_value );
			}
		}

		wp_send_json_success( 'Metadata updated successfully.' );
	}

	/**
	 * Returns a list of supported post types to display the tools on
	 */
	public function get_tools_supported_post_types() {
		$post_types = apply_filters( 'nota_tools_supported_post_types', [ 'post', 'newspack_lst_generic', 'newspack_lst_mktplce', 'newspack_lst_place' ] );
		return $post_types;
	}

	/**
	 * Enqueues various admin scripts
	 * 
	 * @param string $hook The hook suffix.
	 */
	public function admin_enqueue_scripts( $hook ) {
		global $post;
		$screen = get_current_screen();
		if (
			( 'post-new.php' === $hook || 'post.php' === $hook ) &&
			$screen->is_block_editor() &&
			in_array( $post->post_type, $this->get_tools_supported_post_types() )
		) {
			$yoast_enabled     = $this->is_yoast_active_for_post_type( $post->post_type );
			$taxonomies        = get_post_taxonomies();
			$tool_script_args  = include NOTA_PLUGIN_ABSPATH . 'dist/postTools.asset.php';
			$components        = [
				'hashtags'               => true,
				'categories'             => in_array( 'category', $taxonomies ),
				'meta_description'       => true,
				'meta_title'             => true,
				'quotes'                 => true,
				'tags'                   => in_array( 'post_tag', $taxonomies ),
				'keywordSynonyms'        => true,
				'social_posts_facebook'  => true,
				'social_posts_instagram' => true,
				'social_posts_linkedin'  => true,
				'social_posts_threads'   => true,
				'social_posts_tiktok'    => true,
				'social_posts_twitter'   => true,
				'sms'                    => true,
			];
			$selected_brand_id = $this->get_user_selected_brand_id();
			wp_register_script( 'nota-post-tools', NOTA_PLUGIN_URL . 'dist/postTools.js', $tool_script_args['dependencies'], $tool_script_args['version'], true );
			wp_localize_script(
				'nota-post-tools',
				'notaTools',
				[
					'postId'                => get_the_ID() ? get_the_ID() : 0,
					'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
					'metadata'              => $this->get_nota_metadata( get_the_ID() ),
					'nonce'                 => wp_create_nonce( NOTA_PLUGIN_NONCE ),
					'components'            => apply_filters( 'nota_tools_supported_components', $components ),
					'meta_keys'             => array_merge( self::$public_meta_keys, self::$private_meta_keys ),
					'post_title_suffix'     => $this->get_post_title_suffix(),
					'register_controls'     => [
						'seo'   => ! $yoast_enabled,
						'grade' => true,
					],
					'tools_active'          => ! empty( $this->settings->get_option( 'api_key' ) ),
					'tab_seo_enabled'       => $this->settings->get_option( 'hide_seo_tab' ) != 1,
					'tab_social_enabled'    => $this->settings->get_option( 'hide_social_tab' ) != 1,
					'tab_sum_enabled'       => $this->settings->get_option( 'hide_content_tab' ) != 1,
					'trigger_proof_enabled' => $this->settings->get_option( 'trigger_proof_enabled' ) == 1,
					'tracking_enabled'      => $this->settings->get_option( 'tracking_enabled' ) == 1,
					'selected_brand_id'     => $selected_brand_id,
				]
			);
			wp_enqueue_script( 'nota-post-tools' );
			wp_enqueue_style( 'nota-post-tools-style', NOTA_PLUGIN_URL . 'dist/postTools.css', [], $tool_script_args['version'] );
			wp_enqueue_style( 'nota-font-manrope', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap', [], '1.0.0' );
		}
	}

	/**
	 * Returns the post title suffix.
	 * This is normally appended by Yoast, but if not WordPress will add their default.
	 * We want to display this to users so we'll show them the WordPress default if need be.
	 */
	private function get_post_title_suffix() {
		// extracted from wp_get_document_title.
		$sep        = apply_filters( 'document_title_separator', '-' );
		$site_title = get_bloginfo( 'name', 'display' );
		return $sep . ' ' . $site_title;
	}

	/**
	 * Adds meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'nota-post-tools',
			__( 'Nota Tools', 'nota' ),
			array( $this, 'render_post_tools' ),
			$this->get_tools_supported_post_types(),
			'normal',
			'high',
			[ '__block_editor_compatible_meta_box' => true ]
		);
	}

	/**
	 * Renders the tool meta box
	 */
	public function render_post_tools() {
		include_once NOTA_PLUGIN_ABSPATH . 'templates/admin/post-tools-meta-box.php';
	}

	/**
	 * Determines whether Gutenberg is enabled
	 */
	public function is_gutenberg_enabled() {
		$current_screen = get_current_screen();
		return $current_screen->is_block_editor();
	}

	/**
	 * Registers meta fields on posts
	 */
	public function register_seo_meta_fields() {
		$post_types = $this->get_tools_supported_post_types();      
		foreach ( $post_types as $post_type ) {
			$meta_args = array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_meta_callback' ),
			);

			foreach ( self::$public_meta_keys as $meta_key ) {
				register_post_meta(
					$post_type,
					$meta_key,
					$meta_args
				);
			}  
		}
	}

	/**
	 * Whether Yoast is active for a post type
	 * 
	 * @param string $post_type The post type to check.
	 */
	private function is_yoast_active_for_post_type( $post_type ) {
		$yoast_post_types = class_exists( 'WPSEO_Post_Type' ) ? WPSEO_Post_Type::get_accessible_post_types() : [];
		$yoast_enabled    = in_array( $post_type, $yoast_post_types );
		return $yoast_enabled;
	}

	/**
	 * Updates meta title if it exists
	 * 
	 * @param string $post_title The current post title.
	 * @param object $post The current post.
	 */
	public function single_post_title( $post_title, $post ) {
		// if Yoast is active, leave them to it.
		if ( $this->is_yoast_active_for_post_type( $post->post_type ) ) {
			return $post_title;
		}

		$nota_post_title = get_post_meta( $post->ID, self::$public_meta_keys['seo_title'], true );
		if ( ! $nota_post_title ) {
			return $post_title;
		}

		return $nota_post_title;
	}

	/**
	 * Adds the meta description to the page.
	 */
	public function add_meta_desc() {
		global $post;

		// if we're not on the single post page or yoast is enabled,
		// then just return.
		if ( ! is_singular() || $this->is_yoast_active_for_post_type( $post->post_type ) ) {
			return;
		}

		$meta_desc = get_post_meta( $post->ID, self::$public_meta_keys['seo_desc'], true );
		if ( ! $meta_desc ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />';
	}

	/**
	 * Adds the 'nota_published_elements' meta tag to the page.
	 */
	public function add_published_elements_meta() {
		 global $post;

		// Check if we're on a single post page.
		if ( ! is_singular() ) {
			return;
		}

		// Retrieve the 'nota_published_elements' meta value.
		$published_elements = get_post_meta( $post->ID, self::$private_meta_keys['nota_published_elements'], true );

		// If the meta value is not empty, output the meta tag.
		if ( ! empty( $published_elements ) ) {
			echo '<meta name="nota-published-elements" content="' . esc_attr( $published_elements ) . '" />' . PHP_EOL;
		}
	}

	/**
	 * Sanitises registered metadata.
	 * We've discovered that some databases can use a collation on the meta_value column which doesn't support things like emojis.
	 * This unfortunately causes the meta save to fail. So we'll jump in and sanitise the data early, so that it saves OK.
	 *
	 * @param mixed  $meta_value The meta value to be saved.
	 * @param string $_meta_key The meta key to save.
	 * @param string $object_type The type of object metadata is for.
	 */
	public function sanitize_meta_callback( $meta_value, $_meta_key, $object_type ) {
		global $wpdb;
		$table = _get_meta_table( $object_type );
		if ( ! $table ) {
			return $meta_value;
		}
		$sanitised = $wpdb->strip_invalid_text_for_column( $table, 'meta_value', $meta_value );
		return $sanitised;
	}
}
