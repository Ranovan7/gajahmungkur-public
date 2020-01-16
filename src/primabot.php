<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Bendungan
$app->group('/primabot', function() {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        $sejak = intval($request->getParam('sejak', 90));
        $end = date("Y-m-d");
        $start = date('Y-m-d', strtotime($end ." -{$sejak}day"));
        $from = "{$start} 07:00:00";
        $to = "{$end} 06:55:00";
        // get curahhujan list for "sejak" day
        $ch = $this->db->query("SELECT periodik.*, lokasi.nama AS lokasi_nama FROM periodik
                                LEFT JOIN lokasi ON periodik.lokasi_id=lokasi.id
                                WHERE periodik.rain IS NOT NULL
                                    AND periodik.sampling BETWEEN '{$from}' AND '{$to}'
                                ORDER BY periodik.sampling DESC")->fetchAll();
        $lokasi_tma = $this->db->query("SELECT * FROM lokasi
                                WHERE lokasi.jenis = '2'
                                ORDER BY lokasi.id")->fetchAll();
        $result = [
            'curahhujan' => []
        ];
        // generating curahhujan data
        $current_date = Null;
        foreach ($ch as $c) {
            // check sampling (date) change every iteration to append new array
            $date = date('Y-m-d', strtotime($c['sampling'].' -7hour'));
            if ($date != $current_date) {
                $result['curahhujan'][$date] = [
                    'waktu' => tanggal_format(strtotime($date)),
                    'date' => $date,
                    'daftar' => []
                ];
                $current_date = $date;
            }
            // check lokasi id to add
            $lokasi = $c['lokasi_nama'];
            if (array_key_exists($c['lokasi_id'], $result['curahhujan'][$date]['daftar'])) {
                // update ch and durasi
                $result['curahhujan'][$date]['daftar'][$c['lokasi_id']]['ch'] = round($result['curahhujan'][$date]['daftar'][$c['lokasi_id']]['ch'] + $c['rain'], 2);
                $result['curahhujan'][$date]['daftar'][$c['lokasi_id']]['durasi'] += 5;
            } else {
                // append new array cause its not exist
                $result['curahhujan'][$date]['daftar'][$c['lokasi_id']] = [
                    'id' => $c['lokasi_id'],
                    'lokasi' => $lokasi,
                    'ch' => $c['rain'],
                    'durasi' => 5
                ];
            }
        }
        // generating tma data
        $tmalatest = $this->db->query("SELECT * FROM lokasi
                                        LEFT JOIN periodik ON periodik.id = (
                                            SELECT id from periodik
                                                WHERE periodik.lokasi_id = lokasi.id
                                                    AND periodik.sampling <= '{$to}'
                                                ORDER BY sampling DESC
                                                LIMIT 1
                                        )
                                        WHERE lokasi.jenis = '2'
                                        ORDER BY lokasi.id")->fetchAll();
        foreach ($tmalatest as $tma) {
            $tma['wlev'] = max(round($tma['wlev'], 2), 0);
        }

        return $this->view->render($response, 'primabot/index.html', [
            'hujan_sejak' => $sejak,
            'result' => $result,
            'tmalatest' => $tmalatest
        ]);
    })->setName('primabot');

    $this->get('/sehat', function(Request $request, Response $response, $args) {
        $hari = $request->getParam('sampling', date('Y-m-d'));//"2019-06-26");
        $prev_date = date('Y-m-d', strtotime($hari .' -1day'));
        $next_date = date('Y-m-d', strtotime($hari .' +1day'));

        $all_devices = [];
        $devices = $this->db->query("SELECT * FROM device WHERE lokasi_id > 0 ORDER BY sn")->fetchAll();

        foreach ($devices as $dev) {
            $res = $this->db->query("SELECT sampling
                                       FROM periodik
                                       WHERE device_sn='{$dev['sn']}' AND sampling::date='{$hari}'
                                       ORDER BY sampling")->fetchAll();
            $hourly = [];
            foreach ($res as $r) {
                $hour = intval(date('H', strtotime($r['sampling'])));
                if (array_key_exists($hour, $hourly)) {
                    $hourly[$hour] += 1;
                } else {
                    $hourly[$hour] = 1;
                }
                $all_devices[] = [
                    'device' => r,
                    'hourly_count' => hourly
                ];
            }
        }

        return $this->view->render($response, 'primabot/sehat.html', [
            'sampling' => $hari,
            'all_devices' => $all_devices,
            'prev' => $prev_date,
            'next' => $next_date
        ]);
    })->setName('primabot.sehat');

});
