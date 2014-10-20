<?php namespace Hook\Database;

use Hook\Model\App;
use Hook\Model\AppKey;

use Hook\Http\Router;

/**
 * AppContext
 * @author Endel Dreyer <edreyer@doubleleft.com>
 */
class AppContext
{
    protected static $app_key;

    /**
     * config
     *
     * @param mixed $name
     *
     * @return string
     */
    public static function config($name) {
        return Router::getInstance()->config($name);
    }

    /**
     * setKey
     *
     * @param mixed $app_id
     * @param string $key
     *
     * @return Hook\Model\AppKey
     */
    public static function validateKey($app_id, $key) {
        $app_key = AppKey::with('app')
            ->where('app_id', $app_id)
            ->where('key', $key)
            ->first();

        if ($app_key) {
            return static::setKey($app_key);
        }
    }

    public static function clear() {
        static::$app_key = null;
        static::setTablePrefix('');
        AppContext::setPrefix(null);
    }

    public static function setKey($app_key) {
        static::$app_key = $app_key;
        AppContext::setPrefix($app_key->app->_id);
        return static::$app_key;
    }

    public static function getAppKeys($type=null) {
        $app_id = self::getAppId();
        if (!$app_id) { throw new \Exception("app_id is required."); }

        // keep previous
        $connection = \DLModel::getConnectionResolver()->connection();
        $previous_prefix = $connection->getTablePrefix();
        static::setTablePrefix('');

        // filter by app_id
        $query = AppKey::where('app_id', $app_id);

        // filter by type if specified
        if ($type) { $query->where('type', $type); }
        $app_keys = $query->get();

        static::setTablePrefix($previous_prefix);
        return $app_keys;
    }

    public static function getKey() {
        return static::$app_key;
    }

    public static function getAppId() {
        return static::getKey()->app_id;
    }

    public static function setPrefix($prefix = null) {
        if ($prefix) {
            $prefix = 'app' . $prefix . '_';
        }

        // set database prefix
        $connection = \DLModel::getConnectionResolver()->connection();
        static::setTablePrefix($connection->getTablePrefix() . $prefix);

        // set cache prefix
        $connection->getCacheManager()->setPrefix($prefix);
    }

    /**
     * getPrefix
     *
     * @return string
     */
    public static function getPrefix() {
        $connection = \DLModel::getConnectionResolver()->connection();
        return $connection->getTablePrefix();
    }

    public static function setTablePrefix($prefix) {
        $connection = \DLModel::getConnectionResolver()->connection();
        if ($connection->getPdo()) {
            $connection->setTablePrefix($prefix);
        }
    }

    /**
     * migrate
     *
     * Migrate core application schema.
     */
    public static function migrate() {
        $connection = \DLModel::getConnectionResolver()->connection();
        if ($connection->getPdo()) {
            $builder = $connection->getSchemaBuilder();
            if (!$builder->hasTable('modules')) {
                foreach (glob(__DIR__ . '/../../migrations/app/*.php') as $file) {
                    $migration = require($file);
                    if (is_array($migration)) {
                        $builder->create($connection->getTablePrefix() . key($migration), current($migration));
                    }
                }
            }
        }
    }

}
