( function ( $, mw, OO ) {
	'use strict';

	var ProcessDialog, openItUp, setupPage;

	/**
	 * Subclass ProcessDialog.
	 *
	 * @class
	 * @extends OO.ui.ProcessDialog
	 *
	 * @constructor
	 * @param {Object} config
	 */
	ProcessDialog = function ( config ) {
		ProcessDialog.super.call( this, config );
	};
	OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ProcessDialog.static.title = mw.msg( 'collaborationkit-icon-browser' );
	ProcessDialog.static.name = 'collabkit-iconselect';
	ProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-icon-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	/**
	 * Use the initialize() method to add content to the dialog's $body,
	 * to initialize widgets, and to set up event handlers.
	 */
	ProcessDialog.prototype.initialize = function () {
		var iconList, radioChoices, $divElm, i;

		ProcessDialog.super.prototype.initialize.apply( this, arguments );

		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );

		iconList = mw.config.get( 'wgCollaborationKitIconList' );

		radioChoices = [];
		for ( i = 0; i < iconList.length; i++ ) {
			$divElm = $( '<div></div>' )
				.addClass( 'mw-ck-iconbrowser-iconcontainer' )
				.append( $( '<div></div>' )
					.addClass( 'mw-ck-icon-' + iconList[ i ] )
					.addClass( 'mw-ck-iconbrowser-icon' )
				);

			radioChoices.push( new OO.ui.RadioOptionWidget( {
				label: $divElm,
				data: iconList[ i ]
			} ) );
		}

		this.radioSelect = new OO.ui.RadioSelectWidget( {
			name: 'iconChoice',
			items: radioChoices,
			classes: [ 'mw-ck-iconbrowser' ]
		} );

		this.radioSelect.selectItemByData( $( '.mw-ck-icon-input input' ).val() );

		this.content.$element.append( this.radioSelect.$element );

		this.$body.append( this.content.$element );
	};

	/**
	 * In the event "Select" is pressed
	 *
	 * @param action
	 */
	ProcessDialog.prototype.getActionProcess = function ( action ) {
		var dialog, toAppend;

		dialog = this;
		if ( action ) {
			return new OO.ui.Process( function () {
				toAppend = dialog.radioSelect.findSelectedItem().getData();

				// Generate preview
				$( '.iconPreview' )
					.attr( 'class', 'iconPreview' ) // Purges current icon selection
					.addClass( 'mw-ck-icon-' + toAppend )
					.css( 'display', 'block' );
				// Set form value
				$( '.mw-ck-icon-input input' ).val( toAppend );

				dialog.close( { action: action } );
			} );
		}
		// Fallback to parent handler.
		return ProcessDialog.super.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Get dialog height.
	 *
	 * @return {number} Dialog height
	 */
	ProcessDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	/**
	 * Create and append the window manager
	 */
	openItUp = function () {
		var processDialog, windowManager;

		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );

		// Create a new dialog window.
		processDialog = new ProcessDialog( {
			size: 'large'
		} );

		// Add windows to window manager using the addWindows() method.
		windowManager.addWindows( [ processDialog ] );

		// Open the window.
		windowManager.openWindow( processDialog );
	};

	setupPage = function () {
		var iconBrowserButton, $mwCkIconInput;
		iconBrowserButton = new OO.ui.ButtonWidget();
		iconBrowserButton.setLabel( mw.msg( 'collaborationkit-icon-launchbutton' ) );
		iconBrowserButton.on( 'click', openItUp );

		$( '.mw-ck-icon-input.oo-ui-comboBoxInputWidget' ).css( 'display', 'none' );
		$( 'div.mw-ck-icon-input' )
			.append( '<div class="iconPreview mw-ck-icon-circlestar"></div>' )
			.append( iconBrowserButton.$element );

		$mwCkIconInput = $( '.mw-htmlform-field-HTMLComboboxField.mw-ck-icon-input' );
		$( 'fieldset' ).append( $mwCkIconInput );

		// Adding classes to trigger special styles
		$( '.oo-ui-fieldsetLayout-group' ).addClass( 'mw-ck-iconbrowser-enabled' );
		$mwCkIconInput.addClass( 'mw-ck-iconbrowser-enabled' );
		$( '.mw-ck-icon-input .oo-ui-buttonElement' ).addClass( 'mw-ck-iconbrowser-enabled' );

	};

	$( setupPage );

} )( jQuery, mediaWiki, OO );
