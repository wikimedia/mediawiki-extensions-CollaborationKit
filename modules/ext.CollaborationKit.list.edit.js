( function ( $, mw ) {
	var deleteItem, getCurrentJson, saveJson, reorderList, getListOfTitles, getColId;

	/**
	 * Retrieves ID number of column
	 *
	 * @param {jQuery} $item
	 * @return {int}
	 */
	getColId = function ( $item ) {
		var col, id;

		col = $item.closest( '.mw-ck-list-column' );
		id = parseInt( col.data( 'collabkit-column-id' ), 10 );
		if ( col.length === 0 || !isFinite( id ) ) {
			throw new Error( 'Cannot find column' );
		}
		return id;
	};

	/**
	 * Deletes an item from the list
	 *
	 * @param {jQuery} $item
	 */
	deleteItem = function ( $item ) {
		var spinner,
			title = $item.data( 'collabkit-item-title' ),
			colId = getColId( $item );

		if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) ) {
			// Member lists' Column 1 is a pseudocolumn
			colId = 0;
		}

		spinner = $.createSpinner( {
			size: 'small',
			type: 'inline'
		} );
		$item.find( '.jquery-confirmable-wrapper' )
			.empty()
			.append( spinner );

		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
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
		var spinner = $.createSpinner( {
			size: 'small',
			type: 'inline'
		} );
		$item.find( '.mw-ck-list-title' ).append( spinner );

		getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
			var i,
				j,
				reorderedItem,
				findItemInResArray,
				resArray,
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

				indexGuess %= resItems.length;

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
				alert( mw.msg( 'collaborationkit-list-error-editconflict' ) );
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
				spinner.remove();
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

	/**
	 * Retrieves JSON form of the list content
	 *
	 * @param {int} pageId
	 * @param {Object} callback
	 */
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
				alert( mw.msg( 'collaborationkit-list-error-couldnotgetpage' ) );
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
			function () { alert( mw.msg( 'collaborationkit-list-error-generic' ) ); }
		);
	};

	/**
	 * Saves the JSON text to a CollaborationListContent page
	 *
	 * @param {Object} params
	 * @param {Object} callback
	 */
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
			alert( 'collaborationkit-list-error-saving' );
		} );
	};

	module.exports = {
		getColId: getColId,
		deleteItem: deleteItem,
		getListOfTitles: getListOfTitles,
		reorderList: reorderList,
		getCurrentJson: getCurrentJson,
		saveJson: saveJson
	};

} )( jQuery, mediaWiki );
