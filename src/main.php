<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Main Route

// home
$app->get('/', function(Request $request, Response $response, $args) {

    return $this->view->render($response, 'main/index.html', [
        'key' => "value",
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
