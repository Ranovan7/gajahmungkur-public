<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Bendungan
$app->group('/bendungan', function() {

    $this->get('[/]', function(Request $request, Response $response, $args) {

        $waduk = $this->db->query("SELECT waduk.*,
                                            periodik_daily.outflow_deb as outflow_deb,
                                            periodik_daily.spillway_deb as spillway_deb,
                                            periodik_daily.curahhujan as curahhujan,
                                            periodik_daily.outflow_vol as outflow_vol,
                                            periodik_keamanan.debit as debit
                                    FROM waduk
                                    LEFT JOIN periodik_daily ON periodik_daily.id = (
                                        SELECT id from periodik_daily
                                            WHERE periodik_daily.waduk_id = waduk.id
                                            ORDER BY sampling DESC
                                            LIMIT 1
                                    )
                                    LEFT JOIN periodik_keamanan ON periodik_keamanan.id = (
                                        SELECT id from periodik_keamanan
                                            WHERE periodik_keamanan.waduk_id = waduk.id
                                                AND keamanan_type = 'vnotch'
                                            ORDER BY sampling DESC
                                            LIMIT 1
                                    )")->fetchAll();

        return $this->view->render($response, 'bendungan/index.html', [
             'waduk' => $waduk
        ]);
    })->setName('bendungan');

    $this->get('/{id}', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'bendungan/info.html', [
             'key' => 'value'
        ]);
    })->setName('bendungan.info');

});
