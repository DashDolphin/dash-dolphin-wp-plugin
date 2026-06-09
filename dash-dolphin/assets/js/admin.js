/* global dashDolphinAdmin */
/**
 * Dash Dolphin admin scripts.
 *
 * Two responsibilities:
 *
 *   1. Copy-to-clipboard for the Connections tab. The clicked button
 *      briefly turns green and shows "Copied" so the site owner has a
 *      clear confirmation before pasting the forwarding address into
 *      their form plugin's BCC field.
 *   2. Outbound-link preflight (v0.3.0+). Any link rendered through
 *      render_dashboard_link() carries data-dd-dashboard-link="1" and a
 *      data-dd-account-name attribute. On click, we surface a one-time
 *      interstitial that names the account the link will open in, so
 *      users who maintain multiple Dash Dolphin accounts can cancel
 *      before being dropped into the wrong context. We remember the
 *      acknowledgement in sessionStorage so we don't nag.
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

	// -----------------------------------------------------------------------
	// Outbound dashboard-link preflight modal
	// -----------------------------------------------------------------------

	var ACK_KEY = 'ddDashLinkAck';

	function hasAcked() {
		try {
			return window.sessionStorage.getItem( ACK_KEY ) === '1';
		} catch ( _ ) {
			return false;
		}
	}

	function setAcked() {
		try {
			window.sessionStorage.setItem( ACK_KEY, '1' );
		} catch ( _ ) {
			/* sessionStorage may be unavailable in private modes; degrade quietly */
		}
	}

	function buildModal( opts ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'dd-modal-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-labelledby', 'dd-modal-title' );

		var modal = document.createElement( 'div' );
		modal.className = 'dd-modal';

		var title = document.createElement( 'h2' );
		title.id = 'dd-modal-title';
		title.className = 'dd-modal-title';
		title.textContent = 'Open in Dash Dolphin';
		modal.appendChild( title );

		var body = document.createElement( 'p' );
		body.className = 'dd-modal-body';
		if ( opts.account ) {
			body.appendChild( document.createTextNode( 'This link opens in the ' ) );
			var acc = document.createElement( 'span' );
			acc.className = 'dd-modal-account';
			acc.textContent = opts.account;
			body.appendChild( acc );
			body.appendChild( document.createTextNode( ' account on app.dashdolphin.com. If you are signed in to a different Dash Dolphin account in this browser, the dashboard will prompt you to switch.' ) );
		} else {
			body.textContent = 'This link opens app.dashdolphin.com in a new tab. If you are signed in to a different Dash Dolphin account, the dashboard will prompt you to switch.';
		}
		modal.appendChild( body );

		var actions = document.createElement( 'div' );
		actions.className = 'dd-modal-actions';

		var cancelBtn = document.createElement( 'button' );
		cancelBtn.type = 'button';
		cancelBtn.className = 'button';
		cancelBtn.textContent = 'Cancel';
		actions.appendChild( cancelBtn );

		var openBtn = document.createElement( 'button' );
		openBtn.type = 'button';
		openBtn.className = 'button button-primary';
		openBtn.textContent = 'Open dashboard';
		actions.appendChild( openBtn );

		modal.appendChild( actions );
		overlay.appendChild( modal );

		function close() {
			if ( overlay.parentNode ) {
				overlay.parentNode.removeChild( overlay );
			}
			document.removeEventListener( 'keydown', onKey );
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				close();
			}
		}

		cancelBtn.addEventListener( 'click', close );
		overlay.addEventListener( 'click', function ( e ) {
			if ( e.target === overlay ) {
				close();
			}
		} );
		openBtn.addEventListener( 'click', function () {
			setAcked();
			close();
			window.open( opts.href, '_blank', 'noopener' );
		} );
		document.addEventListener( 'keydown', onKey );

		document.body.appendChild( overlay );
		// Focus the primary action so Enter confirms.
		window.setTimeout( function () { openBtn.focus(); }, 0 );
	}

	function handleDashboardLinkClick( ev ) {
		var link = ev.target.closest( 'a[data-dd-dashboard-link="1"]' );
		if ( ! link ) {
			return;
		}
		// If the user has already confirmed this session, let the link work as
		// a normal new-tab open without interruption.
		if ( hasAcked() ) {
			return;
		}
		// Honour modifier-key behavior (ctrl/cmd/shift/middle-click open in
		// background tab/window) so power users aren't slowed down.
		if ( ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button > 0 ) {
			return;
		}
		ev.preventDefault();
		buildModal( {
			href:    link.getAttribute( 'href' ),
			account: link.getAttribute( 'data-dd-account-name' ) || ''
		} );
	}

	document.addEventListener( 'click', handleDashboardLinkClick );

	/**
	 * Expand/collapse handler for the Setup connections table detail rows.
	 *
	 * Each main row has a [data-dd-expand="N"] button. Its sibling detail
	 * row carries [data-dd-row="N"] and the `hidden` attribute. We swap
	 * the `hidden` attribute on click and flip aria-expanded + the caret
	 * rotation (CSS handles the visual).
	 */
	function handleExpandToggleClick( ev ) {
		var btn = ev.target.closest( '.dd-expand-toggle[data-dd-expand]' );
		if ( ! btn ) {
			return;
		}
		ev.preventDefault();
		var idx = btn.getAttribute( 'data-dd-expand' );
		if ( ! idx ) {
			return;
		}
		var detail = document.querySelector( 'tr.dd-row-detail[data-dd-row="' + idx + '"]' );
		if ( ! detail ) {
			return;
		}
		var isExpanded = btn.getAttribute( 'aria-expanded' ) === 'true';
		if ( isExpanded ) {
			detail.setAttribute( 'hidden', '' );
			btn.setAttribute( 'aria-expanded', 'false' );
		} else {
			detail.removeAttribute( 'hidden' );
			btn.setAttribute( 'aria-expanded', 'true' );
		}
	}
	document.addEventListener( 'click', handleExpandToggleClick );
} )();
