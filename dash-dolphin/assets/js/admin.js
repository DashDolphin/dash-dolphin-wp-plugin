/* global dashDolphinAdmin */
/**
 * Dash Dolphin admin scripts.
 *
 * Wires up copy-to-clipboard for the Connections tab. The clicked button
 * briefly turns green and shows "Copied" so the site owner has a clear
 * confirmation before pasting the forwarding address into their form
 * plugin's BCC field.
 *
 * @package DashDolphin
 * @since   0.1.0
 */
( function () {
	'use strict';

	var COPY_RESET_MS = 1600;

	function copyText( text ) {
		// Modern path.
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback: hidden textarea + execCommand.
		return new Promise( function ( resolve, reject ) {
			try {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'absolute';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				resolve();
			} catch ( err ) {
				reject( err );
			}
		} );
	}

	function handleCopyClick( ev ) {
		var btn = ev.target.closest( '.dd-copy' );
		if ( ! btn ) {
			return;
		}
		ev.preventDefault();
		var text = btn.getAttribute( 'data-dd-copy' );
		if ( ! text ) {
			return;
		}
		var originalLabel = btn.textContent;
		copyText( text ).then(
			function () {
				btn.classList.add( 'is-copied' );
				btn.textContent = 'Copied';
				window.setTimeout( function () {
					btn.classList.remove( 'is-copied' );
					btn.textContent = originalLabel;
				}, COPY_RESET_MS );
			},
			function () {
				btn.textContent = 'Copy failed';
				window.setTimeout( function () {
					btn.textContent = originalLabel;
				}, COPY_RESET_MS );
			}
		);
	}

	document.addEventListener( 'click', handleCopyClick );
} )();
