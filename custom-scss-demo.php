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
 * Enqueue preview scripts.
 */
function enqueue_preview_scripts() {
	if ( ! is_customize_preview() ) {
		return;
	}
	$handle = 'custom-scss-demo-preview';
	$src = plugin_dir_url( __FILE__ ) . 'custom-scss-demo-preview.js';
	$deps = array( 'customize-preview' );
	wp_enqueue_script( $handle, $src, $deps );
}
add_action( 'customize_preview_init', function() {
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_preview_scripts' );
} );

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

	require_once __DIR__ . '/class-customize-setting.php';
	$custom_scss_setting = new Customize_Setting( $wp_customize, $custom_css_setting->id, wp_array_slice_assoc(
		get_object_vars( $custom_css_setting ),
		array( 'default', 'capability' )
	) );
	$wp_customize->remove_setting( $custom_css_setting->id );
	$wp_customize->add_setting( $custom_scss_setting );
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
