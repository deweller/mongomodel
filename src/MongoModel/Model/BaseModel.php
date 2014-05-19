<?php

namespace MongoModel\Model;

use Exception;
use MongoModel\Directory\BaseDirectory;

/*
* User
*/
class BaseModel extends \ArrayObject
{

    function __construct(BaseDirectory $directory, $create_vars=array()) {
        $this->setDirectory($directory);

        parent::__construct($create_vars);
    }


    /**
     * the directory class
     * @var BaseDirectory
     */
    protected $directory = null;


    /**
     * gets the MongoID
     * @return MongoID
     */
    public function getID() {
        return isset($this['_id']) ? $this['_id'] : null;
    }

    /**
     * gets the BaseDirectory
     * @return BaseDirectory
     */
    public function getDirectory() {
        return $this->directory;
    }

    /**
     * reloads this model from the database
     * @return BaseModel
     */
    public function reload() {
        return $this->getDirectory()->reload($this);
    }




    /**
     * describes this item as a string
     * @return string
     */
    public function __toString() {
        return $this->desc();
    }

    /**
     * describes this item as a string
     * @return string
     */
    public function desc() {
        $class = implode('', array_slice(explode('\\', get_class($this)), -1));
        if (!isset($this['_id'])) {
            return "{Anonymous {$class}}";
        }
        return "{{$class} {$this['_id']}}";
    }

    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////

    protected function setDirectory(BaseDirectory $directory) {
        $this->directory = $directory;
    }

}
