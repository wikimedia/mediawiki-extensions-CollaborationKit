/**
 * @param $
 * @param mw
 * @param OO
 * @class ext.CollaborationKit.list.members
 */
( function ( $, mw, OO ) {
	'use strict';

	var addSelf, curUserIsInList, LE;

	LE = require( 'ext.CollaborationKit.list.edit' );

	/**
	 * Find if the current user is already is in list.
	 *
	 * @param {number} destinationPage The Page ID of the list if not the current page.
	 * @return {boolean}
	 */
	curUserIsInList = function ( destinationPage ) {
		var titleObj, escapedText, query, currentUser, i;

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
					var newMemberList = data.query.pages[ destinationPage ].revisions[ 0 ][ '*' ];
					newMemberList = JSON.parse( newMemberList ).columns[ 0 ].items;
					for ( i = 0; i < newMemberList.length; i++ ) {
						if ( newMemberList[ i ].title === escapedText ) {
							$( '.mw-ck-members-join' ).css( 'display', 'none' );
						}
					}
				} );
		}
	};

	/**
	 * One-click project-joining button
	 *
	 * @param {number} destinationPage Page ID of member list, if different from current page
	 */
	addSelf = function ( destinationPage ) {
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
				window.location.href = mw.config.get( 'wgScriptPath' ) + '?curid=' + destinationPage;
			} );
		} );

	};

	$( function () {
		var memberListPage, memberListUrl, $list;
		// Workflow assumes existence of username, so we filter against it
		// However, since !curUserIsInList, the button will still render. It will just use no-JS
		// behavior instead.
		if ( mw.config.get( 'wgCollaborationKitAssociatedMemberList' ) && !mw.user.isAnon() ) {
			memberListPage = mw.config.get( 'wgCollaborationKitAssociatedMemberList' );
			curUserIsInList( memberListPage ); // removes Join button if user already is member

			memberListUrl = mw.config.get( 'wgScriptPath' ) + '?curid=' + memberListPage;

			$( '.mw-ck-members-join a' )
				.attr( 'href', memberListUrl );

			$( '.mw-ck-members-join' ).on( 'click', function () {
				event.preventDefault();
				addSelf( memberListPage );
			} );
		}

		if ( mw.config.get( 'wgCollaborationKitIsMemberList' ) &&
			!curUserIsInList() && !mw.user.isAnon() // Workflow assumes existence of username
		) {
			$list = $( '.mw-ck-list' );
			$list.before(
				$( '<div></div>' )
					.addClass( 'mw-ck-list-addself' )
					.append(
						new OO.ui.ButtonWidget( {
							label: mw.msg( 'collaborationkit-list-add-self' ),
							icon: 'add',
							flags: [ 'progressive', 'primary' ]
						} ).on( 'click', addSelf )
							.$element
					)
			);
		}
	} );

} )( jQuery, mediaWiki, OO );
