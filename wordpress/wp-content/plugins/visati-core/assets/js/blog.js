/**
 * Blog grid — category filter (client-side, matches faq.js pattern).
 */
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var grid = document.querySelector('.visati-blog-grid');
		if(!grid) return;

		var filterBtns = document.querySelectorAll('.visati-blog-filter');
		var emptyMsg = document.querySelector('.visati-blog-main > .visati-blog-empty');

		filterBtns.forEach(function(btn){
			btn.addEventListener('click', function(){
				filterBtns.forEach(function(b){ b.classList.remove('is-active'); b.setAttribute('aria-selected','false'); });
				btn.classList.add('is-active');
				btn.setAttribute('aria-selected','true');
				filterCards();
			});
		});

		function filterCards(){
			var active = document.querySelector('.visati-blog-filter.is-active');
			var catVal = active ? active.getAttribute('data-category') : 'all';
			var cards = grid.querySelectorAll('.visati-blog-card');
			var visible = 0;

			cards.forEach(function(card){
				var cardCat = card.getAttribute('data-category');
				var show = (catVal === 'all' || cardCat === catVal);
				card.hidden = !show;
				if(show) visible++;
			});

			if(emptyMsg) emptyMsg.hidden = visible > 0;
		}
	});
})();
