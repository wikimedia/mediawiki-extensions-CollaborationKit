( function ( $, mw, OO ) {

	// Subclass ProcessDialog.
	function ProcessDialog( config ) {
		ProcessDialog.super.call( this, config );
	}
	OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ProcessDialog.static.title = mw.msg( 'collaborationkit-icon-browser' );
	ProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-icon-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	// Use the initialize() method to add content to the dialog's $body,
	// to initialize widgets, and to set up event handlers.
	ProcessDialog.prototype.initialize = function () {
			var iconList, radioChoices, className;

			ProcessDialog.super.prototype.initialize.apply( this, arguments );

			this.content = new OO.ui.PanelLayout( { padded: true, expanded: false } );

			iconList = mw.config.get( 'wgCollaborationKitIconList' );

			radioChoices = [];
			for ( i = 0; i < iconList.length; i++ ) {
				divElm = $( '<div></div>' )
					.addClass( 'mw-ck-iconbrowser-iconcontainer' )
					.append( $( '<div></div>' )
						.addClass( 'mw-ckicon-' + iconList[ i ] )
						.addClass( 'mw-ck-iconbrowser-icon' )
					);

				radioChoices.push( new OO.ui.RadioOptionWidget( {
					label: divElm,
					data: iconList[ i ]
				} ) );
			}

			this.radioSelect = new OO.ui.RadioSelectWidget( {
				name: 'iconChoice',
				items: radioChoices,
				classes: [ 'mw-ck-iconbrowser' ]
			} );

			this.content.$element.append( this.radioSelect.$element );

			this.$body.append( this.content.$element );
		};

	// In the event "Select" is pressed
	ProcessDialog.prototype.getActionProcess = function ( action ) {
			var dialog, toAppend, openItUp, windowManager, processDialog, iconBrowserButton;

			dialog = this;
			if ( action ) {
				return new OO.ui.Process( function () {
						toAppend = dialog.radioSelect.getSelectedItem().getData();

						// Generate preview
						$( '.iconPreview' )
							.addClass( 'mw-ckicon-' + toAppend )
							.css( 'display', 'block' );
						// Set form value
						$( '.mw-ck-iconinput input' ).val( toAppend );

						dialog.close( { action: action } );
					} );
			}
			// Fallback to parent handler.
			return ProcessDialog.super.prototype.getActionProcess.call( this, action );
		};

	// Get dialog height.
	ProcessDialog.prototype.getBodyHeight = function () {
			return this.content.$element.outerHeight( true );
		};

	// Create and append the window manager.
	openItUp = function () {
		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );

		// Create a new dialog window.
		processDialog = new ProcessDialog( {
			size: 'medium'
		} );

		// Add windows to window manager using the addWindows() method.
		windowManager.addWindows( [ processDialog ] );

		// Open the window.
		windowManager.openWindow( processDialog );
	};

	iconBrowserButton = new OO.ui.ButtonWidget();
	iconBrowserButton.setLabel( mw.msg( 'collaborationkit-icon-launchbutton' ) );
	iconBrowserButton.on( 'click', openItUp );

	$( 'div.mw-ck-iconinput' ).addClass( 'icon-browser-field' );
	$( 'div.mw-ck-iconinput .oo-ui-labelElement-label' ).css( 'display', 'none' );
	$( 'div.mw-ck-iconinput .oo-ui-fieldLayout-field' ).css( 'display', 'none' );
	$( 'div.mw-ck-iconinput' )
		.append( '<div class="iconPreview" style="display:none"></div>' )
		.append( iconBrowserButton.$element );

} )( jQuery, mediaWiki, OO );
