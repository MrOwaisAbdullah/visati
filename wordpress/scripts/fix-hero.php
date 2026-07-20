<?php
/**
 * Fix hero: restore widget_types + remove internal grid.
 */
error_reporting(E_ALL);
ini_set("display_errors", 1);

$home_id = 18;
$data = get_post_meta($home_id, "_elementor_data", true);
$json = json_decode($data, true);

// Direct walk — no reference issues
function walk_set(&$elements, $target_id, $key, $value) {
    foreach ($elements as &$el) {
        if (($el["id"] ?? "") === $target_id) {
            $el[$key] = $value;
            return true;
        }
        if (isset($el["elements"])) {
            if (walk_set($el["elements"], $target_id, $key, $value)) return true;
        }
    }
    return false;
}

function walk_settings(&$elements, $target_id, $settings) {
    foreach ($elements as &$el) {
        if (($el["id"] ?? "") === $target_id) {
            foreach ($settings as $k => $v) {
                $el["settings"][$k] = $v;
            }
            return true;
        }
        if (isset($el["elements"])) {
            if (walk_settings($el["elements"], $target_id, $settings)) return true;
        }
    }
    return false;
}

// 1. Fix widget_types
walk_set($json, "c0f5644", "widget_type", "html");
walk_set($json, "c5d2869", "widget_type", "shortcode");

// 2. Set the HTML content (grid-free, max-width container)
$hero_html = '<div style="max-width:580px;"><div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;"><span style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;border:1px solid rgba(10,22,40,0.1);background:rgba(10,22,40,0.05);padding:4px 12px;font-size:12px;font-weight:500;color:rgba(10,22,40,0.7);font-family:sans-serif;"><span style="width:6px;height:6px;border-radius:50%;background:#C9A84C;"></span>Dubai Based</span><span style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;border:1px solid rgba(10,22,40,0.1);background:rgba(10,22,40,0.05);padding:4px 12px;font-size:12px;font-weight:500;color:rgba(10,22,40,0.7);font-family:sans-serif;"><span style="width:6px;height:6px;border-radius:50%;background:#C9A84C;"></span>Trusted Worldwide</span></div><h1 style="font-size:clamp(40px,6vw,72px);font-weight:700;color:#0A1628;margin:0 0 24px;line-height:0.95;font-family:Plus Jakarta Sans,sans-serif;">Dubai Visa<br><span style="color:#C9A84C;">Simple</span><span style="color:#C9A84C;">.</span></h1><p style="font-size:18px;color:rgba(26,26,46,0.6);margin:0 0 32px;max-width:480px;line-height:1.6;">Visa processing for residents worldwide. Dubai based, trusted internationally.</p><div style="display:flex;gap:12px;flex-wrap:wrap;"><a href="/apply" style="display:inline-flex;align-items:center;gap:8px;height:48px;padding:0 24px;border-radius:12px;background:linear-gradient(135deg,#C9A84C,#F0C864);color:#0A1628;font-weight:600;font-size:14px;font-family:sans-serif;text-decoration:none;">Apply Now <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a><a href="/track" style="display:inline-flex;align-items:center;height:48px;padding:0 24px;border-radius:12px;border:1px solid rgba(10,22,40,0.15);color:#0A1628;font-weight:600;font-size:14px;font-family:sans-serif;text-decoration:none;">Track Application</a></div><div style="display:flex;align-items:center;gap:16px;margin-top:28px;flex-wrap:wrap;"><div style="display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(10,22,40,0.1);background:rgba(10,22,40,0.05);padding:8px 12px;border-radius:8px;"><div style="display:flex;gap:2px;"><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg></div><span style="font-size:12px;font-weight:600;color:#0A1628;">4.9/5</span></div><span style="font-size:14px;color:rgba(26,26,46,0.5);">1,000+ clients served</span></div><div style="display:flex;align-items:center;gap:24px;margin-top:32px;flex-wrap:wrap;"><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/></svg> UAE Office</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Secure Documents</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Fast Processing</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg> Worldwide Applicants</span></div></div>';

walk_settings($json, "c0f5644", ["editor" => $hero_html]);
walk_settings($json, "c5d2869", ["shortcode" => "[visati_quick_apply]"]);

// 3. Set hero-row to row layout
walk_settings($json, "hero-row", [
    "layout" => "row",
    "content_width" => "",
    "flex_direction" => "row",
    "flex_wrap" => "nowrap",
    "align_items" => "center",
    "gap" => ["unit" => "px", "size" => 48, "sizes" => []],
]);

$new_data = wp_slash(wp_json_encode($json));
update_post_meta($home_id, "_elementor_data", $new_data);

echo "DONE: widget_types restored, hero HTML=" . strlen($hero_html) . " chars (no grid), hero-row=layout:row\n";
