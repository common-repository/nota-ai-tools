<?php
/**
 * Nota Debug
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nota Debug class
 */
class Nota_Debug {

	/**
	 * Nota_Debug constructor
	 */
	public function __construct() {
		add_filter( 'debug_information', array( $this, 'debug_information' ) );
	}   

	/**
	 * Registers Nota's debug information on the WordPress Site Health page.
	 *
	 * @param array $info The WordPress debugging info.
	 */
	public function debug_information( $info ) {
		global $wpdb;
		$info['nota'] = [
			'label'  => __( 'Nota', 'nota' ),
			'fields' => [],
		];


		$columns = [
			[
				'label' => __( 'Meta Value', 'nota' ),
				'table' => $wpdb->postmeta,
				'col'   => 'meta_value',
			],
			[
				'label' => __( 'Post Title', 'nota' ),
				'table' => $wpdb->posts,
				'col'   => 'post_title',
			],
			[
				'label' => __( 'Post Excerpt', 'nota' ),
				'table' => $wpdb->posts,
				'col'   => 'post_excerpt',
			],
			[
				'label' => __( 'Post Content', 'nota' ),
				'table' => $wpdb->posts,
				'col'   => 'post_content',
			],
		];

		$chars_to_support = [
			[
				'label'     => __( 'emoji', 'nota' ),
				'test_char' => '😀',
			],
		];

		foreach ( $columns as $column ) {
			$charset      = $wpdb->get_col_charset( $column['table'], $column['col'] );
			$supports     = [];
			$not_supports = [];
			foreach ( $chars_to_support as $char_to_support ) {
				$stripped = $wpdb->strip_invalid_text_for_column( $column['table'], $column['col'], $char_to_support['test_char'] );
				if ( $stripped === $char_to_support['test_char'] ) {
					$supports[] = $char_to_support['label'];
				} else {
					$not_supports[] = $char_to_support['label'];
				}           
			}
			// translators: Lists the types of characters that are supported by the DB column.
			$support_string = empty( $supports ) ? '' : sprintf( __( 'Supports %s.', 'nota' ), implode( ', ', $supports ) );
			// translators: Lists the types of characters that are not supported by the DB column.
			$not_support_string = empty( $not_supports ) ? '' : sprintf( __( 'Does not support %s.', 'nota' ), implode( ', ', $not_supports ) );
			// translators: Describes the charset of the column as well as what is supported and not supported by that column.
			$col_value = sprintf( __( 'Charset: %1$s. %2$s %3$s', 'nota' ), $charset, $support_string, $not_support_string );
			$info['nota']['fields'][ "db_col_info_{$column['col']}" ] = [
				'label' => $column['label'],
				'value' => $col_value,
			];
		}
		return $info;
	}

}
