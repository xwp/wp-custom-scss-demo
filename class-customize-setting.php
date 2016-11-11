<?php
/**
 * Customize API: CustomScssDemo\Customize_Setting class
 *
 * @package CustomScssDemo
 */

namespace CustomScssDemo;

/**
 * Custom Setting to handle WP Custom (S)CSS.
 *
 * @see WP_Customize_Custom_CSS_Setting
 */
class Customize_Setting extends \WP_Customize_Custom_CSS_Setting {

	/**
	 * Fetch the value of the setting.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return string
	 */
	public function value() {
		$customized_value = $this->post_value( null );
		if ( $this->is_previewed && null !== $customized_value ) {
			return $customized_value;
		}
		$value = '';
		$post = wp_get_custom_css_post( $this->stylesheet );
		if ( $post ) {
			$value = $post->post_content_filtered;
			if ( empty( $value ) ) {
				$value = $post->post_content;
			}
		}
		if ( empty( $value ) ) {
			$value = $this->default;
		}
		return $value;
	}

	/**
	 * Compile SCSS.
	 *
	 * @param string $scss SCSS.
	 * @return string CSS
	 */
	protected function transpile_scss( $scss ) {
		if ( ! class_exists( 'scssc' ) ) {
			require_once __DIR__ . '/lib/scss.inc.php';
		}
		$scss_compiler = new \scssc();
		return $scss_compiler->compile( $scss );
	}

	/**
	 * Filter `wp_get_custom_css` for applying the customized value.
	 *
	 * This is used in the preview when `wp_get_custom_css()` is called for rendering the styles.
	 *
	 * @param string $css Original CSS.
	 * @param string $stylesheet Current stylesheet.
	 * @return string CSS.
	 */
	public function filter_previewed_wp_get_custom_css( $css, $stylesheet ) {
		$preprocessor_setting = $this->manager->get_setting( 'custom_css_preprocessor' );
		if ( ! $preprocessor_setting || $stylesheet !== $this->stylesheet ) {
			return parent::filter_previewed_wp_get_custom_css( $css, $stylesheet );
		}

		$customized_value = $this->post_value( null );
		if ( ! is_null( $customized_value ) ) {
			$css = $customized_value;
			if ( 'scss' === $preprocessor_setting->value() ) {
				$css = $this->transpile_scss( $css );
			}
		}
		return $css;
	}

	/**
	 * Store the (S)CSS setting value in the custom_css custom post type for the stylesheet.
	 *
	 * @param string $scss The input value.
	 * @return int|false The post ID or false if the value could not be saved.
	 */
	public function update( $scss ) {

		$post_content = $scss;
		$post_content_filtered = $scss;

		$preprocessor_setting = $this->manager->get_setting( 'custom_css_preprocessor' );
		if ( $preprocessor_setting && 'scss' === $preprocessor_setting->post_value( $preprocessor_setting->value() ) ) {
			$post_content = $this->transpile_scss( $scss );
		}

		$args = array(
			'post_content' => $post_content,
			'post_content_filtered' => $post_content_filtered,
			'post_title' => $this->stylesheet,
			'post_name' => sanitize_title( $this->stylesheet ),
			'post_type' => 'custom_css',
			'post_status' => 'publish',
		);

		// Update post if it already exists, otherwise create a new one.
		$post = wp_get_custom_css_post( $this->stylesheet );
		if ( $post ) {
			$args['ID'] = $post->ID;
			$post_id = wp_update_post( wp_slash( $args ) );
		} else {
			$post_id = wp_insert_post( wp_slash( $args ) );
		}
		if ( ! $post_id ) {
			return false;
		}

		// Cache post ID in theme mod for performance to avoid additional DB query.
		if ( $this->manager->get_stylesheet() === $this->stylesheet ) {
			set_theme_mod( 'custom_css_post_id', $post_id );
		}

		return $post_id;
	}
}
