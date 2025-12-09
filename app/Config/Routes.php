<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', function($routes) {
    $routes->post('qc/submit', 'Api\QCController::create');
    $routes->options('qc/submit', 'Api\QCController::options');
    $routes->get('qc/generate-number', 'Api\QCController::generateNumber');

    $routes->post('wo/submit', 'Api\WOController::create');
    $routes->options('wo/submit', 'Api\WOController::options');
    $routes->get('wo/generate-number', 'Api\WOController::generateNumber');
    $routes->get('wo', 'Api\WOController::index');

    $routes->get('storage', 'Api\StorageController::index');
    $routes->post('storage/submit', 'Api\StorageController::create');
    $routes->options('storage/submit', 'Api\StorageController::options');
    $routes->get('storage/recommend', 'Api\StorageController::getRecommendation');
    $routes->get('storage/pending-qc', 'Api\StorageController::getPendingQC');
});

