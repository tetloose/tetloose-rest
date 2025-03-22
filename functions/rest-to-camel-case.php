<?php
/**
 * CamelCase Helper
 *
 * @package Tetloose-Rest
 */

namespace Tetloose\Rest;

if ( ! function_exists( __NAMESPACE__ . '\\wp_rest_to_camel_case' ) ) {
	/**
	 * Recursively converts all array or object keys from snake_case to camelCase.
	 *
	 * @param mixed $data The data to transform.
	 * @return mixed The transformed data with camelCase keys.
	 */
	function wp_rest_to_camel_case( $data ) {
		if ( is_array( $data ) ) {
			$camel_data = [];

			foreach ( $data as $key => $value ) {
				$camel_key = preg_replace_callback(
					'/_([a-z])/',
					static function ( $matches ) {
						return strtoupper( $matches[1] );
					},
					$key
				);

				$camel_data[ $camel_key ] = wp_rest_to_camel_case( $value );
			}

			return $camel_data;
		}

		if ( is_object( $data ) ) {
			return wp_rest_to_camel_case( (array) $data );
		}

		return $data;
	}
}
