'use strict';

jQuery( function ( $ ) {
	$( '#secret-form' ).on( 'submit', function () {
		var $form = $( this );
		var $secretInput = $form.find( 'input[name=zenpost-auth-secret]' );

		if ( confirm( 'Doing this may cause issues authenticating with Zenpost. Continue?' )) {
			generateSecret( function ( newSecret ) {
				setNewSecret( $form, newSecret, function () {
					$secretInput.val( newSecret );
				} );
			} );
		}

		return false;
	} );

	function generateSecret( callback ) {
		$.get( '/wp-json/zenpost/new-secret', function ( response ) {
			if ( response.success ) {
				callback( response.secret );
			} else {
				console.log( 'Could not get new secret: ' + response );
			}
		}, 'json' );
	}

	function setNewSecret( $form, newSecret, callback ) {
		var data = getPostParams( $form, newSecret );
		$.post( 'options.php', data, function ( response, status ) {
			if ( 'success' === status ) {
				callback( newSecret );
			} else {
				console.log( 'Could not set secret: ' + status );
			}
		} );
	}

	function getPostParams( $form, newSecret ) {
		var params = $form.serializeObject();
		params['zenpost-auth-secret'] = newSecret;
		return Object.keys( params ).map( function ( k ) {
			return [k, params[k]].join( '=' );
		} ).join( '&' );
	}

	$.fn.serializeObject = function () {
		return this.serialize().split( /&/ ).reduce( function ( acc, p ) {
			var kv = p.split( /=/ );
			acc[kv[0]] = kv[1];
			return acc;
		}, {} );
	}
} );
