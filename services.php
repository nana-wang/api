<?php
use Phalcon\Di\FactoryDefault as DefaultDI, Phalcon\Config\Adapter\Ini as IniConfig, DwComment\Components\Oauth2\Repositories\AuthCodeRepository, DwComment\Components\Oauth2\Repositories\AccessTokenRepository, DwComment\Components\Oauth2\Repositories\ClientRepository, DwComment\Components\Oauth2\Repositories\ScopeRepository, DwComment\Components\Oauth2\Repositories\UserRepository, DwComment\Components\Oauth2\Repositories\RefreshTokenRepository, League\OAuth2\Server\AuthorizationServer, League\OAuth2\Server\ResourceServer, League\OAuth2\Server\Grant\AuthCodeGrant, League\OAuth2\Server\Grant\ClientCredentialsGrant, League\OAuth2\Server\Grant\ImplicitGrant, League\OAuth2\Server\Grant\PasswordGrant, League\OAuth2\Server\Grant\RefreshTokenGrant;
use Phalcon\Mvc\Application;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Db\Profiler;
/**
 * The DI is our direct injector.
 * It will store pointers to all of our services
 * and we will insert it into all of our controllers.
 *
 * @var DefaultDI
 * @author Frank
 */
$di = new DefaultDI();
$di->set('profiler', function () {
    return new \Phalcon\Db\Profiler();
}, true);
$di->setShared('config', function () {
    return new IniConfig(__DIR__ . "/config/config.ini");
});
$di->set("modelsManager", function () {
    return new ModelsManager();
});


$di->set('ServerNeedle', function () {
    return new Phalcon\Http\Request();
});

$di->set('redis', function () {
    $config = new IniConfig(__DIR__ . "/config/config.ini");
    /*
     * $frontCache = new \Phalcon\Cache\Frontend\Data(
     * array(
     * "lifetime" => 172800
     * ));
     * $redis = new Phalcon\Cache\Backend\Redis($frontCache,
     * array(
     * 'host' => $config->redis_master['redis_master_host'],
     * 'port' => $config->redis_master['redis_master_port'],
     * 'auth' => $config->redis_master['redis_master_auth'],
     * 'persistent' => true
     * ));
     */
    $redis = new \redis();
    $redis->connect($config->redis_master['redis_master_host'], $config->redis_master['redis_master_port']);
    return $redis;
});

$di->set('pool', function () {
    $pool = new pdoProxy('mysql:host=' . $config->Beanstalk['beanstalk_host'] . ';dbname=' . $config->database['host'] . ', ' . $config->database['username'] . ', 
                    ' . $config->database['password']);
    return $pool;
});
$availableVersions = $di->getShared('config')->versions;
$allCollections = [];
foreach ($availableVersions as $versionString => $versionPath) {
    $currentCollections = include ('Modules/' . $versionPath . '/Routes/routeLoader.php');
    $allCollections = array_merge($allCollections, $currentCollections);
}
$di->set('collections', function () use ($allCollections) {
    return $allCollections;
});
// As soon as we request the session service, it will be started.
$di->setShared('session', function () {
    $session = new \Phalcon\Session\Adapter\Files();
    $session->start();
    return $session;
});
/**
 * The slowest option! Consider using memcached/redis or another faster caching
 * system than file...
 * Using the file cache just for the sake of the simplicity here
 */
$di->setShared('cache', function () {
    // Cache data for one day by default
    $frontCache = new \Phalcon\Cache\Frontend\Data(array(
        'lifetime' => 3600
    ));
    // File cache settings
    $cache = new \Phalcon\Cache\Backend\File($frontCache, array(
        'cacheDir' => __DIR__ . '/cache/'
    ));
    
    return $cache;
});
$di->setShared('rateLimits', function ($limitType, $identifier, $app) {
    /**
     *
     * @var \Phalcon\Cache\Backend\File $cache
     */
    $cache = $app->cache;
    /**
     *
     * @var \Phalcon\Config\Adapter\Ini $config
     */
    $config = $app->config;
    $limitName = $limitType . '_limits';
    if (property_exists($config, $limitName)) {
        foreach ($config->{$limitName} as $limit => $seconds) {
            $limit = substr($limit, 1, strlen($limit));
            $cacheName = $limitName . $identifier;
            
            if ($cache->exists($cacheName, $seconds)) {
                $rate = $cache->get($cacheName, $seconds);
                /**
                 * using FileCache with many concurrent connections
                 * around 10% of the time boolean is returned instead of
                 * the real cache data.
                 */
                if (gettype($rate) === 'boolean') {throw new \DwComment\Exceptions\HttpException('Server error', 500, null, [
                        'dev' => 'Please try again in a moment',
                        'internalCode' => 'P1011',
                        'more' => ''
                    ]);}
                $rate['remaining'] --;
                $resetAfter = $rate['saved'] + $seconds - time();
                if ($rate['remaining'] > - 1) {
                    $cache->save($cacheName, $rate, $resetAfter);
                }
            } else {
                $rate = [
                    'remaining' => $limit - 1,
                    'saved' => time()
                ];
                $cache->save($cacheName, $rate, $seconds);
                $resetAfter = $seconds;
            }
            
            $app->response->setHeader('X-Rate-Limit-Limit', $limit);
            $app->response->setHeader('X-Rate-Limit-Remaining', ($rate['remaining'] > - 1 ? $rate['remaining'] : 0) . ' ');
            $app->response->setHeader('X-Rate-Limit-Reset', $resetAfter . ' ');
            // 频繁请求中断
            $request = new Request();
            if (true) {
                // if ($rate['remaining'] > - 1) {
                return true;
            } else {
                throw new \DwComment\Exceptions\HttpException('Too Many Requests', 429, null, [
                    'dev' => 'You have reached your limit. Please try again after ' . $resetAfter . ' seconds.',
                    'internalCode' => 'P1010',
                    'more' => $request->getHeaders()
                ]);
            }
        }
    }
    return false;
});
$di->set("client", function () {
	$config = new IniConfig(__DIR__ . "/config/config.ini");
    	$client = new \WebsocketClient($config->websocket['websocket_host'], $config->websocket['websocket_port']);
    return $client;
});

$di->set('db', function () use ($di) {
    $config = $di->getShared('config')->database->toArray();
    $eventsManager = new \Phalcon\Events\Manager();
    $profiler = $di->getProfiler();
    // 监听所有的db事件
    $eventsManager->attach('db', function ($event, $connection) use ($profiler) {
        // 一条语句查询之前事件，profiler开始记录sql语句
        if ($event->getType() == 'beforeQuery') {
            $profiler->startProfile($connection->getSQLStatement());
        }
        // 一条语句查询结束，结束本次记录，记录结果会保存在profiler对象中
        if ($event->getType() == 'afterQuery') {
            $profiler->stopProfile();
        }
    });
    $dbClass = 'Phalcon\Db\Adapter\Pdo\Mysql';
    $connection = new $dbClass($config);
    $connection->setEventsManager($eventsManager);
    return $connection;
});
$di->set('queue', function () use ($di) {
    $config = $di->getShared('config')->beanstalk->toArray();
    $queue = new \Phalcon\Queue\Beanstalk(array(
        'host' => $config['beanstalk_host'],
        'port' => $config['beanstalk_port']
    ));
    return $queue;
});
/**
 * If our request contains a body, it has to be valid JSON.
 * This parses the
 * body into a standard Object and makes that available from the DI. If this
 * service
 * is called from a function, and the request body is nto valid JSON or is
 * empty,
 * the program will throw an Exception.
 */
$di->setShared('requestBody', function () use ($app) {
    $in = trim($app->request->getJsonRawBody());
    // JSON body could not be parsed, throw exception
    if ($in === '') {throw new HttpException('There was a problem understanding the data sent to the server by the application.', 409, array(
            'dev' => 'The JSON body sent to the server was unable to be parsed.',
            'internalCode' => 'REQ1000',
            'more' => ''
        ));}
    
    return $in;
});
// api data
$di->setShared('resourceServer', function () use ($di) {
    $config = $di->getShared('config');
    $server = new ResourceServer(new AccessTokenRepository(), 'file://' . __DIR__ . DIRECTORY_SEPARATOR . $config->oauth['public']);
    return $server;
});
// security salt
$di->set('security', function () {
    $security = new \Phalcon\Security();
    // Set the password hashing factor to 12 rounds
    $security->setWorkFactor(12);
    return $security;
}, true);
// middleware server
$di->setShared('authorizationServer', function () use ($di) {
    $config = $di->getShared('config');
    $server = new AuthorizationServer(new ClientRepository(), new AccessTokenRepository(), new ScopeRepository(), 'file://' . __DIR__ . DIRECTORY_SEPARATOR . $config->oauth['private'], 'file://' . __DIR__ . DIRECTORY_SEPARATOR . $config->oauth['public']);
    
    $userRepository = new UserRepository();
    $refreshTokenRepository = new RefreshTokenRepository();
    $authCodeRepository = new AuthCodeRepository();
    $accessTokenLifetime = new \DateInterval($config->oauth['accessTokenLifetime']);
    $refreshTokenLifetime = new \DateInterval($config->oauth['refreshTokenLifetime']);
    $authorizationCodeLifetime = new \DateInterval($config->oauth['authorizationCodeLifetime']);
    
    /**
     * Using client_id & client_secret & username & password
     */
    $passwordGrant = new PasswordGrant($userRepository, $refreshTokenRepository);
    $passwordGrant->setRefreshTokenTTL($refreshTokenLifetime);
    $server->enableGrantType($passwordGrant, $accessTokenLifetime);
    
    /**
     * Using client_id & client_secret
     */
    $clientCredentialsGrant = new ClientCredentialsGrant();
    $server->enableGrantType($clientCredentialsGrant, $accessTokenLifetime);
    
    /**
     * Using client_id & client_secret
     */
    $refreshTokenGrant = new RefreshTokenGrant($refreshTokenRepository);
    $refreshTokenGrant->setRefreshTokenTTL($refreshTokenLifetime);
    $server->enableGrantType($refreshTokenGrant, $accessTokenLifetime);
    
    /**
     * Using response_type=code & client_id & redirect_uri & state
     */
    $authCodeGrant = new AuthCodeGrant($authCodeRepository, $refreshTokenRepository, $authorizationCodeLifetime);
    $authCodeGrant->setRefreshTokenTTL($refreshTokenLifetime);
    $server->enableGrantType($authCodeGrant, $accessTokenLifetime);
    
    /**
     * Using response_type=token & client_id & redirect_uri & state
     */
    $server->enableGrantType(new ImplicitGrant($accessTokenLifetime), $accessTokenLifetime);
    return $server;
});
