/**
 * Contact form — client validation + REST submission.
 */
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var form = document.getElementById('visati-contact-form');
		if(!form) return;

		var status = document.getElementById('contact-status');

		form.addEventListener('submit', function(e){
			e.preventDefault();

			/* Clear previous errors */
			form.querySelectorAll('.visati-field').forEach(function(f){ f.classList.remove('has-error'); });

			/* Validate required */
			var valid = true;
			['name','email','subject','message'].forEach(function(name){
				var field = form.querySelector('[name="'+name+'"]');
				if(!field) return;
				var val = field.value.trim();
				if(!val || (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val))){
					field.closest('.visati-field').classList.add('has-error');
					valid = false;
				}
			});
			if(!valid) return;

			/* Disable button */
			var btn = form.querySelector('button[type="submit"]');
			var orig = btn.innerHTML;
			btn.disabled = true;
			btn.innerHTML = 'Sending...';

			var data = {
				name:    form.querySelector('[name="name"]').value.trim(),
				email:   form.querySelector('[name="email"]').value.trim(),
				phone:   form.querySelector('[name="phone"]').value.trim(),
				subject: form.querySelector('[name="subject"]').value.trim(),
				message: form.querySelector('[name="message"]').value.trim(),
			};

			fetch(visatiContact.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': visatiContact.nonce,
				},
				body: JSON.stringify(data),
			})
			.then(function(r){ return r.json(); })
			.then(function(res){
				if(res.id){
					status.className = 'visati-contact-form__status is-success';
					status.textContent = 'Thank you! Your message has been received. We\'ll get back to you shortly.';
					status.hidden = false;
					form.reset();
				} else {
					throw new Error(res.message || 'Submission failed');
				}
			})
			.catch(function(err){
				status.className = 'visati-contact-form__status is-error';
				status.textContent = err.message || 'Something went wrong. Please try again.';
				status.hidden = false;
			})
			.finally(function(){
				btn.disabled = false;
				btn.innerHTML = orig;
			});
		});

		/* Live validation: remove error on input */
		form.querySelectorAll('.visati-field input, .visati-field textarea').forEach(function(el){
			el.addEventListener('input', function(){
				el.closest('.visati-field').classList.remove('has-error');
			});
		});
	});
})();
