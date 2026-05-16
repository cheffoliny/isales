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
    'users'
];

$page = $_GET['page'] ?? 'routes';

if (!in_array($page, $allowedPages)) {
    $page = 'routes';
}

return $page;