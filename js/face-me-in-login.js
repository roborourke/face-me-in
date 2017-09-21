'use strict';

(function ( d, $ ) {

	$( d ).ready( function () {

		$( '<a/>', { "class": "button button-secondary", "href": "#" } )
			.text( FaceMeIn.l10n.login )
			.on( 'click', function ( e ) {
				e.preventDefault();

				$( this ).text( FaceMeIn.l10n.capture );

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
						var $video, video, canvas, context, attempts = 5, timer, fetch;

						// Add video for camera.
						$( '<video/>', {
							"id":    "facemein-video",
							"style": "position:fixed;top:0;left:0;right:0;bottom:0;z-index:10000;width:100%;cursor:pointer;",
							"title": FaceMeIn.l10n.cancel
						} )
							.on( 'click', function() {
								clearInterval( timer );
								$video.remove();
							} )
							.appendTo( 'body' );

						$video = $( '#facemein-video' );
						video  = $video.get( 0 );

						video.src = window.URL.createObjectURL( stream );
						video.play();

						canvas = d.createElement( 'canvas' );
						canvas.width = $video.width();
						canvas.height = $video.height();

						context = canvas.getContext( '2d' );

						fetch = function () {
							if ( ! attempts ) {
								clearInterval( timer );
								return;
							}

							context.drawImage( video, 0, 0, canvas.width, canvas.height );

							var dataURI = canvas.toDataURL( 'image/png' );

							$.ajax( {
								url:     FaceMeIn.endpoint,
								method:  'POST',
								data:    {
									stored:    localStorage.getItem( 'facemein' ),
									challenge: dataURI
								},
								success: function ( data ) {
									clearInterval( timer );

									var redirect = location.search.match( /redirect_to=([^&]+)/ )

									if ( redirect ) {
										window.location.href = decodeURIComponent( redirect );
										return;
									}

									window.location.href = data.redirect;
								},
								error:   function ( xhr ) {
									var data = JSON.parse( xhr.responseText );
									if ( data && data.message === 'AUTHENTICATION_ERROR' ) {
										clearInterval( timer );
										$video.remove();
									}
								}
							} );

							attempts--;
						}

						// Fire one immediately.
						fetch();

						// Capture every 4 secs for 20 seconds.
						timer = setInterval( fetch, 4000 );
					} );
				}
			} )
			.appendTo( $( '<p/>', { "style": "margin-bottom:15px;" } )
				.prependTo( '#loginform' ) );

	} );

})( document, jQuery )
