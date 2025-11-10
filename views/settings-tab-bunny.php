<?php
/**
 * Bunny.net settings helper tab.
 *
 * The primary configuration UI for Bunny.net is rendered by the Svelte settings
 * application. This template exists to provide helpful messaging for legacy
 * integrations that expect a PHP view to exist for each provider-specific tab.
 */
?>
<div class="as3cf-bunny-settings-help notice notice-info">
        <p><?php esc_html_e( 'Configure Bunny.net credentials, storage, and CDN options in the Storage and Delivery sections above. Use the Test Connection and Purge buttons to validate your settings.', 'amazon-s3-and-cloudfront' ); ?></p>
</div>
