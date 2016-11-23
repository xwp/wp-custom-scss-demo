<?php
/**
 * Plugin Name: Custom SCSS Demo
 * Version: 0.1
 * Description: Proof of concept for how to extend the Custom CSS functionality in WordPress to support a CSS transpiler.
 * Author: Weston Ruter, XWP
 * Author URI: https://make.xwp.co/
 * Domain Path: /languages
 *
 * Copyright (c) 2016 XWP (https://make.xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package CustomScssDemo
 */

namespace CustomScssDemo;

/**
 * Add filter for wp_get_custom_css to transpile SCSS into CSS.
 */
function customize_preview_init() {
	add_filter( 'wp_get_custom_css', __NAMESPACE__ . '\filter_previewed_custom_scss', 10, 2 );
}
add_action( 'customize_preview_init', __NAMESPACE__ . '\customize_preview_init' );

/**
 * Filter custom_css to transpile.
 *
 * @param string $css CSS
 * @return string CSS.
 */
function filter_previewed_custom_scss( $css ) {
	global $wp_customize;

	$preprocessor_setting = $wp_customize->get_setting( 'custom_css_preprocessor' );
	if ( $preprocessor_setting && 'scss' === $preprocessor_setting->value() ) {
		$css = transpile_scss( $css );
	}
	return $css;
}

/**
 * Transpile SCSS.
 *
 * @param string $scss SCSS.
 * @return string CSS.
 */
function transpile_scss( $scss ) {
	if ( ! class_exists( 'scssc' ) ) {
		require_once __DIR__ . '/lib/scss.inc.php';
	}
	$scss_compiler = new \scssc();
	return $scss_compiler->compile( $scss );
}

/**
 * Filter customize value for custom_css setting.
 *
 * If a SCSS preprocessor is being used, then the CSS is pulled from `post_content_filtered`
 * which contains the pre-processed source. Note that if the value is customized, then this
 * filter will not apply as the post_value will be used directly.
 *
 * @param string $css CSS value.
 * @param \WP_Customize_Custom_CSS_Setting $setting
 * @return string SCSS value.
 */
function filter_customize_value( $css, \WP_Customize_Custom_CSS_Setting $setting ) {
	$preprocessor_setting = $setting->manager->get_setting( 'custom_css_preprocessor' );

	if ( ! $preprocessor_setting || 'scss' !== $preprocessor_setting->value() ) {
		return $css;
	}

	$post = wp_get_custom_css_post( $setting->stylesheet );
	if ( $post && ! empty( $post->post_content_filtered ) ) {
		$css = $post->post_content_filtered;
	}

	return $css;
}

/**
 * Filter post content args for `custom_css` being updated.
 *
 * Stores SCSS in `post_content_filtered` and transpiled CSS in `post_content`.
 * The reverse operation is done in `filter_customize_value()` when reading the
 * value out of the `custom_css` post.
 *
 * @param array $data {
 *     Custom CSS data.
 *
 *     @type string $css          CSS stored in `post_content`.
 *     @type string $preprocessed Pre-processed CSS stored in `post_content_filtered`. Normally empty string.
 * }
 * @return array Post content args.
 */
function filter_update_custom_css_data( $data ) {
	global $wp_customize;

	/*
	 * When updating custom_css in the customizer, make sure that custom_css_preprocessor
	 * setting is saved up-front so that the value of the theme mod will be available
	 * determining whether transpilation needs to occur. This is needed because during
	 * customize_save the preview filters are not applied and the custom_css_preprocessor
	 * setting could be saved *after* the custom_css setting.
	 */
	if ( $wp_customize instanceof \WP_Customize_Manager && did_action( 'customize_save' ) && ! did_action( 'customize_save_custom_css_preprocessor' ) ) {
		$preprocessor_setting = $wp_customize->get_setting( 'custom_css_preprocessor' );
		if ( $preprocessor_setting ) {
			$preprocessor_setting->save();
		}
	}

	if ( 'scss' === get_theme_mod( 'custom_css_preprocessor' ) ) {
		$data['preprocessed'] = $data['css']; // Stash original SCSS in post_content_filtered.
		$data['css'] = transpile_scss( $data['preprocessed'] );
	}
	return $data;
}

/**
 * Enqueue preview scripts.
 */
function enqueue_preview_scripts() {
	if ( ! is_customize_preview() ) {
		return;
	}
	$handle = 'custom-scss-demo-preview';
	$src = plugin_dir_url( __FILE__ ) . 'custom-scss-demo-preview.js';
	$deps = array( 'customize-preview', 'customize-selective-refresh' );
	wp_enqueue_script( $handle, $src, $deps );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_preview_scripts' );

/**
 * Register controls.
 *
 * @param \WP_Customize_Manager $wp_customize Manager.
 */
function register_controls( \WP_Customize_Manager $wp_customize ) {

	$custom_css_setting = $wp_customize->get_setting( sprintf( 'custom_css[%s]', $wp_customize->get_stylesheet() ) );
	$custom_css_control = $wp_customize->get_control( 'custom_css' );
	if ( ! $custom_css_setting || ! $custom_css_control ) {
		return;
	}

	$custom_css_control->priority = 20;

	$wp_customize->add_setting( 'custom_css_preprocessor', array(
		'type' => 'theme_mod',
		'capability' => $custom_css_setting->capability,
		'transport' => 'postMessage',
		'sanitize_callback' => function( $value ) use ( $wp_customize ) {
			$control = $wp_customize->get_control( 'custom_css_preprocessor' );
			if ( ! $control || ! array_key_exists( $value, $control->choices ) ) {
				return new \WP_Error( 'invalid_value', __( 'Illegal CSS preprocessor.', 'custom-scss-demo' ) );
			}
			return $value;
		}
	) );

	$wp_customize->add_control( 'custom_css_preprocessor', array(
		'section' => $custom_css_control->section,
		'type' => 'select',
		'label' => __( 'CSS Preprocessor', 'custom-scss-demo' ),
		'choices' => array(
			'' => __( 'None (Plain CSS)', 'custom-scss-demo' ),
			'scss' => __( 'SCSS', 'custom-scss-demo' ),
		),
		'priority' => 10,
	) );

	// Ensure that the post_content_filtered is used as the value when SCSS is chosen as the pre-processor.
	add_filter( 'customize_value_custom_css', __NAMESPACE__ . '\filter_customize_value', 10, 2 );

	// Ensure that the SCSS gets stored in post_content_filtered, and transpiled CSS in post_content.
	add_filter( 'update_custom_css_data', __NAMESPACE__ . '\filter_update_custom_css_data', 10 );
}
add_action( 'customize_register', __NAMESPACE__ . '\register_controls', 20 );

/**
 * Setup selective refresh.
 *
 * @param \WP_Customize_Manager $wp_customize Manager.
 */
function register_selective_refresh_partial( \WP_Customize_Manager $wp_customize ) {
	$wp_customize->selective_refresh->add_partial( 'custom_css', array(
		'type' => 'custom_css',
		'selector' => '#wp-custom-css',
		'container_inclusive' => false,
		'fallback_refresh' => false,
		'settings' => array(
			'custom_css[' . $wp_customize->get_stylesheet() . ']',
			'custom_css_preprocessor',
		),
		'render_callback' => function() {
			echo wp_get_custom_css();
		}
	) );
}
add_action( 'customize_register', __NAMESPACE__ . '\register_selective_refresh_partial' );

/**
 * Filter revisioned fields for `custom_css` posts.
 *
 * @param array $fields Fields.
 * @param array $post   Post.
 * @return array Fields.
 */
function filter_custom_css_post_revision_fields( $fields, $post ) {
	if ( 'custom_css' === $post['post_type'] ) {
		$fields['post_content'] = __( 'CSS', 'custom-scss-demo' );
		$fields['post_content_filtered'] = __( 'Pre-processed CSS', 'custom-scss-demo' );
	}
	return $fields;
}
add_filter( '_wp_post_revision_fields', __NAMESPACE__ .  '\filter_custom_css_post_revision_fields', 10, 2 );
