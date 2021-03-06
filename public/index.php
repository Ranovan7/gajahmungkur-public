<?php

use Slim\Http\Request;
use Slim\Http\Response;

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();



/**
 * SETTINGS BLOCK
 */

// Load .env
$dotenv = new Dotenv\Dotenv(__DIR__ .'/../');
$dotenv->load();
function env($key, $defaultValue='') {
    return isset($_ENV[$key]) ? $_ENV[$key] : $defaultValue;
}

// get timezone from ENV, default "Asia/Jakarta"
date_default_timezone_set(env('APP_TIMEZONE', "Asia/Jakarta"));

$settings = [
    'settings' => [
        'displayErrorDetails' => env('APP_ENV', 'local') != 'production',
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header
        'debugMode' => env('APP_DEBUG', 'true') == 'true',

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
			'cache_path' => env('APP_ENV', 'local') != 'production' ? '' : __DIR__ . '/../cache/'
        ],

        // Monolog settings
        'logger' => [
            'name' => env('APP_NAME', 'App'),
            'path' => env('docker') ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],

        // Database
        'db' => [
			'connection' => env('DB_CONNECTION'),
			'host' => env('DB_HOST'),
			'port' => env('DB_PORT'),
			'database' => env('DB_DATABASE'),
			'username' => env('DB_USERNAME'),
			'password' => env('DB_PASSWORD'),
        ],
    ],
];

// Instantiate the app
$app = new \Slim\App($settings);

/**
 * # SETTINGS BLOCK
 */



/**
 * DEPENDENCIES BLOCK
 */

// Set up dependencies
$container = $app->getContainer();

// view renderer
$container['view'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
	$view = new \Slim\Views\Twig($settings['template_path'], [
        // 'cache' => $settings['cache_path']
    ]);

    // Instantiate and add Slim specific extension
    $router = $c->get('router');
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));
    $view->addExtension(new \Knlv\Slim\Views\TwigMessages(new Slim\Flash\Messages()));

    return $view;
};

// not found handler
$container['notFoundHandler'] = function($c) {
    return function (Request $request, Response $response) use ($c) {
        return $c->view->render($response->withStatus(404), 'errors/404.html');
    };
};

// error handler
if (!$container->get('settings')['debugMode'])
{
    $container['errorHandler'] = function($c) {
        return function ($request, $response) use ($c) {
            return $c->view->render($response->withStatus(500), 'errors/500.phtml');
        };
    };
    $container['phpErrorHandler'] = function ($c) {
        return $c['errorHandler'];
    };
}

// flash messages
$container['flash'] = function() {
    return new \Slim\Flash\Messages();
};

// session helper
require_once __DIR__ . '/../src/Session.php';
$container['session'] = function() {
    return Session::getInstance();
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// db
$container['db'] = function($c) {
    $settings = $c->get('settings')['db'];
	$connection = $settings['connection'];
	$host = $settings['host'];
	$port = $settings['port'];
	$database = $settings['database'];
	$username = $settings['username'];
	$password = $settings['password'];

	$dsn = "$connection:host=$host;port=$port;dbname=$database";
	$options = [
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES   => false,
	];

	try {
		return new PDO($dsn, $username, $password, $options);
	} catch (PDOException $e) {
		throw new PDOException($e->getMessage(), (int)$e->getCode());
	}
};

// get active user, cara menggunakan: $this->user
$container['user'] = function($c) {
    $session = Session::getInstance();
	$user_id = $session->user_id;
	if (empty($user_id)) {
		return null;
	}

    // hide password, just because
	$stmt = $c->db->prepare("SELECT id,username,role,lokasi_id FROM public.user WHERE id=:id");
	$stmt->execute([':id' => $user_id]);
	$user = $stmt->fetch();
	return $user ?: null;
};

/**
 * # DEPENDENCIES BLOCK
 */



/**
 * MIDDLEWARES BLOCK
 */

$loggedinMiddleware = function(Request $request, Response $response, $next) {

    $user_refresh_time = $this->session->user_refresh_time;
    $now = time();

    // cek masa aktif login
    if (empty($user_refresh_time) || $user_refresh_time < $now) {
        $this->session->destroy();
        // die('Silahkan login untuk melanjutkan');
        return $this->response->withRedirect('/login');
    }

    // cek user exists, ada di index.php
    $user = $this->user;
    if (!$user) {
        $this->flash->addMessage('errors', 'Silahkan login untuk melanjutkan.');
        return $this->response->withRedirect('/login');
    }

    // inject user ke dalam request agar bisa diakses di route
    $request = $request->withAttribute('user', $user);

    return $next($request, $response);
};

$adminRoleMiddleware = function(Request $request, Response $response, $next) {

    $user = $this->user;
    if (!$user || $user['role'] != '1') {
        $this->flash->addMessage('errors', 'Hanya admin yang diperbolehkan mengakses laman tersebut.');
        return $this->response->withRedirect('/admin');
    }

    return $next($request, $response);
};

/**
 * # MIDDLEWARES BLOCK
 */



/**
 * HELPERS BLOCK
 */

// Menambahkan fungsi env() pada Twig
$env = new Twig_SimpleFunction('env', function ($key, $default) {
	return isset($_ENV[$key]) ? $_ENV[$key] : $default;
});
$container->get('view')->getEnvironment()->addFunction($env);

// Menambahkan fungsi asset() pada Twig
$asset = new Twig_SimpleFunction('asset', function ($path) {
	return $_ENV['APP_URL'] .'/'. $path;
});
$container->get('view')->getEnvironment()->addFunction($asset);

// Menambahkan fungsi session() pada Twig
$session = new Twig_SimpleFunction('session', function () {
	return Session::getInstance();
});
$container->get('view')->getEnvironment()->addFunction($session);

// Menambahkan fungsi user() pada Twig -> untuk mendapatkan current user
$user = new Twig_SimpleFunction('user', function () use ($container) {
	return $container->get('user');
});
$container->get('view')->getEnvironment()->addFunction($user);

/**
 * HELPER UNTUK DUMP + DIE
 */
function dump($var, $die=true) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    if ($die) {
        die();
    }
}

/**
 * HELPER UNTUK FORMAT DATE
 */
function tanggal_format($time, $usetime=false) {
    switch (date('n', $time)) {
        case 1: $month = 'Januari'; break;
        case 2: $month = 'Februari'; break;
        case 3: $month = 'Maret'; break;
        case 4: $month = 'April'; break;
        case 5: $month = 'Mei'; break;
        case 6: $month = 'Juni'; break;
        case 7: $month = 'Juli'; break;
        case 8: $month = 'Agustus'; break;
        case 9: $month = 'September'; break;
        case 10: $month = 'Oktober'; break;
        case 11: $month = 'November'; break;
        default: $month = 'Desember'; break;
    }
    return date('j', $time) .' '. $month .' '. date('Y', $time) . ($usetime ? ' '. date('H:i', $time) : '');
}

/**
 * # HELPERS BLOCK
 */



/**
 * ROUTES BLOCK
 */

$app->group('/api', function() {
    $app = $this;

});

require __DIR__ . '/../src/main.php';
require __DIR__ . '/../src/map.php';
require __DIR__ . '/../src/primabot.php';
require __DIR__ . '/../src/bendungan.php';

/**
 * # ROUTES BLOCK
 */



// Run app
$app->run();
