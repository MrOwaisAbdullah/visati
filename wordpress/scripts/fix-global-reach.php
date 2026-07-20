<?php
/**
 * Rebuild Global Reach section: left text, center map SVG, right stats.
 */
error_reporting(E_ALL);
ini_set("display_errors", 1);

$home_id = 18;
$data = get_post_meta($home_id, "_elementor_data", true);
$json = json_decode($data, true);

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

// World map SVG — dotted continents with glowing connection points
$map_svg = '<svg viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;opacity:0.35;">
  <defs>
    <radialGradient id="glow" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#F0C864" stop-opacity="1"/>
      <stop offset="60%" stop-color="#C9A84C" stop-opacity="0.6"/>
      <stop offset="100%" stop-color="#C9A84C" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="dotGlow" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#F0C864" stop-opacity="1"/>
      <stop offset="100%" stop-color="#C9A84C" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <!-- Dotted world map approximation -->
  <g fill="#3a5a8c" opacity="0.5">
    <!-- North America -->
    <circle cx="180" cy="120" r="1.5"/><circle cx="185" cy="118" r="1.5"/><circle cx="190" cy="116" r="1.5"/>
    <circle cx="175" cy="125" r="1.5"/><circle cx="180" cy="123" r="1.5"/><circle cx="185" cy="121" r="1.5"/>
    <circle cx="190" cy="119" r="1.5"/><circle cx="195" cy="117" r="1.5"/>
    <circle cx="170" cy="130" r="1.5"/><circle cx="175" cy="128" r="1.5"/><circle cx="180" cy="126" r="1.5"/>
    <circle cx="185" cy="124" r="1.5"/><circle cx="190" cy="122" r="1.5"/><circle cx="195" cy="120" r="1.5"/>
    <circle cx="165" cy="135" r="1.5"/><circle cx="170" cy="133" r="1.5"/><circle cx="175" cy="131" r="1.5"/>
    <circle cx="180" cy="129" r="1.5"/><circle cx="185" cy="127" r="1.5"/>
    <circle cx="160" cy="140" r="1.5"/><circle cx="165" cy="138" r="1.5"/><circle cx="170" cy="136" r="1.5"/>
    <circle cx="175" cy="134" r="1.5"/><circle cx="180" cy="132" r="1.5"/>
    <circle cx="155" cy="145" r="1.5"/><circle cx="160" cy="143" r="1.5"/><circle cx="165" cy="141" r="1.5"/>
    <circle cx="150" cy="150" r="1.5"/><circle cx="155" cy="148" r="1.5"/><circle cx="160" cy="146" r="1.5"/>
    <circle cx="145" cy="155" r="1.5"/><circle cx="150" cy="153" r="1.5"/>
    <!-- South America -->
    <circle cx="230" cy="230" r="1.5"/><circle cx="235" cy="228" r="1.5"/><circle cx="240" cy="226" r="1.5"/>
    <circle cx="225" cy="240" r="1.5"/><circle cx="230" cy="238" r="1.5"/><circle cx="235" cy="236" r="1.5"/>
    <circle cx="240" cy="234" r="1.5"/><circle cx="245" cy="232" r="1.5"/>
    <circle cx="220" cy="250" r="1.5"/><circle cx="225" cy="248" r="1.5"/><circle cx="230" cy="246" r="1.5"/>
    <circle cx="235" cy="244" r="1.5"/><circle cx="240" cy="242" r="1.5"/>
    <circle cx="215" cy="260" r="1.5"/><circle cx="220" cy="258" r="1.5"/><circle cx="225" cy="256" r="1.5"/>
    <circle cx="218" cy="270" r="1.5"/><circle cx="222" cy="268" r="1.5"/>
    <!-- Europe -->
    <circle cx="400" cy="100" r="1.5"/><circle cx="405" cy="98" r="1.5"/><circle cx="410" cy="96" r="1.5"/>
    <circle cx="395" cy="105" r="1.5"/><circle cx="400" cy="103" r="1.5"/><circle cx="405" cy="101" r="1.5"/>
    <circle cx="410" cy="99" r="1.5"/><circle cx="415" cy="97" r="1.5"/>
    <circle cx="390" cy="110" r="1.5"/><circle cx="395" cy="108" r="1.5"/><circle cx="400" cy="106" r="1.5"/>
    <circle cx="405" cy="104" r="1.5"/><circle cx="410" cy="102" r="1.5"/>
    <circle cx="385" cy="115" r="1.5"/><circle cx="390" cy="113" r="1.5"/><circle cx="395" cy="111" r="1.5"/>
    <!-- Africa -->
    <circle cx="410" cy="180" r="1.5"/><circle cx="415" cy="178" r="1.5"/><circle cx="420" cy="176" r="1.5"/>
    <circle cx="405" cy="190" r="1.5"/><circle cx="410" cy="188" r="1.5"/><circle cx="415" cy="186" r="1.5"/>
    <circle cx="420" cy="184" r="1.5"/><circle cx="425" cy="182" r="1.5"/>
    <circle cx="400" cy="200" r="1.5"/><circle cx="405" cy="198" r="1.5"/><circle cx="410" cy="196" r="1.5"/>
    <circle cx="415" cy="194" r="1.5"/><circle cx="420" cy="192" r="1.5"/>
    <circle cx="405" cy="210" r="1.5"/><circle cx="410" cy="208" r="1.5"/><circle cx="415" cy="206" r="1.5"/>
    <circle cx="408" cy="220" r="1.5"/><circle cx="412" cy="218" r="1.5"/>
    <!-- Middle East / UAE (brighter) -->
    <circle cx="480" cy="155" r="2" fill="#C9A84C" opacity="0.8"/>
    <circle cx="485" cy="153" r="2" fill="#C9A84C" opacity="0.7"/>
    <circle cx="475" cy="158" r="2" fill="#C9A84C" opacity="0.6"/>
    <!-- Asia -->
    <circle cx="520" cy="120" r="1.5"/><circle cx="525" cy="118" r="1.5"/><circle cx="530" cy="116" r="1.5"/>
    <circle cx="515" cy="130" r="1.5"/><circle cx="520" cy="128" r="1.5"/><circle cx="525" cy="126" r="1.5"/>
    <circle cx="530" cy="124" r="1.5"/><circle cx="535" cy="122" r="1.5"/>
    <circle cx="510" cy="140" r="1.5"/><circle cx="515" cy="138" r="1.5"/><circle cx="520" cy="136" r="1.5"/>
    <circle cx="525" cy="134" r="1.5"/><circle cx="530" cy="132" r="1.5"/>
    <circle cx="540" cy="150" r="1.5"/><circle cx="545" cy="148" r="1.5"/><circle cx="550" cy="146" r="1.5"/>
    <circle cx="535" cy="160" r="1.5"/><circle cx="540" cy="158" r="1.5"/><circle cx="545" cy="156" r="1.5"/>
    <circle cx="560" cy="140" r="1.5"/><circle cx="565" cy="138" r="1.5"/><circle cx="570" cy="136" r="1.5"/>
    <circle cx="555" cy="150" r="1.5"/><circle cx="560" cy="148" r="1.5"/>
    <circle cx="580" cy="160" r="1.5"/><circle cx="585" cy="158" r="1.5"/><circle cx="590" cy="156" r="1.5"/>
    <circle cx="575" cy="170" r="1.5"/><circle cx="580" cy="168" r="1.5"/>
    <!-- Australia -->
    <circle cx="620" cy="260" r="1.5"/><circle cx="625" cy="258" r="1.5"/><circle cx="630" cy="256" r="1.5"/>
    <circle cx="615" cy="270" r="1.5"/><circle cx="620" cy="268" r="1.5"/><circle cx="625" cy="266" r="1.5"/>
    <circle cx="630" cy="264" r="1.5"/><circle cx="635" cy="262" r="1.5"/>
    <circle cx="618" cy="280" r="1.5"/><circle cx="623" cy="278" r="1.5"/><circle cx="628" cy="276" r="1.5"/>
  </g>
  <!-- Connection lines from UAE to key cities -->
  <g stroke="#C9A84C" stroke-width="0.8" fill="none" opacity="0.3">
    <path d="M480,155 Q350,80 180,130"/>
    <path d="M480,155 Q380,100 400,105"/>
    <path d="M480,155 Q550,200 620,265"/>
    <path d="M480,155 Q520,180 545,150"/>
    <path d="M480,155 Q350,200 230,240"/>
  </g>
  <!-- Glowing dots at connection endpoints -->
  <g>
    <!-- UAE hub -->
    <circle cx="480" cy="155" r="12" fill="url(#glow)"/>
    <circle cx="480" cy="155" r="4" fill="#F0C864"/>
    <!-- USA -->
    <circle cx="180" cy="130" r="8" fill="url(#dotGlow)"/>
    <circle cx="180" cy="130" r="3" fill="#F0C84C"/>
    <!-- Europe -->
    <circle cx="400" cy="105" r="8" fill="url(#dotGlow)"/>
    <circle cx="400" cy="105" r="3" fill="#F0C84C"/>
    <!-- Asia -->
    <circle cx="545" cy="150" r="8" fill="url(#dotGlow)"/>
    <circle cx="545" cy="150" r="3" fill="#F0C84C"/>
    <!-- Australia -->
    <circle cx="620" cy="265" r="8" fill="url(#dotGlow)"/>
    <circle cx="620" cy="265" r="3" fill="#F0C84C"/>
    <!-- South America -->
    <circle cx="230" cy="240" r="8" fill="url(#dotGlow)"/>
    <circle cx="230" cy="240" r="3" fill="#F0C84C"/>
  </g>
</svg>';

// Set the HTML widget with map + left content + right stats
$html = '<div style="display:flex;align-items:center;justify-content:space-between;gap:2rem;position:relative;min-height:320px;">
  <!-- Left: Text content -->
  <div style="flex:0 0 35%;z-index:2;">
    <span style="display:block;font-size:0.7rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#C9A84C;margin-bottom:0.75rem;">GLOBAL REACH</span>
    <h2 style="font-size:clamp(28px,4vw,42px);font-weight:800;color:#FFFFFF;margin:0 0 1rem;line-height:1.1;font-family:Plus Jakarta Sans,sans-serif;">Trusted by Applicants Across the Globe</h2>
    <p style="font-size:15px;color:rgba(255,255,255,0.6);margin:0 0 1.5rem;line-height:1.6;max-width:400px;">We are a Dubai based visa agency serving residents of all nationalities across USA, UK, Canada, Australia and Europe.</p>
    <a href="/about" style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border:1px solid rgba(201,168,76,0.4);border-radius:10px;color:#C9A84C;font-size:14px;font-weight:600;text-decoration:none;transition:all 0.2s;background:transparent;">More About Us</a>
  </div>
  <!-- Center: Map -->
  <div style="flex:1 1 auto;z-index:1;display:flex;align-items:center;justify-content:center;overflow:hidden;">
    ' . $map_svg . '
  </div>
  <!-- Right: Stats -->
  <div style="flex:0 0 auto;z-index:2;display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div><div style="font-size:1.3rem;font-weight:800;color:#fff;font-family:Plus Jakarta Sans,sans-serif;">1,000+</div><div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Happy Travelers</div></div>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div><div style="font-size:1.3rem;font-weight:800;color:#fff;font-family:Plus Jakarta Sans,sans-serif;">99%</div><div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Approval Rate</div></div>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <div><div style="font-size:1.3rem;font-weight:800;color:#fff;font-family:Plus Jakarta Sans,sans-serif;">100+</div><div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Google Reviews</div></div>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
      </div>
      <div><div style="font-size:1.3rem;font-weight:800;color:#fff;font-family:Plus Jakarta Sans,sans-serif;">4.9/5</div><div style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Customer Rating</div></div>
    </div>
  </div>
</div>';

// Fix the corrupted widget
walk_set($json, "c29c344", "widget_type", "html");
walk_settings($json, "c29c344", ["editor" => $html]);

// Ensure the global reach container has proper padding
walk_settings($json, "6027592", [
    "padding" => ["unit" => "px", "top" => "60", "right" => "80", "bottom" => "60", "left" => "80", "isLinked" => false],
]);

$new_data = wp_slash(wp_json_encode($json));
update_post_meta($home_id, "_elementor_data", $new_data);

echo "DONE: Global reach widget restored with map SVG (" . strlen($html) . " chars)\n";
