<?php
namespace Magomogo\Persisted;

use Magomogo\Persisted\Test\DbFixture;
use Magomogo\Persisted\Container\Db;
use Magomogo\Persisted\Test\DbNames;
use Magomogo\Persisted\Test\ObjectMother;
use Magomogo\Persisted\Test\Company;
use Magomogo\Persisted\Test\Employee;

class ModelsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DbFixture
     */
    private $fixture;

    protected function setUp()
    {
        $this->fixture = new DbFixture();
        $this->fixture->install();
    }

    /**
     * @dataProvider modelsProvider
     */
    public function testCanBePutInAndLoadedFrom(ModelInterface $model)
    {
        $id = $model->save($this->dbContainer());
        $this->assertEquals($model, $model::load($this->dbContainer(), $id));
    }

    public function testEmployeeModel()
    {
        $props = ObjectMother\Employee::maximProperties();
        $props->foreign()->company->putIn($this->dbContainer());
        $id = $props->putIn($this->dbContainer(), $props->foreign()->company);

        $this->assertEquals(
            new Employee\Model(new Company\Model($props->foreign()->company), $props, $props->tags),
            Employee\Model::load($this->dbContainer(), $id)
        );
    }

    public static function modelsProvider()
    {
        return array(
            array(ObjectMother\CreditCard::datatransTesting()),
            array(ObjectMother\Person::maxim()),
            array(ObjectMother\Person::maximWithoutCC()),
            array(ObjectMother\Company::xiag()),
            array(ObjectMother\Keymarker::friend()),
        );
    }

    private function dbContainer()
    {
        return new Db($this->fixture->db, new DbNames);
    }
}
