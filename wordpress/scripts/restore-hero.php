<?php
/**
 * Restore hero section Elementor data.
 * Rebuilds hero text HTML widget and sets form card as shortcode widget side-by-side.
 */
$home_id = 18;
$data = get_post_meta($home_id, "_elementor_data", true);
$json = json_decode($data, true);

// The hero text HTML (from original rendered output)
$hero_html = <<<'HTML'
<div style="display:flex;flex-direction:column;gap:0;">
<div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;"><span style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;border:1px solid rgba(10,22,40,0.1);background:rgba(10,22,40,0.05);padding:4px 12px;font-size:12px;font-weight:500;color:rgba(10,22,40,0.7);font-family:sans-serif;"><span style="width:6px;height:6px;border-radius:50%;background:#C9A84C;"></span>Dubai Based</span><span style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;border:1px solid rgba(10,22,40,0.1);background:rgba(10,22,40,0.05);padding:4px 12px;font-size:12px;font-weight:500;color:rgba(10,22,40,0.7);font-family:sans-serif;"><span style="width:6px;height:6px;border-radius:50%;background:#C9A84C;"></span>Trusted Worldwide</span></div>
<h1 style="font-size:clamp(40px,6vw,72px);font-weight:700;color:#0A1628;margin:0 0 24px;line-height:0.95;font-family:'Plus Jakarta Sans',sans-serif;">Dubai Visa<br><span style="color:#C9A84C;">Simple</span><span style="color:#C9A84C;">.</span></h1>
<p style="font-size:18px;color:rgba(26,26,46,0.6);margin:0 0 32px;max-width:480px;line-height:1.6;">Visa processing for residents worldwide. Dubai based, trusted internationally.</p>
<div style="display:flex;gap:12px;flex-wrap:wrap;"><a href="/apply" style="display:inline-flex;align-items:center;gap:8px;height:48px;padding:0 24px;border-radius:12px;background:linear-gradient(135deg,#C9A84C,#F0C864);color:#0A1628;font-weight:600;font-size:14px;font-family:sans-serif;text-decoration:none;">Apply Now <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a><a href="/track" style="display:inline-flex;align-items:center;height:48px;padding:0 24px;border-radius:12px;border:1px solid rgba(10,22,40,0.15);color:#0A1628;font-weight:600;font-size:14px;font-family:sans-serif;text-decoration:none;">Track Application</a></div>
<div style="display:flex;align-items:center;gap:16px;margin-top:28px;flex-wrap:wrap;"><div style="display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(10,22,40,0.1);background:rgba(10,22,40,0.05);padding:8px 12px;border-radius:8px;"><div style="display:flex;gap:2px;"><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg><svg width="14" height="14" viewBox="0 0 20 20" fill="#FACC15"><path d="M10 1l2.39 4.84 5.34.78-3.87 3.77.91 5.33L10 13.28l-4.77 2.51.91-5.33L2.27 6.7l5.34-.78z"/></svg></div><span style="font-size:12px;font-weight:600;color:#0A1628;">4.9/5</span></div><span style="font-size:14px;color:rgba(26,26,46,0.5);">1,000+ clients served</span></div>
<div style="display:flex;align-items:center;gap:24px;margin-top:32px;flex-wrap:wrap;"><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/></svg> UAE Office</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Secure Documents</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Fast Processing</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:rgba(10,22,40,0.7);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg> Worldwide Applicants</span></div>
</div>
HTML;

// Fix: the hero HTML has nested h1 with <span> for "Simple." but original had proper line breaks
// Actually the original had: <h1>Dubai Visa<br><span style="color:#C9A84C;">Simple</span><span style="color:#C9A84C;">.</span></h1>
// This is correct.

// Find hero-row and rebuild its children
function find_el(&$elements, $target_id, &$result = null) {
    foreach ($elements as &$el) {
        if (($el["id"] ?? "") === $target_id) {
            $result = &$el;
            return true;
        }
        if (isset($el["elements"])) {
            if (find_el($el["elements"], $target_id, $result)) return true;
        }
    }
    return false;
}

$hero_row = null;
if (!find_el($json, "hero-row", $hero_row)) {
    echo "ERROR: hero-row not found\n";
    exit(1);
}

// Rebuild hero-row children with correct widget types
$hero_row["elements"] = array(
    // Left column: hero text HTML widget
    array(
        "id" => "c0f5644",
        "elType" => "widget",
        "widget_type" => "html",
        "settings" => array(
            "editor" => $hero_html,
            "_element_width" => "",
            "_flex_size" => "",
        ),
        "elements" => array(),
    ),
    // Right column: quick apply shortcode widget
    array(
        "id" => "c5d2869",
        "elType" => "widget",
        "widget_type" => "shortcode",
        "settings" => array(
            "shortcode" => "[visati_quick_apply]",
            "_element_width" => "",
            "_element_custom_width" => array("unit" => "px", "size" => 400, "sizes" => array()),
        ),
        "elements" => array(),
    ),
);

// Make sure hero-row is a row layout
$hero_row["settings"]["layout"] = "row";
$hero_row["settings"]["content_width"] = "";
$hero_row["settings"]["flex_direction"] = "row";
$hero_row["settings"]["flex_wrap"] = "nowrap";
$hero_row["settings"]["align_items"] = "center";
$hero_row["settings"]["gap"] = array("unit" => "px", "size" => 48, "sizes" => array());

// Also fix the hero parent container
$hero_parent = $json[0];
$hero_parent["settings"]["content_width"] = "full_width";
$hero_parent["settings"]["padding"] = array(
    "unit" => "px",
    "top" => "80",
    "right" => "0",
    "bottom" => "80",
    "left" => "0",
    "isLinked" => false,
);
$hero_parent["settings"]["flex_align_items"] = "center";

$new_data = wp_slash(wp_json_encode($json));
update_post_meta($home_id, "_elementor_data", $new_data);

echo "Hero section restored:\n";
echo "  - c0f5644: widget_type=html, editor=" . strlen($hero_html) . " chars\n";
echo "  - c5d2869: widget_type=shortcode, shortcode=[visati_quick_apply]\n";
echo "  - hero-row: layout=row, gap=48px, align=center\n";
echo "DONE\n";
