'use strict';

(function ( d, $ ) {

	$( d ).ready( function () {

		$( '<a/>', { "class": "ab-item", "href": "#" } )
			.text( FaceMeIn.l10n.disable )
			.on( 'click', function ( e ) {
				e.preventDefault();

				// Post to remove any stored faces.
				$.ajax( {
					method:  'DELETE',
					headers: {
						'X-WP-Nonce': FaceMeIn.nonce
					},
					url:     FaceMeIn.endpoint,
					success: function () {
						$( '#wp-admin-bar-enable-facemein, #wp-admin-bar-disable-facemein' ).toggle();
					}
				} )
			} )
			.appendTo( $( '<li/>', { "id": "wp-admin-bar-disable-facemein", "style": "display:none", "aria-live": "polite" } )
				.appendTo( '#wp-admin-bar-user-actions' ) );

		$( '<a/>', { "class": "ab-item", "href": "#" } )
			.text( FaceMeIn.l10n.enable )
			.on( 'click', function ( e ) {
				e.preventDefault();

				$( this ).text( FaceMeIn.l10n.capture );

				// Add video for camera.
				$( '<video/>', {
					"id":    "facemein-video",
					"style": "position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;width:100%;cursor:pointer;"
				} )
					.appendTo( 'body' )
					.on( 'click', function () {
						var canvas = d.createElement( 'canvas' );
						canvas.width = $( this ).width() / 2;
						canvas.height = $( this ).height() / 2;

						var context = canvas.getContext( '2d' );
						context.drawImage( this, 0, 0, canvas.width, canvas.height );

						var dataURI = canvas.toDataURL( 'image/png' );

						//localStorage.setItem( 'facemein', dataURI );

						$.ajax( {
							url:     FaceMeIn.endpoint,
							method:  'POST',
							headers: {
								'X-WP-Nonce': FaceMeIn.nonce
							},
							data:    {
								image: dataURI
							},
							success: function ( data ) {
								if ( data.error ) {
									alert( data.message );
								}

								localStorage.setItem( 'facemein', data.stored_id );

								$( '#wp-admin-bar-enable-facemein a' ).text( FaceMeIn.l10n.enable );
								$( '#facemein-video' ).remove();
								$( '#wp-admin-bar-enable-facemein, #wp-admin-bar-disable-facemein' ).toggle();
							}
						} );
					} );

				// Get access to the camera!
				if ( navigator.mediaDevices && navigator.mediaDevices.getUserMedia ) {
					navigator.mediaDevices.getUserMedia( {
						video: {
							mandatory: {
								minWidth:  1280,
								minHeight: 720
							}
						}
					} ).then( function ( stream ) {
						$( '#facemein-video' ).get( 0 ).src = window.URL.createObjectURL( stream );
						$( '#facemein-video' ).on( 'click', function() {
							stream.getVideoTracks().forEach( function( track ) { track.stop() } );
						} ).get( 0 ).play();
					} );
				}
			} )
			.appendTo( $( '<li/>', {
					"id":        "wp-admin-bar-enable-facemein",
					"style":     "position:relative;overflow:hidden;",
					"aria-live": "polite"
				} )
				.appendTo( '#wp-admin-bar-user-actions' ) );

		if ( FaceMeIn.faces.length ) {
			$( '#wp-admin-bar-enable-facemein, #wp-admin-bar-disable-facemein' ).toggle();
		}

	} );

})( document, jQuery )
