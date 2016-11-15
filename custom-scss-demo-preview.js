/* globals console */
(function( api, $ ) {
	if ( api.settingPreviewHandlers ) {

		// No-op the custom_css preview handler since now handled by partial.
		api.settingPreviewHandlers.custom_css = function() {};
	} else {
		console.warn( 'Missing core patch that adds support for settingPreviewHandlers' );
	}

	api.selectiveRefresh.partialConstructor.custom_css = api.selectiveRefresh.Partial.extend( {

		/**
		 * Refresh custom_css partial, using selective refresh if pre-processor and direct DOM manipulation if otherwise.
		 *
		 * @returns {jQuery.promise}
		 */
		refresh: function() {
			var partial = this, deferred, setting;
			if ( api( 'custom_css_preprocessor' ).get() ) {
				return api.selectiveRefresh.Partial.prototype.refresh.call( partial );
			} else {
				deferred = new $.Deferred();
				setting = api( 'custom_css[' + api.settings.theme.stylesheet + ']' );
				_.each( partial.placements(), function( placement ) {
					placement.container.text( setting.get() );
				} );

				deferred.resolve();
				return deferred.promise();
			}
		},

		/**
		 * Prevent adding edit shortcuts to head.
		 *
		 * @todo Core should prevent adding edit shortcuts if the placement is not inside of the body.
		 */
		createEditShortcutForPlacement: function() {}

	} );

}( wp.customize, jQuery ));
