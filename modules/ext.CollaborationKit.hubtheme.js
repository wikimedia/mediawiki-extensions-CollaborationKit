( function ( $, mw, OO ) {

	var getColourBlock, getThumbnail, ImageProcessDialog, ColourProcessDialog, openColourBrowser;

	/**
	 * Get a colour block for inserting into page
	 *
	 * @param {string} colorName Name of colour
	 * @return {jQuery.Promise} Promise with API result
	 */
	getColourBlock = function ( colorName ) {
		return $( '<div></div>' )
			.addClass( 'mw-ck-colourblock' )
			.addClass( 'mw-ck-colour-' + colorName )
			.attr( 'title', mw.msg( 'collaborationkit-' + colorName ) );
	};

	/**
	 * Get an image thumbnail with 150px width
	 *
	 * @param {string} filename
	 * @return {jQuery} promise
	 */
	getThumbnail = function ( filename ) {
		return new mw.Api()
		.get( {
			action: 'query',
			titles: filename,
			prop: 'imageinfo',
			iiprop: 'url',
			formatversion: 2,
			iiurlwidth: 150
		}
	);
	};

	/**
	 * Subclass ProcessDialog for selecting a colour.
	 *
	 * @param {Object} config
	 */
	ColourProcessDialog = function ( config ) {
		ColourProcessDialog.super.call( this, config );
	};
	OO.inheritClass( ColourProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ColourProcessDialog.static.title = mw.msg( 'collaborationkit-colour-browser' );
	ColourProcessDialog.static.name = 'collabkit-colourselect';
	ColourProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-colour-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	/**
	 * Use the initialize() method to add content to the dialog's $body,
	 * to initialize widgets, and to set up event handlers.
	 */
	ColourProcessDialog.prototype.initialize = function () {
		var colourList, radioChoices, className;

		ColourProcessDialog.super.prototype.initialize.apply( this, arguments );

		this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );

		colourList = mw.config.get( 'wgCollaborationKitColourList' );

		radioChoices = [];
		for ( i = 0; i < colourList.length; i++ ) {
			radioChoices.push( new OO.ui.RadioOptionWidget( {
				label: getColourBlock( colourList[ i ] ),
				data: colourList[ i ]
			} ) );
		}

		this.radioSelect = new OO.ui.RadioSelectWidget( {
			name: 'colourChoice',
			items: radioChoices,
			classes: [ 'mw-ck-colourchoice-container' ]
		} );

		this.radioSelect.selectItemByData( $( '.mw-ck-colour-input select' ).val() );

		this.content.$element.append( this.radioSelect.$element );

		this.$body.append( this.content.$element );
	};

	/**
	 * In the event "Select" is pressed
	 *
	 */
	ColourProcessDialog.prototype.getActionProcess = function ( action ) {
		var dialog, toAppend, openColourBrowser, windowManager, colourBrowserButton, oldColour;

		oldColour = $( 'div.mw-ck-colour-input select option:selected' ).val();
		dialog = this;
		if ( action ) {
			return new OO.ui.Process( function () {
				var toAppend, $newColour;
				toAppend = dialog.radioSelect.getSelectedItem().getData();

				// Generate preview
				$( '.colourPreview .mw-ck-colourblock' )
					.attr( 'title', mw.msg( 'collaborationkit-' + toAppend ) )
					.removeClass()
					.addClass( 'colourPreview mw-ck-colourblock mw-ck-colour-' + toAppend );

				$( '.hubimagePreview' )
					.attr( 'class', 'hubimagePreview mw-ck-icon mw-ck-icon-puzzlepiece mw-ck-icon-puzzlepiece-' + toAppend );

				$( 'body' )
					.removeClass( 'mw-ck-theme-' + oldColour )
					.addClass( 'mw-ck-theme-' + toAppend );

				// Set form value
				$( 'div.mw-ck-colour-input select option[value=' + toAppend + ']' )
					.attr( 'selected', 'selected' );

				dialog.close( { action: action } );
			} );
		}
		// Fallback to parent handler.
		return ColourProcessDialog.super.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Get dialog height.
	 *
	 * @return {int} Dialog height
	 */
	ColourProcessDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	/**
	 * Create and append the window manager.
	 */
	openColourBrowser = function () {
		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );

		// Create a new dialog window.
		processDialog = new ColourProcessDialog( {
			size: 'medium'
		} );

		// Add windows to window manager using the addWindows() method.
		windowManager.addWindows( [ processDialog ] );

		// Open the window.
		windowManager.openWindow( processDialog );
	};

	/**
	 * Subclass ProcessDialog.
	 *
	 * @param {Object} config
	 */
	ImageProcessDialog = function ( config ) {
		ImageProcessDialog.super.call( this, config );
	};
	OO.inheritClass( ImageProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ImageProcessDialog.static.title = mw.msg( 'collaborationkit-hubimage-browser' );
	ImageProcessDialog.static.name = 'collabkit-hubimage';
	ImageProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-hubimage-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	/**
	 * Use the initialize() method to add content to the dialog's $body,
	 * to initialize widgets, and to set up event handlers.
	 */
	ImageProcessDialog.prototype.initialize = function () {
		var defaultSearchTerm, nsPrefix;

		nsPrefix = mw.config.get( 'wgFormattedNamespaces' )[ 6 ] + ':';

		ImageProcessDialog.super.prototype.initialize.apply( this, arguments );

		// Default image order of preference:
		// Display name > Page title > Nothing

		defaultSearchTerm = '';

		if ( mw.config.get( 'wgTitle' ) !== undefined ) {
			defaultSearchTerm = mw.config.get( 'wgTitle' );
		}
		if ( $( 'input[name=wptitle]' ).val() !== '' && $( 'input[name=wptitle]' ).val() !== undefined ) {
			defaultSearchTerm = $( 'input[name=wptitle]' ).val();
		}
		if ( $( 'input[name=wpdisplay_name]' ).val() !== '' && $( 'input[name=wpdisplay_name]' ).val() !== undefined ) {
			defaultSearchTerm = $( 'input[name=wpdisplay_name]' ).val();
		}
		if ( $( 'input[name=wpCollabHubDisplayName]' ).val() !== '' && $( 'input[name=wpCollabHubDisplayName]' ).val() !== undefined ) {
			defaultSearchTerm = $( 'input[name=wpCollabHubDisplayName]' ).val();
		}

		this.content = new mw.widgets.MediaSearchWidget();
		this.content.getQuery().setValue( defaultSearchTerm );
		this.$body.append( this.content.$element );
	};

	/**
	 * In the event "Select" is pressed
	 *
	 */
	ImageProcessDialog.prototype.getActionProcess = function ( action ) {
		var dialog, openImageBrowser, windowManager, processDialog, fileTitle,
			currentImageFilename, currentImage, hubimageBrowserButton;

		dialog = this;
		if ( action ) {
			return new OO.ui.Process( function () {
				fileObj = dialog.content.getResults().getSelectedItem();
				if ( fileObj === null ) {
					return dialog.close();
				}
				getThumbnail( fileObj.getData().title )
					.done( function ( data ) {
						fileUrl = data.query.pages[ 0 ].imageinfo[ 0 ].thumburl;
						fileHeight = data.query.pages[ 0 ].imageinfo[ 0 ].thumbheight;
						fileTitleObj = new mw.Title( fileObj.getData().title );
						fileTitle = fileTitleObj.title + '.' + fileTitleObj.ext;

						// Generate preview
						$( 'div.hubimagePreview' )
							.css( 'background', 'url("' + fileUrl + '")' )
							.css( 'height', fileHeight + 'px' );

						// Set form value
						$( '.mw-ck-hub-image-input input' ).val( fileTitle );
						dialog.close( { action: action } );
					} );
			} );
		}
		// Fallback to parent handler.
		return ImageProcessDialog.super.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Get dialog height.
	 */
	ImageProcessDialog.prototype.getBodyHeight = function () {
			return 600;
		};

	/**
	 * Create and append the window manager.
	 */
	openImageBrowser = function () {
		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );

		// Create a new dialog window.
		processDialog = new ImageProcessDialog( {
			size: 'large'
		} );

		// Add windows to window manager using the addWindows() method.
		windowManager.addWindows( [ processDialog ] );

		// Open the window.
		windowManager.openWindow( processDialog );
	};

	/**
	 * Initial setup function run when DOM loaded.
	 */
	setupPage = function () {
		var curColour, colourBrowserButton, currentImage, currentImageFilename, $hubthemeWidget;

		// Defining buttons
		colourBrowserButton = new OO.ui.ButtonWidget( {
			icon: 'search',
			framed: false,
			classes: [ 'mw-ck-hubtheme-widget-inlinebutton' ]
		} );
		colourBrowserButton.on( 'click', openColourBrowser );

		hubimageBrowserButton = new OO.ui.ButtonWidget( {
			icon: 'search',
			framed: false,
			classes: [ 'mw-ck-hubtheme-widget-inlinebutton' ]
		} );
		hubimageBrowserButton.on( 'click', openImageBrowser );

		// Ascertaining default/pre-selected values
		curColour = $( 'div.mw-ck-colour-input select option:selected' ).val();

		if ( $( 'div.mw-ck-hub-image-input input' ).val() !== '' ) {
			currentImageFilename = 'File:' + $( 'div.mw-ck-hub-image-input input' ).val();
			getThumbnail( currentImageFilename )
				.done( function ( data ) {
					$( 'div.hubimagePreview' )
						.css( 'background', 'url("' + data.query.pages[ 0 ].imageinfo[ 0 ].thumburl + '")' )
						.css( 'height', data.query.pages[ 0 ].imageinfo[ 0 ].thumbheight + 'px' );
				} );
		}

		// Hiding HTML form elements
		$( '.mw-ck-colour-input' ).css( 'display', 'none' );
		$( '.mw-ck-hub-image-input' ).css( 'display', 'none' );

		// Setting up
		$( '.mw-htmlform-ooui-header' ).append( '<div class="mw-ck-hub-topform"></div>' );
		$( '.mw-collabkit-modifiededitform' ).prepend( '<div class="mw-ck-hub-topform"></div>' );

		$hubthemeWidget = $( '<div class="mw-ck-hubtheme-widget"></div>' )
			.append( $( '<div class="oo-ui-fieldLayout-header"></div>' )
				.append( new OO.ui.LabelWidget( {
					label: mw.msg( 'collaborationkit-hubedit-hubtheme' )
				} ).$element )
				.append( new OO.ui.PopupButtonWidget( {
					classes: [ 'mw-ck-hubtheme-widget-help', 'oo-ui-fieldLayout-help' ],
					framed: false,
					icon: 'info',
					popup: {
						$content: $( '<span></span>' ).append( mw.msg( 'collaborationkit-hubedit-hubtheme-help' ) ),
						padded: true
					}
				} ).$element )
			)
			.append( $( '<div class="hubimagePreview mw-ck-icon mw-ck-icon-puzzlepiece" />' )
				.addClass( 'mw-ck-icon-puzzlepiece-' + curColour )
				.append( hubimageBrowserButton.$element )
			)
			.append( $( '<div class="colourPreview mw-ck-colourblock-container"></div>' )
				.append( getColourBlock( curColour )
					.append( colourBrowserButton.$element )
				)
			);

		$( '.mw-ck-hub-topform' ).append( $hubthemeWidget );
		$( '.mw-ck-hub-topform' ).append( '<div class="mw-ck-hub-name"></div>' );
		$( '.mw-htmlform-field-HTMLSelectField.mw-ck-namespace-input' )
			.attr( 'class', 'mw-htmlform-field-HTMLSelectField mw-ck-namespace-input-js' )
			.append( $( '.mw-htmlform-field-HTMLTextField.mw-ck-title-input' ) );
		$( '.mw-ck-hub-name' )
			.append( $( '.mw-htmlform-field-HTMLSelectField.mw-ck-namespace-input-js' ) )
			.append( $( '.mw-htmlform-field-HTMLTextField.mw-ck-display-input' ) );
	};

	$( setupPage );

} )( jQuery, mediaWiki, OO );
