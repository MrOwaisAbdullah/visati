<?php
/**
 * Fix hero layout: embed Quick Apply card inside hero HTML widget's flex row.
 * Usage: wp eval-file fix-hero-layout.php --allow-root
 */
$home_id = 18;
$data = get_post_meta($home_id, "_elementor_data", true);
$json = json_decode($data, true);

function find_widget(&$elements, $target_id, &$result = null) {
    foreach ($elements as &$el) {
        if (($el["id"] ?? "") === $target_id) {
            $result = &$el;
            return true;
        }
        if (isset($el["elements"])) {
            if (find_widget($el["elements"], $target_id, $result)) return true;
        }
    }
    return false;
}

$html_widget = null;
if (!find_widget($json, "c0f5644", $html_widget)) {
    echo "ERROR: Could not find hero HTML widget c0f5644\n";
    exit(1);
}

$old_html = $html_widget["settings"]["editor"] ?? "";
echo "Old HTML length: " . strlen($old_html) . "\n";

// Replace grid with flex
$old_html = str_replace(
    "display:grid;grid-template-columns:58% 42%;gap:48px;align-items:start;",
    "display:flex;gap:48px;align-items:flex-start;",
    $old_html
);

// Find the opening > after the style attribute to insert wrapper
$grid_pos = strpos($old_html, "display:flex;gap:48px;align-items:flex-start;");
if ($grid_pos === false) {
    echo "ERROR: Could not find flex style in HTML\n";
    exit(1);
}

$after_style = strpos($old_html, ">", $grid_pos);
if ($after_style === false) {
    echo "ERROR: Could not find > after style\n";
    exit(1);
}

$insert_pos = $after_style + 1;
$before = substr($old_html, 0, $insert_pos);
$rest = substr($old_html, $insert_pos);

// Find the last </div> which closes the outermost grid div
$last_close = strrpos($rest, "</div>");
if ($last_close === false) {
    echo "ERROR: Could not find last </div>\n";
    exit(1);
}

$inner_content = substr($rest, 0, $last_close);
$grid_close = substr($rest, $last_close);

// Wrap inner content in left column, add right column with shortcode
$shortcode_card = '<div style="flex:0 0 400px;max-width:400px;">[visati_quick_apply]</div>';

$new_html = $before
    . '<div style="flex:1 1 0%;min-width:0;">'  // left column
    . $inner_content
    . '</div>'
    . $shortcode_card                              // right column
    . $grid_close;

$html_widget["settings"]["editor"] = $new_html;
echo "New HTML length: " . strlen($new_html) . "\n";
echo "Has shortcode: " . (strpos($new_html, "[visati_quick_apply]") !== false ? "YES" : "NO") . "\n";

$new_data = wp_slash(wp_json_encode($json));
update_post_meta($home_id, "_elementor_data", $new_data);
echo "DONE - hero layout updated\n";
