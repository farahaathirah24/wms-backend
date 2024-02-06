<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('api', static function ($routes) {
    $routes->group('v1/{locale}/app/', static function ($routes) {
        $routes->post('login', 'V1\App\Home::login');
        $routes->post('logout', 'V1\App\Home::logout');
        $routes->get('me/modules', 'V1\App\Home::modules');

        // Route with Company
        $routes->group('company/(:segment)', static function ($routes) {
            // Route with Company and Location
            $routes->group('location/(:segment)', static function ($routes) {
                $routes->group('sessions', static function ($routes) {
                    $routes->post('(:segment)/end', 'V1\App\Home::endSession/$1', ['offset' => 2]);
                    $routes->post('start', 'V1\App\Home::startSession');
                    $routes->get('check', 'V1\App\Home::checkSession');
                });

                // Include All Routes in Portal Routes Folder
                $routeFolderPath = APPPATH . 'Config/Routes/App/';
                if (is_dir($routeFolderPath)) {
                    $files = scandir($routeFolderPath);

                    foreach ($files as $file) {
                        // Exclude directories and hidden files
                        if (is_file($routeFolderPath . $file) && ! in_array($file, ['.', '..', 'Routes.php'], true)) {
                            require_once $routeFolderPath . $file;
                        }
                    }
                }
            });
        });
    });
});
