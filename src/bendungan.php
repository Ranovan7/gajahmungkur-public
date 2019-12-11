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
        })->setName('bendungan.tma');

        $this->get('/operasi', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $rtow = $this->db->query("SELECT * FROM rencana WHERE waduk_id={$id}")->fetchAll();

            $tanggal = "";
            $operasi = [
                'po_bona' => "",
                'po_bonb' => "",
                'real' => "",
                'elev_min' => "",
                'sedimen' => "",
                'po_outflow' => "",
                'po_inflow' => "",
                'real_outflow' => "",
                'real_inflow' => ""
            ];
            foreach ($rtow as $i => $rt) {
                if ($i != 0) {
                    $tanggal .= ",";
                    $operasi['po_bona'] .= ",";
                    $operasi['po_bonb'] .= ",";
                    $operasi['real'] .= ",";
                    $operasi['elev_min'] .= ",";
                    $operasi['sedimen'] .= ",";
                    $operasi['po_outflow'] .= ",";
                    $operasi['po_inflow'] .= ",";
                    $operasi['real_outflow'] .= ",";
                    $operasi['real_inflow'] .= ",";
                }

                $tgl_str = explode(" ", $rt['waktu'])[0];
                $tanggal .= "'{$tgl_str}'";
                $operasi['po_bona'] .= "{$rt['po_bona']}";
                $operasi['po_bonb'] .= "{$rt['po_bonb']}";
                $operasi['real'] .= (string) $rt['po_bona'] -2;
                $operasi['elev_min'] .= "{$waduk['muka_air_min']}";
                $operasi['sedimen'] .= "{$waduk['sedimen']}";
                $operasi['po_outflow'] .= (string) (!$waduk['po_outflow_deb']) ? '0' : $waduk['po_outflow_deb'];
                $operasi['po_inflow'] .= (string) (!$waduk['po_inflow_deb']) ? '0' : $waduk['po_inflow_deb'];
                $operasi['real_outflow'] .= "0";
                $operasi['real_inflow'] .= "0";
            }
            // dump($operasi);

            return $this->view->render($response, 'bendungan/operasi.html', [
                 'waduk' => $waduk,
                 'operasi' => $operasi,
                 'tanggal' => $tanggal
            ]);
        })->setName('bendungan.operasi');

        $this->get('/vnotch', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $periodik_vnotch = $this->db->query("SELECT periodik_keamanan.*,
                                                periodik_daily.curahhujan as ch,
                                                vnotch.nama as vn_name
                                        FROM periodik_keamanan
                                        LEFT JOIN periodik_daily ON periodik_daily.id = (
                                            SELECT id from periodik_daily
                                                WHERE periodik_daily.sampling = periodik_keamanan.sampling
                                                    AND periodik_daily.waduk_id = periodik_keamanan.waduk_id
                                                LIMIT 1
                                        )
                                        LEFT JOIN vnotch ON vnotch.id = (
                                            SELECT id from vnotch
                                                WHERE vnotch.waduk_id = periodik_keamanan.waduk_id
                                                LIMIT 1
                                        )
                                        WHERE periodik_keamanan.waduk_id={$id}
                                            AND periodik_keamanan.debit > 0
                                        ORDER BY periodik_keamanan.sampling DESC
                                        LIMIT 24");

            $filtered_vnotch = [];
            foreach ($periodik_vnotch as $i => $vn) {
                $tgl_str = explode(" ", $vn['sampling'])[0];
                if (!array_key_exists($tgl_str, $filtered_vnotch)){
                    $filtered_vnotch[$tgl_str] = [
                        'ch' => 0,
                        'vn' => []
                    ];
                }
                if (!array_key_exists($vn['vn_name'], $filtered_vnotch[$tgl_str]['vn'])){
                    $filtered_vnotch[$tgl_str]['vn'][$vn['vn_name']] = 0;
                }
                $filtered_vnotch[$tgl_str]['ch'] += $vn['ch'];
                $filtered_vnotch[$tgl_str]['vn'][$vn['vn_name']] += $vn['debit'];
            }

            $vnotch = [
                'tanggal' => "",
                'ch' => ""
            ];
            $ins = 0;
            foreach ($filtered_vnotch as $i => $vn) {
                if ($ins != 0) {
                    $vnotch['tanggal'] .= ",";
                    $vnotch['ch'] .= ",";
                    foreach ($vn['vn'] as $vnn => $vnn_val) {
                        $vnotch['vn'][$vnn] .= ",";
                    }
                }

                $vnotch['tanggal'] .= "'{$i}'";
                $vnotch['ch'] .= "{$vn['ch']}";
                foreach ($vn['vn'] as $vnn => $vnn_val) {
                    if (!array_key_exists($vnn, $vnotch['vn'])){
                        $vnotch['vn'][$vnn] = "";
                    }
                    $vnotch['vn'][$vnn] .= $vn['vn'][$vnn];
                }
                $ins += 1;
            }
            // dump($vnotch);
            return $this->view->render($response, 'bendungan/vnotch.html', [
                 'waduk' => $waduk,
                 'vnotch' => $vnotch
            ]);
        })->setName('bendungan.vnotch');

        $this->get('/piezometer', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'bendungan/piezo.html', [
                 'waduk' => $waduk
            ]);
        })->setName('bendungan.piezo');
    });

});
