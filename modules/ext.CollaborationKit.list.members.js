( function ( $, mw, OO ) {

	var LE = require( 'ext.CollaborationKit.list.edit' );

	/**
	 * Find if the current user is already is in list.
	 *
	 * @param {int} destinationPage The Page ID of the list if not the current page.
	 * @return {boolean}
	 */
	curUserIsInList = function ( destinationPage ) {
		var titleObj, escapedText, currentUser, wrapper;
		currentUser = mw.config.get( 'wgUserName' );
		if ( !currentUser ) {
			return false;
		}
		titleObj = mw.Title.newFromText( currentUser, 2 );
		escapedText = titleObj.getPrefixedText();
		escapedText = escapedText.replace( /\\/g, '\\\\' );
		escapedText = escapedText.replace( /"/g, '\\"' );
		if ( destinationPage === undefined ) {
			query = '.mw-ck-list-item[data-collabkit-item-title="' +
				escapedText + '"]';
			return $( query ).length > 0;
		} else {
			new mw.Api().get( {
				action: 'query',
				pageids: destinationPage,
				prop: 'revisions',
				rvprop: 'content'
			} )
				.done( function ( data ) {
					newMemberList = data.query.pages[ destinationPage ].revisions[ 0 ][ '*' ];
					newMemberList = JSON.parse( newMemberList ).columns[ 0 ].items;
					for ( i = 0; i < newMemberList.length; i++ ) {
						if ( newMemberList[ i ].title == escapedText ) {
							$( '.mw-ck-members-join' ).css( 'display', 'none' );
						}
					}
				}
			);
		}
	};

	/**
	 * One-click project-joining button
	 *
	 * @param {int} destinationPage Page ID of member list, if different from current page
	 * @param {string} destinationUrl Full URL of member list, if different from current page
	 */
	addSelf = function ( destinationPage, destinationUrl ) {
		if ( destinationPage === undefined ) {
			destinationPage = mw.config.get( 'wgArticleId' );
		}

		LE.getCurrentJson( destinationPage, function ( res ) {
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
			LE.saveJson( res, function () {
				if ( destinationUrl === undefined ) {
					location.reload();
				} else {
					window.location = destinationUrl;
				}
			} );
		} );

	};

	$( function () {
		if ( mw.config.get( 'wgCollaborationKitAssociatedMemberList' ) ) {
			memberListPage = mw.config.get( 'wgCollaborationKitAssociatedMemberList' );
			curUserIsInList( memberListPage ); // removes Join button if user already is member
			new mw.Api().get( {
				action: 'query',
				prop: 'info',
				inprop: 'url',
				pageids: memberListPage
			} ).done( function ( data ) {
				memberListUrl = data.query.pages[ memberListPage ].fullurl;
				$( '.mw-ck-members-join a' )
					.attr( 'href', memberListUrl );

				$( '.mw-ck-members-join' ).on( 'click', function () {
					addSelf( memberListPage, memberListUrl );
				} );
			} );
		}

		if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) &&
			!curUserIsInList()
		) {
			$list = $( '.mw-ck-list' );
			$list.before(
				$( '<div></div>' )
					.addClass( 'mw-ck-list-addself' )
					.append(
						new OO.ui.ButtonWidget( {
							label: mw.msg( 'collaborationkit-list-add-self' ),
							icon: 'add',
							flags: [ 'constructive', 'primary' ]
						} ).on( 'click', addSelf )
						.$element
					)
			);
		}
	} );

} )( jQuery, mediaWiki, OO );
