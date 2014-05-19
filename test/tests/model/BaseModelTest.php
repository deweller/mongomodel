<?php

use MongoModel\Test\Directory\FooDirectory;
use MongoModel\Test\Model\FooModel;
use \Exception;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* 
*/
class BaseModelTest extends \PHPUnit_Framework_TestCase
{


    public function testDesc() {
        $dir = new FooDirectory($this->getMongoDB());
        $model = $dir->create();
        PHPUnit::assertEquals("{Anonymous FooModel}", $model->desc());
    }

    public function testGetDirectory() {
        $dir = new FooDirectory($this->getMongoDB());
        $model = $dir->create();
        $directory = $model->getDirectory();
        PHPUnit::assertTrue($directory instanceof FooDirectory);
    }

    public function testSerializeJSON() {
        $dir = new FooDirectory($this->getMongoDB());
        $model = $dir->create(['baz' => 'one']);
        PHPUnit::assertContains('"baz":"one"', json_encode($model));
    }



    protected function getMongoDB() {
        $m = new MongoClient('localhost');
        return $m->selectDB("test-mongomodel");
    }
}
