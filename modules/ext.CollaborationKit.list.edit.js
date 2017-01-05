( function ( $, mw, OO ) {
	var deleteItem, getCurrentJson, saveJson, addItem, reorderList, getListOfTitles, modifyItem, modifyExistingItem, addSelf, curUserIsInList, getCol;

	addItem = function () {
		modifyItem( {} );
	};

	getColId = function ( $item ) {
		var $col, id;

		$col = $item.closest( '.mw-ck-list-column' );
		id = parseInt( $col.data( 'collabkit-column-id' ), 10 );
		if ( $col.length === 0 || !isFinite( id ) ) {
			throw new Error( 'Cannot find column' );
		}
		return id;
	};

	/**
	 * @param {Object} itemToEdit The name of the title to modify, or false to add new.
	 */
	modifyItem = function ( itemToEdit ) {
		var dialog,
			windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );
		itemToEdit.size = 'medium';
		dialog = new NewItemDialog( itemToEdit );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );
	};

	/**
	 * Find if the current user is already is in list.
	 *
	 * @return {boolean}
	 */
	curUserIsInList = function curUserIsInList() {
		var titleObj, escapedText;
		titleObj = mw.Title.newFromText( mw.config.get( 'wgUserName' ), 2 );
		escapedText = titleObj.getPrefixedText();
		escapedText = escapedText.replace( /\\/g, '\\\\' );
		escapedText = escapedText.replace( /"/g, '\\"' );
		query = '.mw-ck-list-item[data-collabkit-item-title="' +
			escapedText + '"]';
		return $( query ).length > 0;
	};

	/**
	 * Edit an existing item.
	 *
	 * @param {string} itemName The title of the item in question
	 * @param {int} colId Which column the item is in
	 */
	modifyExistingItem = function ( itemName, colId ) {
		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var done = false;
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
				alert( 'Edit conflict!' );
				location.reload();
			}
		} );
	};

	addSelf = function () {
		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var index, i, curUserTitle,
				itemToAdd = {};

			curUserTitle = mw.Title.newFromText(
				mw.config.get( 'wgUserName' ),
				2
			);
			if ( !curUserTitle ) {
				throw new Error( 'User is not valid title?' );
			}
			itemToAdd.title = curUserTitle.getPrefixedText();

			for ( i = 0; i < res.content.columns[ 0 ].items.length; i++ ) {
				// TODO: Title normalization maybe?
				if ( res.content.columns[ 0 ].items[ i ].title === itemToAdd.title ) {
					alert( mw.msg( 'collaborationkit-list-alreadyadded' ) );
					return;
				}
			}
			index = res.content.columns[ 0 ].items.length;
			res.content.columns[ 0 ].items[ index ] = itemToAdd;
			res.summary = mw.msg( 'collaborationkit-list-add-self-summary', itemToAdd.title );
			saveJson( res, function () {
				location.reload();
			} );
		} );

	};

	deleteItem = function ( $item ) {
		var cur,
			$spinner,
			title = $item.data( 'collabkit-item-title' ),
			colId = getColId( $item );

		$spinner = $.createSpinner( {
			size: 'small',
			type: 'inline'
		} );
		$item.find( '.jquery-confirmable-wrapper' )
			.empty()
			.append( $spinner );

		cur = getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var newItems = [];
			$.each( res.content.columns[ colId ].items, function ( index ) {
				if ( this.title === title ) {
					return;
				}
				newItems[ newItems.length ] = this;
			} );
			res.content.columns[ colId ].items = newItems;
			// Interface for extension defined tags lacking...
			// res.tags = 'collabkit-list-delete';
			// FIXME inContentLanguage???
			res.summary = mw.msg( 'collaborationkit-list-delete-summary', title );
			saveJson( res, function () {
				$item.remove();
				mw.notify(
					mw.msg( 'collaborationkit-list-delete-popup', title ),
					{
						tag: 'collabkit',
						title:  mw.msg( 'collaborationkit-list-delete-popup-title' ),
						type: 'info'
					}
				);

			} );
		} );
	};

	/**
	 * Helper function to get ordered list of all items in list
	 *
	 * @param {jQuery} $elm The list of items - $( '.mw-ck-list' )
	 * @return {Array} 2D array of all items in all columns.
	 */
	getListOfTitles = function ( $elm ) {
		var list = [];
		// FIXME must be changed for multilist.
		$elm.children( '.mw-ck-list-column' ).each( function () {
			var $this, colId;
			$this = $( this );
			colId = $this.data( 'collabkit-column-id' );
			list[ colId ] = [];
			$this.children( '.mw-ck-list-item' ).each( function () {
				list[ colId ][ list[ colId ].length ] = $( this ).data( 'collabkit-item-title' );
			} );
		} );
		return list;
	};

	/**
	 * If the order of the list changes, save back to page
	 *
	 * @param {jQuery} $item List item in question
	 * @param {Array} newOrder 2-D list of all items in new order
	 * @param {Array} originalOrder Original order of all items as 2-D list
	 */
	reorderList = function ( $item, newOrder, originalOrder ) {
		var $spinner = $.createSpinner( {
			size: 'small',
			type: 'inline'
		} );
		$item.find( '.mw-ck-list-title' ).append( $spinner );

		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var i,
				j,
				reorderedItem,
				findItemInResArray,
				resArray = [],
				isEditConflict = false;

			reorderedItem = $item.data( 'collabkit-item-title' );

			outer: for ( i = 0; i < originalOrder.length; i++ ) {
				if ( res.content.columns[ i ].items.length !== originalOrder[ i ].length ) {
					isEditConflict = true;
				} else {
					for ( j = 0; j < originalOrder[ i ].length; j++ ) {
						if ( res.content.columns[ i ].items[ j ].title !== originalOrder[ i ][ j ] ) {
							isEditConflict = true;
							break outer;
						}
					}
				}
			}

			if ( isEditConflict ) {
				// FIXME sane error handling.
				alert( 'Edit conflict detected. Rearrangement not saved.' );
				location.reload();
				throw new Error( 'Edit conflict' );
			}

			if ( newOrder.length !== originalOrder.length ) {
				// Should never happen
				mw.log( 'New order:' );
				mw.log( newOrder );
				mw.log( 'Old order:' );
				mw.log( originalOrder );
				throw new Error( 'Lost an item in the list?!' );
			}

			/**
			 * Find an item in the result array.
			 *
			 * Optimized to first look in the most likely spots.
			 * Assumes that titles must be unique in a list.
			 *
			 * @param {string} title Title of list item to find
			 * @param {int} indexGuess Where we think it might be
			 * @param {int} colGuess Which column we think its in
			 * @return {Object} The item object for the given title
			 */
			findItemInResArray = function ( title, indexGuess, colGuess ) {
				var oneLess,
					oneMore,
					i,
					j,
					resItems = res.content.columns[ colGuess ].items;

				indexGuess = indexGuess % resItems.length;

				if ( resItems[ indexGuess ].title === title ) {
					return resItems[ indexGuess ];
				}

				oneMore = ( indexGuess + 1 ) % resItems.length;
				oneLess = indexGuess - 1 < 0 ? resItems.length - 1 : indexGuess - 1;
				if ( resItems[ oneMore ].title === title ) {
					return resItems[ oneMore ];
				}

				if ( resItems[ oneLess ].title === title ) {
					return resItems[ oneLess ];
				}

				// Still here, check entire array.
				for ( i = 0; i < res.content.columns.length; i++ ) {
					for ( j = 0; j < res.content.columns[ i ].items.length; j++ ) {
						if ( res.content.columns[ i ].items[ j ].title === title ) {
							return res.content.columns[ i ].items[ j ];
						}
					}
				}

				// Must be missing.
				// FIXME sane error handling.
				alert( 'Edit conflict detected' );
				location.reload();
				throw new Error( 'Item ' + title + ' is missing' );
			};
			resArray = [];
			for ( i = 0; i < newOrder.length; i++ ) {
				resArray[ i ] = [];
				for ( j = 0; j < newOrder[ i ].length; j++ ) {
					resArray[ i ][ j ] = findItemInResArray( newOrder[ i ][ j ], j, i );
				}
			}
			for ( i = 0; i < resArray.length; i++ ) {
				res.content.columns[ i ].items = resArray[ i ];
			}

			res.summary = mw.msg( 'collaborationkit-list-move-summary', reorderedItem );
			saveJson( res, function () {
				$spinner.remove();
				mw.notify(
					mw.msg( 'collaborationkit-list-move-popup', reorderedItem ),
					{
						tag: 'collabkit',
						title: mw.msg( 'collaborationkit-list-move-popup-title' ),
						type: 'info'
					}
				);
			} );
		} );
	};

	getCurrentJson = function ( pageId, callback ) {
		var api = new mw.Api();

		api.get( {
			action: 'query',
			prop: 'revisions',
			pageids: pageId,
			rvprop: [ 'ids', 'content', 'timestamp' ]
		} ).done( function ( data ) {
			var rev,
				res = {};

			if ( !data.query ||
				!data.query.pages ||
				!data.query.pages[ pageId ] ||
				!data.query.pages[ pageId ].revisions ||
				!data.query.pages[ pageId ].revisions[ 0 ]
			) {
				mw.log( 'Could not get page ' + pageId );
				// FIXME better error handling
				alert( 'Unhandled error fetching page with ajax' );
				throw new Error( 'Could not get page' );
			}
			rev = data.query.pages[ pageId ].revisions[ 0 ];
			if ( rev.contentmodel !== 'CollaborationListContent' ) {
				throw new Error( 'Page not a list' );
			}
			res.revid = rev.revid;
			res.pageid = pageId;
			res.timestamp = rev.timestamp;
			res.content = JSON.parse( rev[ '*' ] );
			callback( res );
		} ).fail(
			function () { alert( 'Unhandled ajax error' ); }
		);
	};

	saveJson = function ( params, callback ) {
		var api = new mw.Api();

		// This will explode if we hit a captcha
		api.postWithEditToken( {
			action: 'edit',
			nocreate: true,
			contentmodel: 'CollaborationListContent',
			// tags: params.tags,
			// FIXME content language.
			summary: params.summary,
			pageid: params.pageid,
			text: JSON.stringify( params.content ),
			basetimestamp: params.timestamp
		} ).done( callback ).fail( function () {
			// FIXME proper error handling.
			alert( 'Unhandled error saving page' );
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
		var titleWidget, fileToUse, description, itemTitleObj;

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

		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
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
					alert( 'Edit conflict' );
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
			saveJson( res, function () {
				dialog.close(); // FIXME should we just leave open?
				location.reload();
			} );
		} );

	};

	$( function () {
		var $list, buttonMsg;

		if ( !mw.config.get( 'wgEnableCollaborationKitListEdit' ) ) {
			// This page is not a list, or user does not have edit rights.
			return;
		}

		$list = $( '.mw-ck-list' );
		$list.find( '.mw-ck-list-item' ).each( function () {
			var deleteButton,
				moveButton,
				editButton,
				$delWrapper,
				$moveWrapper,
				$editWrapper,
				colId,
				$item = $( this );

			colId = getColId( $item );
			deleteButton = new OO.ui.ButtonWidget( {
				framed: false,
				icon: 'remove',
				iconTitle: mw.msg( 'collaborationkit-list-delete' )
			} );

			// Icon instead of button to avoid conflict with jquery.ui
			moveButton = new OO.ui.IconWidget( {
				framed: false,
				icon: 'move',
				iconTitle: mw.msg( 'collaborationkit-list-move' )
			} );

			editButton = new OO.ui.ButtonWidget( {
				label: 'edit',
				framed: false
			} ).on( 'click', function () {
				modifyExistingItem( $item.data( 'collabkit-item-title' ), colId );
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
						deleteItem( $item );
					}
				} );

			$moveWrapper = $( '<div></div>' )
				.addClass( 'mw-ck-list-movebutton' )
				.addClass( 'mw-ck-list-button' )
				.append( moveButton.$element );

			$editWrapper = $( '<div></div>' )
				.addClass( 'mw-ck-list-editbutton' )
				.addClass( 'mw-ck-list-button' )
				.append( editButton.$element );

			$item.find( '.mw-ck-list-title' )
				.append( $delWrapper )
				.append( $moveWrapper )
				.append( $editWrapper );
		} );

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
					.data( 'startTitleList', getListOfTitles( $list ) );
			},
			stop: function ( e, ui ) {
				var oldListTitles, newListTitles, $target, i, j, changed, count;

				$target = $( e.target );
				$target.removeClass( 'mw-ck-dragging' );
				oldListTitles = $target.data( 'startTitleList' );
				newListTitles = getListOfTitles( $list );
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
					reorderList( ui.item, newListTitles, oldListTitles );
				}
			}
		} );

		buttonMsg = mw.config.get( 'wgCollaborationKitIsMemberList' ) ?
			'collaborationkit-list-add-user' :
			'collaborationkit-list-add';
		$list.after(
			$( '<div></div>' )
				// FIXME There is probably a way to add the class without
				// extra div.
				.addClass( 'mw-ck-list-additem' )
				.append(
					new OO.ui.ButtonWidget( {
						label: mw.msg( buttonMsg ),
						icon: 'add',
						flags: 'constructive'
					} ).on( 'click', addItem )
					.$element
				)
		);
		if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) &&
			!curUserIsInList()
		) {
			$list.before(
				$( '<div></div>' )
					.addClass( 'mw-ck-list-addself' )
					.append(
						new OO.ui.ButtonWidget( {
							label: mw.msg( 'collaborationkit-list-add-self' ),
							icon: 'add',
							flags: 'constructive'
						} ).on( 'click', addSelf )
						.$element
					)
			);
		}

	} );

} )( jQuery, mediaWiki, OO );
