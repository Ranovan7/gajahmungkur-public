<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Map
$app->group('/map', function() {

    $this->get('/bendungan', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'map/bendungan.html', [
             'key' => 'value'
        ]);
    })->setName('bendungan.map');

    $this->get('/embung', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'map/embung.html', [
             'key' => 'value'
        ]);
    })->setName('bendungan.info');

});
