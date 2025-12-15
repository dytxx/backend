<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], function($routes) {
    
    // 1. AUTHENTICATION
    $routes->post('auth/login', 'AuthController::login');
    $routes->options('auth/login', 'AuthController::options');

    // 2. USER MANAGEMENT
    $routes->get('users', 'UserController::index');
    $routes->post('users', 'UserController::create');
    $routes->delete('users/(:num)', 'UserController::delete/$1');
    $routes->options('users', 'UserController::options');

    // 3. WORK ORDER (WO)
    $routes->get('wo', 'WOController::index');
    $routes->get('wo/generate-number', 'WOController::generateNumber');
    $routes->post('wo/submit', 'WOController::create');
    $routes->options('wo/submit', 'WOController::options');

    // 4. QC (Finish Goods)
    $routes->get('qc/generate-number', 'QCController::generateNumber');
    $routes->post('qc/submit', 'QCController::create');
    $routes->options('qc/submit', 'QCController::options');

    // 5. STORAGE
    $routes->get('storage', 'StorageController::index');
    $routes->get('storage/pending-qc', 'StorageController::getPendingQC');
    $routes->get('storage/recommend', 'StorageController::getRecommendation');
    $routes->post('storage/submit', 'StorageController::create');
    $routes->put('storage/(:num)', 'StorageController::update/$1');
    $routes->delete('storage/(:num)', 'StorageController::delete/$1');
    $routes->options('storage/submit', 'StorageController::options');
    $routes->options('storage/recommend', 'StorageController::options'); // Tambahan options
    $routes->options('storage/pending-qc', 'StorageController::options'); // Tambahan options

    // 6. DELIVERY
    $routes->get('delivery/generate-number', 'DeliveryController::generateNumber');
    $routes->post('delivery/submit', 'DeliveryController::create');
    $routes->options('delivery/submit', 'DeliveryController::options');
});

