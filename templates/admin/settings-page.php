<?php
/**
 * Settings page
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

?>
<h1>Nota Settings</h1>
<form action="options.php" method="post">
	<?php
	settings_fields( $nota['setting_field_key'] );
	do_settings_sections( $nota['setting_page_slug'] )
	?>
<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
</form>
