<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
// receiving route :faraha :11/01/2024
$routes->get('receiving/queue/list', 'V1\App\Receiving::queueList');
$routes->post('receiving/queue/(:segment)/start', 'V1\App\Receiving::parkingBay/$1/$2', ['offset' => 2, 'offset' => 1]);
$routes->get('receiving/queue/(:segment)/lists', 'V1\App\Receiving::queueItem/$1', ['offset' => 2]);
$routes->post('receiving/queue/(:segment)/reset', 'V1\App\Receiving::resetQty/$1', ['offset' => 2]);
$routes->post('receiving/queue/(:segment)/scan', 'V1\App\Receiving::scanBarcode/$1', ['offset' => 2]);
$routes->post('receiving/queue/(:segment)/submit', 'V1\App\Receiving::submitReceiving/$1', ['offset' => 2]);
$routes->post('receiving/queue/(:segment)/edit', 'V1\App\Receiving::updateQty/$1', ['offset' => 2]);
$routes->post('receiving/queue/(:segment)/scan/serial-number', 'V1\App\Receiving::scanSerialNumber/$1', ['offset' => 2]);
$routes->get('receiving/queue/(:segment)/products/(:segment)', 'V1\App\Receiving::serialNumberList/$1/$2', ['offset' => 1, 'offset' => 2]);
$routes->post('receiving/queue/(:segment)/products/(:segment)', 'V1\App\Receiving::deleteSerialNumber/$1/$2', ['offset' => 1, 'offset' => 2]);
