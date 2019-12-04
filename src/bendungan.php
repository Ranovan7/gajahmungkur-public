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
                                            periodik_keamanan.debit as debit,
                                            tma_p.manual as tma6,
                                            tma_s.manual as tma12,
                                            tma_m.manual as tma18
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
                                    )
                                    LEFT JOIN tma tma_p ON tma_p.id = (
                                        SELECT id from tma
                                            WHERE waduk_id = waduk.id
                                                AND EXTRACT(HOUR FROM sampling) = '6'
                                            ORDER BY sampling DESC
                                            LIMIT 1
                                    )
                                    LEFT JOIN tma tma_s ON tma_s.id = (
                                        SELECT id from tma
                                            WHERE waduk_id = waduk.id
                                                AND EXTRACT(HOUR FROM sampling) = '12'
                                            ORDER BY sampling DESC
                                            LIMIT 1
                                    )
                                    LEFT JOIN tma tma_m ON tma_m.id = (
                                        SELECT id from tma
                                            WHERE waduk_id = waduk.id
                                                AND EXTRACT(HOUR FROM sampling) = '18'
                                            ORDER BY sampling DESC
                                            LIMIT 1
                                    )")->fetchAll();

        return $this->view->render($response, 'bendungan/index.html', [
             'waduk' => $waduk
        ]);
    })->setName('bendungan');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT waduk.*,
                                            tma.manual as tma
                                        FROM waduk
                                        LEFT JOIN tma ON tma.id = (
                                            SELECT id from tma
                                                WHERE waduk_id = waduk.id
                                                ORDER BY sampling DESC
                                                LIMIT 1
                                        )
                                        WHERE waduk.id={$id}")->fetch();

            return $this->view->render($response, 'bendungan/info.html', [
                 'waduk' => $waduk
            ]);
        })->setName('bendungan.info');
    });

});
