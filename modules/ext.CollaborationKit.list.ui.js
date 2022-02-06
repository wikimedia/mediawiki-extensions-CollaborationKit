/**
 * @param $
 * @param mw
 * @param OO
 * @class ext.CollaborationKit.list.ui
 */
( function ( $, mw, OO ) {
	'use strict';

	var getItemFromUid, addItem, modifyItem, modifyExistingItem, LE;

	LE = require( 'ext.CollaborationKit.list.edit' );

	/**
	 * Finds item object based on a UID.
	 *
	 * @param {number} uid The relevant unique ID
	 * @param {Object} columns The columns object from the content object
	 * @return {Object} An object with keys relevantItem, relevantColumn, relevantRow
	 */
	getItemFromUid = function ( uid, columns ) {
		var i, j, relevantItem, relevantColumn, relevantRow;

		outer: for ( i = 0; i < columns.length; i++ ) {
			for ( j = 0; j < columns[ i ].items.length; j++ ) {
				if ( columns[ i ].items[ j ].uid === uid ) {
					relevantItem = columns[ i ].items[ j ];
					relevantColumn = i;
					relevantRow = j;
					break outer;
				}
			}
		}

		return {
			relevantItem: relevantItem,
			relevantColumn: relevantColumn,
			relevantRow: relevantRow
		};
	};

	/**
	 * Adds a new item to a list
	 *
	 * @param {number} colId The ID number of the column
	 */
	addItem = function ( colId ) {
		modifyItem( { itemColId: colId } );
	};

	/**
	 * Opens a window to manage list item modification
	 *
	 * @param {Object} itemToEdit Data concerning the item to edit/add. At
	 *    minimum you need an itemColId attribute with the column ID.
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
	 * @param {string} uid Unique identifier based on order in native JSON representation
	 */
	modifyExistingItem = function ( itemName, uid ) {
		LE.getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var done, itemIdNumber, toEdit;
			done = false;

			toEdit = getItemFromUid( uid, res.content.columns );

			done = true;
			modifyItem( {
				itemTitle: toEdit.relevantItem.title,
				itemImage: toEdit.relevantItem.image,
				itemDescription: toEdit.relevantItem.notes,
				itemColId: toEdit.relevantColumn,
				itemUid: uid
			} );

			if ( !done ) {
				// FIXME error handling
				alert( mw.msg( 'collaborationkit-list-error-edit' ) );
				location.reload();
			}
		} );
	};

	/**
	 * @class NewItemDialog
	 * @extends OO.ui.ProcessDialog
	 *
	 * @constructor
	 * @param {Object} config Configuration object
	 */
	// There's probably an easier way to do this.
	function NewItemDialog( config ) {
		var buttonlabel = 'collaborationkit-list-newitem-label';

		if ( config.itemTitle ) {
			this.itemTitle = config.itemTitle;
			this.itemDescription = config.itemDescription;
			this.itemImage = config.itemImage;
			this.itemUid = config.itemUid;
		}
		if ( config.itemColId ) {
			this.itemColId = config.itemColId;
		} else {
			this.itemColId = 0;
		}
		if ( this.itemUid ) {
			// Item already exists, we're actually editing it
			NewItemDialog.static.title = mw.msg( 'collaborationkit-list-edititem-title' );
			NewItemDialog.static.name = 'collabkit-edititemdialog';
			buttonlabel = 'collaborationkit-list-edititem-label';
		} else {
			// Usual new item dance
			NewItemDialog.static.title = mw.msg( 'collaborationkit-list-newitem-title' );
			NewItemDialog.static.name = 'collabkit-newitemdialog';
		}
		NewItemDialog.static.actions = [
			{
				action: 'continue',
				modes: 'edit',
				label: mw.msg( buttonlabel ),
				flags: [ 'primary', 'progressive' ]
			},
			{ modes: 'edit', label: mw.msg( 'cancel' ), flags: 'safe' }
		];

		NewItemDialog.parent.call( this, config );
	}

	OO.inheritClass( NewItemDialog, OO.ui.ProcessDialog );

	/**
	 * @param {Object} itemInfo info from json
	 */
	NewItemDialog.prototype.initialize = function ( itemInfo ) {
		var description, itemTitleObj, i, j;

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
				validateTitle: false // we want people to be able to put anything
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
		this.description = new OO.ui.MultilineTextInputWidget( {
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

		dialog.pushPending();

		if ( this.titleWidget instanceof mw.widgets.UserInputWidget ) {
			titleObj = mw.Title.newFromText( 'User:' + title );
			if ( titleObj ) {
				title = titleObj.getPrefixedText();
			} else {
				mw.log( 'UserInputWidget gave invalid title' );
			}
		}

		LE.getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var index,
				itemToAdd = {
					title: title,
					notes: notes
				},
				relevantItem,
				relevantRow;

			if ( file ) {
				itemToAdd.image = file;
			}
			if ( dialog.itemUid !== undefined ) {
				// We are editing an existing item

				relevantItem = getItemFromUid( dialog.itemUid, res.content.columns );

				relevantRow = relevantItem.relevantRow;
				relevantItem = relevantItem.relevantItem;

				// Edit conflict detection
				if ( relevantItem.title !== dialog.itemTitle ) {
					alert( mw.msg( 'collaborationkit-list-error-editconflict' ) );
					location.reload();
					// fixme proper handling.
					throw new Error( 'edit conflict' );
				}

				index = relevantRow;
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

		$list.find( '.mw-ck-list-item' ).each( function () {
			var $buttonRow,
				deleteButton,
				moveButton,
				editButton,
				$delWrapper,
				$moveWrapper,
				$editWrapper,
				colId,
				$item = $( this );

			colId = LE.getColId( $item );
			deleteButton = new OO.ui.ButtonWidget( {
				framed: false,
				icon: 'trash',
				title: mw.msg( 'collaborationkit-list-delete' )
			} );

			// Icon instead of button to avoid conflict with jquery.ui

			if ( !mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
				moveButton = new OO.ui.IconWidget( {
					framed: false,
					icon: 'move',
					title: mw.msg( 'collaborationkit-list-move' )
				} );
			}

			editButton = new OO.ui.ButtonWidget( {
				icon: 'edit',
				framed: false
			} ).on( 'click', function () {
				modifyExistingItem(
					$item.data( 'collabkit-item-title' ),
					$item.data( 'collabkit-item-uid' )
				);
			} );

			// FIXME, the <a> might make an extra target when tabbing
			// through the document (Maybe also messing up screen readers).
			// not sure. Its used so that jquery.confirmable makes a link.
			$delWrapper = $( '<a></a>' )
				.attr( 'href', '#' )
				.click( function ( e ) { e.preventDefault(); } )
				.addClass( 'mw-ck-list-deletebutton' )
				.addClass( 'mw-ck-list-button' )
				.append( deleteButton.$element )
				.confirmable( {
					handler: function () {
						LE.deleteItem( $item );
					}
				} );

			if ( !mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
				$moveWrapper = $( '<div></div>' )
					.addClass( 'mw-ck-list-movebutton' )
					.addClass( 'mw-ck-list-button' )
					.append( moveButton.$element );
			}

			$editWrapper = $( '<div></div>' )
				.addClass( 'mw-ck-list-editbutton' )
				.addClass( 'mw-ck-list-button' )
				.append( editButton.$element );

			$buttonRow = $( '<div></div>' )
				.addClass( 'mw-ck-list-buttonrow' )
				.append( $moveWrapper )
				.append( $editWrapper )
				.append( $delWrapper );

			$item.find( '.mw-ck-list-notes' )
				.append( $buttonRow );
		} );

		if ( !mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
			$list.sortable( {
				placeholder: 'mw-ck-list-dragplaceholder',
				forcePlaceholderSize: true,
				handle: '.mw-ck-list-movebutton',
				opacity: 0.6,
				scroll: true,
				items: '.mw-ck-list-item',
				cursor: 'grabbing', // Also overriden in CSS
				start: function ( e ) {
					$( e.target )
						.addClass( 'mw-ck-dragging' )
						.data( 'startItemList', LE.getListOfItems( $list ) );
				},
				stop: function ( e, ui ) {
					var oldListItems,
						newListItems,
						oldPosition,
						newPosition,
						$target,
						i,
						j,
						changed,
						count;

					$target = $( e.target );
					$target.removeClass( 'mw-ck-dragging' );
					oldListItems = $target.data( 'startItemList' );
					newListItems = LE.getListOfItems( $list );
					$target.data( 'startItemList', null );
					// FIXME better error handling
					if ( oldListItems.length !== newListItems.length ) {
						throw new Error( 'We somehow lost a column?!' );
					}

					changed = false;
					count = 0;
					for ( i = 0; i < oldListItems.length; i++ ) {
						count += oldListItems.length;
						count -= newListItems.length;
						for ( j = 0; j < oldListItems[ i ].length; j++ ) {
							if ( oldListItems[ i ][ j ] !== newListItems[ i ][ j ] ) {
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
						LE.reorderList( ui.item, newListItems, oldListItems );
					}
				}
			} );
		}

		buttonMsg = mw.config.get( 'wgCollaborationKitIsMemberList' ) ?
			'collaborationkit-list-add-user' :
			'collaborationkit-list-add';
		$( '.mw-ck-list-additem-container' ).each( function () {
			var $addButton = $( this );
			$addButton.append(
				$( '<div></div>' )
					// FIXME There is probably a way to add the class without
					// extra div.
					.addClass( 'mw-ck-list-additem' )
					.append(
						new OO.ui.ButtonWidget( {
							label: mw.msg( buttonMsg ),
							icon: 'add',
							flags: 'progressive'
						} ).on( 'click', function ( event ) {
							addItem( $addButton.closest( '.mw-ck-list-column' ).data( 'collabkit-column-id' ) );
						} )
							.$element
					)
			);
		} );
	} );

} )( jQuery, mediaWiki, OO );
