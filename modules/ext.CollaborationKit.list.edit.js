/**
 * @param $
 * @param mw
 * @class ext.CollaborationKit.list.edit
 */
( function ( $, mw ) {
	'use strict';

	var deleteItem, getCurrentJson, saveJson, reorderList, getListOfItems, getColId, renumberElements;

	/**
	 * Retrieves ID number of column
	 *
	 * @param {jQuery} $item
	 * @return {number}
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
	 * Renumbers the item IDs within a column following a re-ordering or a deletion
	 *
	 * @param {number} colId the column in which to re-number the items
	 */
	renumberElements = function ( colId ) {
		$( '.mw-ck-list-column[ data-collabkit-column-id=' + colId + ' ] .mw-ck-list-item' )
			.each( function ( index ) {
				$( this ).data( 'collabkit-item-id', colId + '-' + index );
			} );
	};

	/**
	 * Deletes an item from the list
	 *
	 * @param {jQuery} $item
	 */
	deleteItem = function ( $item ) {
		var i,
			oldItems,
			spinner,
			title = $item.data( 'collabkit-item-title' ),
			itemId = $item.data( 'collabkit-item-id' ),
			uid = $item.data( 'collabkit-item-uid' ),
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
			oldItems = res.content.columns[ colId ].items;
			for ( i = 0; i < oldItems.length; i++ ) {
				if ( oldItems[ i ].uid === uid ) {
					continue;
				}
				newItems[ newItems.length ] = oldItems[ i ];
			}
			res.content.columns[ colId ].items = newItems;
			// Interface for extension defined tags lacking...
			// res.tags = 'collabkit-list-delete';
			// FIXME inContentLanguage???
			res.summary = mw.msg( 'collaborationkit-list-delete-summary', title );
			saveJson( res, function () {
				$item.remove();
				renumberElements( colId );
				mw.notify(
					mw.msg( 'collaborationkit-list-delete-popup', title ),
					{
						tag: 'collabkit',
						title: mw.msg( 'collaborationkit-list-delete-popup-title' ),
						type: 'info'
					}
				);
			} );
		} );
	};

	/**
	 * Helper function to get ordered list of all items in list
	 *
	 * @param {jQuery} $elm The list of items
	 * @return {Array} 2D array of all items in all columns.
	 */
	getListOfItems = function ( $elm ) {
		var list = [],
			relevantItem;
		$elm.children( '.mw-ck-list-column' ).each( function () {
			var $this, colId;
			$this = $( this );
			colId = $this.data( 'collabkit-column-id' );
			list[ colId ] = [];
			$this.children( '.mw-ck-list-item' ).each( function () {
				relevantItem = $( this ).data( 'collabkit-item-id' );
				if ( relevantItem ) {
					list[ colId ][ list[ colId ].length ] = relevantItem;
				}
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
				moveFrom,
				moveTo,
				movingItem,
				newPosition,
				oldPosition = $item.data( 'collabkit-item-id' ),
				reorderedItem,
				resColumns,
				isEditConflict = false;

			reorderedItem = $item.data( 'collabkit-item-title' );

			// Edit conflict detection
			outer: for ( i = 0; i < originalOrder.length; i++ ) {
				if ( res.content.columns[ i ].items.length !== originalOrder[ i ].length ) {
					isEditConflict = true;
				} else {
					for ( j = 0; j < originalOrder[ i ].length; j++ ) {
						if ( i + '-' + j !== originalOrder[ i ][ j ] ) {
							isEditConflict = true;
							break outer;
						}
					}
				}
			}

			if ( isEditConflict ) {
				// FIXME sane error handling.
				alert( mw.msg( 'collaborationkit-list-error-editconflict' ) );
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

			outer2: for ( i = 0; i < newOrder.length; i++ ) {
				for ( j = 0; j < newOrder[ i ].length; j++ ) {
					if ( newOrder[ i ][ j ] === oldPosition ) {
						newPosition = i + '-' + j;
						break outer2;
					}
				}
			}

			resColumns = [];
			for ( i = 0; i < res.content.columns.length; i++ ) {
				resColumns[ i ] = res.content.columns[ i ].items;
			}

			moveFrom = oldPosition.split( '-' ); // 0 = column; 1 = item
			moveTo = newPosition.split( '-' ); // 0 = column; 1 = item

			movingItem = resColumns[ moveFrom[ 0 ] ][ moveFrom[ 1 ] ];
			resColumns[ moveFrom[ 0 ] ].splice( moveFrom[ 1 ], 1 );
			resColumns[ moveTo[ 0 ] ].splice( moveTo[ 1 ], 0, movingItem );

			for ( i = 0; i < res.content.columns.length; i++ ) {
				res.content.columns[ i ].items = resColumns[ i ];
			}

			res.summary = mw.msg( 'collaborationkit-list-move-summary', reorderedItem );
			saveJson( res, function () {
				spinner.remove();
				renumberElements( moveFrom[ 0 ] );
				renumberElements( moveTo[ 0 ] );
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
	 * @param {number} pageId
	 * @param {Object} callback
	 */
	getCurrentJson = function ( pageId, callback ) {
		var api = new mw.Api(),
			i,
			j,
			uidCounter = 0;

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

			// Assigning UID to each list entry
			for ( i = 0; i < res.content.columns.length; i++ ) {
				for ( j = 0; j < res.content.columns[ i ].items.length; j++ ) {
					res.content.columns[ i ].items[ j ].uid = uidCounter;
					uidCounter++;
				}
			}

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
		var api = new mw.Api(),
			i,
			j,
			lastRevTS,
			baseTimestamp = params.timestamp;

		// Strip out UID; we don't want to save it.
		for ( i = 0; i < params.content.columns.length; i++ ) {
			for ( j = 0; j < params.content.columns[ i ].items.length; j++ ) {
				delete params.content.columns[ i ].items[ j ].uid;
			}
		}

		// Since we depend on things in the DOM, make our base timestamp
		// for edit conflict the earlier of the last edit + 1 second and
		// the time data was fetched.
		lastRevTS = mw.config.get( 'wgCollabkitLastEdit' );
		if ( lastRevTS ) {
			lastRevTS += 1; // 1 second after last rev timestamp
			baseTimestamp = Math.min( lastRevTS, +( params.timestamp.replace( /\D/g, '' ) ) );
		}
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
			basetimestamp: baseTimestamp
		} ).done( callback ).fail( function () {
			// FIXME proper error handling.
			alert( mw.msg( 'collaborationkit-list-error-saving' ) );
		} );
	};

	module.exports = {
		getColId: getColId,
		deleteItem: deleteItem,
		getListOfItems: getListOfItems,
		reorderList: reorderList,
		getCurrentJson: getCurrentJson,
		saveJson: saveJson,
		renumberElements: renumberElements
	};

} )( jQuery, mediaWiki );
