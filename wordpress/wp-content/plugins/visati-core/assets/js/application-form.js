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
	var errorBox  = form.querySelector( '.visati-error' );
	var origNextText = nextBtn.textContent;
	var draftPromise = null;
	var activeUploads = 0;

	/* ── Traveler DOM refs (declared early so formData can use them) ── */
	var travelersSection = form.querySelector( '[data-travelers]' );
	var travelersList    = form.querySelector( '[data-travelers-list]' );
	var travelersAdd     = form.querySelector( '[data-travelers-add]' );
	var travelerCount    = 0;

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
		errorBox.hidden  = true;
		if ( n === total ) {
			renderReview();
			nextBtn.textContent = 'Submit & Pay';
			nextBtn.className = 'visati-btn visati-btn--navy';
		} else {
			nextBtn.textContent = origNextText;
			nextBtn.type = 'button';
			nextBtn.className = 'visati-btn';
		}
		var liveRegion = document.getElementById( 'step-live' );
		if ( liveRegion ) {
			var labels = [ 'Visa Selection', 'Personal Details', 'Documents', 'Review & Submit' ];
			liveRegion.textContent = 'Step ' + n + ' of ' + total + ': ' + ( labels[ n - 1 ] || '' );
		}
		window.scrollTo( { top: form.offsetTop - 24, behavior: 'smooth' } );
	}

	function validateStep( n ) {
		var section = form.querySelector( '.visati-step[data-step="' + n + '"]' );

		/* Step 3: check uploaded docs */
		if ( n === 3 ) {
			var hasPassport = docs.some( function ( d ) { return d.doc_type === 'passport_copy'; } );
			var hasPhoto    = docs.some( function ( d ) { return d.doc_type === 'photo'; } );
			if ( ! hasPassport || ! hasPhoto ) {
				showError( 'Please upload passport copy and photo.' );
				return false;
			}
			return true;
		}

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

		function getVisibleItems() {
			return Array.prototype.slice.call( list.querySelectorAll( 'li' ) ).filter( function ( li ) {
				return li.style.display !== 'none';
			} );
		}
		function selectItem( li ) {
			if ( ! li ) return;
			var val  = li.getAttribute( 'data-value' );
			var flag = li.getAttribute( 'data-flag' );
			hidden.value = val;
			textEl.textContent = val;
			button.classList.add( 'is-filled' );
			button.setAttribute( 'aria-expanded', 'false' );
			flagEl.innerHTML = flag ? '<img src="' + visatiApply.flagsUrl + flag + '.svg" alt="" width="20" height="14">' : '';
			Array.prototype.forEach.call( list.querySelectorAll( 'li' ), function ( x ) {
				x.classList.toggle( 'is-selected', x === li );
			} );
			close();
			button.focus();
		}
		function open() {
			cb.classList.add( 'is-open' );
			panel.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			if ( search ) {
				setTimeout( function () { search.focus(); }, 0 );
			}
		}
		function close() {
			cb.classList.remove( 'is-open' );
			panel.hidden = true;
			button.setAttribute( 'aria-expanded', 'false' );
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
			search.addEventListener( 'keydown', function ( e ) {
				var items = getVisibleItems();
				if ( ! items.length ) return;
				if ( e.key === 'ArrowDown' ) {
					e.preventDefault();
					items[0].focus();
				} else if ( e.key === 'Escape' ) {
					close();
					button.focus();
				}
			} );
		}
		list.addEventListener( 'click', function ( e ) {
			var li = e.target.closest ? e.target.closest( 'li' ) : null;
			if ( ! li ) return;
			selectItem( li );
		} );
		list.addEventListener( 'keydown', function ( e ) {
			var items = getVisibleItems();
			var idx = items.indexOf( document.activeElement );
			if ( e.key === 'ArrowDown' ) {
				e.preventDefault();
				if ( idx < items.length - 1 ) items[ idx + 1 ].focus();
			} else if ( e.key === 'ArrowUp' ) {
				e.preventDefault();
				if ( idx > 0 ) items[ idx - 1 ].focus();
				else if ( search ) search.focus();
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				if ( idx >= 0 ) selectItem( items[ idx ] );
			} else if ( e.key === 'Escape' ) {
				close();
				button.focus();
			} else if ( e.key === 'Home' ) {
				e.preventDefault();
				if ( items.length ) items[0].focus();
			} else if ( e.key === 'End' ) {
				e.preventDefault();
				if ( items.length ) items[ items.length - 1 ].focus();
			}
		} );
		Array.prototype.forEach.call( list.querySelectorAll( 'li' ), function ( li ) {
			li.setAttribute( 'tabindex', '-1' );
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! cb.contains( e.target ) ) {
				close();
			}
		} );
	}
	Array.prototype.forEach.call( form.querySelectorAll( '[data-combobox]' ), initCombobox );

	/* ── Traveler helpers ─────────────────────────────── */
	function getVisaData( slug ) {
		var visas = ( window.visatiApply && window.visatiApply.visas ) || {};
		var visa  = visas[ slug ];
		if ( ! visa ) { return null; }
		/* Fallback: check visa name for multi-entry keywords if meta is empty */
		if ( visa.entry_type !== 'multi' && visa.name ) {
			var lower = visa.name.toLowerCase();
			if ( lower.indexOf( 'multi' ) !== -1 || lower.indexOf( 'multiple' ) !== -1 ) {
				visa.entry_type = 'multi';
			}
		}
		return visa;
	}

	function isMultiEntry( slug ) {
		var visa = getVisaData( slug );
		return visa && visa.entry_type === 'multi';
	}

	/* ── Order summary sidebar ────────────────────────── */
	var summaryEl = document.querySelector( '[data-order-summary]' );
	var appliedDiscount = null; /* { code, amount, key } once validated; cleared if visa/tier changes */

	function formatAed( amount ) {
		return 'AED ' + Math.round( amount ).toLocaleString( 'en-US' );
	}

	/* ── Show/hide Express option based on the selected visa's has_express flag ── */
	function updateExpressAvailability() {
		var visaSelectEl  = form.querySelector( 'select[name="visa_type_slug"]' );
		var expressOption = form.querySelector( '[data-express-option]' );
		var expressRadio  = expressOption ? expressOption.querySelector( 'input[value="express"]' ) : null;
		var standardRadio = form.querySelector( 'input[name="processing_tier"][value="standard"]' );
		if ( ! visaSelectEl || ! expressOption || ! expressRadio ) { return; }

		var visa = visaSelectEl.value ? getVisaData( visaSelectEl.value ) : null;
		var hasExpress = ! visa || visa.has_express !== false; /* default true until a visa is chosen */

		expressOption.hidden = ! hasExpress;
		if ( ! hasExpress && expressRadio.checked ) {
			expressRadio.checked = false;
			if ( standardRadio ) { standardRadio.checked = true; }
			updateOrderSummary();
		}
	}

	function updateOrderSummary() {
		if ( ! summaryEl ) { return; }

		var visaSelectEl = form.querySelector( 'select[name="visa_type_slug"]' );
		var tierInput    = form.querySelector( 'input[name="processing_tier"]:checked' );
		var slug         = visaSelectEl ? visaSelectEl.value : '';
		var visa         = slug ? getVisaData( slug ) : null;

		var emptyEl   = summaryEl.querySelector( '[data-summary-empty]' );
		var detailsEl = summaryEl.querySelector( '[data-summary-details]' );

		if ( ! visa ) {
			if ( emptyEl ) { emptyEl.hidden = false; }
			if ( detailsEl ) { detailsEl.hidden = true; }
			return;
		}

		if ( emptyEl ) { emptyEl.hidden = true; }
		if ( detailsEl ) { detailsEl.hidden = false; }

		var isExpress   = tierInput && tierInput.value === 'express';
		/* wp_localize_script() casts all top-level scalars to strings, so this
		   must be parsed as a number — otherwise fee + extra string-concatenates. */
		var surchargeAed = parseFloat( ( window.visatiApply && window.visatiApply.surcharge_aed ) || 0 ) || 0;
		var fee   = parseFloat( visa.price_aed ) || 0;
		var extra = isExpress ? surchargeAed : 0;
		var subtotal = fee + extra;

		/* A discount was validated against a specific fee+tier combo — if the
		   visa or processing tier changed since, the amount no longer applies
		   (it may exceed the new price, or the code may have different rules
		   the server would re-check anyway) so drop it and ask to re-apply. */
		var priceKey = slug + '|' + ( isExpress ? 'express' : 'standard' );
		if ( appliedDiscount && appliedDiscount.key !== priceKey ) {
			appliedDiscount = null;
			var msgElReset = summaryEl.querySelector( '[data-discount-msg]' );
			if ( msgElReset ) {
				msgElReset.hidden = true;
				msgElReset.removeAttribute( 'data-state' );
			}
			var inputElReset = summaryEl.querySelector( '[data-discount-input]' );
			if ( inputElReset ) { inputElReset.value = ''; }
		}

		var discountRow    = summaryEl.querySelector( '[data-summary-discount-row]' );
		var discountCodeEl = summaryEl.querySelector( '[data-summary-discount-code]' );
		var discountAmtEl  = summaryEl.querySelector( '[data-summary-discount-amount]' );
		var discountAmount = appliedDiscount ? appliedDiscount.amount : 0;
		if ( discountRow ) { discountRow.hidden = ! appliedDiscount; }
		if ( appliedDiscount && discountCodeEl ) { discountCodeEl.textContent = appliedDiscount.code; }
		if ( appliedDiscount && discountAmtEl ) { discountAmtEl.textContent = '-' + formatAed( discountAmount ); }

		var total = Math.max( 0, subtotal - discountAmount );

		var nameEl = summaryEl.querySelector( '[data-summary-name]' );
		var metaEl = summaryEl.querySelector( '[data-summary-meta]' );
		if ( nameEl ) { nameEl.textContent = visa.name || ''; }
		if ( metaEl ) {
			metaEl.textContent = visa.tagline
				? visa.tagline
				: ( visa.entry_type === 'multi' ? 'Multiple entry' : 'Single entry' ) +
					' · ' + ( isExpress ? '12–24h' : '24–72h' ) + ' processing';
		}

		var feeEl = summaryEl.querySelector( '[data-summary-fee]' );
		if ( feeEl ) { feeEl.textContent = formatAed( fee ); }

		var expressRow = summaryEl.querySelector( '[data-summary-express-row]' );
		var expressEl  = summaryEl.querySelector( '[data-summary-express]' );
		if ( expressRow ) { expressRow.hidden = ! isExpress; }
		if ( expressEl && isExpress ) { expressEl.textContent = formatAed( surchargeAed ); }

		var totalEl = summaryEl.querySelector( '[data-summary-total]' );
		if ( totalEl ) { totalEl.textContent = formatAed( total ); }
	}

	/* ── Discount code ─────────────────────────────────── */
	if ( summaryEl ) {
		var discountInputEl = summaryEl.querySelector( '[data-discount-input]' );
		var discountApplyBtn = summaryEl.querySelector( '[data-discount-apply]' );
		var discountMsgEl = summaryEl.querySelector( '[data-discount-msg]' );

		function showDiscountMsg( text, state ) {
			if ( ! discountMsgEl ) { return; }
			discountMsgEl.textContent = text;
			discountMsgEl.hidden = false;
			discountMsgEl.setAttribute( 'data-state', state );
		}

		async function applyDiscount() {
			if ( ! discountInputEl || ! discountApplyBtn ) { return; }
			var code = discountInputEl.value.trim();
			if ( ! code ) {
				showDiscountMsg( 'Enter a discount code.', 'error' );
				return;
			}

			var visaSelectEl = form.querySelector( 'select[name="visa_type_slug"]' );
			var tierInput    = form.querySelector( 'input[name="processing_tier"]:checked' );
			var slug         = visaSelectEl ? visaSelectEl.value : '';
			var visa         = slug ? getVisaData( slug ) : null;
			if ( ! visa ) {
				showDiscountMsg( 'Select a visa type first.', 'error' );
				return;
			}
			var isExpress = tierInput && tierInput.value === 'express';
			var surchargeAed = parseFloat( ( window.visatiApply && window.visatiApply.surcharge_aed ) || 0 ) || 0;
			var subtotal = ( parseFloat( visa.price_aed ) || 0 ) + ( isExpress ? surchargeAed : 0 );

			discountApplyBtn.disabled = true;
			discountApplyBtn.textContent = 'Checking…';

			try {
				var resp = await fetch( restUrl + '/discount/validate', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body: JSON.stringify( { code: code, price_aed: subtotal } ),
				} );
				var data = await resp.json();

				if ( ! resp.ok ) {
					appliedDiscount = null;
					showDiscountMsg( data.message || 'This discount code is not valid.', 'error' );
				} else {
					appliedDiscount = {
						code: data.code,
						amount: parseFloat( data.discount_amount_aed ) || 0,
						key: slug + '|' + ( isExpress ? 'express' : 'standard' ),
					};
					showDiscountMsg( 'Code "' + data.code + '" applied.', 'ok' );
				}
			} catch ( err ) {
				appliedDiscount = null;
				showDiscountMsg( 'Network error — please try again.', 'error' );
			}

			discountApplyBtn.disabled = false;
			discountApplyBtn.textContent = 'Apply';
			updateOrderSummary();
		}

		if ( discountApplyBtn ) {
			discountApplyBtn.addEventListener( 'click', applyDiscount );
		}
		if ( discountInputEl ) {
			discountInputEl.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) {
					e.preventDefault();
					applyDiscount();
				}
			} );
		}
	}

	function updateTravelersVisibility() {
		var visaSelect = form.querySelector( 'select[name="visa_type_slug"]' );
		if ( ! visaSelect || ! travelersSection ) { return; }
		if ( isMultiEntry( visaSelect.value ) ) {
			travelersSection.hidden = false;
		} else {
			travelersSection.hidden = true;
			travelersList.innerHTML = '';
			travelerCount = 0;
		}
	}

	function addTravelerCard() {
		travelerCount++;
		var n = travelerCount;
		var card = document.createElement( 'div' );
		card.className = 'visati-traveler-card';
		card.dataset.travelerCard = n;
		card.innerHTML =
			'<div class="visati-traveler-card__header">' +
				'<span class="visati-traveler-card__num">Traveler ' + n + '</span>' +
				'<button type="button" class="visati-traveler-card__remove" data-remove-traveler="' + n + '">Remove</button>' +
			'</div>' +
			'<div class="visati-grid">' +
				'<label class="visati-field">Relationship' +
					'<select name="traveler_' + n + '_relationship" required>' +
						'<option value="">Select…</option>' +
						'<option value="wife">Wife</option>' +
						'<option value="son">Son</option>' +
						'<option value="daughter">Daughter</option>' +
						'<option value="father">Father</option>' +
						'<option value="mother">Mother</option>' +
						'<option value="brother">Brother</option>' +
						'<option value="sister">Sister</option>' +
						'<option value="other">Other</option>' +
					'</select>' +
				'</label>' +
				'<label class="visati-field">Full name<input name="traveler_' + n + '_name" required placeholder="As in passport"></label>' +
				'<label class="visati-field">Passport number<input name="traveler_' + n + '_passport" required placeholder="e.g. A1234567" class="visati-mono"></label>' +
				'<label class="visati-field">Date of birth<input type="date" name="traveler_' + n + '_dob" required></label>' +
			'</div>';
		travelersList.appendChild( card );

		card.querySelector( '[data-remove-traveler]' ).addEventListener( 'click', function () {
			card.remove();
		} );
	}

	if ( travelersAdd ) {
		travelersAdd.addEventListener( 'click', addTravelerCard );
	}

	/* ── Draft + upload ───────────────────────────────── */
	function formData() {
		var fd = new FormData( form );
		var o = {};
		fd.forEach( function ( v, k ) {
			if ( v instanceof File ) { return; }
			o[ k ] = v;
		} );

		/* Collect traveler cards into an array */
		if ( travelersList ) {
			var cards   = travelersList.querySelectorAll( '.visati-traveler-card' );
			var travelers = [];
			cards.forEach( function ( card ) {
				var rel  = card.querySelector( 'select[name$="_relationship"]' );
				var name = card.querySelector( 'input[name$="_name"]' );
				var pass = card.querySelector( 'input[name$="_passport"]' );
				var dob  = card.querySelector( 'input[name$="_dob"]' );
				travelers.push( {
					relationship: rel ? rel.value : '',
					name:         name ? name.value : '',
					passport:     pass ? pass.value : '',
					dob:          dob ? dob.value : '',
				} );
			} );
			o.travelers = travelers;
		}
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
				console.error( 'Visati draft creation failed:', j );
				showError( j.message || 'Could not start application.' );
				return null;
			}
			return j.id;
		} catch ( e ) {
			console.error( 'Visati draft creation network error:', e );
			showError( 'Network error.' );
			return null;
		}
	}

	async function ensureDraft() {
		if ( appId ) {
			return appId;
		}
		if ( draftPromise ) {
			return draftPromise;
		}
		draftPromise = createDraft();
		appId = await draftPromise;
		draftPromise = null;
		return appId;
	}

	function setLoading( btn, loading, text ) {
		if ( loading ) {
			btn.disabled = true;
			btn.dataset.origText = btn.textContent;
			btn.innerHTML = '<span class="visati-spinner"></span> ' + ( text || btn.textContent );
		} else {
			btn.disabled = false;
			btn.textContent = btn.dataset.origText || text || origNextText;
			delete btn.dataset.origText;
		}
	}

	async function uploadFile( input ) {
		var file = input.files[ 0 ];
		if ( ! file ) {
			return;
		}
		var docType = input.dataset.docType || 'document';
		var box = form.querySelector( '[data-doc-box="' + docType + '"]' );
		if ( box ) {
			var nameEl = box.querySelector( '[data-doc-name]' );
			if ( nameEl ) { nameEl.textContent = file.name; }
			box.classList.add( 'is-filled' );
			var icon = box.querySelector( 'svg' );
			if ( file.type && file.type.match( /^image\// ) ) {
				var reader = new FileReader();
				reader.onload = function ( ev ) {
					var preview = box.querySelector( '.visati-doc-upload__preview' );
					if ( ! preview ) {
						preview = document.createElement( 'img' );
						preview.className = 'visati-doc-upload__preview';
						box.insertBefore( preview, box.firstChild );
					}
					preview.src = ev.target.result;
					if ( icon ) { icon.style.display = 'none'; }
				};
				reader.readAsDataURL( file );
			} else {
				if ( icon ) { icon.style.display = ''; }
			}
		}
		if ( ! ( await ensureDraft() ) ) {
			if ( box ) { box.classList.remove( 'is-filled' ); }
			return;
		}
		activeUploads++;
		setLoading( nextBtn, true, 'Uploading…' );
		var fd = new FormData();
		fd.append( 'application_id', appId );
		fd.append( 'document_type', docType );
		fd.append( 'document', file );
		try {
			var r = await fetch( restUrl + '/documents', {
				method: 'POST',
				headers: { 'X-WP-Nonce': nonce },
				body: fd
			} );
			var j = await r.json();
			if ( ! r.ok ) {
				console.error( 'Visati document upload failed:', j );
				showError( j.message || 'Upload failed.' );
				if ( box ) { box.classList.remove( 'is-filled' ); }
			} else {
				docs.push( { filename: j.filename, doc_type: docType } );
				console.log( 'Visati doc uploaded:', docType, j.filename, '— docs array now has', docs.length, 'items' );
			}
		} catch ( e ) {
			console.error( 'Visati document upload network error:', e );
			showError( 'Network error during upload.' );
			if ( box ) { box.classList.remove( 'is-filled' ); }
		}
		activeUploads--;
		if ( activeUploads <= 0 ) {
			activeUploads = 0;
			setLoading( nextBtn, false );
		}
	}

	function renderReview() {
		var d = formData();
		var dl = form.querySelector( '.visati-review' );
		dl.innerHTML = '';
		var visaName = d.visa_type_slug || 'N/A';
		var visas = ( window.visatiApply && window.visatiApply.visas ) || {};
		if ( visas[ d.visa_type_slug ] && visas[ d.visa_type_slug ].name ) {
			visaName = visas[ d.visa_type_slug ].name;
		}
		var rows = [
			[ 'Visa', visaName ],
			[ 'Processing', d.processing_tier === 'express' ? 'Express (12-24h)' : 'Standard (1-3 days)' ],
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

		/* Travelers */
		if ( travelersList ) {
			var cards = travelersList.querySelectorAll( '.visati-traveler-card' );
			if ( cards.length > 0 ) {
				var tDt = document.createElement( 'dt' );
				tDt.textContent = 'Travelers';
				var tDd = document.createElement( 'dd' );
				var tLines = [];
				cards.forEach( function ( card, i ) {
					var rel   = card.querySelector( 'select[name$="_relationship"]' );
					var tName = card.querySelector( 'input[name$="_name"]' );
					var pass  = card.querySelector( 'input[name$="_passport"]' );
					var dob   = card.querySelector( 'input[name$="_dob"]' );
					tLines.push( ( i + 1 ) + '. ' + ( rel ? rel.value : '' ) + ' — ' + ( tName ? tName.value : '' ) + ', Passport: ' + ( pass ? pass.value : '' ) + ', DOB: ' + ( dob ? dob.value : '' ) );
				} );
				tDd.textContent = tLines.join( '; ' );
				dl.appendChild( tDt );
				dl.appendChild( tDd );
			}
		}
	}

	/* ── WhatsApp message builder ──────────────────────── */
	function buildWhatsAppMessage() {
		var d = formData();
		var visaName = d.visa_type_slug || 'N/A';
		var visas = ( window.visatiApply && window.visatiApply.visas ) || {};
		if ( visas[ d.visa_type_slug ] && visas[ d.visa_type_slug ].name ) {
			visaName = visas[ d.visa_type_slug ].name;
		}
		var lines = [
			'*Visa Application Inquiry*',
			'',
			'*Visa Type:* ' + visaName,
			'*Processing:* ' + ( d.processing_tier === 'express' ? 'Express (12-24h)' : 'Standard (1-3 days)' ),
			'',
			'*Name:* ' + d.given_name + ' ' + d.surname,
			'*Nationality:* ' + d.nationality,
			'*Passport:* ' + d.passport_number,
			'*Date of Birth:* ' + d.date_of_birth,
			'*Passport Expiry:* ' + d.passport_expiry,
			'*Travel Date:* ' + d.travel_date,
			'*Email:* ' + ( d.applicant_email || '—' ),
		];
		if ( travelersList ) {
			var cards = travelersList.querySelectorAll( '.visati-traveler-card' );
			if ( cards.length > 0 ) {
				lines.push( '', '*Travelers (' + cards.length + '):*' );
				cards.forEach( function ( card, i ) {
					var rel  = card.querySelector( 'select[name$="_relationship"]' );
					var tName = card.querySelector( 'input[name$="_name"]' );
					var pass = card.querySelector( 'input[name$="_passport"]' );
					var dob  = card.querySelector( 'input[name$="_dob"]' );
					lines.push( ( i + 1 ) + '. ' + ( rel ? rel.value : '' ) + ' — ' + ( tName ? tName.value : '' ) + ', Passport: ' + ( pass ? pass.value : '' ) + ', DOB: ' + ( dob ? dob.value : '' ) );
				} );
			}
		}
		lines.push( '', '*Documents:* ' + ( docs.map( function ( x ) { return x.filename; } ).join( ', ' ) || 'None uploaded yet' ) );
		return lines.join( '\n' );
	}

	var whatsappBtn = form.querySelector( '[data-action="whatsapp"]' );
	if ( whatsappBtn ) {
		whatsappBtn.addEventListener( 'click', function () {
			var number = ( window.visatiApply && window.visatiApply.whatsapp_number ) || '';
			if ( ! number ) {
				showError( 'WhatsApp number not configured. Please contact us directly.' );
				return;
			}
			var msg = buildWhatsAppMessage();
			var clean = number.replace( /[^0-9]/g, '' );
			window.open( 'https://wa.me/' + clean + '?text=' + encodeURIComponent( msg ), '_blank', 'noopener' );
		} );
	}

	/* ── Nav + submit ─────────────────────────────────── */
	nextBtn.addEventListener( 'click', function () {
		if ( current === total ) {
			form.requestSubmit();
			return;
		}
		if ( validateStep( current ) && current < total ) {
			showStep( current + 1 );
		}
	} );
	backBtn.addEventListener( 'click', function () {
		if ( current > 1 ) {
			showStep( current - 1 );
		}
	} );

	/* File inputs: change → upload. Use unique names to avoid FormData collisions. */
	Array.prototype.forEach.call( form.querySelectorAll( 'input[type=file]' ), function ( input ) {
		input.addEventListener( 'change', function () {
			uploadFile( input );
			/* Reset input so re-uploading the same file triggers change */
			input.value = '';
		} );
	} );

	form.addEventListener( 'submit', async function ( e ) {
		e.preventDefault();
		if ( ! ( await ensureDraft() ) ) {
			return;
		}
		setLoading( nextBtn, true, 'Preparing payment…' );

		try {
			var resp = await fetch( visatiApply.restUrl + '/checkout-session', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': visatiApply.nonce,
				},
				body: JSON.stringify( {
					application_id: appId,
					discount_code: appliedDiscount ? appliedDiscount.code : '',
				} ),
			} );
			var data = await resp.json();

			if ( data.url ) {
				window.location.href = data.url;
			} else {
				showError( data.message || 'Payment setup failed. Please try again.' );
				setLoading( nextBtn, false, 'Submit & Pay' );
			}
		} catch ( err ) {
			showError( 'Network error. Please try again.' );
			setLoading( nextBtn, false, 'Submit & Pay' );
		}
	} );

	/* ── Visa type change → show/hide travelers, express option, order summary ── */
	var visaSelect = form.querySelector( 'select[name="visa_type_slug"]' );
	if ( visaSelect ) {
		visaSelect.addEventListener( 'change', updateTravelersVisibility );
		visaSelect.addEventListener( 'change', updateExpressAvailability );
		visaSelect.addEventListener( 'change', updateOrderSummary );
	}

	/* ── Processing tier change → update order summary ─── */
	Array.prototype.forEach.call( form.querySelectorAll( 'input[name="processing_tier"]' ), function ( radio ) {
		radio.addEventListener( 'change', updateOrderSummary );
	} );

	/* ── Pre-select from URL params (?visa=&nationality=) ── */
	(function () {
		var params = new URLSearchParams( window.location.search );
		var visaParam = params.get( 'visa' );
		if ( visaParam && visaSelect ) {
			var opt = visaSelect.querySelector( 'option[value="' + visaParam + '"]' );
			if ( opt ) {
				visaSelect.value = visaParam;
			}
		}
		var natParam = params.get( 'nationality' );
		if ( natParam ) {
			var natInput = form.querySelector( 'input[name="nationality"]' );
			if ( natInput ) {
				natInput.value = natParam;
				var cb = natInput.closest( '[data-combobox]' );
				if ( cb ) {
					var textEl = cb.querySelector( '[data-combobox-text]' );
					var flagEl = cb.querySelector( '[data-combobox-flag]' );
					if ( textEl ) { textEl.textContent = natParam; }
					if ( flagEl ) { flagEl.innerHTML = '<img src="' + visatiApply.flagsUrl + natParam.toLowerCase() + '.svg" alt="" width="20" height="14">'; }
					var btn = cb.querySelector( '[data-combobox-button]' );
					if ( btn ) { btn.classList.add( 'is-filled' ); }
				}
			}
		}
		/* Apply travelers visibility AFTER pre-select sets visa value */
		updateTravelersVisibility();
		updateExpressAvailability();
		updateOrderSummary();
	})();

	/* ── Upload boxes: click + keyboard to open file dialog ────────────── */
	Array.prototype.forEach.call( form.querySelectorAll( '[data-doc-box]' ), function ( box ) {
		box.addEventListener( 'click', function ( e ) {
			if ( e.target.tagName === 'INPUT' ) { return; }
			var input = box.querySelector( 'input[type=file]' );
			if ( input ) { input.click(); }
		} );
		box.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				var input = box.querySelector( 'input[type=file]' );
				if ( input ) { input.click(); }
			}
		} );
	} );

	showStep( 1 );
}() );
