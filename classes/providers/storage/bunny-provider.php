<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage;

use AS3CF_Error;
use DeliciousBrains\WP_Offload_Media\Integrations\Bunny_Helper;
use Exception;
use WP_Error;

class Bunny_Provider extends Storage_Provider {
	const PUBLIC_ACL  = 'public';
	const PRIVATE_ACL = 'private';

        /**
         * @var array
         */
        private $client_config = array();

        /**
         * @var string
         */
        protected static $provider_name = 'Bunny.net';

        /**
         * @var string
         */
        protected static $provider_short_name = 'Bunny.net';

        /**
         * Used in filters and settings.
         *
         * @var string
         */
        protected static $provider_key_name = 'bunny';

        /**
         * @var string
         */
        protected static $service_name = 'Storage';

        /**
         * @var string
         */
        protected static $service_short_name = 'Storage';

        /**
         * Used in filters and settings.
         *
         * @var string
         */
        protected static $service_key_name = 'storage';

        /**
         * Optional override of "Provider Name" + "Service Name" for friendly name for service.
         *
         * @var string
         */
        protected static $provider_service_name = 'Bunny.net Storage';

        /**
         * The slug for the service's quick start guide doc.
         *
         * @var string
         */
        protected static $provider_service_quick_start_slug = 'bunny-storage-quick-start-guide';

        /**
         * Bunny Storage does not support ACL management.
         *
         * @var bool
         */
        protected static $block_public_access_supported = false;

        /**
         * Bunny Storage does not support ACL management.
         *
         * @var bool
         */
        protected static $object_ownership_supported = false;

        /**
         * Bunny Storage endpoints do not expose a list of predefined regions.
         *
         * @var array
         */
        protected static $regions = array();

        /**
         * Bunny provider does not require a region.
         *
         * @var bool
         */
        protected static $region_required = false;

        /**
         * Bunny Storage default domain for raw URLs.
         *
         * @var string
         */
        protected $default_domain = 'bunnycdn.com';

        /**
         * Bunny console URL.
         *
         * @var string
         */
        protected $console_url = 'https://dash.bunny.net/storage/';

        /**
         * Bunny console prefix param.
         *
         * @var string
         */
        protected $console_url_prefix_param = '';

        /**
         * Get the suffix param to append to the link to the provider's console.
         *
         * @param string $bucket
         * @param string $prefix
         * @param string $region
         *
         * @return string
         */
        protected function get_console_url_suffix_param( string $bucket = '', string $prefix = '', string $region = '' ): string {
                return '';
        }

        /**
         * Settings key where Bunny CDN URL is stored.
         */
        const CDN_URL_SETTING = 'bunny-cdn-url';

        /**
         * Settings key where Bunny custom CNAME is stored.
         */
        const CUSTOM_CNAME_SETTING = 'bunny-custom-cname';

        /**
         * Settings key for Bunny API key.
         */
        const API_KEY_SETTING = 'access-key-id';

        /**
         * Settings key for Bunny Storage Zone.
         */
        const STORAGE_ZONE_SETTING = 'bucket';

        /**
         * Settings key for Bunny region.
         */
        const REGION_SETTING = 'region';

        /**
         * Bunny allows storing the API key via the standard access key fields.
         *
         * @return bool
         */
        public static function use_access_keys_allowed() {
                return true;
        }

        public static function get_access_keys_help() {
                return __( 'Use your Bunny API Key to authenticate requests. You can find it in the Bunny dashboard under Storage Zone settings.', 'amazon-s3-and-cloudfront' );
        }

        public static function get_define_access_keys_desc() {
                return __( 'Define the Bunny API Key in wp-config.php for the most secure configuration.', 'amazon-s3-and-cloudfront' );
        }

        public static function get_enter_access_keys_desc() {
                return __( 'Store the Bunny API Key in the database. This is less secure but may be convenient for testing.', 'amazon-s3-and-cloudfront' );
        }

        public static function get_define_access_keys_example() {
                return "define( 'AS3CF_SETTINGS', serialize( array(\n        'provider' => '" . static::get_provider_key_name() . "',\n        'access-key-id' => '******************************',\n) ) );";
        }

        /**
         * Bunny provider does not expose stream wrappers.
         *
         * @param string $region
         *
         * @return string
         */
        protected function get_stream_wrapper_protocol( $region ) {
                return 'bunnystorage';
        }

        /**
         * Bunny provider does not have native stream wrappers, return false.
         *
         * @param string $region
         *
         * @return bool
         */
        public function register_stream_wrapper( $region ) {
                return false;
        }

        /**
         * Bunny requires only an API key.
         *
         * @return bool
         */
        public function needs_access_keys() {
                if ( $this->get_api_key() ) {
                        return false;
                }

                $this->add_validation_issue( 'miss_access_key_id', true );

                return true;
        }

        /**
         * Bunny requires only an API key.
         *
         * @return bool
         */
        public function are_access_keys_set() {
                return (bool) $this->get_api_key();
        }

        /**
         * Settings stored in database should be whitelisted for saving.
         *
         * @return array
         */
	public static function additional_allowed_settings(): array {
		return array(
			static::CDN_URL_SETTING,
			static::CUSTOM_CNAME_SETTING,
		);
	}

	/**
	 * Returns default args array for the client.
	 *
	 * @return array
	 */
	protected function default_client_args() {
		return array();
	}

	/**
	 * Make sure the region string matches the expected format.
	 *
	 * @param string $region
	 *
	 * @return string
	 */
	public function sanitize_region( $region ) {
		if ( ! is_string( $region ) ) {
			return '';
		}

		$region = strtolower( trim( $region ) );

		return preg_replace( '/[^a-z0-9\-]/', '', $region );
	}


        /**
         * Process the args before instantiating a new client for the provider's SDK.
         *
         * @param array $args
         *
         * @return array
         */
        protected function init_client_args( array $args ) {
                $config = array(
                        'storage_zone' => $this->get_storage_zone(),
                        'api_key'      => $this->get_api_key(),
                        'region'       => $this->get_storage_region(),
                        'cdn_url'      => $this->get_cdn_url(),
                        'cname'        => $this->get_custom_cname(),
                );

                return array_merge( $config, $args );
        }

        /**
         * Initialise the client.
         *
         * @param array $args
         *
         * @return array
         */
        protected function init_client( array $args ) {
                $this->client_config = $args;

                return $args;
        }

        /**
         * Process the args before instantiating a new service specific client.
         *
         * @param array $args
         *
         * @return array
         */
        protected function init_service_client_args( array $args ) {
                return $args;
        }

        /**
         * Initialise the service client.
         *
         * @param array $args
         *
         * @return Bunny_Provider
         */
        protected function init_service_client( array $args ) {
                $this->client_config = $args;

                return $this;
        }

	/**
	 * Bunny Storage zones must be created in the Bunny dashboard.
	 *
	 * @param array $args
	 *
	 * @throws Exception
	 */
	public function create_bucket( array $args ) {
		throw new Exception( __( 'Buckets (Storage Zones) must be created from the Bunny.net dashboard.', 'amazon-s3-and-cloudfront' ) );
	}

	/**
	 * Determine whether the configured bucket exists.
	 *
	 * @param string $bucket
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function does_bucket_exist( $bucket ) {
		$response = Bunny_Helper::storage_request( 'HEAD', $bucket, '', $this->get_api_key(), null, array(), $this->get_storage_region() );

		if ( is_wp_error( $response ) ) {
			$data = $response->get_error_data();

			if ( is_array( $data ) && isset( $data['code'] ) && 404 === (int) $data['code'] ) {
				return false;
			}

			throw new Exception( $response->get_error_message() );
		}

		return true;
	}

	/**
	 * Determine the location (region) for the given bucket.
	 *
	 * @param array $args
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_bucket_location( array $args ) {
		$bucket = isset( $args['Bucket'] ) ? $args['Bucket'] : '';

		if ( empty( $bucket ) ) {
			throw new Exception( __( 'No bucket specified when requesting Bunny bucket location.', 'amazon-s3-and-cloudfront' ) );
		}

		$response = Bunny_Helper::storage_request( 'HEAD', $bucket, '', $this->get_api_key(), null, array(), $this->get_storage_region() );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$headers = wp_remote_retrieve_headers( $response );

		if ( isset( $headers['x-storagezoneregion'] ) && ! empty( $headers['x-storagezoneregion'] ) ) {
			return $this->sanitize_region( $headers['x-storagezoneregion'] );
		}

		$region = $this->get_storage_region();

		if ( ! empty( $region ) ) {
			return $this->sanitize_region( $region );
		}

		return '';
	}

	/**
	 * Bunny does not expose a bucket listing endpoint.
	 *
	 * @param array $args
	 *
	 * @throws Exception
	 */
	public function list_buckets( array $args = array() ) {
		throw new Exception( __( 'Listing Bunny Storage Zones via the API is not supported. Please enter the zone name manually.', 'amazon-s3-and-cloudfront' ) );
	}

	/**
	 * Check whether an object exists.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param array  $options
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function does_object_exist( $bucket, $key, array $options = array() ) {
		$response = Bunny_Helper::storage_request( 'HEAD', $bucket, $key, $this->get_api_key(), null, array(), $this->get_storage_region() );

		if ( is_wp_error( $response ) ) {
			$data = $response->get_error_data();

			if ( is_array( $data ) && isset( $data['code'] ) && 404 === (int) $data['code'] ) {
				return false;
			}

			throw new Exception( $response->get_error_message() );
		}

		return true;
	}

	/**
	 * Get public ACL string.
	 *
	 * @return string
	 */
	public function get_public_acl() {
		return static::PUBLIC_ACL;
	}

	/**
	 * Get private ACL string.
	 *
	 * @return string
	 */
	public function get_private_acl() {
		return static::PRIVATE_ACL;
	}

        /**
         * Fetch an object from Bunny storage.
         *
         * @param array $args
         *
         * @return array|WP_Error
         */
        public function get_object( array $args ) {
                $response = Bunny_Helper::get( $args['Bucket'], $args['Key'], $this->get_api_key(), $this->get_storage_region() );

                if ( is_wp_error( $response ) ) {
                        throw new Exception( $response->get_error_message() );
                }

                return $response;
        }

        /**
         * Get object's URL.
         *
         * @param string $bucket
         * @param string $key
         * @param int    $timestamp
         * @param array  $args
         *
         * @return string
         */
        public function get_object_url( $bucket, $key, $timestamp, array $args = array() ) {
                $cdn_base = $this->get_cdn_domain();

                if ( empty( $cdn_base ) ) {
                        $cdn_base = Bunny_Helper::storage_url( $bucket, '', $this->get_storage_region() );
                }

                $cdn_base = untrailingslashit( $cdn_base );
                $key      = ltrim( $key, '/' );

                $url = $cdn_base . '/' . $key;

                if ( ! empty( $timestamp ) ) {
                        $delimiter = false === strpos( $url, '?' ) ? '?' : '&';
                        $url      .= $delimiter . 'ver=' . $timestamp;
                }

                return $url;
        }

        /**
         * List objects.
         *
         * @param array $args
         *
         * @return array
         */
        public function list_objects( array $args = array() ) {
                $response = Bunny_Helper::list( $args['Bucket'], $args['Prefix'], $this->get_api_key(), $this->get_storage_region() );

                if ( is_wp_error( $response ) ) {
                        throw new Exception( $response->get_error_message() );
                }

                return Bunny_Helper::decode_body( $response );
        }

        /**
         * Bunny does not support ACL updates. Silently ignore.
         *
         * @param array $args
         */
        public function update_object_acl( array $args ) {
                // Bunny Storage is private by default with CDN handling public delivery.
        }

        /**
         * Bunny does not support ACL updates. Return empty failures list.
         *
         * @param array $items
         *
         * @return array
         */
        public function update_object_acls( array $items ) {
                return array();
        }

        /**
         * Upload file to bucket.
         *
         * @param array $args
         */
        public function upload_object( array $args ) {
                $headers = array();

                if ( ! empty( $args['ContentType'] ) ) {
                        $headers['Content-Type'] = $args['ContentType'];
                }

                $source_file = ! empty( $args['SourceFile'] ) ? $args['SourceFile'] : null;

                $temporary_file = null;

                if ( empty( $source_file ) && ! empty( $args['Body'] ) ) {
                        $tmp = wp_tempnam( basename( $args['Key'] ) );
                        file_put_contents( $tmp, $args['Body'] );
                        $source_file = $tmp;
                        $temporary_file = $tmp;
                }

                $result = Bunny_Helper::upload( $args['Bucket'], $args['Key'], $source_file, $this->get_api_key(), $this->get_storage_region(), $headers );

                if ( is_wp_error( $result ) ) {
                        if ( $temporary_file && file_exists( $temporary_file ) ) {
                                unlink( $temporary_file );
                        }
                        throw new Exception( $result->get_error_message() );
                }

                $this->purge_cdn_for_key( $args['Bucket'], $args['Key'] );

                if ( $temporary_file && file_exists( $temporary_file ) ) {
                        unlink( $temporary_file );
                }
        }

        /**
         * Delete object from bucket.
         *
         * @param array $args
         */
        public function delete_object( array $args ) {
                $result = Bunny_Helper::delete( $args['Bucket'], $args['Key'], $this->get_api_key(), $this->get_storage_region() );

                if ( is_wp_error( $result ) ) {
                        throw new Exception( $result->get_error_message() );
                }

                $this->purge_cdn_for_key( $args['Bucket'], $args['Key'] );
        }

        /**
         * Delete multiple objects from bucket.
         *
         * @param array $args
         */
        public function delete_objects( array $args ) {
                if ( isset( $args['Objects'] ) ) {
                        $objects = $args['Objects'];
                } else {
                        $objects = array();
                }

                foreach ( $objects as $object ) {
                        if ( empty( $object['Key'] ) ) {
                                continue;
                        }

                        try {
                                $this->delete_object(
                                        array(
                                                'Bucket' => $args['Bucket'],
                                                'Key'    => $object['Key'],
                                        )
                                );
                        } catch ( Exception $e ) {
                                AS3CF_Error::log( 'Bunny delete failure: ' . $e->getMessage() );
                        }
                }
        }

        /**
         * Returns arrays of found keys for given bucket and prefix locations, retaining given array's integer based index.
         *
         * @param array $locations Array with attachment ID as key and Bucket and Prefix in an associative array as values.
         *
         * @return array
         */
        public function list_keys( array $locations ) {
                $keys = array();

                foreach ( $locations as $attachment_id => $location ) {
                        try {
                                $objects = $this->list_objects(
                                        array(
                                                'Bucket' => $location['Bucket'],
                                                'Prefix' => $location['Prefix'],
                                        )
                                );
                        } catch ( Exception $e ) {
                                AS3CF_Error::log( 'Bunny list failure: ' . $e->getMessage() );
                                continue;
                        }

                        if ( empty( $objects ) ) {
                                continue;
                        }

                        $keys[ $attachment_id ] = array();

                        foreach ( $objects as $object ) {
                                if ( empty( $object['ObjectName'] ) ) {
                                        continue;
                                }

                                $keys[ $attachment_id ][] = ltrim( $location['Prefix'], '/' ) . ltrim( $object['ObjectName'], '/' );
                        }
                }

                return $keys;
        }

        /**
         * Copies objects into current bucket from another bucket hosted with provider.
         *
         * @param array $items
         *
         * @return array Failures with elements Key and Message
         */
        public function copy_objects( array $items ) {
                $failures = array();

                foreach ( $items as $item ) {
                        try {
                                $response = Bunny_Helper::get( $item['CopySourceBucket'], $item['CopySourceKey'], $this->get_api_key(), $this->get_storage_region() );

                                if ( is_wp_error( $response ) ) {
                                        throw new Exception( $response->get_error_message() );
                                }

                                $tmp = wp_tempnam( basename( $item['Key'] ) );
                                file_put_contents( $tmp, wp_remote_retrieve_body( $response ) );

                                $upload = Bunny_Helper::upload( $item['Bucket'], $item['Key'], $tmp, $this->get_api_key(), $this->get_storage_region() );

                                if ( is_wp_error( $upload ) ) {
                                        throw new Exception( $upload->get_error_message() );
                                }

                                if ( file_exists( $tmp ) ) {
                                        unlink( $tmp );
                                }
                        } catch ( Exception $e ) {
                                $failures[] = array(
                                        'Key'     => $item['Key'],
                                        'Message' => $e->getMessage(),
                                );
                        }
                }

                return $failures;
        }

        /**
         * Check that a bucket and key can be written to.
         *
         * @param string $bucket
         * @param string $key
         * @param string $file_contents
         *
         * @return bool|string Error message on unexpected exception
         */
        public function can_write( $bucket, $key, $file_contents ) {
                $tmp = wp_tempnam( basename( $key ) );
                file_put_contents( $tmp, $file_contents );

                try {
                        $result = Bunny_Helper::upload( $bucket, $key, $tmp, $this->get_api_key(), $this->get_storage_region() );

                        if ( is_wp_error( $result ) ) {
                                if ( file_exists( $tmp ) ) {
                                        unlink( $tmp );
                                }
                                return $result->get_error_message();
                        }

                        Bunny_Helper::delete( $bucket, $key, $this->get_api_key(), $this->get_storage_region() );
                } catch ( Exception $e ) {
                        if ( file_exists( $tmp ) ) {
                                unlink( $tmp );
                        }
                        return $e->getMessage();
                }

                if ( file_exists( $tmp ) ) {
                        unlink( $tmp );
                }

                return true;
        }

        /**
         * Get the region specific prefix for raw URL
         *
         * @param string   $region
         * @param null|int $expires
         *
         * @return string
         */
        protected function url_prefix( $region = '', $expires = null ) {
                $cdn = $this->get_cdn_domain();

                if ( ! empty( $cdn ) ) {
                                return set_url_scheme( $cdn, $this->as3cf->force_https() ? 'https' : null );
                }

                return Bunny_Helper::storage_url( $this->get_storage_zone(), '', $region );
        }

        /**
         * Get the url domain for the files
         *
         * @param string $domain Likely prefixed with region
         * @param string $bucket
         * @param string $region
         * @param int    $expires
         * @param array  $args   Allows you to specify custom URL settings
         *
         * @return string
         */
        protected function url_domain( $domain, $bucket, $region = '', $expires = null, $args = array() ) {
                $cdn = $this->get_cdn_domain();

                if ( ! empty( $cdn ) ) {
                        return trailingslashit( $cdn );
                }

                return trailingslashit( Bunny_Helper::storage_url( $bucket, '', $region ) );
        }

        /**
         * Get the configured storage zone.
         *
         * @return string
         */
        protected function get_storage_zone(): string {
                return (string) $this->as3cf->get_setting( static::STORAGE_ZONE_SETTING );
        }

        /**
         * Get the API key for Bunny requests.
         *
         * @return string
         */
        protected function get_api_key(): string {
                return (string) $this->as3cf->get_setting( static::API_KEY_SETTING );
        }

        /**
         * Get the configured region.
         *
         * @return string
         */
        protected function get_storage_region(): string {
                return (string) $this->as3cf->get_setting( static::REGION_SETTING );
        }

        /**
         * Get the CDN domain that should be used for delivery.
         *
         * @return string
         */
        protected function get_cdn_domain(): string {
                $cdn = (string) $this->as3cf->get_setting( static::CDN_URL_SETTING );

                if ( empty( $cdn ) ) {
                        $cdn = (string) $this->as3cf->get_setting( static::CUSTOM_CNAME_SETTING );
                }

                if ( empty( $cdn ) && $this->as3cf->get_setting( 'enable-delivery-domain' ) ) {
                        $cdn = (string) $this->as3cf->get_setting( 'delivery-domain' );
                }

                return $cdn;
        }

        /**
         * Return CDN URL without fallbacks.
         *
         * @return string
         */
        protected function get_cdn_url(): string {
                return (string) $this->as3cf->get_setting( static::CDN_URL_SETTING );
        }

        /**
         * Return custom CNAME if configured.
         *
         * @return string
         */
        protected function get_custom_cname(): string {
                return (string) $this->as3cf->get_setting( static::CUSTOM_CNAME_SETTING );
        }

        /**
         * Purge the CDN for a specific key when possible.
         *
         * @param string $bucket
         * @param string $key
         */
        protected function purge_cdn_for_key( string $bucket, string $key ) {
                $cdn = $this->get_cdn_domain();

                if ( empty( $cdn ) ) {
                        return;
                }

                $cdn = untrailingslashit( $cdn );
                $key = ltrim( $key, '/' );

                $url = $cdn . '/' . $key;

                $result = Bunny_Helper::purge_url( $this->get_api_key(), $url );

                if ( is_wp_error( $result ) ) {
                        AS3CF_Error::log( 'Bunny CDN purge failed: ' . $result->get_error_message() );
                }
        }
}
