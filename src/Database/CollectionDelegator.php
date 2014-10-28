<?php
namespace Hook\Database;

use Hook\Model\App;
use Hook\Model\Collection;
use Hook\Exceptions\UnauthorizedException;

use ArrayIterator;
use IteratorAggregate;

/**
 * CollectionDelegator
 * @extends IteratorAggregate
 * @author Endel Dreyer <edreyer@doubleleft.com>
 */
class CollectionDelegator implements IteratorAggregate
{
    /**
     * name
     * @var string
     */
    protected $name;

    /**
     * query
     * @var Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * is_collection
     * @var bool
     */
    protected $is_collection;

    /**
     * custom_collections
     * @var array
     */
    static $custom_collections = array(
        'auths' => 'Hook\\Model\\Auth',
        'auth_tokens' => 'Hook\\Model\\AuthToken',
        'files' => 'Hook\\Model\\File',
        'push_messages' => 'Hook\\Model\\PushMessage',
        'push_registrations' => 'Hook\\Model\\PushRegistration',
    );

    /**
     * Create a new CollectionDelegator instance.
     *
     * @param string                            $name
     * @param Illuminate\Database\Query\Builder $query  query
     */
    public function __construct($name)
    {
        // force plural collection names.
        $name = str_plural($name);
        $is_collection = (!isset(static::$custom_collections[$name]));

        // protected collections
        if ($name == 'modules' || $name == '__sessions') {
            throw new UnauthorizedException('not_authorized');
        }

        if ($is_collection) {
            $query = Collection::from($name);
        } else {
            $tmp_query = call_user_func(array(static::$custom_collections[$name], 'query'));
            $tmp_query->getModel()->setTable($name);
            $query = $tmp_query->getModel()->newQuery();
        }

        $this->name = $name;
        $this->is_collection = $is_collection;
        $this->query = $query;
    }

    /**
     * getName
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Get query items for iteration.
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->get()->all());
    }

    /**
     * each
     *
     * @param Closure $callback
     * @return Database\CollectionDelegator
     */
    public function each(\Closure $callback) {
        foreach ($this as $row) {
            $callback($row);
        }

        return $this;
    }

    /**
     * Create a new Collection instance. No database operations here.
     *
     * @param  array             $attributes attributes
     * @return \Model\Collection
     */
    public function create_new(array $attributes = array())
    {
        if (!$this->is_collection) {
            $klass = self::$custom_collections[$this->name];

            return new $klass($attributes);

        } else {
            $attributes['table_name'] = $this->name;

            return new Collection($attributes);
        }
    }

    /**
     * Create a new record into the database.
     *
     * @param  array             $attributes attributes
     * @return \Model\Collection
     */
    public function create(array $attributes)
    {
        $model = $this->create_new($attributes);
        $model->save();

        return $model;
    }

    /**
     * Update a record in the database.
     *
     * @param  array $values
     * @return int
     */
    public function update(array $values)
    {
        $allowed = $this->fireEvent('updating_multiple', array($this, $values));
        if ($allowed === false) {
            return false;
        } elseif (is_array($allowed)) {
            $values = $allowed;
        }

        return $this->query->update($values);
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed $id
     * @return int
     */
    public function delete($id = null)
    {
        if ($id === null && $this->fireEvent('deleting_multiple', $this) === false) {
            return false;
        }

        return $this->query->delete($id);
    }

    /**
     * Alias to delete
     * @see delete
     */
    public function remove($id = null)
    {
        return $this->delete($id);
    }

    /**
     * filter
     *
     * @param  array                        $filters filters
     * @return Database\CollectionDelegator
     */
    public function filter($filters = null)
    {
        if ($filters) {
            foreach ($filters as $where) {
                // Use 'and' as default boolean method
                if (!isset($where[3])) { $where[3] = 'and'; }

                // Sugar for 'IN' operations
                if ($where[1] == '=' && gettype($where[2]) == 'array') {
                    $where[1] = 'in';

                } else if ($where[1] == '!=' && $where[2] == null) {
                    // Workaround to support whereNotNull
                    $where[1] = 'not_null';
                    $where[2] = 'and';
                }

                if (preg_match('/^[a-z_]+$/', $where[1]) !== 0 && strtolower($where[1]) !== 'like') {
                    $method = 'where' . ucfirst(\Illuminate\Support\Str::camel($where[1]));
                    $this->query->{$method}($where[0], $where[2], $where[3]);
                } else {
                    $this->query->where($where[0], $where[1], $where[2], $where[3]);
                }
            }
        }

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  dynamic  $columns
     * @return \Illuminate\Database\Query\Builder
     */
    public function group()
    {
        call_user_func_array(array($this->query, 'groupBy'), func_get_args());
        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string                       $column
     * @param  string|int                   $direction
     * @return Database\CollectionDelegator
     */
    public function sort($column, $direction = 'asc')
    {
        if (is_int($direction)) {
            $direction = ($direction == -1) ? 'desc' : 'asc';
        }
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int      $count
     * @param  callable $callback
     * @return void
     */
    public function chunk($count, $callback)
    {
        //
        // ----------------
        // Developer alert:
        // ----------------
        //
        // This block of code is exactly the same on
        // Eloquent\Builder and Query\Builder as of this writing.
        //
        // It was necessary to define it here to CollectionDelegator
        // be able to intercept the ->get() method and fix the
        // Collection table's name.
        //

        $results = $this->forPage($page = 1, $count)->get();

        while (count($results) > 0) {
            echo PHP_EOL . PHP_EOL;
            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            call_user_func($callback, $results);

            $page++;

            $results = $this->forPage($page, $count)->get();
        }
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array                                             $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = array('*'))
    {
        if ($this->is_collection) {
            $this->query->setModel(new Collection(array('table_name' => $this->name)));
        } elseif ($this->query instanceof \Illuminate\Database\Query\Builder) {
            $this->query->from($this->name);
        }

        return $this->__call('get', func_get_args());
    }

    /**
     * Shortcut for get+toArray methods.
     * @param  string $columns columns
     * @return array
     */
    public function toArray($columns=array('*'))
    {
        return $this->query->get($columns)->toArray();
    }

    /**
     * Shortcut for get+toJson methods.
     * @param  string $columns columns
     * @return string
     */
    public function toJson($columns=array('*'))
    {
        return $this->query->get($columns)->toJson();
    }

    protected function fireEvent($event, $payload)
    {
        $dispatcher = Collection::getEventDispatcher();
        if (!$dispatcher) return true;

        $event = "eloquent.{$event}: ".$this->name;

        return $dispatcher->until($event, $payload);
    }

    /**
     * getQueryBuilder
     * @return \Illuminate\Database\Eloquent\Builder | \Illuminate\Database\Query\Builder
     */
    public function getQueryBuilder() {
        return $this->query;
    }

    /**
     * Handle Illuminate\Database\Query\Builder methods.
     *
     * @param  mixed                        $method     method
     * @param  mixed                        $parameters parameters
     * @return Database\CollectionDelegator
     */
    public function __call($method, $parameters)
    {
        try {
            $mixed = call_user_func_array(array($this->query, $method), $parameters);

        } catch (\BadMethodCallException $e) {
            $model = App::collection($this->name)->getModel();
            $mixed = call_user_func_array(array($model, $method), $parameters);
        }

        if ($mixed instanceof \Illuminate\Database\Eloquent\Builder || $mixed instanceof \Illuminate\Database\Query\Builder) {
            return $this;
        } else {
            return $mixed;
        }
    }

}
