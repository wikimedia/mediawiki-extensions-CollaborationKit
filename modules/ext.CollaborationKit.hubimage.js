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
		var defaultSearchTerm, nsPrefix;

		nsPrefix = mw.config.get( 'wgFormattedNamespaces' )[ 6 ] + ':';

		ProcessDialog.super.prototype.initialize.apply( this, arguments );

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

	// In the event "Select" is pressed
	ProcessDialog.prototype.getActionProcess = function ( action ) {
		var dialog, openItUp, windowManager, processDialog,
			currentImageFilename, currentImage, hubimageBrowserButton;

		dialog = this;
		if ( action ) {
			return new OO.ui.Process( function () {
				fileObj = dialog.content.getResults().getSelectedItem();
				if ( fileObj === null ) {
					return dialog.close();
				}
				fileObj = fileObj.getData();
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

	$( 'div.mw-ck-hub-image-input input' ).css( 'display', 'none' );
	$( 'div.mw-ck-hub-image-input div.oo-ui-textInputWidget' )
		.append( '<img class="hubimagePreview" style="width:200px; background:#eee; height:200px; margin-bottom:10px; display:block;" /><div class="hubimageBrowserButton">' )
		.append( hubimageBrowserButton.$element )
		.append( '</div>' );
	// Load current hub image
	if ( $( 'div.mw-ck-hub-image-input input' ).val() !== '' ) {
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
					.css( 'height', 'auto' );
			}
		);
	}
} )( jQuery, mediaWiki, OO );
