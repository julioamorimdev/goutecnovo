<?php
declare(strict_types=1);

function menu_fetch_all_enabled(): array {
    try {
        // Garantir UTF-8 na conexão
        db()->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        db()->exec("SET CHARACTER SET utf8mb4");
        db()->exec("SET character_set_connection=utf8mb4");
        
        $stmt = db()->query("SELECT * FROM menu_items WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function menu_fetch_all(): array {
    $stmt = db()->query("SELECT * FROM menu_items ORDER BY parent_id IS NOT NULL, parent_id ASC, sort_order ASC, id ASC");
    return $stmt->fetchAll();
}

function menu_build_tree(array $items): array {
    $byId = [];
    foreach ($items as $it) {
        $it['children'] = [];
        $byId[(int)$it['id']] = $it;
    }
    $tree = [];
    foreach ($byId as $id => &$it) {
        $pid = $it['parent_id'] !== null ? (int)$it['parent_id'] : null;
        if ($pid && isset($byId[$pid])) {
            $byId[$pid]['children'][] = &$it;
        } else {
            $tree[] = &$it;
        }
    }
    unset($it);
    return $tree;
}


