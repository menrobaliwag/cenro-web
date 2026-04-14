<?php
declare(strict_types=1);

if (!function_exists('render_skeleton_lines')) {
    function render_skeleton_lines(int $lines = 5): void {
        $lines = max(1, min(12, $lines));
        for ($i = 0; $i < $lines; $i++) {
            echo '<div class="skeleton skeleton-line mb-2"></div>';
        }
    }
}

if (!function_exists('render_skeleton_table_rows')) {
    function render_skeleton_table_rows(int $rows = 6, int $cols = 6): void {
        $rows = max(1, min(20, $rows));
        $cols = max(1, min(20, $cols));

        echo '<tbody class="skeleton-tbody">';
        for ($r = 0; $r < $rows; $r++) {
            echo '<tr>';
            for ($c = 0; $c < $cols; $c++) {
                echo '<td><div class="skeleton skeleton-cell"></div></td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
    }
}

