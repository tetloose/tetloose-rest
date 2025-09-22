<?php
/**
 * Password Protected (opaque token) — single-file include
 *
 * Provides a REST endpoint to "password_protected" password-protected posts/pages/CPTs
 * without exposing the plaintext password in storage or responses. On
 * successful verification, an HttpOnly, signed, opaque cookie is set. Later,
 * REST responses are post-processed server-side to mark the resource as
 * unprotected and to inject real content when a valid token is present.
 *
 * Usage (FE flow):
 *  - POST /wp-json/tetloose/v1/password_protected   { id|slug|path, [type], password, [ttl] }
 *  - If { ok: true }, refetch the same REST route; this file will handle
 *    the password_protected state based on the signed cookie (no CORS required for
 *    same-origin requests via Next.js rewrites/proxy).
 *
 * Security:
 *  - No plaintext password is persisted.
 *  - Token is signed using WP salts and includes post ID + expiry.
 *  - Cookie is HttpOnly, SameSite=Lax, and Secure when on HTTPS.
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

defined( 'ABSPATH' ) || exit;

/** ───────────────────────────────────────────────────────────────────────────
 * Helpers
 * ─────────────────────────────────────────────────────────────────────────── */

if ( ! function_exists( __NAMESPACE__ . '\\resolve_post' ) ) {
    /**
     * Resolve a post by ID, path, or slug across one or more post types.
     *
     * When $args['type'] is provided, it may be a comma-separated list of types.
     * Otherwise all public post types are searched.
     *
     * @since 1.0.0
     *
     * @param array $args {
     *     Optional. Post resolution arguments.
     *
     *     @type int    $id   Post ID.
     *     @type string $path Hierarchical path (e.g., 'parent/child').
     *     @type string $slug Post slug (name).
     *     @type string $type Comma-separated list of post types to search within.
     * }
     * @return \WP_Post|null Matching post object or null when not found/invalid.
     */
    function resolve_post( array $args ) : ?\WP_Post {
        $id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
        $path = isset( $args['path'] ) ? trim( (string) $args['path'], "/ \t\n\r\0\x0B" ) : '';
        $slug = isset( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : '';
        $type = isset( $args['type'] ) ? (string) $args['type'] : '';

        $post_types = $type
            ? array_map( 'trim', explode( ',', $type ) )
            : array_values( get_post_types( array( 'public' => true ), 'names' ) );

        if ( $id ) {
            $p = get_post( $id );
            return ( $p && in_array( $p->post_type, $post_types, true ) ) ? $p : null;
        }

        if ( $path ) {
            $p = get_page_by_path( $path, OBJECT, $post_types );
            if ( $p ) {
                return $p;
            }
        }

        if ( $slug ) {
            $ids = get_posts(
                array(
                    'name'           => $slug,
                    'post_type'      => $post_types,
                    'post_status'    => 'any',
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                    'posts_per_page' => 1,
                )
            );
            if ( $ids ) {
                return get_post( (int) $ids[0] );
            }
        }

        return null;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\password_protected_signing_key' ) ) {
    /**
     * Derive a signing key from WordPress salts.
     *
     * The key is deterministic across requests and environments that share the same
     * salts, and returns a 32-byte binary string suitable as an HMAC key.
     *
     * @since 1.0.0
     *
     * @return string 32-byte binary key.
     */
    function password_protected_signing_key() : string {
        $k = \AUTH_SALT . \SECURE_AUTH_SALT . \LOGGED_IN_SALT . \NONCE_SALT;
        return hash( 'sha256', $k, true ); // 32 bytes (binary).
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\password_protected_sign' ) ) {
    /**
     * Create a signed opaque token from a payload.
     *
     * The token is constructed as base64(body) . '.' . base64(hmac).
     *
     * @since 1.0.0
     *
     * @param array $payload Associative array containing at least 'id' and 'exp'.
     * @return string Signed opaque token.
     */
    function password_protected_sign( array $payload ) : string {
        $body = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
        $sig  = hash_hmac( 'sha256', $body, password_protected_signing_key(), true );
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return base64_encode( $body ) . '.' . base64_encode( $sig );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\password_protected_verify' ) ) {
    /**
     * Verify an opaque token and return its decoded payload.
     *
     * @since 1.0.0
     *
     * @param string $token Token string to verify.
     * @return array|null Payload array on success, or null on failure/expiry.
     */
    function password_protected_verify( string $token ) : ?array {
        $parts = explode( '.', $token, 2 );
        if ( 2 !== count( $parts ) ) {
            return null;
        }

        $body = base64_decode( $parts[0], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $sig  = base64_decode( $parts[1], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

        if ( false === $body || false === $sig ) {
            return null;
        }

        $calc = hash_hmac( 'sha256', $body, password_protected_signing_key(), true );
        if ( ! hash_equals( $calc, $sig ) ) {
            return null;
        }

        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) ) {
            return null;
        }

        if ( empty( $payload['id'] ) || empty( $payload['exp'] ) ) {
            return null;
        }

        if ( time() >= (int) $payload['exp'] ) {
            return null;
        }

        return $payload;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\register_password_protected_endpoint' ) ) {
    /** ───────────────────────────────────────────────────────────────────────────
     * REST: /password_protected — checks password, sets token cookie
     * ─────────────────────────────────────────────────────────────────────────── */

    /**
     * Register the password_protected REST endpoint.
     *
     * POST /wp-json/tetloose/v1/password_protected
     *
     * @since 1.0.0
     * @return void
     */
    function register_password_protected_endpoint() : void {
        register_rest_route(
            'tetloose/v1',
            '/password-protected',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'permission_callback' => '__return_true',
                'args'                => array(
                    'id'       => array(
                        'type'     => 'integer',
                        'required' => false,
                    ),
                    'path'     => array(
                        'type'     => 'string',
                        'required' => false,
                    ),
                    'slug'     => array(
                        'type'     => 'string',
                        'required' => false,
                    ),
                    'type'     => array(
                        'type'     => 'string',
                        'required' => false,
                    ),
                    'password' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                    'ttl'      => array(
                        'type'     => 'integer',
                        'required' => false, // seconds; default 10 days.
                    ),
                ),
                'callback'            => __NAMESPACE__ . '\\password_protected_callback',
            )
        );
    }
    add_action( 'rest_api_init', __NAMESPACE__ . '\\register_password_protected_endpoint' );
}

if ( ! function_exists( __NAMESPACE__ . '\\password_protected_callback' ) ) {
    /**
     * Handle password_protected POST, validate password, and set the token cookie.
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $req Request object.
     * @return \WP_REST_Response|\WP_Error REST response.
     */
    function password_protected_callback( \WP_REST_Request $req ) {
        $post = resolve_post(
            array(
                'id'   => $req['id'],
                'path' => $req['path'],
                'slug' => $req['slug'],
                'type' => $req['type'],
            )
        );

        if ( ! $post instanceof \WP_Post ) {
            return new \WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
        }

        $pwd = (string) ( $req['password'] ?? '' );

        // If not protected, nothing to do—treat as OK.
        if ( empty( $post->post_password ) ) {
            return rest_ensure_response( array( 'ok' => true ) );
        }

        // Constant-time compare when possible.
        $ok = ( '' !== $pwd )
            ? ( function_exists( 'hash_equals' )
                ? hash_equals( (string) $post->post_password, $pwd )
                : ( (string) $post->post_password === $pwd ) )
            : false;

        if ( ! $ok ) {
            return rest_ensure_response(
                array(
                    'ok'      => false,
                    'message' => 'Incorrect password',
                )
            );
        }

        // Mint a signed token bound to this post ID with an expiry.
        $ttl   = (int) ( $req['ttl'] ?? ( 10 * DAY_IN_SECONDS ) );
        $exp   = time() + max( 60, $ttl ); // Minimum 60 seconds.
        $token = password_protected_sign(
            array(
                'id'  => (int) $post->ID,
                'exp' => $exp,
            )
        );

        $cookie  = 'password_protected'; // Opaque token cookie (HttpOnly).
        $options = array(
            'expires'  => $exp,
            'path'     => COOKIEPATH,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );

        // PHP ≥ 7.3 supports options array; older versions require legacy signature.
        if ( PHP_VERSION_ID >= 70300 ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cookies_setcookie
            setcookie( $cookie, $token, $options );
            if ( COOKIEPATH !== SITECOOKIEPATH ) {
                $options['path'] = SITECOOKIEPATH;
				// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cookies_setcookie
                setcookie( $cookie, $token, $options );
            }
        } else {
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cookies_setcookie
            setcookie( $cookie, $token, $exp, COOKIEPATH, '', is_ssl(), true );
            if ( COOKIEPATH !== SITECOOKIEPATH ) {
				// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cookies_setcookie
                setcookie( $cookie, $token, $exp, SITECOOKIEPATH, '', is_ssl(), true );
            }
        }

        return rest_ensure_response( array( 'ok' => true ) );
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\register_password_protected_response_filter' ) ) {
    /** ───────────────────────────────────────────────────────────────────────────
     * REST post-process: mark password_protected + inject content when token valid
     * ─────────────────────────────────────────────────────────────────────────── */

    /**
     * Register a REST response filter that marks posts as password_protected and injects the
     * real content when a valid token or direct password is provided.
     *
     * Adds a top-level `protected` boolean to the REST payload. When still locked,
     * any ACF payload at `acf` is removed to avoid accidental leakage of fields.
     *
     * @since 1.0.0
     * @return void
     */
    function register_password_protected_response_filter() : void {

        $cb = function ( $response, $post, $request ) {
            if ( ! ( $response instanceof \WP_REST_Response ) || ! ( $post instanceof \WP_Post ) ) {
                return $response;
            }

            $data         = $response->get_data();
            $is_protected = ! empty( $post->post_password );

            // Allow direct ?password= (useful for one-off calls).
            $provided = (string) $request->get_param( 'password' );
            $match    = ( '' !== $provided )
                ? ( function_exists( 'hash_equals' )
                    ? hash_equals( (string) $post->post_password, $provided )
                    : ( (string) $post->post_password === $provided ) )
                : false;

            // Or accept our signed/opaque cookie for ongoing access.
            $token = '';

            if ( isset( $_COOKIE['password_protected'] ) ) {
                $token = sanitize_text_field( wp_unslash( $_COOKIE['password_protected'] ) );
            }

            $valid = false;

            if ( $token ) {
                $payload = password_protected_verify( $token );
                $valid   = ( $payload && (int) $payload['id'] === (int) $post->ID );
            }

            $is_password_protected = ( $match || $valid );

            if ( $is_protected && $is_password_protected ) {
                $is_protected = false;

                // Inject real content into REST payload (bypasses core gating).
                if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
                    $raw                          = get_post_field( 'post_content', $post->ID );
                    $data['content']['rendered']  = apply_filters( 'the_content', $raw );
                    $data['content']['protected'] = false;
                }
            }

            // Top-level flag for FE.
            $data['protected'] = (bool) $is_protected;

            // Hide ACF group when still locked.
            if ( $is_protected && isset( $data['acf'] ) ) {
                unset( $data['acf'] );
            }

            $response->set_data( $data );
            return $response;
        };

        // Apply to all types shown in REST.
        foreach ( get_post_types( array( 'show_in_rest' => true ), 'names' ) as $type ) {
            // Run before any camelCase filter if that runs at 100.
            add_filter( "rest_prepare_{$type}", $cb, 90, 3 );
        }
    }
    add_action( 'rest_api_init', __NAMESPACE__ . '\\register_password_protected_response_filter' );
}

/** ───────────────────────────────────────────────────────────────────────────
 * Notes
 * ───────────────────────────────────────────────────────────────────────────
 *
 * - If TLS is terminated at a proxy, in wp-config.php:
 *     if (
 *         ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) &&
 *         'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']
 *     ) {
 *         $_SERVER['HTTPS'] = 'on';
 *     }
 *
 * - With Next.js rewrites/proxy, requests are first-party; CORS is not required.
 *
 * - FE flow:
 *     POST /password_protected → if { ok: true } then refetch the REST route.
 *     Cookie is HttpOnly & opaque; server decides password_protected/locked here.
 */
