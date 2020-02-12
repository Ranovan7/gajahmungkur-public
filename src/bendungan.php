<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Bendungan
$app->group('/bendungan', function() {

    $this->get('[/]', function(Request $request, Response $response, $args) {

        $sampling = $request->getParam('sampling', date('Y-m-d'));
        $bendungan = $this->db->query("SELECT * FROM waduk ORDER BY id")->fetchAll();
        $periodik_daily = $this->db->query("SELECT outflow_deb,
                                                    spillway_deb,
                                                    curahhujan,
                                                    outflow_vol,
                                                    waduk_id
                                                FROM periodik_daily
                                                WHERE sampling='{$sampling} 00:00:00'")->fetchAll();
        $vnotch = $this->db->query("SELECT vn1_debit, waduk_id
                                                FROM periodik_vnotch
                                                WHERE sampling='{$sampling} 00:00:00'")->fetchAll();
        $tma = $this->db->query("SELECT manual, sampling, waduk_id
                                    FROM tma
                                    WHERE sampling BETWEEN '{$sampling} 00:00:00' AND '{$sampling} 23:55:00'")->fetchAll();

        $waduk = [];
        foreach($bendungan as $bend) {
            $waduk[$bend['id']] = [
                'nama' => $bend['nama'],
                'id' => $bend['id'],
                'volume' => $bend['volume'],
                'lbi' => $bend['lbi'],
                'elev_puncak' => $bend['elev_puncak'],
                'muka_air_max' => $bend['muka_air_max'],
                'muka_air_min' => $bend['muka_air_min']
            ];
        }
        foreach($periodik_daily as $daily){
            $waduk[$daily['waduk_id']]['outflow_deb'] = $daily['outflow_deb'];
            $waduk[$daily['waduk_id']]['spillway_deb'] = $daily['spillway_deb'];
            $waduk[$daily['waduk_id']]['curahhujan'] = $daily['curahhujan'];
            $waduk[$daily['waduk_id']]['outflow_vol'] = $daily['outflow_vol'];
        }
        foreach($vnotch as $keamanan){
            if (array_key_exists("debit", $keamanan['waduk_id'])) {
                $waduk[$keamanan['waduk_id']]['debit'] += $keamanan['vn1_debit'];
            } else {
                $waduk[$keamanan['waduk_id']]['debit'] = $keamanan['vn1_debit'];
            }
        }
        foreach($tma as $t){
            if ($t['sampling'] == "{$sampling} 06:00:00"){
                $waduk[$t['waduk_id']]['tma6'] = $t['manual'];
            }
            if ($t['sampling'] == "{$sampling} 12:00:00"){
                $waduk[$t['waduk_id']]['tma12'] = $t['manual'];
            }
            if ($t['sampling'] == "{$sampling} 18:00:00"){
                $waduk[$t['waduk_id']]['tma18'] = $t['manual'];
            }
        }
        // dump($waduk);

        return $this->view->render($response, 'bendungan/index.html', [
             'waduk' => $waduk,
             'sampling' => $sampling
        ]);
    })->setName('bendungan');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $sampling = $request->getParam('sampling', date('Y-m-d'));

            $waduk = $this->db->query("SELECT waduk.*,
                                            tma.manual as tma
                                        FROM waduk
                                        LEFT JOIN tma ON tma.id = (
                                            SELECT id from tma
                                                WHERE waduk_id = waduk.id
                                                    AND sampling='{$sampling} 06:00:00'
                                                ORDER BY sampling DESC
                                                LIMIT 1
                                        )
                                        WHERE waduk.id={$id}")->fetch();
            // dump($waduk);
            return $this->view->render($response, 'bendungan/info.html', [
                 'waduk' => $waduk,
                 'sampling' => $sampling
            ]);
        })->setName('bendungan.tma');

        $this->get('/operasi', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $sampling = $request->getParam('sampling', date('Y'));
            $end = date('Y-m-d', strtotime("{$sampling}-11-1"));
            $start = date('Y-m-d', strtotime($end .' -1year'));

            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $rtow = $this->db->query("SELECT * FROM rencana
                                        WHERE waduk_id={$id}
                                            AND waktu BETWEEN '{$start}' AND '{$end}'")->fetchAll();

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
                 'tanggal' => $tanggal,
                 'sampling' => $sampling
            ]);
        })->setName('bendungan.operasi');

        $this->get('/vnotch', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $sampling = $request->getParam('sampling', date('Y'));
            $end = date('Y-m-d', strtotime("{$sampling}-11-1"));
            $start = date('Y-m-d', strtotime($end .' -1year'));

            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $periodik_vnotch = $this->db->query("SELECT periodik_vnotch.*,
                                                periodik_daily.curahhujan as ch
                                        FROM periodik_vnotch
                                        LEFT JOIN periodik_daily ON periodik_daily.id = (
                                            SELECT id from periodik_daily
                                                WHERE periodik_daily.sampling = periodik_vnotch.sampling
                                                    AND periodik_daily.waduk_id = periodik_vnotch.waduk_id
                                                LIMIT 1
                                        )
                                        WHERE periodik_vnotch.waduk_id={$id}
                                            AND periodik_vnotch.sampling BETWEEN '{$start}' AND '{$end}'
                                        ORDER BY periodik_vnotch.sampling
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
                $filtered_vnotch[$tgl_str]['ch'] += $vn['ch'];
                $filtered_vnotch[$tgl_str]['vn']['VNotch 1'] += $vn['vn1_debit'];
                $filtered_vnotch[$tgl_str]['vn']['VNotch 2'] += $vn['vn2_debit'];
                $filtered_vnotch[$tgl_str]['vn']['VNotch 3'] += $vn['vn3_debit'];
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

                $tgl = date_format(date_create($i), "j M Y");
                $vnotch['tanggal'] .= "'{$tgl}'";
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
                 'vnotch' => $vnotch,
                 'sampling' => $sampling
            ]);
        })->setName('bendungan.vnotch');

        $this->get('/piezometer', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $sampling = $request->getParam('sampling', date('Y'));
            $end = date('Y-m-d', strtotime("{$sampling}-11-1"));
            $start = date('Y-m-d', strtotime($end .' -1year'));

            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $piezo_perio = $this->db->query("SELECT * FROM periodik_piezo
                                                WHERE periodik_piezo.waduk_id={$id}
                                                    AND sampling BETWEEN '{$start}' AND '{$end}'
                                                ORDER BY periodik_piezo.sampling")->fetchAll();

            $tgl_perio = [];
            $piezodata = [];

            foreach ($piezometer as $p) {
                $code = explode(" ", $p['nama'])[1];
                $profile = str_split($code)[0];
                $alpha = str_split($code)[1];
                if (!array_key_exists($profile, $piezodata)) {
                    $piezodata[$profile] = [];
                }
                $piezodata[$profile][$alpha] = [
                    'bts_pori' => $p['bts_tekanan_pori'],
                    'tgls' => "",
                    'bts_pori_ds' => "",
                    'piezo_ds' => ""
                ];
            }

            foreach ($piezo_perio as $p) {
                $tgl_str = explode(" ", $p['sampling'])[0];
                $date = date_create($tgl_str);
                $tgl = date_format($date, "j M Y");
                if (!array_key_exists($tgl, $tgl_labels)) {
                    $tgl_perio[$tgl] = [];
                    $code = explode(" ", $p['nama_piezo'])[1];
                    if (!empty($code)) {
                        $tgl_perio[$tgl][$code] = $p['tma'];
                    }
                }
            }

            $tgl_labels = "";
            $count = 0;
            foreach ($tgl_perio as $tgl => $pie) {
                if ($count > 0) {
                    $tgl_labels .= ",";
                }
                $tgl_labels .= "'{$tgl}'";
                foreach ($pie as $c => $p) {
                    $profile = str_split($c)[0];
                    $alpha = str_split($c)[1];

                    if (!empty($piezodata[$profile][$alpha]['tgls'])) {
                        $piezodata[$profile][$alpha]['piezo_ds'] .= ",";
                        $piezodata[$profile][$alpha]['bts_pori_ds'] .= ",";
                        $piezodata[$profile][$alpha]['tgls'] .= ",";
                    }
                    $piezodata[$profile][$alpha]['piezo_ds'] .= $p;
                    $piezodata[$profile][$alpha]['bts_pori_ds'] .= $piezodata[$profile][$alpha]['bts_pori'];
                    $piezodata[$profile][$alpha]['tgls'] .= "'{$tgl}'";
                }
                $count += 1;
            }
            // dump($piezodata);

            return $this->view->render($response, 'bendungan/piezo.html', [
                 'waduk' => $waduk,
                 'tgl_labels' => $tgl_labels,
                 'piezodata' => $piezodata,
                 'sampling' => $sampling
            ]);
        })->setName('bendungan.piezo');
    });

});
