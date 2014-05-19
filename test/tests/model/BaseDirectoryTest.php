<?php

use MongoModel\Model\BaseModel;
use MongoModel\Test\Directory\BarDirectory;
use MongoModel\Test\Directory\BazDirectory;
use MongoModel\Test\Directory\FooDirectory;
use MongoModel\Test\Model\FooModel;
use MongoModel\Test\Model\Special\BarModel;
use MongoModel\Test\Model\Special\SpecialBazModel;
use MongoModel\Util\ModelUtil;
use MongoModel\Util\SerialUtil;
use \PHPUnit_Framework_Assert as PHPUnit;

/*
* Requires MongoDB running on localhost
* Will create a DB called test-mongomodel
*/
class BaseDirectoryTest extends \PHPUnit_Framework_TestCase
{

    public function testCreate() {
        $directory = new FooDirectory($this->getMongoDB());
        $model = $directory->create(['memoryOnly' => 'yes']);
        PHPUnit::assertObjectNotHasAttribute('_id', $model);
        PHPUnit::assertEquals('yes', $model['memoryOnly']);
    }

    public function testCreateAndSave() {
        $directory = new FooDirectory($this->getMongoDB());
        $model = $directory->create([]);
        $model['added1'] = 'yes';
        $model = $directory->save($model);
        PHPUnit::assertNotNull($model['_id']);


        $model = $directory->reload($model);
        PHPUnit::assertEquals('yes', $model['added1']);
    }


    public function testGetModel() {
        $directory = new FooDirectory($this->getMongoDB());
        $model = $directory->create([]);
        PHPUnit::assertTrue($model instanceof FooModel);
        $model = $directory->create([]);
        PHPUnit::assertTrue($model instanceof FooModel);
    }

    public function testGetSpecialModels() {
        $directory = new BarDirectory($this->getMongoDB());
        $model = $directory->create([]);
        PHPUnit::assertTrue($model instanceof BarModel);
        $model = $directory->create([]);
        PHPUnit::assertTrue($model instanceof BarModel);

        $directory = new BazDirectory($this->getMongoDB());
        $model = $directory->create([]);
        PHPUnit::assertTrue($model instanceof SpecialBazModel);
        $model = $directory->create([]);
        PHPUnit::assertTrue($model instanceof SpecialBazModel);
    }

    public function testSerialWithRetries() {
        $directory = new FooDirectory($this->getMongoDB());
        $model = $directory->createAndSave(['bar' => 'baz']);
        PHPUnit::assertEquals('baz', $model['bar']);

        $attempts = 0;
        $directory->updateWithSerialConstraint($model, function($model, $update_vars, $attempt_offset) use (&$attempts) {
            if ($attempt_offset < 5) {
                $this->makeExternalModificationToModel($model, $attempt_offset);
            }

            ++$attempts;
            return array(
                'goodMod' => 1,
            );
        }, 10, 1000);

        PHPUnit::assertEquals(6, $attempts);
        $model = $directory->reload($model);
        PHPUnit::assertEquals(5, $model['badMod']);
        PHPUnit::assertEquals(1, $model['goodMod']);
    }



    ////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////

    protected function makeExternalModificationToModel(BaseModel $model, $offset) {
        usleep(2);

        $directory = new FooDirectory($this->getMongoDB());
        $directory->update($model, ['badMod' => $offset+1, 'serial' => SerialUtil::newSerial()]);

        usleep(2);
    }

    protected function getMongoDB() {
        $m = new MongoClient('localhost');
        return $m->selectDB("test-mongomodel");
    }

}
