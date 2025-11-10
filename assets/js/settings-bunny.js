(function( $ ) {
        function handleRequest( action, button ) {
                if ( ! window.as3cf_settings || ! window.ajaxurl ) {
                        return;
                }

                const nonce   = as3cf_settings.nonce || '';
                const spinner = $( '<span class="spinner is-active"></span>' );
                const originalText = button.text();

                button.prop( 'disabled', true );
                button.after( spinner );

                $.post( ajaxurl, {
                        action: action,
                        nonce: nonce
                } )
                        .done( function( response ) {
                                if ( response && response.success ) {
                                        button.text( button.data( 'success' ) || originalText );
                                } else {
                                        const message = response && response.data && response.data.message ? response.data.message : as3cf_settings.strings.settings_saved;
                                        window.alert( message );
                                }
                        } )
                        .fail( function() {
                                window.alert( button.data( 'error' ) || 'An unexpected error occurred.' );
                        } )
                        .always( function() {
                                spinner.remove();
                                button.prop( 'disabled', false );
                                button.text( originalText );
                        } );
        }

        $( document ).on( 'click', '#as3cf-bunny-test-connection', function( event ) {
                event.preventDefault();
                handleRequest( 'as3cf_bunny_test_connection', $( this ) );
        } );

        $( document ).on( 'click', '#as3cf-bunny-purge-all', function( event ) {
                event.preventDefault();
                handleRequest( 'as3cf_bunny_purge_all', $( this ) );
        } );
})( jQuery );
