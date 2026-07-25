/**
 * Global Reach section — count-up stats on scroll into view.
 * Arc-dash travel + pin pulse are pure CSS animations (see app.css).
 */
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var section = document.querySelector('.visati-global-reach');
		if(!section) return;

		var nums = section.querySelectorAll('[data-count-text]');
		if(!nums.length) return;

		var animated = false;

		function animateCount(el){
			var text = el.getAttribute('data-count-text') || '';
			var match = text.match(/^(\d+(?:\.\d+)?)(.*)$/);
			if(!match){
				el.textContent = text;
				return;
			}
			var end = parseFloat(match[1]);
			var decimals = (match[1].split('.')[1] || '').length;
			var suffix = match[2] || '';
			var duration = 1400;
			var start = null;

			function step(ts){
				if(!start) start = ts;
				var progress = Math.min((ts - start) / duration, 1);
				var eased = 1 - Math.pow(1 - progress, 3);
				var current = end * eased;
				el.textContent = current.toFixed(decimals) + suffix;
				if(progress < 1) requestAnimationFrame(step);
				else el.textContent = end.toFixed(decimals) + suffix;
			}
			requestAnimationFrame(step);
		}

		function runAll(){
			if(animated) return;
			animated = true;
			nums.forEach(animateCount);
		}

		if('IntersectionObserver' in window){
			var observer = new IntersectionObserver(function(entries){
				entries.forEach(function(entry){
					if(entry.isIntersecting) runAll();
				});
			}, { threshold: 0.3 });
			observer.observe(section);
		} else {
			runAll();
		}
	});
})();
