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
    'low_stock'
];

$page = $_GET['page'] ?? 'routes';

if (!in_array($page, $allowedPages)) {
    $page = 'routes';
}

return $page;