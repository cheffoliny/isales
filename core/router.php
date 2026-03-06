<?php

$allowedPages = [
    'routes',
    'orders',
    'objects',
    'items',
    'object_order',
    'route_objects',
    'import_nomenclatures',
    'import_sales',
    'delivery_request'
];

$page = $_GET['page'] ?? 'routes';

if (!in_array($page, $allowedPages)) {
    $page = 'routes';
}

return $page;