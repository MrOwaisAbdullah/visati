<?php
/**
 * Template Name: Track Page
 * Description: Application tracking page.
 *
 * @package Visati_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="visati-page visati-page--track">
	<div class="visati-section">
		<div class="visati-container">
			<div style="text-align:center;margin-bottom:2rem;">
				<span class="visati-eyebrow"><?php esc_html_e( 'Application Status', 'visati-core' ); ?></span>
				<h1 style="margin-top:0.5rem;"><?php esc_html_e( 'Track Your Visa Application', 'visati-core' ); ?></h1>
				<p style="color:rgba(26,26,46,0.6);max-width:480px;margin:0.75rem auto 0;">
					<?php esc_html_e( 'Enter your application ID to see the current status and timeline.', 'visati-core' ); ?>
				</p>
			</div>

			<div class="visati-track-search" style="justify-content:center;">
				<input type="text" id="track-id-input" placeholder="VIS-2026-XXXXXX" aria-label="Application ID" style="text-transform:uppercase;">
				<button type="button" class="visati-btn" id="track-search-btn">
					Track
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
				</button>
			</div>

			<div id="track-result" style="max-width:600px;margin:0 auto;"></div>
		</div>
	</div>
</main>

<script>
(function(){
	var input = document.getElementById('track-id-input');
	var btn   = document.getElementById('track-search-btn');
	var result= document.getElementById('track-result');
	if(!input || !btn || !result) return;

	/* Pre-fill from URL ?id= */
	var params = new URLSearchParams(window.location.search);
	var prefill = params.get('id');
	if(prefill){ input.value = prefill; doSearch(); }

	btn.addEventListener('click', doSearch);
	input.addEventListener('keydown', function(e){ if(e.key==='Enter') doSearch(); });

	function doSearch(){
		var id = input.value.trim().toUpperCase();
		if(!id){ input.focus(); return; }
		result.innerHTML = '<p style="text-align:center;color:rgba(26,26,46,0.5);padding:2rem;">Loading...</p>';

		fetch('<?php echo esc_url( rest_url( 'visati/v1/track' ) ); ?>?id=' + encodeURIComponent(id), {
			headers: { 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' }
		})
		.then(function(r){ return r.json(); })
		.then(function(data){
			if(data.message && !data.id){
				result.innerHTML = '<div class="visati-track-not-found"><p>' + data.message + '</p><p style="font-size:0.85rem;margin-top:0.5rem;">Please check your application ID and try again.</p></div>';
				return;
			}
			renderResult(data);
		})
		.catch(function(){
			result.innerHTML = '<div class="visati-track-not-found">Something went wrong. Please try again.</div>';
		});
	}

	function renderResult(data){
		var statusClass = 'is-default';
		if(data.status === 'approved') statusClass = 'is-approved';
		else if(data.status === 'rejected') statusClass = 'is-rejected';
		else if(data.status === 'processing' || data.status === 'reviewing') statusClass = 'is-processing';

		var html = '<div class="visati-card" style="margin-bottom:1.5rem;">';
		html += '<div class="visati-visa-card__meta" style="margin-bottom:0.75rem;"><span>Application </span><span class="visati-mono" style="font-weight:700;">' + data.id + '</span></div>';
		html += '<span class="visati-track-status-badge ' + statusClass + '">' + data.status + '</span>';
		if(data.visa_type) html += '<p style="color:rgba(26,26,46,0.6);font-size:0.9rem;margin:0.5rem 0 0;">Visa: ' + data.visa_type + '</p>';
		html += '</div>';

		if(data.timeline && data.timeline.length > 0){
			html += '<h3 style="font-size:1rem;margin-bottom:1rem;">Status Timeline</h3>';
			html += '<div class="visati-timeline">';
			data.timeline.forEach(function(item, i){
				var isCurrent = (i === 0) ? ' is-current' : '';
				html += '<div class="visati-timeline-item' + isCurrent + '">';
				html += '<div class="visati-timeline-item__status">' + item.status + '</div>';
				if(item.note) html += '<div class="visati-timeline-item__note">' + item.note + '</div>';
				if(item.date) html += '<div class="visati-timeline-item__date">' + item.date + '</div>';
				html += '</div>';
			});
			html += '</div>';
		}
		result.innerHTML = html;
	}
})();
</script>

<?php
get_footer();
