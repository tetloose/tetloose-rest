<?php
/**
 * Expose ACF Options Page in REST
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

if ( ! function_exists( __NAMESPACE__ . '\\expose_acf_options_in_rest' ) ) {
    /**
     * Register the ACF options page in the REST API.
     */
    function expose_acf_options_in_rest() {
        register_rest_route(
            'tetloose/v1',
            '/options/',
            array(
                'methods'             => 'GET',
                'callback'            => function () {
                    $options = get_fields( 'option' );
                    return wp_rest_to_camel_case( $options );
                },
                'permission_callback' => '__return_true',
            )
        );
    }

    add_action( 'rest_api_init', __NAMESPACE__ . '\\expose_acf_options_in_rest' );
}
