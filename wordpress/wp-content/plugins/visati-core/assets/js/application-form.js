( function () {
	'use strict';

	var form = document.getElementById( 'visati-apply-form' );
	if ( ! form ) {
		return;
	}

	var cfg      = window.visatiApply || {};
	var restUrl  = cfg.restUrl || '/wp-json/visati/v1';
	var nonce    = cfg.nonce || '';
	var steps    = Array.prototype.slice.call( form.querySelectorAll( '.visati-step' ) );
	var markers  = form.querySelectorAll( '.visati-stepper__item' );
	var progress = form.querySelector( '[data-progress]' );
	var total    = steps.length;
	var current  = 1;
	var appId    = null;
	var docs     = [];

	var backBtn   = form.querySelector( '[data-action=back]' );
	var nextBtn   = form.querySelector( '[data-action=next]' );
	var submitBtn = form.querySelector( '[data-action=submit]' );
	var errorBox  = form.querySelector( '.visati-error' );

	function showError( msg ) {
		errorBox.textContent = msg;
		errorBox.hidden = false;
	}

	function showStep( n ) {
		current = n;
		steps.forEach( function ( s ) {
			var num = Number( s.dataset.step );
			s.hidden = num !== n;
			s.classList.toggle( 'is-current', num === n );
		} );
		Array.prototype.forEach.call( markers, function ( m ) {
			var mn = Number( m.dataset.stepMarker );
			m.classList.toggle( 'is-active', mn === n );
			m.classList.toggle( 'is-done', mn < n );
		} );
		if ( progress ) {
			progress.style.width = ( ( ( n - 1 ) / 3 ) * 75 ) + '%';
		}
		backBtn.hidden   = n === 1;
		nextBtn.hidden   = n === total;
		submitBtn.hidden = n !== total;
		errorBox.hidden  = true;
		if ( n === total ) {
			renderReview();
		}
		window.scrollTo( { top: form.offsetTop - 24, behavior: 'smooth' } );
	}

	function validateStep( n ) {
		var section = form.querySelector( '.visati-step[data-step="' + n + '"]' );
		var required = section.querySelectorAll( '[required]' );
		for ( var i = 0; i < required.length; i++ ) {
			var el = required[ i ];
			if ( ! el.value ) {
				showError( 'Please complete all required fields.' );
				if ( el.type === 'hidden' && el.name === 'nationality' ) {
					var cb = el.closest( '[data-combobox]' );
					if ( cb ) {
						cb.classList.add( 'is-open' );
						var panel = cb.querySelector( '[data-combobox-panel]' );
						if ( panel ) { panel.hidden = false; }
					}
				} else {
					el.focus();
				}
				return false;
			}
		}
		return true;
	}

	/* ── Nationality combobox ─────────────────────────── */
	function initCombobox( cb ) {
		var button = cb.querySelector( '[data-combobox-button]' );
		var panel  = cb.querySelector( '[data-combobox-panel]' );
		var search = cb.querySelector( '[data-combobox-search]' );
		var list   = cb.querySelector( '[data-combobox-list]' );
		var hidden = cb.querySelector( 'input[name="nationality"]' );
		var textEl = cb.querySelector( '[data-combobox-text]' );
		var flagEl = cb.querySelector( '[data-combobox-flag]' );

		function open() {
			cb.classList.add( 'is-open' );
			panel.hidden = false;
			if ( search ) {
				setTimeout( function () { search.focus(); }, 0 );
			}
		}
		function close() {
			cb.classList.remove( 'is-open' );
			panel.hidden = true;
			if ( search ) {
				search.value = '';
				filter( '' );
			}
		}
		function toggle() {
			panel.hidden ? open() : close();
		}
		function filter( q ) {
			q = q.toLowerCase();
			Array.prototype.forEach.call( list.querySelectorAll( 'li' ), function ( li ) {
				li.style.display = ( li.getAttribute( 'data-value' ).toLowerCase().indexOf( q ) !== -1 ) ? '' : 'none';
			} );
		}

		button.addEventListener( 'click', function ( e ) { e.preventDefault(); toggle(); } );
		if ( search ) {
			search.addEventListener( 'input', function () { filter( search.value ); } );
		}
		list.addEventListener( 'click', function ( e ) {
			var li = e.target.closest ? e.target.closest( 'li' ) : null;
			if ( ! li ) {
				return;
			}
			var val  = li.getAttribute( 'data-value' );
			var flag = li.getAttribute( 'data-flag' );
			hidden.value = val;
			textEl.textContent = val;
			button.classList.add( 'is-filled' );
			flagEl.innerHTML = flag ? '<img src="https://flagcdn.com/' + flag + '.svg" alt="" width="20" height="14">' : '';
			Array.prototype.forEach.call( list.querySelectorAll( 'li' ), function ( x ) {
				x.classList.toggle( 'is-selected', x === li );
			} );
			close();
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! cb.contains( e.target ) ) {
				close();
			}
		} );
	}
	Array.prototype.forEach.call( form.querySelectorAll( '[data-combobox]' ), initCombobox );

	/* ── Draft + upload ───────────────────────────────── */
	function formData() {
		var fd = new FormData( form );
		var o = {};
		fd.forEach( function ( v, k ) { o[ k ] = v; } );
		return o;
	}

	async function createDraft() {
		try {
			var r = await fetch( restUrl + '/applications', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body: JSON.stringify( formData() )
			} );
			var j = await r.json();
			if ( ! r.ok ) {
				showError( j.message || 'Could not start application.' );
				return null;
			}
			return j.id;
		} catch ( e ) {
			showError( 'Network error.' );
			return null;
		}
	}

	async function ensureDraft() {
		if ( appId ) {
			return appId;
		}
		appId = await createDraft();
		return appId;
	}

	async function uploadFile( input ) {
		var file = input.files[ 0 ];
		if ( ! file ) {
			return;
		}
		if ( ! ( await ensureDraft() ) ) {
			return;
		}
		var fd = new FormData();
		fd.append( 'application_id', appId );
		fd.append( 'document_type', input.dataset.docType || 'document' );
		fd.append( 'document', file );
		try {
			var r = await fetch( restUrl + '/documents', {
				method: 'POST',
				headers: { 'X-WP-Nonce': nonce },
				body: fd
			} );
			var j = await r.json();
			if ( ! r.ok ) {
				showError( j.message || 'Upload failed.' );
				return;
			}
			docs.push( { filename: j.filename, doc_type: input.dataset.docType } );
			var li = document.createElement( 'li' );
			li.textContent = '✓ ' + j.filename;
			form.querySelector( '.visati-doc-list' ).appendChild( li );
			input.value = '';
		} catch ( e ) {
			showError( 'Network error during upload.' );
		}
	}

	function renderReview() {
		var d = formData();
		var dl = form.querySelector( '.visati-review' );
		dl.innerHTML = '';
		var rows = [
			[ 'Visa', d.visa_type_slug ],
			[ 'Processing', d.processing_tier ],
			[ 'Name', d.given_name + ' ' + d.surname ],
			[ 'Nationality', d.nationality ],
			[ 'Passport', d.passport_number ],
			[ 'Travel date', d.travel_date ],
			[ 'Email', d.applicant_email || '—' ],
			[ 'Documents', docs.map( function ( x ) { return x.filename; } ).join( ', ' ) || 'none' ]
		];
		rows.forEach( function ( pair ) {
			var dt = document.createElement( 'dt' );
			dt.textContent = pair[ 0 ];
			var dd = document.createElement( 'dd' );
			dd.textContent = pair[ 1 ];
			dl.appendChild( dt );
			dl.appendChild( dd );
		} );
	}

	/* ── Nav + submit ─────────────────────────────────── */
	nextBtn.addEventListener( 'click', function () {
		if ( validateStep( current ) && current < total ) {
			showStep( current + 1 );
		}
	} );
	backBtn.addEventListener( 'click', function () {
		if ( current > 1 ) {
			showStep( current - 1 );
		}
	} );
	Array.prototype.forEach.call( form.querySelectorAll( 'input[type=file]' ), function ( input ) {
		input.addEventListener( 'change', function () { uploadFile( input ); } );
	} );
	form.addEventListener( 'submit', async function ( e ) {
		e.preventDefault();
		if ( ! ( await ensureDraft() ) ) {
			return;
		}
		submitBtn.disabled = true;
		submitBtn.textContent = 'Preparing payment…';
		showError( 'Application ' + appId + ' ready. Stripe payment handoff is wired in the next step.' );
		submitBtn.disabled = false;
		submitBtn.textContent = 'Submit & Pay';
	} );

	showStep( 1 );
}() );
