<?php
if (!function_exists('get_all_menus_from_db')) {
    function get_all_menus_from_db(PDO $pdo): array {
        try {
            $stmt = $pdo->prepare("SELECT * FROM menus ORDER BY parent_id, sort_order ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("NAVBAR_DB_ERROR: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('build_navbar_html')) {
    function build_navbar_html(array $menus, int $parentId = 0): string {
        $html = '';
        $current_page = basename($_SERVER['PHP_SELF']);
        $current_lang_code = $_SESSION['lang_code'] ?? 'vi';

        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                if (!empty($menu['permission_key']) && !has_permission($menu['permission_key'])) {
                    continue;
                }

                $children = array_filter($menus, fn($child) => $child['parent_id'] == $menu['id']);
                $visible_children = array_filter($children, fn($child) => (empty($child['permission_key']) || has_permission($child['permission_key'])));

                if ($menu['url'] === '#' && empty($visible_children) && !empty($children)) {
                    continue;
                }

                $has_visible_children = !empty($visible_children);
                $li_class = 'nav-item';
                $link_class = 'nav-link';

                if ($has_visible_children) {
                    $li_class .= ' dropdown';
                    $link_class .= ' dropdown-toggle';
                    $child_pages = array_column($visible_children, 'url');
                    if (in_array($current_page, $child_pages)) {
                        $li_class .= ' active';
                    }
                } else {
                    if ($current_page === $menu['url']) {
                        $li_class .= ' active';
                    }
                }

                $menu_name = $current_lang_code === 'en'
                    ? ($menu['name_en'] ?: $menu['name'])
                    : $menu['name'];

                $html .= '<li class="' . htmlspecialchars($li_class) . '">';
                $html .= '<a class="' . htmlspecialchars($link_class) . '" href="' . htmlspecialchars($menu['url']) . '"';
                if ($has_visible_children) {
                    $html .= ' id="menu-dropdown-' . $menu['id'] . '" role="button" data-bs-toggle="dropdown" aria-expanded="false"';
                }
                $html .= '><i class="' . htmlspecialchars($menu['icon']) . ' me-1"></i>' . htmlspecialchars($menu_name) . '</a>';

                if ($has_visible_children) {
                    $html .= '<ul class="dropdown-menu" aria-labelledby="menu-dropdown-' . $menu['id'] . '">';
                    $html .= build_navbar_html($menus, $menu['id']);
                    $html .= '</ul>';
                }

                $html .= '</li>';
            }
        }

        if ($parentId != 0) {
            $html = str_replace(['<li class="nav-item">', '<li class="nav-item active">'], ['<li>', '<li class="active">'], $html);
            $html = str_replace('class="nav-link', 'class="dropdown-item', $html);
        }

        return $html;
    }
}

?>
