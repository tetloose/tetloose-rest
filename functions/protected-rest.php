<?php
/**
 * Hide ACF/meta on password-protected content in REST responses.
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

// Register once for all post types that are show_in_rest.
add_action( 'rest_api_init', __NAMESPACE__ . '\\protected_rest' );

/**
 * Attach filters for every REST-exposed post type, plus a generic fallback.
 *
 * Registers a per-type `rest_prepare_{type}` filter (late priority so ACF/meta
 * are already present) and a `rest_post_dispatch` catch-all for custom routes.
 *
 * @return void
 */
function protected_rest() : void {
    $types = \get_post_types( [ 'show_in_rest' => true ], 'names' );

    foreach ( $types as $type ) {
        \add_filter( "rest_prepare_{$type}", __NAMESPACE__ . '\\hide_protected_extra_fields', 100, 3 );
    }

    \add_filter( 'rest_post_dispatch', __NAMESPACE__ . '\\scrub_generic_response_if_protected', 9, 2 );
}

/**
 * Check whether the requester has access to a password-protected post.
 *
 * Uses the `password` query param when present; otherwise falls back to
 * WordPress’ cookie-based check.
 *
 * @param int              $post_id Post ID to check.
 * @param \WP_REST_Request $request Current REST request.
 * @return bool                         True if access is granted; false otherwise.
 */
function has_access( int $post_id, $request ) : bool {
    $provided = (string) $request->get_param( 'password' );

    return \post_password_required( $post_id, $provided );
}

/**
 * Strip ACF/meta when a post is password-protected and not unlocked.
 *
 * Runs on `rest_prepare_{type}`. If the post is protected and the requester
 * does not have access, removes the `acf` blob and clears `meta`. Also marks
 * `content.protected = true` to make the locked state explicit for clients.
 *
 * @param \WP_REST_Response $response The response object to modify.
 * @param \WP_Post          $post     The post object the response was generated from.
 * @param \WP_REST_Request  $request  The current REST request.
 * @return \WP_REST_Response          The (potentially) modified response.
 */
function hide_protected_extra_fields( $response, $post, $request ) {
    if ( empty( $post->post_password ) || has_access( $post->ID, $request ) ) {
        return $response;
    }

    $data = $response->get_data();

    if ( isset( $data['acf'] ) ) {
        unset( $data['acf'] );
    }

    if ( isset( $data['meta'] ) ) {
        $data['meta'] = [];
    }

    if ( isset( $data['content'] ) && \is_array( $data['content'] ) ) {
        $data['content']['protected'] = true;
    }

    $response->set_data( $data );

    return $response;
}

/**
 * Catch-all scrubber for protected content after controller callbacks run.
 *
 * Applies to any REST route (lists or single items) via `rest_post_dispatch`.
 * For protected posts without access, removes `acf`, clears `meta`, and sets
 * `content.protected = true`.
 *
 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error $response Response to filter.
 * @param \WP_REST_Request                              $request  Current request.
 * @return \WP_REST_Response|\WP_HTTP_Response|\WP_Error           Filtered response.
 */
function scrub_generic_response_if_protected( $response, $request ) {
    if ( ! $response instanceof \WP_REST_Response ) {
        return $response;
    }

    $data = $response->get_data();

    $scrub = function ( $item ) use ( $request ) {
        if ( ! \is_array( $item ) || ! isset( $item['id'] ) ) {
            return $item;
        }

        $post = \get_post( (int) $item['id'] );

        if ( $post && ! empty( $post->post_password ) && ! has_access( $post->ID, $request ) ) {
            unset( $item['acf'] );

            if ( isset( $item['meta'] ) ) {
                $item['meta'] = [];
            }

            if ( isset( $item['content'] ) && \is_array( $item['content'] ) ) {
                $item['content']['protected'] = true;
            }
        }

        return $item;
    };

    if ( \function_exists( 'wp_is_numeric_array' ) && \wp_is_numeric_array( $data ) ) {
        $data = \array_map( $scrub, $data );
    } elseif ( \is_array( $data ) ) {
        $data = $scrub( $data );
    }

    $response->set_data( $data );

    return $response;
}
