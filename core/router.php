<?php

$allowedPages = [
    'routes',
    'orders',
    'object_order',
    'route_objects',
    'import_nomenclatures',
    'delivery_request'
];

$page = $_GET['page'] ?? 'routes';

if (!in_array($page, $allowedPages)) {
    $page = 'routes';
}

return $page;