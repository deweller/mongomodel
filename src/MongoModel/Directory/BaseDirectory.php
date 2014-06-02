<?php

namespace MongoModel\Directory;


use MongoModel\Model\BaseModel;
use MongoModel\Util\CallbackIterator;
use MongoModel\Util\SerialUtil;
use MongoDB;
use MongoDate;
use MongoCollection;
use Exception;

/*
* User
*/
class BaseDirectory
{

    /**
     * An optional model namespace
     *
     * like MyClasses\Model
     * @var string
     */
    protected $model_namespace = null;

    /**
     * an optional fully qualified class name for the model
     * like MyClasses\MySpecialModels\SpecialModel
     * @var string
     */
    protected $model_class = null;

    /**
     * whether to add a creationDate field on creating
     * @var boolean
     */
    protected $add_creation_date = true;

    /**
     * whether to add and update a serial field
     * @var boolean
     */
    protected $use_serial = true;

    /**
     * an optional collection name
     * This will be determined automatically by the class name if left null
     * @var string
     */
    protected $collection_name;



    /**
     * the MongoDB instance
     * @var MongoDB
     */
    private $mongodb;


    /**
     * create a new directory
     * @param MongoDB $mongodb
     */
    public function __construct(MongoDB $mongodb) {
        $this->mongodb = $mongodb;
    }

    /**
     * creates a new model
     * @param  array  $create_vars
     * @return BaseModel a new model
     */
    public function createAndSave($create_vars=[]) {
        // build new object
        $new_model = $this->create($create_vars);

        // save it to the database
        return $this->save($new_model);
    }

    /**
     * creates a model without saving to the database
     * @param  array $create_vars
     * @return BaseModel a new model
     */
    public function create($create_vars=[]) {
        // add
        $new_model_doc_vars = array_merge($this->newModelDefaults(), $create_vars);
        $new_model_doc_vars = $this->onCreate_pre($new_model_doc_vars);

        return $this->newModel($new_model_doc_vars);
    }

    /**
     * saves a new model to the database
     * @param  array $create_vars
     * @return BaseModel a new model
     */
    public function save(BaseModel $model) {
        $model_doc_vars = (array)$model;
        $this->getCollection()->save($model_doc_vars);

        // add the id
        $model['_id'] = $model_doc_vars['_id'];

        return $model;
    }

    /**
     * finds a model by ID
     * @param  BaseModel|MongoID|string $item_or_id
     * @return BaseModel|null
     */
    public function findByID($item_or_id) {
        $id = $this->extractID($item_or_id);
        return $this->findOne(['_id' => $id]);
    }


    /**
     * queries the database for a single object
     * @param  array $query
     * @return BaseModel or null
     */
    public function findOne($query) {
        $doc = $this->getCollection()->findOne($query);
        if ($doc) {
            return $this->newModel($doc);
        }

        return null;
    }

    /**
     * Finds all models for this collection
     * @return Iterator a collection of BaseModels
     */
    public function findAll() {
        return $this->find([]);
    }


    /**
     * queries the database for models
     * @param  array $query
     * @param  array $sort
     * @param  array $limit
     * @return Iterator a collection of BaseModels
     */
    public function find($query, $sort=null, $limit=null) {
        $cursor = $this->getCollection()->find($query);
        if ($sort !== null) { $cursor->sort($sort); }
        if ($limit !== null) { $cursor->limit($limit); }
        return new CallbackIterator($cursor, function($doc) {
            return $this->newModel($doc);
        });
    }


    /**
     * reloads the model from the database
     * @param  BaseModel|MongoID|string $item_or_id
     * @return BaseModel
     */
    public function reload($item_or_id) {
        return $this->findByID($item_or_id);
    }


    /**
     * updates a model in the database
     * @param  BaseModel|MongoID|string $item_or_id
     * @param  array $update_vars
     * @param  array $mongo_options
     * @return BaseModel
     */
    public function update($item_or_id, $update_vars, $mongo_options=[]) {
        $id = $this->extractID($item_or_id);
        return $this->updateWhere($update_vars, ['_id' => $id], $mongo_options);
    }


    /**
     * updates multiple models in the database
     * @param  array $update_vars
     * @param  array $query
     * @param  array  $mongo_options
     * @return integer the number of items updated
     */
    public function updateWhere($update_vars, $query, $mongo_options=[]) {
        if ($this->use_serial AND !isset($update_vars['serial'])) {
            $update_vars['serial'] = SerialUtil::newSerial();
        }
        $update_vars = $this->onUpdate_pre($update_vars);
        $update_commands = ['$set' => $update_vars];
        $result = $this->getCollection()->update($query, $update_commands, $mongo_options);
        return $result['n'];
    }


    /**
     * updates a model based on the serial number
     *
     * Uses optimistic locking to avoid conflicts
     * <code>
     * $user = $user_directory->updateWithSerialConstraint($user, function($user, $update_vars, $attempt_offset) use($bar) {
     *     $update_vars['foo'] = $user['foo'] - $bar;
     *     return $update_vars;
     * });
     * </code>
     * @param  BaseModel $model
     * @param  callable  $callback a function that accepts BaseModel $model, array $update_vars, integer $attempt_offset and returns $update_vars
     * @param  integer   $max_attempts maximum attempts to try updating this model
     * @param  integer   $sleep microseconds to sleep between update attempts
     * @return BaseModel
     */
    public function updateWithSerialConstraint(BaseModel $model, $callback, $max_attempts=10, $sleep=250000) {
        return $this->updateWithQueryConstraint($model, function($model, $attempt_offset) use ($callback) {
            $prepop_update_vars = ['serial' => SerialUtil::newSerial()];
            $query_vars = ['serial' => $model['serial']];

            $update_vars = $callback($model, $prepop_update_vars, $attempt_offset);
            if (!is_array($update_vars)) { throw new Exception("invalid \$update_vars found", 1); }

            return [$update_vars, $query_vars];
        }, $max_attempts, $sleep);
    }


    /**
     * updates a model based on the serial number
     *
     * Uses optimistic locking to avoid conflicts
     * <code>
     * $user = $user_directory->updateWithQueryConstraint($user, function($user, $attempt_offset) use($bar) {
     *     $query_vars = ['id' => 1001];
     *     $update_vars['foo'] = $user['foo'] - $bar;
     *     return [$update_vars, $query_vars];
     * });
     * </code>
     * @param  BaseModel $model
     * @param  callable  $callback a function that accepts BaseModel $model, integer $attempt_offset and returns [$update_vars, $query_vars]
     * @param  integer   $max_attempts maximum attempts to try updating this model
     * @param  integer   $sleep microseconds to sleep between update attempts
     * @return BaseModel
     */    
    public function updateWithQueryConstraint(BaseModel $model, $callback, $max_attempts=10, $sleep=250000) {
        $attempts_remaining = $max_attempts;

        $attempt_offset = 0;
        while (true) {
            list($update_vars, $query_vars) = $callback($model, $attempt_offset);
            if (!is_array($update_vars)) { throw new Exception("invalid \$update_vars found", 1); }
            if (!is_array($query_vars)) { throw new Exception("invalid \$query_vars found", 1); }

            $query_vars['_id'] = $model->getID();
            $success = $this->updateWhere($update_vars, $query_vars);
            if ($success) {
                $model = $this->reload($model);
                break;
            }

            --$attempts_remaining;
            if ($attempts_remaining <= 0) { throw new Exception("Failed to update ".$model." after $max_attempts attempts.", 1); }

            usleep($sleep);
            ++$attempt_offset;
            $model = $this->reload($model);
        }

        return $model;
    }

    /**
     * deletes a single item
     * @param  BaseModel|MongoID|string $item_or_id
     * @param  array  $mongo_options
     */
    public function delete($item_or_id, $mongo_options=[]) {
        $id = $this->extractID($item_or_id);
        $this->getCollection()->remove(['_id' => $id], $mongo_options);
    }

    /**
     * deletes all items in this collection
     */
    public function deleteAll() {
        $this->getCollection()->remove([]);
    }


    /**
     * builds DB indexes specific to this directory
     */
    public function bringUpToDate() {
        // parent::bringUpToDate();

        // build indexes, etc
        // $this->getCollection()->ensureIndex(['primaryUser' => 1], ['unique' => true]);

        return;
    }


    ////////////////////////////////////////////////////////////////////////

    protected function newModel($data) {
        if (isset($this->model_class)) {
            $class = $this->model_class;

        } else {
            $namespace_tokens = explode('\\', get_class($this));

            // transforms FooDirectory to FooModel
            $directory_class = implode('', array_slice($namespace_tokens, -1));
            $model_class_name = substr($directory_class, 0, -9)."Model";

            if (!$this->model_namespace) {
                // transforms ACME\MyStuff\Directory to ACME\MyStuff\Model
                $this->model_namespace = implode('\\', array_slice($namespace_tokens, 0, -2)).'\\Model';
            }

            $this->model_class = "{$this->model_namespace}\\{$model_class_name}";
            $class = $this->model_class;
        }

        return new $class($this, $data);
    }

    protected function newModelDefaults() {
        $out = [];
        if ($this->add_creation_date) {
            $out['creationDate'] = new MongoDate();
        }
        if ($this->use_serial) {
            $out['serial'] = SerialUtil::newSerial();
        }
        return $out;
    }

    protected function getCollection() {
        if ($this->collection_name === null) {
            $directory_class = implode('', array_slice(explode('\\', get_class($this)), -1));
            $model_class = substr($directory_class, 0, -9)."Model";
            $collection_name = $model_class;
        } else {
            $collection_name == $this->collection_name;
        }

        return new MongoCollection($this->mongodb, $collection_name);
    }



    protected function extractID($item_or_id) {
        if ($item_or_id instanceof \MongoId) { return $item_or_id; }
        if (is_object($item_or_id)) { return $item_or_id['_id']; }
        if (is_string($item_or_id)) { return new \MongoId($item_or_id); }

        throw new Exception("Unknown ID: ".json_encode($item_or_id)."", 1);
    }


    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////
    // create / update modifiers

    protected function onCreate_pre($create_vars) {
        // modify all create operations
        return $this->onCreateOrUpdate_pre($create_vars);
    }

    protected function onUpdate_pre($update_vars) {
        // modify all updates
        return $this->onCreateOrUpdate_pre($update_vars);
    }
    

    protected function onCreateOrUpdate_pre($vars) {
        return $vars;
    }    


}
