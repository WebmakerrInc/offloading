<?php

namespace DeliciousBrains\WP_Offload_Media\Integrations;

use Exception;
use WP_Error;

/**
 * Shared helper utilities for working with Bunny.net storage and CDN APIs.
 */
class Bunny_Helper {
        const STORAGE_HOST = 'storage.bunnycdn.com';
        const CDN_HOST     = 'api.bunny.net';

        /**
         * Build the base URL for a storage request.
         *
         * @param string $storage_zone
         * @param string $key
         * @param string $region
         *
         * @return string
         */
        public static function storage_url( string $storage_zone, string $key = '', string $region = '' ): string {
                $storage_zone = ltrim( $storage_zone, '/' );
                $key          = ltrim( $key, '/' );
                $host         = static::STORAGE_HOST;

                if ( ! empty( $region ) ) {
                        $host = $region . '.' . $host;
                }

                $path = trailingslashit( $storage_zone );

                if ( ! empty( $key ) ) {
                        $path .= $key;
                }

                return sprintf( 'https://%s/%s', $host, $path );
        }

        /**
         * Perform a request against the Bunny storage API.
         *
         * @param string       $method
         * @param string       $storage_zone
         * @param string       $key
         * @param string       $api_key
         * @param array|string $body
         * @param array        $headers
         * @param string       $region
         * @param array        $args
         *
         * @return array|WP_Error
         */
        public static function storage_request( string $method, string $storage_zone, string $key, string $api_key, $body = null, array $headers = array(), string $region = '', array $args = array() ) {
                if ( empty( $storage_zone ) ) {
                        return new WP_Error( 'bunny_missing_storage_zone', __( 'Missing Bunny Storage Zone name.', 'amazon-s3-and-cloudfront' ) );
                }

                if ( empty( $api_key ) ) {
                        return new WP_Error( 'bunny_missing_api_key', __( 'Missing Bunny API key.', 'amazon-s3-and-cloudfront' ) );
                }

                $url = static::storage_url( $storage_zone, $key, $region );

                $default_headers = array(
                        'AccessKey'    => $api_key,
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/octet-stream',
                );

                if ( is_array( $headers ) && ! empty( $headers ) ) {
                        $default_headers = array_merge( $default_headers, $headers );
                }

                $request_args = array_merge(
                        array(
                                'method'  => $method,
                                'headers' => $default_headers,
                        ),
                        $args
                );

                if ( ! is_null( $body ) ) {
                        $request_args['body'] = $body;
                }

                $response = wp_remote_request( $url, $request_args );

                if ( is_wp_error( $response ) ) {
                        return $response;
                }

                $code = wp_remote_retrieve_response_code( $response );

                if ( $code < 200 || $code >= 300 ) {
                        $message = wp_remote_retrieve_response_message( $response );
                        $body    = wp_remote_retrieve_body( $response );

                        return new WP_Error(
                                'bunny_http_error',
                                sprintf( __( 'Bunny API request failed: %1$s (%2$s)', 'amazon-s3-and-cloudfront' ), $message, $code ),
                                array(
                                        'code'     => $code,
                                        'response' => $body,
                                        'url'      => $url,
                                )
                        );
                }

                return $response;
        }

        /**
         * Decode the JSON response returned from a storage request.
         *
         * @param array $response
         *
         * @return array
         */
        public static function decode_body( array $response ): array {
                $body = wp_remote_retrieve_body( $response );

                if ( empty( $body ) ) {
                        return array();
                }

                $decoded = json_decode( $body, true );

                if ( is_array( $decoded ) ) {
                        return $decoded;
                }

                return array();
        }

        /**
         * Upload an object to storage.
         *
         * @param string $storage_zone
         * @param string $key
         * @param string $source_file
         * @param string $api_key
         * @param string $region
         * @param array  $headers
         *
         * @return array|WP_Error
         * @throws Exception
         */
        public static function upload( string $storage_zone, string $key, string $source_file, string $api_key, string $region = '', array $headers = array() ) {
                if ( empty( $source_file ) || ! file_exists( $source_file ) ) {
                        throw new Exception( sprintf( __( 'Source file does not exist: %s', 'amazon-s3-and-cloudfront' ), $source_file ) );
                }

                $contents = file_get_contents( $source_file );

                return static::storage_request( 'PUT', $storage_zone, $key, $api_key, $contents, $headers, $region );
        }

        /**
         * Delete an object from storage.
         *
         * @param string $storage_zone
         * @param string $key
         * @param string $api_key
         * @param string $region
         *
         * @return array|WP_Error
         */
        public static function delete( string $storage_zone, string $key, string $api_key, string $region = '' ) {
                return static::storage_request( 'DELETE', $storage_zone, $key, $api_key, null, array(), $region );
        }

        /**
         * Retrieve an object.
         *
         * @param string $storage_zone
         * @param string $key
         * @param string $api_key
         * @param string $region
         *
         * @return array|WP_Error
         */
        public static function get( string $storage_zone, string $key, string $api_key, string $region = '' ) {
                return static::storage_request( 'GET', $storage_zone, $key, $api_key, null, array(), $region );
        }

        /**
         * List objects at a given path.
         *
         * @param string $storage_zone
         * @param string $prefix
         * @param string $api_key
         * @param string $region
         *
         * @return array|WP_Error
         */
        public static function list( string $storage_zone, string $prefix, string $api_key, string $region = '' ) {
                return static::storage_request( 'GET', $storage_zone, rtrim( $prefix, '/' ), $api_key, null, array(), $region );
        }

        /**
         * Purge a URL from the Bunny CDN cache.
         *
         * @param string $api_key
         * @param string $url
         *
         * @return array|WP_Error
         */
        public static function purge_url( string $api_key, string $url ) {
                if ( empty( $api_key ) ) {
                        return new WP_Error( 'bunny_missing_api_key', __( 'Missing Bunny API key.', 'amazon-s3-and-cloudfront' ) );
                }

                if ( empty( $url ) ) {
                        return new WP_Error( 'bunny_missing_url', __( 'No URL supplied for Bunny CDN purge.', 'amazon-s3-and-cloudfront' ) );
                }

                $request_args = array(
                        'method'  => 'POST',
                        'headers' => array(
                                'AccessKey'    => $api_key,
                                'Content-Type' => 'application/json',
                        ),
                        'body'    => wp_json_encode( array( 'url' => $url ) ),
                );

                $endpoint = sprintf( 'https://%s/purge', static::CDN_HOST );
                $response = wp_remote_request( $endpoint, $request_args );

                if ( is_wp_error( $response ) ) {
                        return $response;
                }

                $code = wp_remote_retrieve_response_code( $response );

                if ( $code < 200 || $code >= 300 ) {
                        $message = wp_remote_retrieve_response_message( $response );
                        $body    = wp_remote_retrieve_body( $response );

                        return new WP_Error(
                                'bunny_purge_failed',
                                sprintf( __( 'Failed to purge Bunny CDN cache: %1$s (%2$s)', 'amazon-s3-and-cloudfront' ), $message, $code ),
                                array(
                                        'code'     => $code,
                                        'response' => $body,
                                        'url'      => $url,
                                )
                        );
                }

                return $response;
        }
}
