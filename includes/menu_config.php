<?php
// includes/menu_config.php

$menu_items = [
    [
        'label' => 'Thống kê giao hàng',
        'link' => 'delivery_comparison.php',
        'icon' => 'bi-graph-up',
        'permission' => 'dashboard_view'
    ],
    [
        'label' => 'Báo giá',
        'link' => 'sales_quotes.php',
        'icon' => 'bi-file-earmark-text',
        'permission' => 'quotes_view'
    ],
    // Thêm các menu có sẵn tại đây
];
