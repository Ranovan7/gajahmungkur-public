<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Bendungan
$app->group('/bendungan', function() {

    $this->get('[/]', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'bendungan/index.html', [
             'key' => 'value'
        ]);
    })->setName('bendungan');

    $this->get('/{id}', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'bendungan/info.html', [
             'key' => 'value'
        ]);
    })->setName('bendungan.info');

});
