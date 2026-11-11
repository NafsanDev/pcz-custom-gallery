( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

        /* Gallery lightbox */
		var photos   = window.pczGalleryPhotos || [];
		var index    = 0;
		var box      = document.getElementById( 'gLightbox' );
		var boxImg   = document.getElementById( 'gLightboxImg' );
		var counter  = document.getElementById( 'gLightboxCounter' );

		function render() {
			if ( ! boxImg || ! photos.length ) { return; }
			boxImg.src = photos[ index ];
			if ( counter ) { counter.textContent = ( index + 1 ) + ' / ' + photos.length; }
		}
		function open( i ) {
			index = i;
			render();
			if ( box ) { box.classList.add( 'active' ); }
			document.body.style.overflow = 'hidden';
		}
		function close() {
			if ( box ) { box.classList.remove( 'active' ); }
			document.body.style.overflow = '';
		}
		function step( dir ) {
			if ( ! photos.length ) { return; }
			index = ( index + dir + photos.length ) % photos.length;
			render();
		}

		document.querySelectorAll( '.pcz-gallery-item' ).forEach( function ( el ) {
			var i = parseInt( el.getAttribute( 'data-index' ), 10 ) || 0;
			el.addEventListener( 'click', function () { open( i ); } );
			el.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); open( i ); }
			} );
		} );

		if ( box ) {
			box.addEventListener( 'click', function ( e ) { if ( e.target === box ) { close(); } } );
		}
		var btnClose = document.getElementById( 'gLightboxClose' );
		var btnPrev  = document.getElementById( 'gLightboxPrev' );
		var btnNext  = document.getElementById( 'gLightboxNext' );
		if ( btnClose ) { btnClose.addEventListener( 'click', close ); }
		if ( btnPrev ) { btnPrev.addEventListener( 'click', function ( e ) { e.stopPropagation(); step( -1 ); } ); }
		if ( btnNext ) { btnNext.addEventListener( 'click', function ( e ) { e.stopPropagation(); step( 1 ); } ); }

		document.addEventListener( 'keydown', function ( e ) {
			if ( ! box || ! box.classList.contains( 'active' ) ) { return; }
			if ( e.key === 'Escape' ) { close(); }
			if ( e.key === 'ArrowLeft' ) { step( -1 ); }
			if ( e.key === 'ArrowRight' ) { step( 1 ); }
		} );
    
    } );
} )();