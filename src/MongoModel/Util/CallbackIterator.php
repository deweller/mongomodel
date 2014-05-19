<?php

namespace MongoModel\Util;

use \Exception;

/*
* CallbackIterator
*/
class CallbackIterator extends \IteratorIterator implements \Countable {

    protected $callback_fn;

    public function __construct(\Iterator $iterator, \Closure $callback_fn = null) {
        $this->callback_fn = $callback_fn;
        parent::__construct($iterator);
    }


    public function current() {
        $out = parent::current();

        if ($this->callback_fn) {
            $modified_out = call_user_func($this->callback_fn, $out);
            return $modified_out;
        }

        return $out;
    }

    public function count() {
        $inner_iterator = $this->getInnerIterator();

        if (method_exists($inner_iterator, 'count')) {
            return $inner_iterator->count();
        }

        return 0;
    }
}