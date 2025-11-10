<script>
        import {delivery_provider, settings, storage_provider, strings} from "../js/stores";
        import Panel from "./Panel.svelte";
        import DeliverySettingsHeadingRow
                from "./DeliverySettingsHeadingRow.svelte";
        import SettingsValidationStatusRow from "./SettingsValidationStatusRow.svelte";
        import SettingsPanelOption from "./SettingsPanelOption.svelte";

        const BUNNY_PROVIDER_KEY = "bunny";

	/**
	 * Potentially returns a reason that the provided domain name is invalid.
	 *
	 * @param {string} domain
	 *
	 * @return {string}
	 */
        function domainValidator( domain ) {
                const domainPattern = /[^a-z0-9.-]/;

                let message = "";

                if ( domain.trim().length === 0 ) {
                        message = $strings.domain_blank;
                } else if ( true === domainPattern.test( domain ) ) {
                        message = $strings.domain_invalid_content;
                } else if ( domain.length < 3 ) {
                        message = $strings.domain_too_short;
                }

                return message;
        }

        function cdnUrlValidator( url ) {
                if ( url.trim().length === 0 ) {
                        return "";
                }

                const pattern = /^(https?:\/\/)?[a-z0-9.-]+(\/[a-z0-9._~\-\/]*)?$/i;

                if ( ! pattern.test( url ) ) {
                        return $strings.url_invalid;
                }

                return "";
        }
</script>

<Panel name="settings" heading={$strings.delivery_settings_title} helpKey="delivery-provider">
	<DeliverySettingsHeadingRow/>
	<SettingsValidationStatusRow section="delivery"/>
	<SettingsPanelOption
		heading={$strings.rewrite_media_urls}
		description={$delivery_provider.rewrite_media_urls_desc}
		toggleName="serve-from-s3"
		bind:toggle={$settings["serve-from-s3"]}
	/>

        {#if $delivery_provider.delivery_domain_allowed}
                <SettingsPanelOption
                        heading={$strings.delivery_domain}
                        description={$delivery_provider.delivery_domain_desc}
                        toggleName="enable-delivery-domain"
                        bind:toggle={$settings["enable-delivery-domain"]}
                        textName="delivery-domain"
                        bind:text={$settings["delivery-domain"]}
                        validator={domainValidator}
                />
		{#if $delivery_provider.use_signed_urls_key_file_allowed && $settings[ "enable-delivery-domain" ]}
			<SettingsPanelOption
				heading={$delivery_provider.signed_urls_option_name}
				description={$delivery_provider.signed_urls_option_description}
				toggleName="enable-signed-urls"
				bind:toggle={$settings["enable-signed-urls"]}
			>
				<!-- Currently only CloudFront needs a key file for signing -->
				{#if $settings[ "enable-signed-urls" ]}
					<SettingsPanelOption
						heading={$delivery_provider.signed_urls_key_id_name}
						description={$delivery_provider.signed_urls_key_id_description}
						textName="signed-urls-key-id"
						bind:text={$settings["signed-urls-key-id"]}
						nested={true}
						first={true}
					/>

					<SettingsPanelOption
						heading={$delivery_provider.signed_urls_key_file_path_name}
						description={$delivery_provider.signed_urls_key_file_path_description}
						textName="signed-urls-key-file-path"
						bind:text={$settings["signed-urls-key-file-path"]}
						placeholder={$delivery_provider.signed_urls_key_file_path_placeholder}
						nested={true}
					/>

					<SettingsPanelOption
						heading={$delivery_provider.signed_urls_object_prefix_name}
						description={$delivery_provider.signed_urls_object_prefix_description}
						textName="signed-urls-object-prefix"
						bind:text={$settings["signed-urls-object-prefix"]}
						placeholder="private/"
						nested={true}
					/>
				{/if}
			</SettingsPanelOption>
		{/if}
        {/if}

        {#if $storage_provider.provider_key_name === BUNNY_PROVIDER_KEY}
                <SettingsPanelOption
                        heading={$strings.bunny_cdn_url}
                        description={$strings.bunny_cdn_url_desc}
                        textName="bunny-cdn-url"
                        bind:text={$settings["bunny-cdn-url"]}
                        placeholder="https://example.b-cdn.net"
                        validator={cdnUrlValidator}
                />

                <SettingsPanelOption
                        heading={$strings.bunny_custom_cname}
                        description={$strings.bunny_custom_cname_desc}
                        textName="bunny-custom-cname"
                        bind:text={$settings["bunny-custom-cname"]}
                        placeholder="cdn.example.com"
                        validator={domainValidator}
                />

                <div class="bunny-actions">
                        <button
                                id="as3cf-bunny-test-connection"
                                class="button button-secondary"
                                data-success={$strings.success_action_complete}
                                data-error={$strings.error_action_failed}
                        >{$strings.bunny_test_connection}</button>
                        <button
                                id="as3cf-bunny-purge-all"
                                class="button"
                                data-success={$strings.success_action_complete}
                                data-error={$strings.error_action_failed}
                        >{$strings.bunny_purge_all}</button>
                        <p class="description">{$strings.bunny_purge_all_desc}</p>
                </div>
        {/if}

        <SettingsPanelOption
                heading={$strings.force_https}
                description={$strings.force_https_desc}
                toggleName="force-https"
		bind:toggle={$settings["force-https"]}
	/>
</Panel>
