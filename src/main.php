<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Main Route

// home
$app->get('/', function(Request $request, Response $response, $args) {
    $waduk = $this->db->query("SELECT waduk.*,
                                        rencana.po_vol AS po_vol,
                                        rencana.po_inflow_deb as po_inflow_deb,
                                        rencana.po_outflow_deb as po_outflow_deb,
                                        periodik_daily.inflow_deb as inflow_deb,
                                        periodik_daily.outflow_deb as outflow_deb,
                                        tma.volume as real_vol
                                FROM waduk
                                LEFT JOIN rencana ON rencana.id = (
                                    SELECT id from rencana
                                        WHERE rencana.waduk_id = waduk.id
                                        ORDER BY waktu DESC
                                        LIMIT 1
                                )
                                LEFT JOIN periodik_daily ON periodik_daily.id = (
                                    SELECT id from periodik_daily
                                        WHERE periodik_daily.waduk_id = waduk.id
                                        ORDER BY sampling DESC
                                        LIMIT 1
                                )
                                LEFT JOIN tma ON tma.id = (
                                    SELECT id from tma
                                        WHERE tma.waduk_id = waduk.id
                                        ORDER BY sampling DESC
                                        LIMIT 1
                                )")->fetchAll();

    $volume = 0;
    $real = 0;
    $real_in = 0;
    $real_out = 0;
    $rtow = 0;
    $rtow_in = 0;
    $rtow_out = 0;
    foreach ($waduk as $w) {
        $volume += $w["volume"];
        $real += $w["real_vol"];
        $real_in += $w["inflow_deb"];
        $real_out += $w["outflow_deb"];
        $rtow += $w["po_vol"];
        $rtow_in += $w["po_inflow_deb"];
        $rtow_out += $w["po_outflow_deb"];
    }

    return $this->view->render($response, 'main/index.html', [
        'jumlah' => count($waduk),
        'volume' => $volume,
        'real' => $real,
        'real_in' => round($real_in, 2),
        'real_out' => round($real_out, 2),
        'rtow' => $rtow,
        'rtow_in' => round($rtow_in, 2),
        'rtow_out' => round($rtow_out, 2),
    ]);
});

$app->get('/about', function(Request $request, Response $response, $args) {

    return $this->view->render($response, 'main/about.html', [
        'key' => "value",
    ]);
});

// Auth User
$app->get('/login', function(Request $request, Response $response, $args) {
    return $this->view->render($response, 'main/login.html');
});
// dummy login flow, bisa di uncomment ke POST
// $app->get('/lg', function(Request $request, Response $response, $args) {
$app->post('/login', function(Request $request, Response $response, $args) {
    $credentials = $request->getParams();
    if (empty($credentials['username']) || empty($credentials['password'])) {
        die("Masukkan username dan password");
    }

    $stmt = $this->db->prepare("SELECT * FROM public.user WHERE username=:username");
    $stmt->execute([':username' => $credentials['username']]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($credentials['password'], $user['password'])) {
        $this->flash->addMessage('messages', "Username / password salah!");
        return $this->view->render($response, 'main/login.html');
    }

    $this->session->user_id = $user['id'];
    $this->session->user_refresh_time = strtotime("+1hour");

    return $this->response->withRedirect('/admin');
});

// generate admin, warning!
// $app->get('/gen', function(Request $request, Response $response, $args) {
//     $credentials = $request->getParams();
//     if (empty($credentials['username']) || empty($credentials['password'])) {
//         die("Masukkan username dan password");
//     }

//     $stmt = $this->db->prepare("SELECT * FROM public.user WHERE username=:username");
//     $stmt->execute([':username' => $credentials['username']]);
//     $user = $stmt->fetch();

//     // jika belum ada di DB, tambahkan
//     if (!$user) {
//         $stmt = $this->db->prepare("INSERT INTO public.user (username, password, role) VALUES (:username, :password, 1)");
//         $stmt->execute([
//             ':username' => $credentials['username'],
//             ':password' => password_hash($credentials['password'], PASSWORD_DEFAULT)
//         ]);
//         die("Username {$credentials['username']} ditambahkan!");
//     } else { // else update password
//         $stmt = $this->db->prepare("UPDATE public.user SET password=:password WHERE id=:id");
//         $stmt->execute([
//             ':password' => password_hash($credentials['password'], PASSWORD_DEFAULT),
//             ':id' => $user['id']
//         ]);
//         die("Password {$user['username']} diubah!");
//     }
// });

$app->get('/logout', function(Request $request, Response $response, $args) {
    $this->session->destroy();
    return $this->response->withRedirect('/');
});
