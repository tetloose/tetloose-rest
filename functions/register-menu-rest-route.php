<?php
/**
 * Register Menu REST Route
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

use function Tetloose\Rest\wp_rest_to_camel_case;

if ( ! function_exists( __NAMESPACE__ . '\\register_menu_in_rest_route' ) ) {
    /**
     * Register a REST endpoint to expose a WordPress menu by ID.
     */
    function register_menu_in_rest_route() {
        register_rest_route(
            'tetloose/v1',
            '/menu/(?P<id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => function ( $data ) {
                    $menu = wp_get_nav_menu_object( intval( $data['id'] ) );

                    if ( ! $menu ) {
                        return new \WP_Error( 'no_menu', 'Menu not found', array( 'status' => 404 ) );
                    }

                    $items = wp_get_nav_menu_items( $menu->term_id );

                    return wp_rest_to_camel_case( $items );
                },
                'permission_callback' => '__return_true',
            )
        );
    }

    add_action( 'rest_api_init', __NAMESPACE__ . '\\register_menu_in_rest_route' );
}
