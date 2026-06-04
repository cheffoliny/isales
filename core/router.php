<?php

$allowedPages = [
    'routes',
    'orders',
    'objects',
    'items',
    'object_order',
    'route_objects',
    'import_nomenclatures',
    'general_report',
    'delivery_request',
    'users',
    'low_stock',
    'sales_analysis',
    'products_top',
    'products_slow',
    'products_profit',
    'items_list',
    'financial_dashboard',
    'financial_dashboard2',
    'financial_dashboard3',
    'financial_dashboard4'
];

$page = $_GET['page'] ?? 'routes';

if (!in_array($page, $allowedPages)) {
    $page = 'routes';
}

return $page;