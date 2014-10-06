<?php
require __DIR__ . '/../vendor/autoload.php';

// setup dummy server variables
$_SERVER['REQUEST_METHOD'] = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

$db_driver = getenv('DB_DRIVER') ?: 'mysql';

$app = require __DIR__ . '/../src/Hook.php';

$app->config('database', require(__DIR__ . "/configs/{$db_driver}.php"));
$app->config('paths', require(__DIR__ . '/../config/paths.php'));

require __DIR__ . '/../src/bootstrap/connection.php';
Hook\Http\Router::setInstance($app);

//
// Ensure that tests will run against an valid fresh app
//
if (Hook\Model\AppKey::count() == 0) {
    $app->environment->offsetSet('PATH_INFO', '/apps');
    $app->environment->offsetSet('slim.request.form_hash', array(
        'app' => array(
            'name' => 'testing'
        )
    ));
    $app_controller = new Hook\Controllers\ApplicationController();
    $app_controller->create();
}

// Force application key for testing
Hook\Database\AppContext::setTablePrefix('');
Hook\Database\AppContext::setKey(Hook\Model\AppKey::with('app')->first());

$app->log->setWriter(new Hook\Logger\LogWriter(storage_dir() . '/logs.txt'));

class TestCase extends PHPUnit_Framework_TestCase
{
}

class HTTP_TestCase extends PHPUnit_Framework_TestCase
{
    // protected $base_url = 'http://localhost/index.php/';
    // protected $base_url = 'http://localhost/index.php/';
    protected $base_url = 'http://hook.dev:58790/index.php/';
    protected $app_keys = array();
    protected $app_key = array();
    // protected $base_url = 'http://dl-api.dev/index.php/';

    public function setUp()
    {
        $this->useApp('default');
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    public function useApp($id)
    {
        $apps = $this->get('apps');
        if (!isset($apps[0])) {
            $this->post('apps', array(
                'app' => array('name' => 'phpunit')
            ));
            return $this->useApp($id);
        }

        // associate keys by type
        foreach($apps[0]['keys'] as $key) {
            if (!isset($key['deleted_at']) || $key['deleted_at']==null) {
                $this->app_keys[$key['type']] = $key;
            }
        }

        // use browser key by default
        $this->setKeyType('browser');
    }

    public function setKeyType($type)
    {
        $this->app_key = $this->app_keys[$type];
    }

    public function get($uri, $headers = array())
    {
        return $this->request('get', $uri, array(), $headers);
    }

    public function post($uri, $data = array(), $headers = array())
    {
        return $this->request('post', $uri, $data, $headers);
    }

    public function put($uri, $data = array(), $headers = array())
    {
        return $this->request('put', $uri, $data, $headers);
    }

    public function delete($uri, $data = array(), $headers = array())
    {
        return $this->request('delete', $uri, $data, $headers);
    }

    protected function request($method, $uri, $data = array(), $headers = array())
    {
        $uri = $this->base_url . $uri;
        $client = new \GuzzleHttp\Client();

        // $uri .= '?X-App-Id=' . $this->app['app_id'] . '&X-App-Key=' . $this->app['key'];

        $headers['Content-Type'] = 'application/json';
        $headers['User-Agent'] = 'hook-cli';

        if ($this->app_key) {
            $headers['X-App-Id'] = $this->app_key['app_id'];
            $headers['X-App-Key'] = $this->app_key['key'];
        }

        return $client->{$method}($uri, array('headers' => $headers), json_encode($data), array(
            'exceptions' => false
        ))->json();
    }

}
