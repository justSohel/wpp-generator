<?php
/**
 * Plugin Name: WPPGenerator
 * Description: WP-CLI command to generate WordPress plugin boilerplate (MVC + Composer Autoload).
 * Author: justSohel
 * Author URI: https://github.com/justSohel
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH')) exit;

// Load WP-CLI command if running in CLI
if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/class-wpp-generate-command.php';
    WP_CLI::add_command('generate:plugin', 'WPP_Generate_Command');
}
