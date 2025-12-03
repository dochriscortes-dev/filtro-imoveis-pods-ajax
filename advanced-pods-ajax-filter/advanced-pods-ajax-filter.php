<?php
/**
 * Plugin Name: Advanced Pods AJAX Filter
 * Description: Custom advanced search and filtering system for 'imovel' CPT using AJAX.
 * Version: 1.0.0
 * Author: Jules
 * Text Domain: advanced-pods-ajax-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'APAF_PATH', plugin_dir_path( __FILE__ ) );
define( 'APAF_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue Scripts and Styles
 */
function apaf_enqueue_scripts() {
	// Styles
	wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
	wp_enqueue_style( 'nouislider-css', 'https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.css', array(), '15.7.0' );
	wp_enqueue_style( 'apaf-style', APAF_URL . 'assets/css/style.css', array(), '1.0.0' );

	// Scripts
	wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
	wp_enqueue_script( 'nouislider-js', 'https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.7.0/nouislider.min.js', array(), '15.7.0', true );
	wp_enqueue_script( 'apaf-script', APAF_URL . 'assets/js/script.js', array( 'jquery', 'select2-js', 'nouislider-js' ), '1.0.0', true );

	// Localize script for AJAX
	wp_localize_script( 'apaf-script', 'apaf_obj', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'apaf_filter_nonce' ),
	));
}
add_action( 'wp_enqueue_scripts', 'apaf_enqueue_scripts' );

// Include Shortcode and AJAX Handler
require_once APAF_PATH . 'includes/shortcode.php';
require_once APAF_PATH . 'includes/ajax-handler.php';
