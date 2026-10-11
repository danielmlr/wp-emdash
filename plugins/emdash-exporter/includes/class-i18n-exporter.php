<?php
/**
 * i18n Exporter
 *
 * Detects WPML / Polylang and exposes per-post locale and translation group
 * information in the format the EmDash importer expects:
 * - site info:  { plugin: "wpml"|"polylang", default_locale, locales }
 * - per post:   locale (BCP 47), translation_group (stable string ID)
 */

defined('ABSPATH') || exit;

class EmDash_I18n_Exporter {

    /**
     * Which multilingual plugin is active, if any.
     *
     * @return string|null 'wpml', 'polylang', or null
     */
    public static function active_plugin() {
        if (defined('ICL_SITEPRESS_VERSION')) {
            return 'wpml';
        }
        if (function_exists('pll_languages_list')) {
            return 'polylang';
        }
        return null;
    }

    /**
     * Site-level i18n info, or null when no multilingual plugin is active.
     *
     * @return array|null { plugin, default_locale, locales }
     */
    public static function site_info() {
        $plugin = self::active_plugin();
        if ($plugin === null) {
            return null;
        }

        if ($plugin === 'wpml') {
            $languages = apply_filters('wpml_active_languages', null);
            $locales = [];
            if (is_array($languages)) {
                foreach ($languages as $lang) {
                    $locale = isset($lang['default_locale']) ? $lang['default_locale'] : $lang['code'];
                    $locales[] = self::to_bcp47($locale);
                }
            }
            $default_code = apply_filters('wpml_default_language', null);
            $default_locale = $default_code;
            if (is_array($languages) && $default_code && isset($languages[$default_code]['default_locale'])) {
                $default_locale = $languages[$default_code]['default_locale'];
            }

            return [
                'plugin' => 'wpml',
                'default_locale' => self::to_bcp47($default_locale ?: get_locale()),
                'locales' => $locales,
            ];
        }

        // Polylang
        $locales = [];
        $slugs = pll_languages_list(['fields' => 'slug']);
        foreach ((array) $slugs as $slug) {
            $locales[] = self::polylang_locale($slug);
        }
        $default_slug = function_exists('pll_default_language') ? pll_default_language('slug') : null;

        return [
            'plugin' => 'polylang',
            'default_locale' => $default_slug ? self::polylang_locale($default_slug) : self::to_bcp47(get_locale()),
            'locales' => $locales,
        ];
    }

    /**
     * Per-post i18n fields, or [] when not applicable.
     *
     * @param int $post_id
     * @return array May contain 'locale' and 'translation_group'.
     */
    public static function post_info($post_id) {
        $plugin = self::active_plugin();
        if ($plugin === null) {
            return [];
        }

        if ($plugin === 'wpml') {
            $data = [];
            $details = apply_filters('wpml_post_language_details', null, $post_id);
            if (is_array($details) && !empty($details['locale'])) {
                $data['locale'] = self::to_bcp47($details['locale']);
            }
            $post_type = get_post_type($post_id);
            $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . $post_type);
            if ($trid) {
                $data['translation_group'] = 'wpml-' . $trid;
            }
            return $data;
        }

        // Polylang
        $data = [];
        if (function_exists('pll_get_post_language')) {
            $slug = pll_get_post_language($post_id, 'slug');
            if ($slug) {
                $data['locale'] = self::polylang_locale($slug);
            }
        }
        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($post_id);
            if (is_array($translations) && count($translations) > 0) {
                // ponytail: smallest post ID in the group is a stable group key
                // without needing Polylang's internal term IDs.
                $data['translation_group'] = 'pll-' . min(array_map('intval', $translations));
            }
        }
        return $data;
    }

    /**
     * Full BCP 47 locale for a Polylang language slug (e.g. 'de' -> 'de-DE').
     */
    private static function polylang_locale($slug) {
        if (function_exists('PLL')) {
            $language = PLL()->model->get_language($slug);
            if ($language && !empty($language->locale)) {
                return self::to_bcp47($language->locale);
            }
        }
        return self::to_bcp47($slug);
    }

    /**
     * WordPress locale ('de_DE') to BCP 47 ('de-DE').
     */
    private static function to_bcp47($locale) {
        return str_replace('_', '-', (string) $locale);
    }
}
