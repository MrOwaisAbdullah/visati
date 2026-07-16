/**
 * FAQ accordion + search + category filter.
 */
(function(){
	document.addEventListener('DOMContentLoaded', function(){
		var list = document.querySelector('.visati-faq-list');
		if(!list) return;

		/* Accordion toggle */
		list.addEventListener('click', function(e){
			var btn = e.target.closest('.visati-faq-item__q');
			if(!btn) return;
			var item = btn.closest('.visati-faq-item');
			var isOpen = item.classList.contains('is-open');

			/* Close all in same category (single-open per view) */
			var visible = list.querySelectorAll('.visati-faq-item:not([hidden])');
			visible.forEach(function(v){ v.classList.remove('is-open'); v.querySelector('.visati-faq-item__q').setAttribute('aria-expanded','false'); });

			if(!isOpen){
				item.classList.add('is-open');
				btn.setAttribute('aria-expanded','true');
			}
		});

		/* Category filter */
		var catBtns = document.querySelectorAll('.visati-faq-cat-btn');
		var emptyMsg = document.querySelector('.visati-faq-empty');
		catBtns.forEach(function(btn){
			btn.addEventListener('click', function(){
				catBtns.forEach(function(b){ b.classList.remove('is-active'); b.setAttribute('aria-selected','false'); });
				btn.classList.add('is-active');
				btn.setAttribute('aria-selected','true');
				filterItems();
			});
		});

		/* Search */
		var search = document.querySelector('.visati-faq-search');
		if(search){
			search.addEventListener('input', function(){ filterItems(); });
		}

		function filterItems(){
			var cat = document.querySelector('.visati-faq-cat-btn.is-active');
			var catVal = cat ? cat.getAttribute('data-category') : 'all';
			var query = search ? search.value.toLowerCase().trim() : '';
			var items = list.querySelectorAll('.visati-faq-item');
			var visible = 0;

			items.forEach(function(item){
				var itemCat = item.getAttribute('data-category');
				var text = item.textContent.toLowerCase();
				var matchCat = (catVal === 'all' || itemCat === catVal);
				var matchQuery = !query || text.indexOf(query) !== -1;
				var show = matchCat && matchQuery;
				item.hidden = !show;
				if(show) visible++;
			});

			if(emptyMsg) emptyMsg.hidden = visible > 0;
		}
	});
})();
