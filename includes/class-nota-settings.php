<?php
/**
 * Nota Settings
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nota Settings class
 */
class Nota_Settings {
	/**
	 * Key for the settings
	 * 
	 * @var string
	 */
	public $setting_field_key = 'nota_plugin_settings';

	/**
	 * Key for the general settings section
	 * 
	 * @var string
	 */
	public $general_settings_section_key = 'nota_general_settings';

	/**
	 * Key for the output settings section
	 * 
	 * @var string
	 */
	public $output_settings_section_key = 'nota_output_settings';

	/**
	 * Key for the tool settings section
	 * 
	 * @var string
	 */
	public $tool_settings_section_key = 'nota_tool_settings';

	/**
	 * Key for the troubleshooting settings section
	 * 
	 * @var string
	 */
	public $troubleshooting_settings_section_key = 'nota_troubleshooting_settings';

	/**
	 * The settings page slug
	 * 
	 * @var string
	 */
	public $setting_page_slug = 'nota-settings';

	/**
	 * Nota Settings constructor
	 */
	public function __construct() {         
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Registers menus
	 */
	public function register_menu() {
		add_options_page( __( 'Nota settings', 'nota' ), __( 'Nota tools', 'nota' ), 'manage_options', $this->setting_page_slug, array( $this, 'render_settings_page' ) );
	}

	/**
	 * Registers settings
	 */
	public function register_settings() {
		// general settings.

		add_settings_section( $this->general_settings_section_key, __( 'General settings', 'nota' ), '__return_false', $this->setting_page_slug );
		
		$this->add_setting_field(
			'api_key',
			__( 'API Key', 'nota' ),
			array( $this, 'render_text_input' ),
			$this->general_settings_section_key
		);

		$this->add_setting_field(
			'api_url',
			__( 'API URL', 'nota' ),
			array( $this, 'render_text_input' ),
			$this->general_settings_section_key
		);

		$this->add_setting_field(
			'request_timeout_seconds',
			__( 'Request Timeout (Seconds)', 'nota' ),
			array( $this, 'render_numeric_input' ),
			$this->general_settings_section_key,
			array(
				'description' => __( 'Leave empty to use the WordPress defaults. If you are experiencing timeout errors, try increasing this value.', 'nota' ),
				'max'         => 30,
				'min'         => 0,
			)
		);

		// output settings.

		add_settings_section( $this->output_settings_section_key, __( 'Output Settings', 'nota' ), '__return_false', $this->setting_page_slug );

		$this->add_setting_field(
			'headline_max_characters',
			__( 'Max Headline Length', 'nota' ),
			array( $this, 'render_numeric_input' ),
			$this->output_settings_section_key,
			array(
				'description' => __( 'The maximum number of characters included in a generated headline.', 'nota' ),
				'max'         => 120,
				'min'         => 30,
			)
		);

		$case_options = [
			''            => __( 'None', 'nota' ),
			'sentence'    => __( 'Sentence case', 'nota' ),
			'title'       => __( 'Title case', 'nota' ),
			'capitalized' => __( 'Capitalized case', 'nota' ),
		];

		$social_post_options = [
			''            => __( 'None', 'nota' ),
			'news'        => __( 'News', 'nota' ),
			'promotional' => __( 'Promotional', 'nota' ),
		];

		$this->add_setting_field(
			'headline_casing',
			__( 'Headline Casing', 'nota' ),
			array( $this, 'render_select_input' ),
			$this->output_settings_section_key,
			array(
				'description' => __( 'The type of casing used when generating headlines.', 'nota' ),
				'empty_text'  => __( 'Select one', 'nota' ),
				'options'     => $case_options,
			)
		);

		$this->add_setting_field(
			'meta_title_casing',
			__( 'Page Title Casing', 'nota' ),
			array( $this, 'render_select_input' ),
			$this->output_settings_section_key,
			array(
				'description' => __( 'The type of casing used when generating page titles.', 'nota' ),
				'empty_text'  => __( 'Select one', 'nota' ),
				'options'     => $case_options,
			)
		);

		$this->add_setting_field(
			'social_post_style',
			__( 'Social Post Style', 'nota' ),
			array( $this, 'render_select_input' ),
			$this->output_settings_section_key,
			array(
				'description' => __( 'The style of social post output.', 'nota' ),
				'empty_text'  => __( 'Select one', 'nota' ),
				'options'     => $social_post_options,
			)
		);

		// tool settings.

		add_settings_section( $this->tool_settings_section_key, __( 'Tool Settings', 'nota' ), '__return_false', $this->setting_page_slug );

		$this->add_setting_field(
			'trigger_proof_enabled',
			__( 'Automatically trigger Proof', 'nota' ),
			array( $this, 'render_checkbox_input' ),
			$this->tool_settings_section_key,
			array(
				'subtitle' => __( 'When enabled, analyzing the page with Nota tools will refresh Proof.', 'nota' ),
			)
		);      

		$this->add_setting_field(
			'hide_content_tab',
			__( 'Hide Content Tab', 'nota' ),
			array( $this, 'render_checkbox_input' ),
			$this->tool_settings_section_key
		);

		$this->add_setting_field(
			'hide_seo_tab',
			__( 'Hide SEO Tab', 'nota' ),
			array( $this, 'render_checkbox_input' ),
			$this->tool_settings_section_key
		);

		$this->add_setting_field(
			'hide_social_tab',
			__( 'Hide Social Tab', 'nota' ),
			array( $this, 'render_checkbox_input' ),
			$this->tool_settings_section_key
		);

		$this->add_setting_field(
			'tracking_enabled',
			__( 'Enable Analytics', 'nota' ),
			array( $this, 'render_checkbox_input' ),
			$this->tool_settings_section_key
		);

		// troubleshooting settings.

		add_settings_section( $this->troubleshooting_settings_section_key, __( 'Troubleshooting', 'nota' ), '__return_false', $this->setting_page_slug );

		$this->add_setting_field(
			'debug',
			__( 'Debug', 'nota' ),
			array( $this, 'render_checkbox_input' ),
			$this->troubleshooting_settings_section_key
		);
	}

	/**
	 * Adds a setting field
	 * 
	 * @param string   $name The name of the option.
	 * @param string   $label The option label.
	 * @param callable $callback The fn to render the input.
	 * @param string   $section The section ID to render into.
	 * @param array    $args Extra arguments to pass to the callback.
	 */
	private function add_setting_field( $name, $label, $callback, $section, $args = array() ) {

		// ensure the setting is registered with WP.
		register_setting( $this->setting_field_key, $this->get_option_name( $name ) );

		// set up some default args that every input will need.
		$input_id     = "nota_setting_{$name}";
		$default_args = array(
			'name'      => $name,
			'input_id'  => $input_id,
			'label_for' => $input_id,
		);

		// display the settings field.
		add_settings_field(
			'nota_' . $name,
			$label,
			$callback,
			$this->setting_page_slug,
			$section,
			array_merge( $default_args, $args )
		);
	}

	/**
	 * Renders the settings page
	 */
	public function render_settings_page() {
		// not used directly, but passed through to the template via include.
		$nota = array(
			'setting_field_key' => $this->setting_field_key,
			'setting_page_slug' => $this->setting_page_slug,
		);
		include_once NOTA_PLUGIN_ABSPATH . 'templates/admin/settings-page.php';
	}

	/**
	 * Gets the real option name
	 * 
	 * @param string $key The option name.
	 */
	public function get_option_name( $key ) {
		return "nota_{$key}";
	}

	/**
	 * Returns an option
	 * 
	 * @param string $key The name of the option, without the nota_ prefix.
	 * @param mixed  $default The default value.
	 */
	public function get_option( $key, $default = false ) {
		return get_option( $this->get_option_name( $key ), $default );
	}

	/**
	 * Renders a numeric input field
	 * 
	 * @param mixed $args Any args sent by the registering function.
	 */
	public function render_numeric_input( $args ) {
		$value      = $this->get_option( $args['name'] );
		$field_name = $this->get_option_name( $args['name'] );
		?>
		<input 
			id='<?php echo esc_attr( $args['input_id'] ); ?>' 
			min='<?php echo esc_attr( $args['min'] ?? 0 ); ?>'
			max='<?php echo esc_attr( $args['max'] ); ?>'
			name='<?php echo esc_attr( $field_name ); ?>' 
			value='<?php echo esc_attr( $value ); ?>' 
			type='number'
		/>
		<?php
		if ( array_key_exists( 'description', $args ) && ! empty( $args['description'] ) ) {
			echo '<p>' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Renders a select input field
	 * 
	 * @param mixed $args Any args sent by the registering function.
	 */
	public function render_select_input( $args ) {
		$value      = $this->get_option( $args['name'] );
		$field_name = $this->get_option_name( $args['name'] );
		?>
		<select id='<?php echo esc_attr( $args['input_id'] ); ?>' name='<?php echo esc_attr( $field_name ); ?>'>
		<?php if ( array_key_exists( 'empty_text', $args ) && ! empty( $args['empty_text'] ) ) : ?>
	  <option value="" disabled <?php echo selected( $value ? $value : '', '', false ); ?> ><?php echo esc_html( $args['empty_text'] ); ?></option>
	<?php endif; ?>
		<?php
		if ( array_key_exists( 'options', $args ) && ! empty( $args['options'] ) && is_array( $args['options'] ) ) {
			foreach ( $args['options'] as $key => $label ) {
				?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php echo selected( $value, $key, false ); ?>><?php echo esc_html( $label ); ?></option>
				<?php
			}
		}
		?>
		</select>
		<?php
		if ( array_key_exists( 'description', $args ) && ! empty( $args['description'] ) ) {
			echo '<p>' . esc_html( $args['description'] ) . '</p>';
		}
	}

		/**
		 * Renders a text input field
		 * 
		 * @param mixed $args Any args sent by the registering function.
		 */
	public function render_text_input( $args ) {
		$value      = $this->get_option( $args['name'] );
		$field_name = $this->get_option_name( $args['name'] );
		?>
		<input 
			class="regular-text"
			id='<?php echo esc_attr( $args['input_id'] ); ?>' 
			name='<?php echo esc_attr( $field_name ); ?>' 
			value='<?php echo esc_attr( $value ); ?>' 
			type='text'
		/>
			<?php
			if ( array_key_exists( 'description', $args ) && ! empty( $args['description'] ) ) {
				echo '<p>' . esc_html( $args['description'] ) . '</p>';
			}
	}

		/**
		 * Renders a checkbox field
		 * 
		 * @param mixed $args Any args sent by the registering function.
		 */
	public function render_checkbox_input( $args ) {
		$checked    = $this->get_option( $args['name'] ) ? 'checked' : '';
		$field_name = $this->get_option_name( $args['name'] );
		?>
			<input id='<?php echo esc_attr( $args['input_id'] ); ?>' name='<?php echo esc_attr( $field_name ); ?>' value='1' type="checkbox" <?php echo esc_attr( $checked ); ?> />
			<?php
			if ( array_key_exists( 'subtitle', $args ) && ! empty( $args['subtitle'] ) ) {
				echo '<p class="description">' . esc_html( $args['subtitle'] ) . '</p>';
			}
	}
}
