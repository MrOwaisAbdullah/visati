( function () {
	'use strict';

	var form = document.getElementById( 'visati-apply-form' );
	if ( ! form ) {
		return;
	}

	var cfg     = window.visatiApply || {};
	var restUrl = cfg.restUrl || '/wp-json/visati/v1';
	var nonce   = cfg.nonce || '';
	var steps   = Array.prototype.slice.call( form.querySelectorAll( '.visati-step' ) );
	var dots    = form.querySelectorAll( '.visati-step-indicator li' );
	var total   = steps.length;
	var current = 1;
	var appId   = null;
	var docs    = []; // {filename, doc_type}

	var backBtn    = form.querySelector( '[data-action=back]' );
	var nextBtn    = form.querySelector( '[data-action=next]' );
	var submitBtn  = form.querySelector( '[data-action=submit]' );
	var errorBox   = form.querySelector( '.visati-error' );

	function showStep( n ) {
		current = n;
		steps.forEach( function ( s ) { s.hidden = Number( s.dataset.step ) !== n; } );
		Array.prototype.forEach.call( dots, function ( li, i ) {
			li.classList.toggle( 'is-active', ( i + 1 ) === n );
			li.classList.toggle( 'is-done', ( i + 1 ) < n );
		} );
		backBtn.hidden   = n === 1;
		nextBtn.hidden   = n === total;
		submitBtn.hidden = n !== total;
		errorBox.hidden  = true;
		window.scrollTo( { top: form.offsetTop - 24, behavior: 'smooth' } );
	}

	function showError( msg ) {
		errorBox.textContent = msg;
		errorBox.hidden = false;
	}

	function validateStep( n ) {
		var section = form.querySelector( '.visati-step[data-step="' + n + '"]' );
		var fields = section.querySelectorAll( '[required]' );
		for ( var i = 0; i < fields.length; i++ ) {
			if ( ! fields[ i ].value ) {
				showError( 'Please complete all required fields.' );
				fields[ i ].focus();
				return false;
			}
		}
		return true;
	}

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

	nextBtn.addEventListener( 'click', function () {
		if ( ! validateStep( current ) ) {
			return;
		}
		if ( current < total ) {
			showStep( current + 1 );
		}
		if ( current === total ) {
			renderReview();
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
		// US2 (Stripe) handoff lands here next. For now, surface readiness.
		showError( 'Application ' + appId + ' ready. Stripe payment handoff is wired in the next step.' );
		submitBtn.disabled = false;
		submitBtn.textContent = 'Submit & Pay';
	} );

	showStep( 1 );
}() );
