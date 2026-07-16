/**
 * Visa type filtering and sorting on the visa-types page.
 */
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var grid = document.querySelector('.visati-visa-grid');
		if(!grid) return;

		var filterBtns = document.querySelectorAll('.visati-visa-filter-btn');
		var sortSelect = document.getElementById('visa-sort');
		var cards = Array.prototype.slice.call(grid.querySelectorAll('.visati-visa-card'));

		/* Filter by entry type */
		filterBtns.forEach(function(btn){
			btn.addEventListener('click', function(){
				filterBtns.forEach(function(b){ b.classList.remove('is-active'); });
				btn.classList.add('is-active');
				var filter = btn.getAttribute('data-filter');
				cards.forEach(function(card){
					if(filter === 'all'){
						card.style.display = '';
					} else {
						var meta = card.querySelector('.visati-visa-card__meta');
						var text = meta ? meta.textContent.toLowerCase() : '';
						card.style.display = text.indexOf(filter) !== -1 ? '' : 'none';
					}
				});
			});
		});

		/* Sort */
		if(sortSelect){
			sortSelect.addEventListener('change', function(){
				var val = sortSelect.value;
				cards.sort(function(a, b){
					if(val === 'price-low' || val === 'price-high'){
						var pa = parseFloat((a.querySelector('.visati-visa-card__price')||{}).textContent.replace(/[^0-9.]/g,''))||0;
						var pb = parseFloat((b.querySelector('.visati-visa-card__price')||{}).textContent.replace(/[^0-9.]/g,''))||0;
						return val === 'price-low' ? pa - pb : pb - pa;
					}
					if(val === 'duration'){
						var da = parseInt((a.querySelector('.visati-visa-card__meta')||{}).textContent)||0;
						var db = parseInt((b.querySelector('.visati-visa-card__meta')||{}).textContent)||0;
						return da - db;
					}
					return 0;
				});
				cards.forEach(function(card){ grid.appendChild(card); });
			});
		}
	});
})();
