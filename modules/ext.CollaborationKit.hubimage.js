( function ( $, mw, OO ) {

	// Subclass ProcessDialog.
	function ProcessDialog( config ) {
		ProcessDialog.super.call( this, config );
	}
	OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ProcessDialog.static.title = mw.msg( 'collaborationkit-hubimage-browser' );
	ProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-hubimage-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	// Use the initialize() method to add content to the dialog's $body,
	// to initialize widgets, and to set up event handlers.
	ProcessDialog.prototype.initialize = function () {
			ProcessDialog.super.prototype.initialize.apply( this, arguments );

			this.content = new ve.ui.MWMediaSearchWidget();
			this.$body.append( this.content.$element );
		};

	// In the event "Select" is pressed
	ProcessDialog.prototype.getActionProcess = function ( action ) {
			var dialog, openItUp, windowManager, processDialog,
				currentImageFilename, currentImage, hubimageBrowserButton;

			dialog = this;
			if ( action ) {
				return new OO.ui.Process( function () {
						fileObj = dialog.content.getResults().getSelectedItem().getData();
						fileUrl = fileObj.thumburl;
						fileTitle = new mw.Title( fileObj.title );
						fileTitle = fileTitle.title + '.' + fileTitle.ext;

						// Generate preview
						$( 'img.hubimagePreview' )
							.attr( 'style', 'display:block' )
							.attr( 'src', fileUrl )
							.attr( 'width', '200px' )
							.css( 'margin-bottom', '10px' );

						// Set form value
						$( '.mw-ck-hubimageinput input, div#wpCollabHubImage input' ).val( fileTitle );

						dialog.close( { action: action } );
					} );
			}
			// Fallback to parent handler.
			return ProcessDialog.super.prototype.getActionProcess.call( this, action );
		};

	// Get dialog height.
	ProcessDialog.prototype.getBodyHeight = function () {
			return 600;
		};

	// Create and append the window manager.
	openItUp = function () {
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

	hubimageBrowserButton = new OO.ui.ButtonWidget();
	hubimageBrowserButton.setLabel( mw.msg( 'collaborationkit-hubimage-launchbutton' ) );
	hubimageBrowserButton.on( 'click', openItUp );

	$( 'div.mw-ck-hubimageinput, div#wpCollabHubImage' ).addClass( 'hubimage-browser-field' );
	$( 'div.mw-ck-hubimageinput label, div#wpCollabHubImage' )
		.append( '<img class="hubimagePreview" /><div class="hubimageBrowserButton">' )
		.append( hubimageBrowserButton.$element )
		.append( '</div>' );
	$( 'div.mw-ck-hubimageinput input, div#wpCollabHubImage input' ).css( 'display', 'none' );

	// Load current hub image
	if ( $( 'input#wpCollabHubImage' ).val() !== undefined ) {
		currentImageFilename = 'File:' + $( 'input#wpCollabHubImage' ).val();
		currentImage = new mw.Api()
			.get( {
				action: 'query',
				titles: currentImageFilename,
				prop: 'imageinfo',
				iiprop: 'url',
				iiurlwidth: 200 } )
			.done( function ( data ) {
					$( 'img.hubimagePreview' )
						.attr( 'src', data.query.pages[ -1 ].imageinfo[ 0 ].thumburl )
						.css( 'margin-bottom', '10px' )
						.css( 'display', 'block' );
				}
		);
	}

} )( jQuery, mediaWiki, OO );
