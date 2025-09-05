<?php
/**
 * Core Endpoints to CamelCase
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

use function Tetloose\Rest\wp_rest_to_camel_case;

if ( ! function_exists( __NAMESPACE__ . '\\register_core_endpoint_filters' ) ) {
    /**
     * Registers filters to convert core REST API responses to camelCase.
     */
    function register_core_endpoint_filters() {
        add_filter(
            'rest_prepare_page',
            function ( $response ) {
                $data = $response->get_data();
                $response->set_data( wp_rest_to_camel_case( $data ) );
                return $response;
            },
            10,
            3
        );

        add_filter(
            'rest_prepare_acf-page',
            function ( $response ) {
                $data = $response->get_data();
                $response->set_data( wp_rest_to_camel_case( $data ) );
                return $response;
            },
            10,
            3
        );
    }

    add_action( 'rest_api_init', __NAMESPACE__ . '\\register_core_endpoint_filters' );
}
