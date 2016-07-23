( function ( $, mw, OO ) {
	var deleteItem, getCurrentJson, saveJson, addItem, reorderList, getListOfTitles, modifyItem, modifyExistingItem;

	addItem = function () {
		modifyItem( {} );
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

	modifyExistingItem = function ( itemName ) {
		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var done = false;
			$.each( res.content.items, function ( index ) {
				if ( this.title === itemName ) {
					done = true;
					modifyItem( {
						itemTitle: this.title,
						itemImage: this.image,
						itemDescription: this.notes,
						itemIndex: index
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

	deleteItem = function ( $item ) {
		var cur,
			$spinner,
			title = $item.data( 'collabkit-item-title' );

		$spinner = $.createSpinner( {
			size: 'small',
			type: 'inline'
		} );
		$item.find( '.jquery-confirmable-wrapper' )
			.empty()
			.append( $spinner );

		cur = getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var newItems = [];
			$.each( res.content.items, function ( index ) {
				if ( this.title === title ) {
					return;
				}
				newItems[ newItems.length ] = this;
			} );
			res.content.items = newItems;
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
	 * @param {jQuery} $elm The list of items - $( '.mw-collabkit-list' )
	 * @return {Array}
	 */
	getListOfTitles = function ( $elm ) {
		var list = [];
		$elm.children( '.mw-collabkit-list-item' ).each( function () {
			list[ list.length ] = $( this ).data( 'collabkit-item-title' );
		} );
		return list;
	};

	/**
	 * If the order of the list changes, save back to page
	 */
	reorderList = function ( $item, newOrder, originalOrder ) {
		var $spinner = $.createSpinner( {
			size: 'small',
			type: 'inline'
		} );
		$item.find( '.mw-collabkit-list-title' ).append( $spinner );

		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var i,
				reorderedItem,
				findItemsInResArray,
				resArray = [],
				isEditConflict = false;

			reorderedItem = $item.data( 'collabkit-item-title' );

			if ( res.content.items.length !== originalOrder.length ) {
				isEditConflict = true;
			} else {
				for ( i = 0; i < originalOrder.length; i++ ) {
					if ( res.content.items[ i ].title !== originalOrder[ i ] ) {
						isEditConflict = true;
						break;
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
			 * Optimized to first look in the most likely spots
			 */
			findItemInResArray = function ( title, indexGuess ) {
				var oneLess,
					oneMore,
					i,
					resItems = res.content.items;

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
				for ( i = 0; i < resItems.length; i++ ) {
					if ( resItems[ i ].title === title ) {
						return resItems[ i ];
					}
				}

				// Must be missing.
				// FIXME sane error handling.
				alert( 'Edit conflict detected' );
				location.reload();
				throw new Error( 'Item ' + title + ' is missing' );
			};
			for ( i = 0; i < newOrder.length; i++ ) {
				resArray[ resArray.length ] = findItemInResArray( newOrder[ i ], i );
			}

			res.content.items = resArray;
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
		var titleWidget, fileToUse, description;

		NewItemDialog.parent.prototype.initialize.apply( this, arguments );
		this.panel1 = new OO.ui.PanelLayout( { padded: true, expanded: false } );

		this.titleWidget = new mw.widgets.TitleInputWidget( {
			label: mw.msg( 'collaborationkit-list-newitem-page' ),
			validateTitle: false, // we want people to be able to put anything.
			showRedlink: true
			// Maybe should also showDescriptions and showImages
		} );
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

		if ( this.itemTitle ) {
			this.titleWidget.setValue( this.itemTitle );
			if ( this.itemDescription ) {
				this.description.setValue( this.itemDescription );
			}
			if ( this.itemImage ) {
				this.fileToUse.setValue( this.itemImage );
			}
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
			dialog = this;

		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var itemToAdd = {
				title: title,
				notes: notes
			},
				index;
			if ( dialog.itemIndex ) {
				if (	res.content.items <= dialog.itemIndex ||
					res.content.items[ dialog.itemIndex ].title !== dialog.itemTitle
				) {
					alert( 'Edit conflict' );
					location.reload();
					// fixme proper handling.
					throw new Error( 'edit conflict' );
				}
				index = dialog.itemIndex;
			} else {
				index = res.content.items.length;
			}
			res.content.items[ index ] = {
				title: title,
				notes: notes
			};
			if ( file !== '' ) {
				itemToAdd.image = file;
			}
			res.summary = mw.msg( 'collaborationkit-list-add-summary', title );
			saveJson( res, function () {
				dialog.close(); // FIXME should we just leave open?
				location.reload();
			} );
		} );

	};

	$( function () {
		var $list;

		if ( !mw.config.get( 'wgEnableCollaborationKitListEdit' ) ) {
			// This page is not a list, or user does not have edit rights.
			return;
		}

		$list = $( '.mw-collabkit-list' );
		if ( $list.length > 1 ) {
			mw.log( 'Wrong number of mw-collabkit-list??' );
			return;
		}

		$list.find( '.mw-collabkit-list-item' ).each( function () {
			var deleteButton,
				moveButton,
				editButton,
				$delWrapper,
				$moveWrapper,
				$editWrapper,
				$item = $( this );

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
				modifyExistingItem( $item.data( 'collabkit-item-title' ) );
			} );

			// FIXME, the <a> might make an extra target when tabbing
			// through the document (Maybe also messing up screen readers).
			// not sure. Its used so that jquery.confirmable makes a link.
			$delWrapper = $( '<a></a>' )
				.attr( 'href', '#' )
				.click( function ( e ) { e.preventDefault(); } )
				.addClass( 'mw-collabkit-list-deletebutton' )
				.addClass( 'mw-collabkit-list-button' )
				.append( deleteButton.$element )
				.confirmable( {
					handler: function () {
						deleteItem( $item );
					}
				} );

			$moveWrapper = $( '<div></div>' )
				.addClass( 'mw-collabkit-list-movebutton' )
				.addClass( 'mw-collabkit-list-button' )
				.append( moveButton.$element );

			$editWrapper = $( '<div></div>' )
				.addClass( 'mw-collabkit-list-editbutton' )
				.addClass( 'mw-collabkit-list-button' )
				.append( editButton.$element );

			$item.find( '.mw-collabkit-list-title' )
				.append( $delWrapper )
				.append( $moveWrapper )
				.append( $editWrapper );
		} );

		$list.sortable( {
			placeholder: 'mw-collabkit-list-dragplaceholder',
			axis: 'y',
			forcePlaceholderSize: true,
			handle: '.mw-collabkit-list-movebutton',
			opacity: 0.6,
			scroll: true,
			cursor: 'grabbing', // Also overriden in CSS
			start: function ( e ) {
				$( e.target )
					.addClass( 'mw-collabkit-dragging' )
					.data( 'startTitleList', getListOfTitles( $list ) );
			},
			stop: function ( e, ui ) {
				var oldListTitles, newListTitles, $target, i, changed;

				$target = $( e.target );
				$target.removeClass( 'mw-collabkit-dragging' );
				oldListTitles = $target.data( 'startTitleList' );
				newListTitles = getListOfTitles( $list );
				$target.data( 'startTitleList', null );
				// FIXME better error handling
				if ( oldListTitles.length !== newListTitles.length ) {
					throw new Error( 'We somehow lost an item?!' );
				}

				changed = false;
				for ( i = 0; i < oldListTitles.length; i++ ) {
					if ( oldListTitles[ i ] !== newListTitles[ i ] ) {
						changed = true;
						break;
					}
				}
				if ( changed ) {
					reorderList( ui.item, newListTitles, oldListTitles );
				}
			}
		} );

		$list.after(
			$( '<div></div>' )
				// FIXME There is probably a way to add the class without
				// extra div.
				.addClass( 'mw-collabkit-list-additem' )
				.append(
					new OO.ui.ButtonWidget( {
						label: mw.msg( 'collaborationkit-list-add' ),
						icon: 'add',
						flags: 'constructive'
					} ).on( 'click', addItem )
					.$element
				)
		);

	} );

} )( jQuery, mediaWiki, OO );
