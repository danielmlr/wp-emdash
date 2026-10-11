<?php
/**
 * Menu Exporter
 *
 * Exports navigation menus in the format EmDash's importMenusFromPlugin()
 * expects (see packages/core/src/import/menus.ts, PluginMenu/PluginMenuItem).
 */

defined('ABSPATH') || exit;

class EmDash_Menu_Exporter {

    /**
     * Get all navigation menus with their items.
     *
     * @return array[] PluginMenu-shaped arrays
     */
    public function get_menus() {
        $menus = wp_get_nav_menus();
        $result = [];

        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]);
            if ($items === false) {
                $items = [];
            }

            $result[] = [
                'id' => (int) $menu->term_id,
                'name' => $menu->slug,
                'label' => $menu->name,
                'locations' => $this->menu_locations($menu->term_id),
                'items' => array_map([$this, 'format_item'], $items),
            ];
        }

        return $result;
    }

    /**
     * Theme locations this menu is assigned to (e.g. ['primary']).
     */
    private function menu_locations($menu_id) {
        $locations = [];
        foreach ((array) get_nav_menu_locations() as $location => $assigned_id) {
            if ((int) $assigned_id === (int) $menu_id) {
                $locations[] = $location;
            }
        }
        return $locations;
    }

    /**
     * Format a single nav menu item (WP_Post decorated by wp_setup_nav_menu_item).
     */
    private function format_item($item) {
        $type = $item->type; // 'custom' | 'post_type' | 'taxonomy' | 'post_type_archive'
        if (!in_array($type, ['custom', 'post_type', 'taxonomy'], true)) {
            // ponytail: archives etc. degrade to a custom link; EmDash menus
            // have no archive item type.
            $type = 'custom';
        }

        return [
            'id' => (int) $item->ID,
            'parent_id' => $item->menu_item_parent ? (int) $item->menu_item_parent : null,
            'sort_order' => (int) $item->menu_order,
            'type' => $type,
            'object' => $item->object ?: null,
            'object_id' => $item->object_id ? (int) $item->object_id : null,
            'url' => (string) $item->url,
            'title' => (string) $item->title,
            'target' => $item->target ?: null,
            'classes' => is_array($item->classes) ? trim(implode(' ', array_filter($item->classes))) ?: null : null,
        ];
    }
}
