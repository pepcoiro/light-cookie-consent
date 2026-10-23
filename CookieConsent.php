/**
 * Cookie Consent (CookieConsent v3) per WordPress — versione completa
 * Tema: Neve
 *
 * Integra:
 *  - Blocco script enqueued (script_loader_tag) + inline/hardcoded (output buffering)
 *  - Google Consent Mode v2 (default "denied" + update sui consensi)
 *  - data-service → toggle per singolo servizio nel pannello preferenze
 *  - Banner CookieConsent v3 localizzato (it) + pulsante flottante accessibile
 *  - Guardia se la libreria non è caricata
 *  - Asset local-preferred con fallback CDN + SRI opzionale
 *  - Pinnato alla v3.1.0
 */
if (!defined('ABSPATH')) {
    exit; // Evita l'accesso diretto
}
if (defined('NVCC_BOOTSTRAPPED')) {
    return; // Evita registrazioni duplicate se lo snippet viene incluso due volte
}
define('NVCC_BOOTSTRAPPED', true);

/* ==========================================================================
   0. CONFIGURAZIONE
   ========================================================================== */

if (!defined('NVCC_VERSION')) { define('NVCC_VERSION', '3.1.0'); } // ultima stabile verificata

/**
 * Google Consent Mode v2.
 * true  → i servizi Google (GTM / GA4 / Google Ads) NON vengono bloccati: caricano
 *         in modalità "cookieless" e ricevono i segnali di consenso via gtag().
 * false → anche i Google vengono bloccati con type="text/plain".
 */
if (!defined('NVCC_CONSENT_MODE')) { define('NVCC_CONSENT_MODE', true); }

/**
 * Regioni a cui applicare il default "denied" (ISO 3166-2, es. ['IT','DE','FR','ES']).
 * Array vuoto = "denied" in tutto il mondo (scelta più prudente per il GDPR).
 */
if (!defined('NVCC_CM_REGIONS')) { define('NVCC_CM_REGIONS', []); }

// SRI opzionale per gli asset CDN (se vuoto, integrity non viene stampato)
if (!defined('NVCC_SRI_CSS')) { define('NVCC_SRI_CSS', ''); }
if (!defined('NVCC_SRI_JS'))  { define('NVCC_SRI_JS', '');  }
// Dominio condiviso opzionale, ad esempio '.example.com'. Non si indovina il dominio registrabile.
if (!defined('NVCC_COOKIE_DOMAIN')) { define('NVCC_COOKIE_DOMAIN', ''); }

/**
 * Mappa servizio/categoria (in ORDINE: il primo pattern che matcha vince).
 * Uso hostname/percorsi reali degli script per ridurre i falsi positivi.
 */
function nvcc_service_map() {
    return [
        // ---- Google (i pattern più specifici prima) ----
        ['match' => 'gtm.js',               'cat' => 'analytics', 'service' => 'Google Tag Manager'],
        ['match' => 'gtag/js',              'cat' => 'analytics', 'service' => 'Google Analytics'],
        ['match' => 'googletagmanager',     'cat' => 'analytics', 'service' => 'Google Tag Manager'],
        ['match' => 'google-analytics.com', 'cat' => 'analytics', 'service' => 'Google Analytics'],
        ['match' => 'google-analytics',     'cat' => 'analytics', 'service' => 'Google Analytics'],
        ['match' => 'googlesyndication',    'cat' => 'marketing', 'service' => 'Google Ads'],
        ['match' => 'googleadservices',     'cat' => 'marketing', 'service' => 'Google Ads'],
        ['match' => 'doubleclick',          'cat' => 'marketing', 'service' => 'Google Ads'],
        ['match' => 'google-ads',           'cat' => 'marketing', 'service' => 'Google Ads'],
        // ---- Analytics ----
        ['match' => 'clarity.ms',           'cat' => 'analytics', 'service' => 'Microsoft Clarity'],
        ['match' => 'clarity',              'cat' => 'analytics', 'service' => 'Microsoft Clarity'],
        ['match' => 'matomo',               'cat' => 'analytics', 'service' => 'Matomo'],
        ['match' => 'plausible',            'cat' => 'analytics', 'service' => 'Plausible'],
        ['match' => 'umami',                'cat' => 'analytics', 'service' => 'Umami'],
        ['match' => 'hotjar',               'cat' => 'analytics', 'service' => 'Hotjar'],
        ['match' => 'fathom',               'cat' => 'analytics', 'service' => 'Fathom'],
        ['match' => 'rudderstack',          'cat' => 'analytics', 'service' => 'RudderStack'],
        // ---- Marketing ----
        ['match' => 'connect.facebook.net', 'cat' => 'marketing', 'service' => 'Meta Pixel'],
        ['match' => 'fbevents',             'cat' => 'marketing', 'service' => 'Meta Pixel'],
        ['match' => 'fbq',                  'cat' => 'marketing', 'service' => 'Meta Pixel'],
        ['match' => 'bat.bing',             'cat' => 'marketing', 'service' => 'Microsoft Ads'],
        ['match' => 'snap.licdn',           'cat' => 'marketing', 'service' => 'LinkedIn Insight Tag'],
        ['match' => 'licdn.com',            'cat' => 'marketing', 'service' => 'LinkedIn Insight Tag'],
        ['match' => 'analytics.tiktok',     'cat' => 'marketing', 'service' => 'TikTok Pixel'],
        ['match' => 'ct.pinterest',         'cat' => 'marketing', 'service' => 'Pinterest Tag'],
        ['match' => 'sc-static.net',        'cat' => 'marketing', 'service' => 'Snapchat Pixel'],
        ['match' => 'hubspot',              'cat' => 'marketing', 'service' => 'HubSpot'],
        ['match' => 'reddit',               'cat' => 'marketing', 'service' => 'Reddit Pixel'],
        ['match' => 'tiktok',               'cat' => 'marketing', 'service' => 'TikTok Pixel'],
        ['match' => 'pinterest',            'cat' => 'marketing', 'service' => 'Pinterest Tag'],
        ['match' => 'linkedin',             'cat' => 'marketing', 'service' => 'LinkedIn Insight Tag'],
    ];
}

/**
 * Marker per script INLINE (più stretti, con servizio associato).
 */
function nvcc_inline_map() {
    return [
        ['pattern' => '/googletagmanager/i',        'cat' => 'analytics', 'service' => 'Google Tag Manager'],
        ['pattern' => '/datalayer\s*\.\s*push/i', 'cat' => 'analytics', 'service' => 'Google Tag Manager'],
        ['pattern' => '/\bgtag\s*\(/i',           'cat' => 'analytics', 'service' => 'Google Analytics'],
        ['pattern' => '/\bga\s*\(/i',             'cat' => 'analytics', 'service' => 'Google Analytics'],
        ['pattern' => '/\bclarity\s*\(/i',        'cat' => 'analytics', 'service' => 'Microsoft Clarity'],
        ['pattern' => '/\b_paq\s*\.\s*push/i',   'cat' => 'analytics', 'service' => 'Matomo'],
        ['pattern' => '/\bplausible\s*\(/i',      'cat' => 'analytics', 'service' => 'Plausible'],
        ['pattern' => '/\bumami\s*\./i',          'cat' => 'analytics', 'service' => 'Umami'],
        ['pattern' => '/\bfbq\s*\(/i',            'cat' => 'marketing', 'service' => 'Meta Pixel'],
        ['pattern' => '/\b_fbq\b/i',               'cat' => 'marketing', 'service' => 'Meta Pixel'],
        ['pattern' => '/\bttq\s*\./i',            'cat' => 'marketing', 'service' => 'TikTok Pixel'],
        ['pattern' => '/\bsnaptr\s*\(/i',         'cat' => 'marketing', 'service' => 'Snapchat Pixel'],
        ['pattern' => '/\bpintrk\s*\(/i',         'cat' => 'marketing', 'service' => 'Pinterest Tag'],
        ['pattern' => '/\btwq\s*\(/i',            'cat' => 'marketing', 'service' => 'Twitter Pixel'],
        ['pattern' => '/\blintrk\s*\(/i',         'cat' => 'marketing', 'service' => 'LinkedIn Insight Tag'],
        ['pattern' => '/\bhj\s*\(/i',             'cat' => 'analytics', 'service' => 'Hotjar'],
    ];
}

/**
 * Rileva categoria + servizio. Restituisce ['cat'=>..., 'service'=>...] oppure null.
 */
function nvcc_detect($haystack, $map = null) {
    $haystack = strtolower((string) $haystack);
    if ($haystack === '') return null;
    $map = $map ?: nvcc_service_map();
    foreach ($map as $row) {
        $matched = isset($row['pattern'])
            ? preg_match($row['pattern'], $haystack) === 1
            : strpos($haystack, strtolower($row['match'])) !== false;
        if ($matched) return ['cat' => $row['cat'], 'service' => $row['service']];
    }
    return null;
}

/** Rileva tutti i servizi presenti in uno snippet inline. */
function nvcc_detect_all_inline($haystack) {
    $haystack = (string) $haystack;
    if ($haystack === '') return [];
    $found = [];
    foreach (nvcc_inline_map() as $row) {
        if (preg_match($row['pattern'], $haystack) === 1) {
            $found[$row['service']] = ['cat' => $row['cat'], 'service' => $row['service']];
        }
    }
    return array_values($found);
}

/** Consent Mode lascia liberi solo gli snippet composti esclusivamente da servizi Google. */
function nvcc_all_google_services($detections) {
    if (!$detections) return false;
    foreach ($detections as $detected) {
        if (!nvcc_is_consent_mode_service($detected['service'])) return false;
    }
    return true;
}

/**
 * I servizi Google sono gestiti da Consent Mode: in quel caso non li blocchiamo.
 */
function nvcc_is_consent_mode_service($service) {
    return in_array($service, ['Google Tag Manager', 'Google Analytics', 'Google Ads'], true);
}

/**
 * Trasforma un <script> in uno gestito da CookieConsent.
 */
function nvcc_to_managed_tag($tag, $cat, $service) {
    $tag = preg_replace('/\stype=(["\'])[^"\']*\1/i', '', $tag);
    $attrs = 'type="text/plain" data-category="' . esc_attr($cat) . '"';
    if ($service) {
        $attrs .= ' data-service="' . esc_attr($service) . '"';
    }
    return preg_replace('/<script/i', '<script ' . $attrs, $tag, 1);
}

/* ==========================================================================
   2. GOOGLE CONSENT MODE v2 — default "denied" (il più presto possibile)
   --------------------------------------------------------------------------
   Deve girare PRIMA di GTM/gtag. Se il container è hardcoded prima di wp_head,
   copia questo blocco a mano in cima al <head> del tema.
   ========================================================================== */
add_action('wp_head', 'nvcc_consent_mode_default', 1);
function nvcc_consent_mode_default() {
    if (is_admin() || !NVCC_CONSENT_MODE) return;

    $region = NVCC_CM_REGIONS;
    ?>
<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" nowprocket data-cc-skip>
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  gtag('consent', 'default', {
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'analytics_storage': 'denied',
    'personalization_storage': 'denied',
    'functionality_storage': 'granted',
    'security_storage': 'granted',
    'wait_for_update': 500<?php if (!empty($region)) {
        echo ",\n    'region': " . wp_json_encode(array_values($region));
    } ?>
  });
  // Ripristina subito il consenso salvato, prima che GTM/gtag invii la prima pageview.
  try {
    var match = document.cookie.match(/(?:^|;\s*)cc_cookie=([^;]*)/);
    if (match) {
      var saved = JSON.parse(decodeURIComponent(match[1]));
      var levels = Array.isArray(saved.level) ? saved.level : (Array.isArray(saved.categories) ? saved.categories : []);
      var analytics = levels.indexOf('analytics') !== -1;
      var marketing = levels.indexOf('marketing') !== -1;
      gtag('consent', 'update', {
        'analytics_storage': analytics ? 'granted' : 'denied',
        'personalization_storage': marketing ? 'granted' : 'denied',
        'ad_storage': marketing ? 'granted' : 'denied',
        'ad_user_data': marketing ? 'granted' : 'denied',
        'ad_personalization': marketing ? 'granted' : 'denied'
      });
    }
  } catch (e) {}
  gtag('set', 'ads_data_redaction', true);
</script>
    <?php
}

/* ==========================================================================
   3a. BLOCCO SCRIPT ENQUEUED (script_loader_tag)
   ========================================================================== */
add_filter('script_loader_tag', 'nvcc_block_enqueued_scripts', 99, 3);
function nvcc_block_enqueued_scripts($tag, $handle, $src) {
    if (is_admin()) return $tag;
    if (strpos($tag, 'data-category=') !== false) return $tag; // già gestito

    $detected = nvcc_detect($handle . ' ' . $src);
    if (!$detected) return $tag;

    // Con Consent Mode attivo, i servizi Google caricano in "cookieless".
    if (NVCC_CONSENT_MODE && nvcc_is_consent_mode_service($detected['service'])) {
        return $tag;
    }

    return nvcc_to_managed_tag($tag, $detected['cat'], $detected['service']);
}

/* ==========================================================================
   3b. BLOCCO SCRIPT INLINE / HARDCODED (output buffering)
   ========================================================================== */
add_action('template_redirect', 'nvcc_start_output_buffer');
function nvcc_start_output_buffer() {
    if (is_admin() || is_feed() || wp_doing_ajax()
        || (function_exists('is_customize_preview') && is_customize_preview())
        || (function_exists('is_preview') && is_preview())
        || (function_exists('is_embed') && is_embed())
        || (function_exists('is_robots') && is_robots())
        || (function_exists('wp_is_json_request') && wp_is_json_request())
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }
    // NB: verifica eventuali conflitti con plugin di cache/minify che lavorano sull'output.
    ob_start('nvcc_rewrite_inline_scripts');
}

function nvcc_rewrite_inline_scripts($html) {
    if (!is_string($html) || stripos($html, '</head>') === false || stripos($html, '<html') === false) {
        return $html;
    }

    $out = preg_replace_callback(
        '#<script\b([^>]*)>(.*?)</script>#is',
        function ($m) {
            $attrs   = $m[1];
            $content = $m[2];
            $tag     = $m[0];
            $lower   = strtolower($attrs . ' ' . $content);

            // Salta script già gestiti, la libreria di consenso e i nostri script
            if (strpos($lower, 'data-category=') !== false) return $tag;
            if (strpos($lower, 'cookieconsent') !== false)   return $tag;
            if (strpos($lower, 'cc-main') !== false)         return $tag;
            if (strpos($lower, 'data-cc-skip') !== false) return $tag;

            // 1) Script con src
            if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $src)) {
                $detected = nvcc_detect($attrs . ' ' . $src[2]);
                if (!$detected) return $tag;

                if (NVCC_CONSENT_MODE && nvcc_is_consent_mode_service($detected['service'])) {
                    return $tag;
                }

                return nvcc_to_managed_tag($tag, $detected['cat'], $detected['service']);
            }

            // 2) Script inline
            $detections = nvcc_detect_all_inline($content);
            if (!$detections) return $tag;

            // Consent Mode lascia liberi esclusivamente gli snippet con servizi tutti Google.
            if (NVCC_CONSENT_MODE && nvcc_all_google_services($detections)) return $tag;

            $services = array_column($detections, 'service');
            $categories = array_column($detections, 'cat');
            // In uno snippet misto prevale la categoria più restrittiva.
            $category = in_array('marketing', $categories, true) ? 'marketing' : 'analytics';
            $serviceAttr = count($services) === 1
                ? ' data-service="' . esc_attr($services[0]) . '"'
                : '';

            $clean = preg_replace('/\stype=(["\'])[^"\']*\1/i', '', $attrs);
            return '<script type="text/plain" data-category="' . esc_attr($category) . '"'
                . $serviceAttr . $clean . '>' . $content . '</script>';
        },
        $html
    );

    // Un errore PCRE non deve svuotare la pagina HTML.
    if ($out === null) return $html;
    $out = nvcc_block_known_iframes($out);
    $out = nvcc_block_noscript_pixels($out);
    return $out;
}


/** Blocca iframe noti fino al consenso marketing: CookieConsent v3 non gestisce iframe nativamente. */
function nvcc_block_known_iframes($html) {
    $result = preg_replace_callback(
        '#<iframe\b([^>]*)>(.*?)</iframe>#is',
        function ($m) {
            $attrs = $m[1];
            $body  = $m[2];
            if (stripos($attrs, 'data-nvcc-src=') !== false) return $m[0];
            if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $src)) return $m[0];

            $url = html_entity_decode($src[2], ENT_QUOTES, 'UTF-8');
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (!$host) return $m[0];

            $isVideo = preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com)$/i', $host) === 1;
            $isMaps = preg_match('/(^|\.)(google\.com|google\.[a-z.]+|googleusercontent\.com)$/i', $host) === 1
                && (stripos($url, '/maps') !== false || stripos($url, 'maps/embed') !== false);
            if (!$isVideo && !$isMaps) return $m[0];

            $safeUrl = esc_attr($url);
            $cleanAttrs = preg_replace('/\s+src\s*=\s*(["\'])(.*?)\1/i', '', $attrs);
            $iframe = '<iframe' . $cleanAttrs . ' src="about:blank" data-nvcc-src="' . $safeUrl . '" data-nvcc-embed="1" aria-label="Contenuto esterno bloccato in attesa del consenso">' . $body . '</iframe>';
            return '<div class="nvcc-embed-container" data-nvcc-container="1"><div class="nvcc-embed-placeholder">Per visualizzare questo contenuto esterno, accetta i cookie di marketing. <button type="button" data-cc="show-preferencesModal">Gestisci preferenze</button></div>' . $iframe . '</div>';
        },
        $html
    );
    return $result === null ? $html : $result;
}

/** Elimina pixel di tracciamento riconosciuti dentro noscript, anche se JavaScript è disattivato. */
function nvcc_block_noscript_pixels($html) {
    $trackers = '(?:connect\.facebook\.net|facebook\.com/tr|google-analytics\.com|googletagmanager\.com|googleadservices\.com|doubleclick\.net|googlesyndication\.com|clarity\.ms|hotjar\.com|analytics\.tiktok\.com|tiktok\.com|snap\.licdn\.com|linkedin\.com|ct\.pinterest\.com|pinterest\.com|sc-static\.net|reddit\.com|bat\.bing\.com)';
    $result = preg_replace_callback(
        '#<noscript\b[^>]*>(.*?)</noscript>#is',
        function ($m) use ($trackers) {
            $content = preg_replace_callback(
                '#<img\b[^>]*>#is',
                function ($img) use ($trackers) {
                    return preg_match('/\bsrc\s*=\s*(["\'])[^"\']*(?:' . $trackers . ')[^"\']*\1/i', $img[0])
                        ? ''
                        : $img[0];
                },
                $m[1]
            );
            return '<noscript>' . ($content === null ? $m[1] : $content) . '</noscript>';
        },
        $html
    );
    return $result === null ? $html : $result;
}

/* ==========================================================================
   4. RISOLUZIONE ASSET (locale preferito, fallback CDN)
   ========================================================================== */
function nvcc_asset($file) {
    $rel    = 'cookieconsent/' . $file;
    $child  = get_stylesheet_directory() . '/' . $rel;
    $parent = get_template_directory() . '/' . $rel;

    if (file_exists($child)) {
        return ['url' => get_stylesheet_directory_uri() . '/' . $rel . '?ver=' . filemtime($child), 'integrity' => ''];
    }
    if (file_exists($parent)) {
        return ['url' => get_template_directory_uri() . '/' . $rel . '?ver=' . filemtime($parent), 'integrity' => ''];
    }

    $is_css = (strpos($file, '.css') !== false);
    return [
        'url'       => 'https://cdn.jsdelivr.net/gh/orestbida/cookieconsent@' . NVCC_VERSION . '/dist/' . $file,
        'integrity' => $is_css ? NVCC_SRI_CSS : NVCC_SRI_JS,
    ];
}

/** Registra gli asset con WordPress per mantenere ordine, versioning e compatibilità. */
add_action('wp_enqueue_scripts', 'nvcc_enqueue_assets', 1);
function nvcc_enqueue_assets() {
    if (is_admin()) return;

    $css = nvcc_asset('cookieconsent.css');
    $js  = nvcc_asset('cookieconsent.umd.js');
    wp_enqueue_style('nvcc-cookieconsent', $css['url'], [], null);
    wp_enqueue_script('nvcc-cookieconsent', $js['url'], [], null, false);

    $inlineCss = <<<'NVCCCSS'
  /* ---- Colori banner: variabili tema Neve (con fallback) ---- */
  #cc-main {
    --cc-bg: var(--nv-site-bg, #ffffff) !important;
    --cc-primary-color: var(--nv-primary-accent, #222222) !important;
    --cc-secondary-color: var(--nv-text-color, #555555) !important;
    --cc-btn-primary-bg: var(--nv-primary-accent, #0366d6) !important;
    --cc-btn-primary-color: var(--nv-text-dark-bg, #ffffff) !important;
    --cc-btn-primary-hover-bg: var(--nv-secondary-accent, #000000) !important;
    --cc-btn-primary-hover-color: var(--nv-text-dark-bg, #ffffff) !important;
    --cc-btn-secondary-bg: var(--nv-light-bg, #f4f4f4) !important;
    --cc-btn-secondary-color: #222222 !important;
    --cc-btn-secondary-hover-bg: var(--nv-primary-accent, #0366d6) !important;
    --cc-btn-secondary-hover-color: var(--nv-text-dark-bg, #ffffff) !important;
    --cc-toggle-on-bg: var(--nv-primary-accent, #0366d6) !important;
    --cc-separator-border-color: var(--nv-light-bg, #e5e5e5) !important;
    --cc-cookie-category-block-bg: var(--nv-light-bg, #f9f9f9) !important;
    --cc-cookie-category-block-hover-bg: var(--nv-light-bg, #f0f0f0) !important;
  }

  /* ---- Pulsante flottante (basso-destra) ---- */
  #cc-rewrite-btn {
    all: unset !important;
    position: fixed !important;
    bottom: var(--nvcc-button-bottom, 20px) !important;
    right: var(--nvcc-button-right, 20px) !important;
    left: auto !important;
    z-index: 9999999 !important;
    width: 42px !important;
    height: 42px !important;
    border-radius: 50% !important;
    background-color: var(--nv-primary-accent, #111111) !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    box-sizing: border-box !important;
    transition: transform 0.2s ease, background-color 0.2s ease !important;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15) !important;
  }
  #cc-rewrite-btn:hover {
    background-color: var(--nv-secondary-accent, #000000) !important;
    transform: scale(1.08) !important;
  }
  #cc-rewrite-btn:active { border: none !important; }
  #cc-rewrite-btn:focus { outline: none !important; }
  #cc-rewrite-btn:focus-visible {
    outline: 3px solid var(--nv-primary-accent, #0366d6) !important;
    outline-offset: 3px !important;
  }
  #cc-rewrite-btn svg {
    width: 20px !important;
    height: 20px !important;
    fill: none !important;
    stroke: #ffffff !important;
    stroke-width: 2px !important;
    stroke-linecap: round !important;
    stroke-linejoin: round !important;
    display: block !important;
  }
NVCCCSS;
    $inlineCss .= "\n.nvcc-embed-container { position: relative; width: 100%; max-width: 100%; }\n.nvcc-embed-container iframe[data-nvcc-embed=\"1\"] { display: none; max-width: 100%; }\n.nvcc-embed-placeholder { box-sizing: border-box; min-height: 220px; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 24px; text-align: center; background: var(--nv-light-bg, #f4f4f4); color: var(--nv-text-color, #333); }\n.nvcc-embed-placeholder button { cursor: pointer; padding: 8px 14px; border: 0; border-radius: 4px; background: var(--nv-primary-accent, #0366d6); color: var(--nv-text-dark-bg, #fff); }\n.nvcc-embed-container.nvcc-embed-active iframe[data-nvcc-embed=\"1\"] { display: block; width: 100%; }\n.nvcc-embed-container.nvcc-embed-active .nvcc-embed-placeholder { display: none; }\n";
    wp_add_inline_style('nvcc-cookieconsent', $inlineCss);
}

add_filter('style_loader_tag', 'nvcc_cookieconsent_style_tag', 10, 4);
function nvcc_cookieconsent_style_tag($html, $handle, $href, $media) {
    if ($handle !== 'nvcc-cookieconsent') return $html;
    $asset = nvcc_asset('cookieconsent.css');
    if (!empty($asset['integrity'])) {
        $html = str_replace('<link ', '<link integrity="' . esc_attr($asset['integrity']) . '" crossorigin="anonymous" ', $html);
    }
    return $html;
}

add_filter('script_loader_tag', 'nvcc_cookieconsent_asset_tag', 10, 3);
function nvcc_cookieconsent_asset_tag($tag, $handle, $src) {
    if ($handle !== 'nvcc-cookieconsent') return $tag;
    $asset = nvcc_asset('cookieconsent.umd.js');
    $attrs = ' data-cfasync="false" data-no-optimize="1" data-no-defer="1" nowprocket defer';
    if (!empty($asset['integrity'])) {
        $attrs .= ' integrity="' . esc_attr($asset['integrity']) . '" crossorigin="anonymous"';
    }
    return preg_replace('/<script\\b/i', '<script' . $attrs, $tag, 1);
}


/* ==========================================================================
   5. BANNER COOKIECONSENT V3 + STILI TEMA NEVE
   ========================================================================== */
add_action('wp_head', 'nvcc_render_head', 8);
function nvcc_render_head() {
    if (is_admin() || !wp_script_is('nvcc-cookieconsent', 'enqueued')) return;

    ob_start();
    ?>


<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" nowprocket>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof CookieConsent === 'undefined') {
        console.warn('[CookieConsent] libreria non caricata: banner non attivo.');
        var btn = document.getElementById('cc-rewrite-btn');
        if (btn) { btn.style.display = 'none'; }
        return;
    }

    var configuredCookieDomain = <?php echo wp_json_encode(NVCC_COOKIE_DOMAIN); ?>;
    var domainRoot = configuredCookieDomain || window.location.hostname.replace(/^www\./, '');

    // --- Google Consent Mode v2: aggiorna i segnali in base alle scelte ---
    function syncThirdPartyIframes() {
        var allowed = CookieConsent.acceptedCategory('marketing');
        document.querySelectorAll('iframe[data-nvcc-embed="1"]').forEach(function (frame) {
            var wrapper = frame.closest('[data-nvcc-container="1"]');
            if (allowed) {
                var savedSrc = frame.getAttribute('data-nvcc-src');
                if (savedSrc && frame.getAttribute('src') === 'about:blank') frame.setAttribute('src', savedSrc);
                if (wrapper) wrapper.classList.add('nvcc-embed-active');
            } else {
                if (frame.getAttribute('src') !== 'about:blank') frame.setAttribute('src', 'about:blank');
                if (wrapper) wrapper.classList.remove('nvcc-embed-active');
            }
        });
    }

    function pushConsentConsentMode() {
        syncThirdPartyIframes();
        if (typeof gtag !== 'function') return;
        var analytics = CookieConsent.acceptedCategory('analytics');
        var marketing = CookieConsent.acceptedCategory('marketing');
        gtag('consent', 'update', {
            'analytics_storage':  analytics ? 'granted' : 'denied',
            'personalization_storage': marketing ? 'granted' : 'denied',
            'ad_storage':         marketing ? 'granted' : 'denied',
            'ad_user_data':       marketing ? 'granted' : 'denied',
            'ad_personalization': marketing ? 'granted' : 'denied'
        });
    }

    CookieConsent.run({
        onConsent: pushConsentConsentMode, // al caricamento (consenso già espresso)
        onChange:  pushConsentConsentMode, // a ogni modifica delle preferenze

        categories: {
            necessary: { enabled: true, readOnly: true },
            analytics: {
                enabled: false,
                services: {
                    'Google Tag Manager': { label: 'Google Tag Manager' },
                    'Google Analytics': { label: 'Google Analytics' },
                    'Microsoft Clarity': { label: 'Microsoft Clarity' },
                    'Matomo': { label: 'Matomo' },
                    'Plausible': { label: 'Plausible' },
                    'Umami': { label: 'Umami' },
                    'Hotjar': { label: 'Hotjar' },
                    'Fathom': { label: 'Fathom' },
                    'RudderStack': { label: 'RudderStack' }
                },
                autoClear: {
                    cookies: [
                        { name: /^_ga/,  path: '/', domain: domainRoot },
                        { name: /^_gid/, path: '/', domain: domainRoot },
                        { name: /^_gat/, path: '/', domain: domainRoot },
                        { name: /^_cl/,  path: '/', domain: domainRoot },
                        { name: /^_pk_/, path: '/', domain: domainRoot },
                        { name: /^ph_/,  path: '/', domain: domainRoot },
                        { name: /^_hj/,  path: '/', domain: domainRoot }
                    ]
                }
            },
            marketing: {
                enabled: false,
                services: {
                    'Google Ads': { label: 'Google Ads' },
                    'Meta Pixel': { label: 'Meta Pixel' },
                    'Microsoft Ads': { label: 'Microsoft Ads' },
                    'LinkedIn Insight Tag': { label: 'LinkedIn Insight Tag' },
                    'TikTok Pixel': { label: 'TikTok Pixel' },
                    'Pinterest Tag': { label: 'Pinterest Tag' },
                    'Snapchat Pixel': { label: 'Snapchat Pixel' },
                    'HubSpot': { label: 'HubSpot' },
                    'Reddit Pixel': { label: 'Reddit Pixel' },
                    'Twitter Pixel': { label: 'Twitter Pixel' }
                },
                autoClear: {
                    cookies: [
                        { name: /^_fbp/, path: '/', domain: domainRoot },
                        { name: /^_fbc/, path: '/', domain: domainRoot },
                        { name: /^_gcl/, path: '/', domain: domainRoot },
                        { name: /^_uet/, path: '/', domain: domainRoot },
                        { name: /^_pin_/,path: '/', domain: domainRoot },
                        { name: /^_ttp/, path: '/', domain: domainRoot },
                        { name: /^_scid/,path: '/', domain: domainRoot },
                        { name: 'li_fat_id', path: '/', domain: domainRoot }
                    ]
                }
            }
        },

        language: {
            default: <?php echo wp_json_encode(apply_filters('nvcc_language', 'it')); ?>,
            translations: {
                it: {
                    consentModal: {
                        title: 'Informativa sui Cookie',
                        description: <?php echo wp_json_encode(apply_filters('nvcc_consent_description', 'Utilizziamo cookie tecnici necessari e, previo consenso, strumenti di analisi e marketing.')); ?> + <?php $privacyUrl = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : ''; echo wp_json_encode($privacyUrl ? ' <a href="' . esc_url($privacyUrl) . '" target="_blank" rel="noopener">Informativa sulla privacy</a>' : ''); ?>,
                        acceptAllBtn: 'Accetta tutti',
                        acceptNecessaryBtn: 'Rifiuta tutti',
                        showPreferencesBtn: 'Personalizza'
                    },
                    preferencesModal: {
                        title: 'Gestione Preferenze Cookie',
                        acceptAllBtn: 'Accetta tutti',
                        acceptNecessaryBtn: 'Rifiuta tutti',
                        savePreferencesBtn: 'Salva preferenze',
                        closeIconLabel: 'Chiudi',
                        serviceCounterLabel: 'Servizi',
                        sections: [
                            { title: 'Strettamente Necessari', description: 'Cookie tecnici indispensabili per il funzionamento del sito.', linkedCategory: 'necessary' },
                            { title: 'Statistiche e Analisi', description: 'Cookie per comprendere l\'utilizzo del sito in forma aggregata.', linkedCategory: 'analytics' },
                            { title: 'Marketing e Profilazione', description: 'Cookie per la personalizzazione dei contenuti pubblicitari.', linkedCategory: 'marketing' }
                        ]
                    }
                }
            }
        }
    });
});
</script>
    <?php
    $inlineScript = ob_get_clean();
    $inlineScript = preg_replace('#^\s*<script\b[^>]*>|</script>\s*$#i', '', $inlineScript);
    if (is_string($inlineScript) && trim($inlineScript) !== '') {
        wp_add_inline_script('nvcc-cookieconsent', trim($inlineScript), 'after');
    }
}

/* ==========================================================================
   6. PULSANTE FLUTTUANTE NEL FOOTER
   ========================================================================== */
add_action('wp_footer', 'nvcc_render_footer_button', 10);
function nvcc_render_footer_button() {
    if (is_admin()) return;
    ?>
<button type="button" id="cc-rewrite-btn" data-cc-skip
        data-cc="show-preferencesModal"
        title="Gestisci Preferenze Cookie" aria-label="Gestisci Preferenze Cookie">
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path d="M12 2a10 10 0 1 0 10 10 3.5 3.5 0 0 1-4.5-4.5A3.5 3.5 0 0 1 12 2z" />
    <circle cx="8.5" cy="8.5" r="1" />
    <circle cx="7" cy="14.5" r="1" />
    <circle cx="12" cy="15.5" r="1" />
    <circle cx="15" cy="11" r="1" />
  </svg>
</button>
    <?php
}
