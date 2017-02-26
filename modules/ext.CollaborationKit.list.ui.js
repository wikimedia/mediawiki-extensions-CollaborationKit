( function ( $, mw, OO ) {
	var addItem, modifyItem, modifyExistingItem, LE;

	LE = require( 'ext.CollaborationKit.list.edit' );

	/**
	 * Adds a new item to a list
	 *
	 * @param {int} colId The ID number of the column
	 */
	addItem = function ( colId ) {
		modifyItem( { itemColId: colId } );
	};

	/**
	 * Opens a window to manage list item modification
	 *
	 * @param {Object} itemToEdit The name of the title to modify, or false to add new.
	 */
	modifyItem = function ( itemToEdit ) {
		var dialog, windowManager;
		windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );
		itemToEdit.size = 'medium';
		dialog = new NewItemDialog( itemToEdit );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	};

	/**
	 * Edit an existing item.
	 *
	 * @param {string} itemName The title of the item in question
	 * @param {int} colId Which column the item is in
	 */
	modifyExistingItem = function ( itemName, colId ) {
		LE.getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var done = false;
			// Member lists pretend to have two columns (active and inactive),
			// but in the source code, it's only one column. This forces one column
			// for the purposes of the JS editor.
			if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
				colId = 0;
			}
			$.each( res.content.columns[ colId ].items, function ( index ) {
				if ( this.title === itemName ) {
					done = true;
					modifyItem( {
						itemTitle: this.title,
						itemImage: this.image,
						itemDescription: this.notes,
						itemIndex: index,
						itemColId: colId
					} );
					return false;
				}
			} );
			if ( !done ) {
				// FIXME error handling
				alert( mw.msg( 'collaborationkit-list-error-edit' ) );
				location.reload();
			}
		} );
	};

	// There's probably an easier way to do this.
	function NewItemDialog( config ) {
		if ( config.itemTitle ) {
			this.itemTitle = config.itemTitle;
			this.itemDescription = config.itemDescription;
			this.itemImage = config.itemImage;
			this.itemIndex = config.itemIndex;
		}
		if ( config.itemColId ) {
			this.itemColId = config.itemColId;
		} else {
			this.itemColId = 0;
		}
		NewItemDialog.parent.call( this, config );
	}

	OO.inheritClass( NewItemDialog, OO.ui.ProcessDialog );
	NewItemDialog.static.title = mw.msg( 'collaborationkit-list-newitem-title' );
	NewItemDialog.static.name = 'collabkit-newitemdialog';
	NewItemDialog.static.actions = [
		{
			action: 'continue',
			modes: 'edit',
			label: mw.msg( 'collaborationkit-list-newitem-label' ),
			flags: [ 'primary', 'constructive' ]
		},
		{ modes: 'edit', label: mw.msg( 'cancel' ), flags: 'safe' }
	];

	/**
	 * @param {Object} itemInfo info from json
	 */
	NewItemDialog.prototype.initialize = function ( itemInfo ) {
		var description, itemTitleObj;

		NewItemDialog.parent.prototype.initialize.apply( this, arguments );
		this.panel1 = new OO.ui.PanelLayout( { padded: true, expanded: false } );

		itemTitleObj = this.itemTitle ? new mw.Title.newFromText( this.itemTitle ) : false;
		if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) &&
			( !itemTitleObj || itemTitleObj.namespace === 2 )
		) {
			this.titleWidget = new mw.widgets.UserInputWidget( {
				label: mw.msg( 'collaborationkit-list-newitem-user' )
			} );
			if ( itemTitleObj ) {
				this.titleWidget.setValue( itemTitleObj.getMainText() );
			} else if ( this.itemTitle ) {
				// Something weird happened to get here.
				mw.log( 'Memberlist mode but invalid Title??' );
				this.titleWidget.setValue( this.itemTitle );
			}
		} else {
			this.titleWidget = new mw.widgets.TitleInputWidget( {
				label: mw.msg( 'collaborationkit-list-newitem-page' ),
				validateTitle: false, // we want people to be able to put anything.
				showRedlink: true
				// Maybe should also showDescriptions and showImages
			} );
			if ( this.itemTitle ) {
				this.titleWidget.setValue( this.itemTitle );
			}
		}
		this.fileToUse = new mw.widgets.TitleInputWidget( {
			label: mw.msg( 'collaborationkit-list-newitem-image' ),
			namespace: 6,
			showImages: true,
			validateTitle: false // want empty titles allowed.
		} );
		this.description = new OO.ui.TextInputWidget( {
			multiline: true,
			label: mw.msg( 'collaborationkit-list-newitem-description' )
		} );

		if ( this.itemDescription ) {
			this.description.setValue( this.itemDescription );
		}
		if ( this.itemImage ) {
			this.fileToUse.setValue( this.itemImage );
		}
		this.panel1.$element.append( this.titleWidget.$element );
		this.panel1.$element.append( $( '<br>' ) );
		this.panel1.$element.append( this.fileToUse.$element );
		this.panel1.$element.append( $( '<br>' ) );
		this.panel1.$element.append( this.description.$element );
		this.stackLayout = new OO.ui.StackLayout( {
			items: [ this.panel1 ]
		} );
		this.$body.append( this.stackLayout.$element );
	};

	NewItemDialog.prototype.getActionProcess = function ( action ) {
		var dialog = this;
		if ( action === 'continue' ) {
			return new OO.ui.Process( function () {
				dialog.saveItem();
			} );
		}
		return NewItemDialog.parent.prototype.getActionProcess.call( this, action );
	};

	NewItemDialog.prototype.getBodyHeight = function () {
		return this.panel1.$element.outerHeight( true ) + 60;
	};

	NewItemDialog.prototype.saveItem = function () {
		var title = this.titleWidget.getValue().trim(),
			file = this.fileToUse.getValue().trim(),
			notes = this.description.getValue().trim(),
			dialog = this,
			titleObj;

		if ( this.titleWidget instanceof mw.widgets.UserInputWidget ) {
			titleObj = mw.Title.newFromText( 'User:' + title );
			if ( titleObj ) {
				title = titleObj.getPrefixedText();
			} else {
				mw.log( 'UserInputWidget gave invalid title' );
			}
		}

		LE.getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var index, itemToAdd = {
				title: title,
				notes: notes
			};

			if ( file ) {
				itemToAdd.image = file;
			}
			if ( dialog.itemIndex !== undefined ) {
				if (	res.content.columns[ dialog.itemColId ].items <= dialog.itemIndex ||
					res.content.columns[ dialog.itemColId ].items[ dialog.itemIndex ].title !== dialog.itemTitle
				) {
					alert( mw.msg( 'collaborationkit-list-error-editconflict' ) );
					location.reload();
					// fixme proper handling.
					throw new Error( 'edit conflict' );
				}
				index = dialog.itemIndex;
			} else {
				index = res.content.columns[ dialog.itemColId ].items.length;
			}
			res.content.columns[ dialog.itemColId ].items[ index ] = itemToAdd;
			res.summary = mw.msg( 'collaborationkit-list-add-summary', title );
			LE.saveJson( res, function () {
				dialog.close(); // FIXME should we just leave open?
				location.reload();
			} );
		} );

	};

	$( function () {
		var $list, column, buttonMsg;

		if ( !mw.config.get( 'wgEnableCollaborationKitListEdit' ) ) {
			// This page is not a list, or user does not have edit rights.
			return;
		}

		$list = $( '.mw-ck-list' );
		if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
			column = $( '.mw-ck-list-column[data-collabkit-column-id=0] .mw-ck-list-item:last-child' );
		} else {
			column = $( '.mw-ck-list-item:last-child' );
		}
		$list.find( '.mw-ck-list-item' ).each( function () {
				var deleteButton,
					moveButton,
					editButton,
					delWrapper,
					moveWrapper,
					editWrapper,
					colId,
					item = $( this );

				colId = LE.getColId( item );
				deleteButton = new OO.ui.ButtonWidget( {
					framed: false,
					icon: 'remove',
					iconTitle: mw.msg( 'collaborationkit-list-delete' )
				} );

				// Icon instead of button to avoid conflict with jquery.ui

				if ( !mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
					moveButton = new OO.ui.IconWidget( {
						framed: false,
						icon: 'move',
						iconTitle: mw.msg( 'collaborationkit-list-move' )
					} );
				}

				editButton = new OO.ui.ButtonWidget( {
					label: 'edit',
					framed: false
				} ).on( 'click', function () {
					modifyExistingItem( item.data( 'collabkit-item-title' ), colId );
				} );

				// FIXME, the <a> might make an extra target when tabbing
				// through the document (Maybe also messing up screen readers).
				// not sure. Its used so that jquery.confirmable makes a link.
				delWrapper = $( '<a></a>' )
					.attr( 'href', '#' )
					.click( function ( e ) { e.preventDefault(); } )
					.addClass( 'mw-ck-list-deletebutton' )
					.addClass( 'mw-ck-list-button' )
					.append( deleteButton.$element )
					.confirmable( {
						handler: function () {
							LE.deleteItem( item );
						}
					} );

				if ( !mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
					moveWrapper = $( '<div></div>' )
						.addClass( 'mw-ck-list-movebutton' )
						.addClass( 'mw-ck-list-button' )
						.append( moveButton.$element );
				}

				editWrapper = $( '<div></div>' )
					.addClass( 'mw-ck-list-editbutton' )
					.addClass( 'mw-ck-list-button' )
					.append( editButton.$element );

				item.find( '.mw-ck-list-title' )
					.append( delWrapper )
					.append( moveWrapper )
					.append( editWrapper );
			} );

		if ( !mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
			$list.sortable( {
				placeholder: 'mw-ck-list-dragplaceholder',
				axis: 'y',
				forcePlaceholderSize: true,
				handle: '.mw-ck-list-movebutton',
				opacity: 0.6,
				scroll: true,
				items: '.mw-ck-list-item',
				cursor: 'grabbing', // Also overriden in CSS
				start: function ( e ) {
					$( e.target )
						.addClass( 'mw-ck-dragging' )
						.data( 'startTitleList', LE.getListOfTitles( $list ) );
				},
				stop: function ( e, ui ) {
					var oldListTitles, newListTitles, $target, i, j, changed, count;

					$target = $( e.target );
					$target.removeClass( 'mw-ck-dragging' );
					oldListTitles = $target.data( 'startTitleList' );
					newListTitles = LE.getListOfTitles( $list );
					$target.data( 'startTitleList', null );
					// FIXME better error handling
					if ( oldListTitles.length !== newListTitles.length ) {
						throw new Error( 'We somehow lost a column?!' );
					}

					changed = false;
					count = 0;
					for ( i = 0; i < oldListTitles.length; i++ ) {
						count += oldListTitles.length;
						count -= newListTitles.length;
						for ( j = 0; j < oldListTitles[ i ].length; j++ ) {
							if ( oldListTitles[ i ][ j ] !== newListTitles[ i ][ j ] ) {
								changed = true;
								break;
							}
						}
					}
					if ( count !== 0 ) {
						// Sanity check failure
						throw new Error( 'List item has disappeared?' );
					}
					if ( changed ) {
						LE.reorderList( ui.item, newListTitles, oldListTitles );
					}
				}
			} );
		}

		buttonMsg = mw.config.get( 'wgCollaborationKitIsMemberList' ) ?
			'collaborationkit-list-add-user' :
			'collaborationkit-list-add';
		column.after(
			$( '<div></div>' )
				// FIXME There is probably a way to add the class without
				// extra div.
				.addClass( 'mw-ck-list-additem' )
				.append(
					new OO.ui.ButtonWidget( {
						label: mw.msg( buttonMsg ),
						icon: 'add',
						flags: 'constructive'
					} ).on( 'click', function () {
						addItem( $( event.target ).closest( '.mw-ck-list-column' ).data( 'collabkit-column-id' ) );
					} )
					.$element
				)
		);
	} );

} )( jQuery, mediaWiki, OO );
