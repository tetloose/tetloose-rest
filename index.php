<?php
/**
 * Plugin Name: Tetloose REST
 * Description: Enhances the WordPress REST API with camelCase keys, ACF Fields in REST, ACF Options, and menu endpoints.
 * Author: James Tetley
 * Author URI: https://github.com/tetloose
 * Version: 1.0.0
 * Plugin URI: https://github.com/tetloose/tetloose-rest
 * License: MIT
 * Requires PHP: ^7.4
 *
 * @package Tetloose-Rest
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/functions/rest-to-camel-case.php';
require_once dirname( __FILE__ ) . '/functions/acf-options-page.php';
require_once dirname( __FILE__ ) . '/functions/register-menu-rest-route.php';
require_once dirname( __FILE__ ) . '/functions/core-endpoints-to-camel-case.php';
require_once dirname( __FILE__ ) . '/functions/unlock-endpoint.php';
