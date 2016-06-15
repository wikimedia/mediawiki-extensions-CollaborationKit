( function ( $, mw, OO ) {
	$( function () {
		var $list, deleteItem, getCurrentJson, saveJson, addItem;

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
			var $item = $( this );

			$item.find( '.mw-collabkit-list-title' ).append(
				$( '<a></a>' )
					.attr( {
						'class': 'mw-collabkit-list-delete mw-collabkit-list-deletebutton',
						href: '#'
					} ).click( function ( e ) {
						deleteItem( $item );
						e.preventDefault();
					} ).text( 'X' )
			);
		} );

		addItem = function () {
			// throw away UI
			var title = prompt( 'Name of page you want to add to list' );
			if ( title === null ) {
				return;
			}

			getCurrentJson( mw.config.get( 'wgArticleId' ), function ( res ) {
				res.content.items[ res.content.items.length ] = {
					title: title,
					notes: ''
				};
				res.summary = mw.msg( 'collabkit-list-add-summary', title );
				saveJson( res, function () { location.reload(); } );
			} );
		};

		$list.after(
			$( '<div></div>' )
				// FIXME There is probably a way to add the class without
				// extra div.
				.addClass( 'mw-collabkit-list-additem' )
				.append(
					new OO.ui.ButtonWidget( {
						label: mw.msg( 'collaborationkit-list-add-button' ),
						icon: 'add',
						flags: 'constructive'
					} ).on( 'click', addItem )
					.$element
				)
		);

		deleteItem = function ( $item ) {
			var cur,
				$spinner,
				title = $item.data( 'collabkit-item-title' );

			$spinner = $.createSpinner( {
				size: 'small',
				type: 'inline'
			} );
			$item.find( '.mw-collabkit-list-deletebutton' )
				.empty()
				.removeClass( 'mw-collabkit-list-delete' )
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
				// fixme inContentLanguage???
				res.summary = mw.msg( 'collabkit-list-delete-summary', title );
				saveJson( res, function () {
					$item.remove();
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
					throw new Error( 'Could not get page' );
				}
				rev = data.query.pages[ pageId ].revisions[ 0 ];
				if ( rev.contentmodel !== 'CollaborationListContent' ) {
					throw new Error( 'Page not a list' );
				}
				res.revid = rev.revid;
				res.pageid = rev.pageid;
				res.timestamp = rev.timestamp;
				res.content = JSON.parse( rev[ '*' ] );
				callback( res );
			} );
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
			} ).done( callback );
		};
	} );
} )( jQuery, mediaWiki, OO );
