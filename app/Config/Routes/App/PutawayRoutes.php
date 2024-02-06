<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->post('putaway/start', 'V1\App\Putaway::scanParkingBay');
$routes->get('putaway/(:segment)/list', 'V1\App\Putaway::putawayItem/$1', ['offset' => 2]);
$routes->post('putaway/(:segment)/reset', 'V1\App\Putaway::resetQty/$1', ['offset' => 2]);
$routes->post('putaway/(:segment)/scan-rack-in', 'V1\App\Putaway::scanRackIn/$1', ['offset' => 2]);
$routes->post('putaway/(:segment)/scan/barcode', 'V1\App\Putaway::scanBarcode/$1', ['offset' => 2]);
$routes->post('putaway/(:segment)/scan/serial-number', 'V1\App\Putaway::scanSerialNumber/$1', ['offset' => 2]);
$routes->get('putaway/(:segment)/products/(:segment)', 'V1\App\Putaway::serialNumberList/$1/$2', ['offset' => 1, 'offset' => 2]);
$routes->post('putaway/(:segment)/products/(:segment)', 'V1\App\Putaway::deleteSerialNumber/$1/$2', ['offset' => 1, 'offset' => 2]);

