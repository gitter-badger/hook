<?php
namespace API\Model;

use API\Database\CollectionDelegator as CollectionDelegator;

/**
 * App
 */
class App extends Model
{

    public static function boot()
    {
        parent::boot();
        static::created(function ($instance) { $instance->afterCreate(); });
    }

    /**
     * currentId
     * @static
     * @return int
     */
    public static function currentId()
    {
        $app = \Slim\Slim::getInstance();

        return $app->key->app_id;
    }

    /**
     * collection
     * @static
     * @param  mixed                        $name name
     * @return Database\CollectionDelegator
     */
    public static function collection($name)
    {
        return new CollectionDelegator($name, static::currentId());
    }

    public function keys()
    {
        return $this->hasMany('API\Model\AppKey', 'app_id');
    }

    public function modules()
    {
        return $this->hasMany('API\Model\Module', 'app_id');
    }

    public function configs()
    {
        return $this->hasMany('API\Model\AppConfig', 'app_id');
    }

    public function generateKey($admin=false)
    {
        return $this->keys()->create(array('admin' => $admin));
    }

    public function afterCreate()
    {
        // Generate admin key
        $this->generateKey(true);

        // Generate user key
        $this->generateKey();

        // Create storage directory for this app
        $storage_dir = storage_dir(true, $this->_id);
        if (!file_exists($storage_dir)) {
            mkdir($storage_dir, 0777, true);
        }
    }

    public function toArray()
    {
        $arr = parent::toArray();
        $arr['keys'] = $this->keys->toArray();

        return $arr;
    }

}