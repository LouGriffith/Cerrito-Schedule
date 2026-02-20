<?php
/**
 * Plugin Name: Cerrito Schedule Display
 * Plugin URI:  https://github.com/LouGriffith/Cerrito-Schedule
 * Description: Schedule shortcodes for displaying events (works with ACF)
 * Version:     6.3
 * Author:      Lou Griffith
 * Author URI:  https://lougriffith.com
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CERRITO_SCHEDULE_VERSION', '6.3' );
define( 'CERRITO_SCHEDULE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CERRITO_SCHEDULE_URL',     plugin_dir_url( __FILE__ ) );

// ── Core ──────────────────────────────────────────────────────────────────────
require_once CERRITO_SCHEDULE_DIR . 'includes/helpers.php';
require_once CERRITO_SCHEDULE_DIR . 'includes/class-updater.php';

// ── Shortcodes ────────────────────────────────────────────────────────────────
require_once CERRITO_SCHEDULE_DIR . 'includes/shortcode-schedule.php';
require_once CERRITO_SCHEDULE_DIR . 'includes/shortcode-recurring.php';
require_once CERRITO_SCHEDULE_DIR . 'includes/shortcode-master.php';
require_once CERRITO_SCHEDULE_DIR . 'includes/shortcode-today.php';
require_once CERRITO_SCHEDULE_DIR . 'includes/shortcode-themed-rounds.php';
require_once CERRITO_SCHEDULE_DIR . 'includes/shortcode-upcoming-themes.php';

// ── Assets ────────────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'cerrito_schedule_enqueue_assets' );
function cerrito_schedule_enqueue_assets() {
    wp_register_style(
        'cerrito-schedule',
        CERRITO_SCHEDULE_URL . 'assets/schedule.css',
        array(),
        CERRITO_SCHEDULE_VERSION
    );
}

// ── Updater ───────────────────────────────────────────────────────────────────
new Cerrito_Schedule_Updater( __FILE__ );
