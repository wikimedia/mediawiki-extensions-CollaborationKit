( function ( $, mw, OO ) {
	var setupPage, getColourBlock, ProcessDialog, openItUp;

	/**
	 * Get a colour block for inserting into page
	 *
	 * @param {string} colorName Name of colour
	 * @return {jQuery} A div showing the colour
	 */
	getColourBlock = function ( colorName ) {
		return $( '<div></div>' )
			.addClass( 'mw-ck-colourblock' )
			.addClass( 'mw-ck-colour-' + colorName )
			.attr( 'title', mw.msg( 'collaborationkit-' + colorName ) );
	};

	/**
	 * Subclass ProcessDialog.
	 *
	 * @param {Object} config
	 */
	ProcessDialog = function ( config ) {
		ProcessDialog.super.call( this, config );
	};
	OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

	// Specify a static title and actions.
	ProcessDialog.static.title = mw.msg( 'collaborationkit-colour-browser' );
	ProcessDialog.static.name = 'collabkit-colourselect';
	ProcessDialog.static.actions = [
		{ action: 'save', label: mw.msg( 'collaborationkit-colour-select' ), flags: 'primary' },
		{ label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	/**
	 * Use the initialize() method to add content to the dialog's $body,
	 * to initialize widgets, and to set up event handlers.
	 */
	ProcessDialog.prototype.initialize = function () {
		var colourList, radioChoices, className;

		ProcessDialog.super.prototype.initialize.apply( this, arguments );

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
	ProcessDialog.prototype.getActionProcess = function ( action ) {
		var dialog, toAppend, openItUp, windowManager, processDialog, colourBrowserButton;

		dialog = this;
		if ( action ) {
			return new OO.ui.Process( function () {
				var toAppend, $newColour;
				toAppend = dialog.radioSelect.getSelectedItem().getData();
				$newColour = getColourBlock( toAppend );

				// Generate preview
				$( '.colourPreview .mw-ck-colourblock' )
					.replaceWith( $newColour );
				$( '.colourPreview .mw-ck-colourname' ).text(
					mw.msg( 'collaborationkit-' + toAppend )
				);

				// Set form value
				$( 'div.mw-ck-colour-input select option[value=' + toAppend + ']' )
					.attr( 'selected', 'selected' );

				dialog.close( { action: action } );
			} );
		}
		// Fallback to parent handler.
		return ProcessDialog.super.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Get dialog height.
	 *
	 * @return {int} Dialog height
	 */
	ProcessDialog.prototype.getBodyHeight = function () {
		return this.content.$element.outerHeight( true );
	};

	/**
	 * Create and append the window manager.
	 */
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

	/**
	 * Initial setup function run when DOM loaded.
	 */
	setupPage = function () {
		var curColour;
		colourBrowserButton = new OO.ui.ButtonWidget();
		colourBrowserButton.setLabel( mw.msg( 'collaborationkit-colour-launchbutton' ) );
		colourBrowserButton.on( 'click', openItUp );

		curColour = $( 'div.mw-ck-colour-input select option:selected' ).val();

		$( '.mw-ck-colour-input .oo-ui-dropdownWidget' ).css( 'display', 'none' );
		$( '.mw-ck-colour-input .mw-ck-colour-input' ).append(
			$( '<div class="colourPreview mw-ck-colourblock-container"></div>' )
				.append( getColourBlock( curColour ) )
				.append(
					$( '<div class="mw-ck-colourname"></div>' )
						.text( mw.msg( 'collaborationkit-' + curColour ) )
				)
				.append( $( '<div class="colourBrowserButton"></div>' )
					.append( colourBrowserButton.$element )
				)
		);
	};

	$( setupPage );
} )( jQuery, mediaWiki, OO );
