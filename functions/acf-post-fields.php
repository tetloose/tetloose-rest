<?php
/**
 * Attach ACF fields to core REST responses (pages, posts, etc.)
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

use function Tetloose\Rest\wp_rest_to_camel_case;

if ( ! function_exists( __NAMESPACE__ . '\\add_acf_fields_to_rest' ) ) {
    /**
     * Append ACF fields to REST API response.
     *
     * @param \WP_REST_Response $response The response object to modify.
     * @param \WP_Post          $post     The post object the response was generated from.
     * @return \WP_REST_Response          The modified response.
     */
    function add_acf_fields_to_rest( $response, $post ) {
        if ( function_exists( 'get_fields' ) ) {
            $data        = $response->get_data();
            $data['acf'] = wp_rest_to_camel_case( get_fields( $post->ID ) ?? [] );
            $response->set_data( $data );
        }

        return $response;
    }

    /**
     * Register ACF fields for post types in REST.
     */
    function register_acf_rest_support() {
        $post_types = get_post_types( [ 'show_in_rest' => true ], 'names' );

        foreach ( $post_types as $type ) {
            add_filter( "rest_prepare_{$type}", __NAMESPACE__ . '\\add_acf_fields_to_rest', 10, 2 );
        }
    }

    add_action( 'rest_api_init', __NAMESPACE__ . '\\register_acf_rest_support' );
}
