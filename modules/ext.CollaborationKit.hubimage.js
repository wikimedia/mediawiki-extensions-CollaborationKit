( function ( $, mw, OO ) {

	// Subclass ProcessDialog.
	function ProcessDialog( config ) {
		ProcessDialog.super.call( this, config );
	}
	OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ProcessDialog.static.title = mw.msg( 'collaborationkit-hubimage-browser' );
	ProcessDialog.static.name = 'collabkit-hubimage';
	ProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-hubimage-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	// Use the initialize() method to add content to the dialog's $body,
	// to initialize widgets, and to set up event handlers.
	ProcessDialog.prototype.initialize = function () {
		ProcessDialog.super.prototype.initialize.apply( this, arguments );

		this.content = new mw.widgets.MediaSearchWidget();
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
				$( '.mw-ck-hub-image-input input' ).val( fileTitle );

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

	$( 'div.mw-ck-hub-image-input' ).addClass( 'hubimage-browser-field' );
	$( 'div.mw-ck-hub-image-input div' ).css( 'display', 'none' );
	$( 'div.mw-ck-hub-image-input' )
		.append( '<img class="hubimagePreview" /><div class="hubimageBrowserButton">' )
		.append( hubimageBrowserButton.$element )
		.append( '</div>' );
	// Load current hub image
	if ( $( 'div.mw-ck-hub-image-input input' ).val() !== undefined ) {
		currentImageFilename = 'File:' + $( 'div.mw-ck-hub-image-input input' ).val();
		currentImage = new mw.Api()
			.get( {
				action: 'query',
				titles: currentImageFilename,
				prop: 'imageinfo',
				iiprop: 'url',
				formatversion: 2,
				iiurlwidth: 200
			} )
			.done( function ( data ) {
				$( 'img.hubimagePreview' )
					.attr( 'src', data.query.pages[ 0 ].imageinfo[ 0 ].thumburl )
					.css( 'margin-bottom', '10px' )
					.css( 'width', '100px' )
					.css( 'height', 'auto' )
					.css( 'display', 'block' );
			}
		);
	}
} )( jQuery, mediaWiki, OO );
