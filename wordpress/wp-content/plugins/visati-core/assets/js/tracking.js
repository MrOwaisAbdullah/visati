/**
 * Visati — application tracking page.
 *
 * Reads config from the localized `visatiTrack` object:
 *   { restUrl: 'https://…/wp-json/visati/v1', nonce: '…' }
 *
 * Fetches GET /track?id=… and renders the status timeline. All server-provided
 * strings (notes, statuses) are escaped before insertion to avoid injecting
 * admin-entered HTML into the page.
 */
(function () {
	'use strict';

	var cfg = window.visatiTrack || {};
	var input = document.getElementById( 'track-id-input' );
	var btn = document.getElementById( 'track-search-btn' );
	var result = document.getElementById( 'track-result' );

	if ( ! input || ! btn || ! result || ! cfg.restUrl ) {
		return;
	}

	// Human-readable labels for status slugs.
	var STATUS_LABELS = {
		draft: 'Draft',
		submitted: 'Submitted',
		under_review: 'Under Review',
		reviewing: 'Under Review',
		processing: 'Processing',
		approved: 'Approved',
		rejected: 'Rejected'
	};

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = null === value || undefined === value ? '' : String( value );
		return div.innerHTML;
	}

	function statusLabel( slug ) {
		return STATUS_LABELS[ slug ] || escapeHtml( slug );
	}

	function statusClass( slug ) {
		if ( 'approved' === slug ) {
			return 'is-approved';
		}
		if ( 'rejected' === slug ) {
			return 'is-rejected';
		}
		if ( 'processing' === slug || 'reviewing' === slug || 'under_review' === slug ) {
			return 'is-processing';
		}
		return 'is-default';
	}

	function setMessage( html ) {
		result.innerHTML = html;
	}

	function doSearch() {
		var id = input.value.trim().toUpperCase();
		if ( ! id ) {
			input.focus();
			return;
		}

		input.value = id;
		btn.disabled = true;
		setMessage( '<p class="visati-track-loading" style="text-align:center;color:rgba(26,26,46,0.5);padding:2rem;">Loading…</p>' );

		var url = cfg.restUrl.replace( /\/$/, '' ) + '/track?id=' + encodeURIComponent( id );

		fetch( url, {
			headers: cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {}
		} )
			.then( function ( r ) {
				return r.json().then( function ( data ) {
					return { ok: r.ok, data: data };
				} );
			} )
			.then( function ( res ) {
				var data = res.data || {};
				if ( ! res.ok || ! data.status ) {
					var msg = data.message ? escapeHtml( data.message ) : 'Application not found.';
					setMessage(
						'<div class="visati-track-not-found"><p>' + msg + '</p>' +
						'<p style="font-size:0.85rem;margin-top:0.5rem;">Please check your application ID and try again.</p></div>'
					);
					return;
				}
				renderResult( data );
			} )
			.catch( function () {
				setMessage( '<div class="visati-track-not-found">Something went wrong. Please try again.</div>' );
			} )
			.then( function () {
				btn.disabled = false;
			} );
	}

	function renderResult( data ) {
		var cls = statusClass( data.status );

		var html = '<div class="visati-card" style="margin-bottom:1.5rem;">';
		html += '<div class="visati-visa-card__meta" style="margin-bottom:0.75rem;">' +
			'<span>Application </span>' +
			'<span class="visati-mono" style="font-weight:700;">' + escapeHtml( data.id ) + '</span></div>';
		html += '<span class="visati-track-status-badge ' + cls + '">' + statusLabel( data.status ) + '</span>';

		if ( 'approved' === data.status ) {
			html += '<p class="visati-track-outcome visati-track-outcome--approved" style="margin:0.75rem 0 0;">' +
				'🎉 Your application has been approved.</p>';
		} else if ( 'rejected' === data.status ) {
			html += '<p class="visati-track-outcome visati-track-outcome--rejected" style="margin:0.75rem 0 0;">' +
				'Your application was not approved. Please contact support for details.</p>';
		}

		if ( data.visa_type ) {
			html += '<p style="color:rgba(26,26,46,0.6);font-size:0.9rem;margin:0.5rem 0 0;">Visa: ' +
				escapeHtml( data.visa_type ) + '</p>';
		}

		if ( data.visa_documents && data.visa_documents.length > 0 ) {
			html += '<div style="margin-top:1rem;display:flex;flex-direction:column;gap:0.5rem;align-items:center;">';
			data.visa_documents.forEach( function ( doc ) {
				var label = doc.label ? ' — ' + escapeHtml( doc.label ) : '';
				html += '<a class="visati-btn" href="' + escapeHtml( doc.download_url ) + '" target="_blank" rel="noopener">' +
					'Download Your Visa' + label +
					'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
					'</a>';
			} );
			html += '</div>';
		}
		html += '</div>';

		if ( data.timeline && data.timeline.length > 0 ) {
			html += '<h3 style="font-size:1rem;margin-bottom:1rem;">Status Timeline</h3>';
			html += '<div class="visati-timeline">';
			data.timeline.forEach( function ( item, i ) {
				var isCurrent = 0 === i ? ' is-current' : '';
				html += '<div class="visati-timeline-item' + isCurrent + '">';
				html += '<div class="visati-timeline-item__status">' + statusLabel( item.status ) + '</div>';
				if ( item.note ) {
					html += '<div class="visati-timeline-item__note">' + escapeHtml( item.note ) + '</div>';
				}
				if ( item.date ) {
					html += '<div class="visati-timeline-item__date">' + escapeHtml( item.date ) + '</div>';
				}
				html += '</div>';
			} );
			html += '</div>';
		}

		setMessage( html );
	}

	btn.addEventListener( 'click', doSearch );
	input.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			doSearch();
		}
	} );

	// Pre-fill and auto-search from ?id= in the URL.
	var params = new URLSearchParams( window.location.search );
	var prefill = params.get( 'id' );
	if ( prefill ) {
		input.value = prefill;
		doSearch();
	}
})();
