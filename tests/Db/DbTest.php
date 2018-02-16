<?php
/**
 * Tlumx (https://tlumx.github.io/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-db
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-db/blob/master/LICENSE  (MIT License)
 */
namespace Tlumx\Tests\Db;

use Tlumx\Db\Db;

class DbTest extends \PHPUnit_Framework_TestCase
{

    protected $db;

    public function setUp()
    {
        $db = new Db("sqlite::memory:", null, null, null, true);
        return $this->db = $db;
    }

    public function tearDown()
    {
        unset($this->db);
    }

    public function makeTestTable(\PDO $pdo)
    {
        $pdo->query("create table users ( id INTEGER PRIMARY KEY, name TEXT)");
        $pdo->query("insert into users (name) values ('Name1')");
        $pdo->query("insert into users (name) values ('Name2')");
    }

    public function testConnection()
    {
        $this->assertEquals($this->db->isConnect(), false);
        $this->assertInstanceOf('\PDO', $this->db->getConnection());
        $this->assertEquals($this->db->isConnect(), true);
        $this->db->close();
        $this->assertEquals($this->db->isConnect(), false);
        $this->db->connect();
        $this->assertEquals($this->db->isConnect(), true);
    }

    public function testProfiler()
    {

        $this->assertEquals($this->db->getEnabledProfiler(), true);
        $this->assertInstanceOf('Tlumx\Db\DbProfiler', $this->db->getProfiler());
        $this->db->setEnabledProfiler(false);
        $this->assertEquals($this->db->getEnabledProfiler(), false);
        $this->db->setEnabledProfiler(true);
        $this->assertEquals($this->db->getEnabledProfiler(), true);

        $key = $this->db->startProfiler('some sql', ['a' => 100,'b' => 'foo']);
        usleep(1000);
        $this->db->endProfiler($key);
        $profiler = $this->db->getProfiler();
        $this->assertEquals(count($profiler->getProfiles()), 1);

        $profile = $profiler->getProfile($key);
        $this->assertEquals($profile['sql'], 'some sql');
        $this->assertEquals($profile['params'], ['a' => 100, 'b' => 'foo']);
        $this->assertEquals($profile['total'], $profile['end'] - $profile['start']);
    }

    public function testGetDriverName()
    {
        $this->assertEquals($this->db->getDriverName(), 'sqlite');
    }

    public function testQuoteValue()
    {
        $string = 'Great';
        $actual = $this->db->quoteValue("some str: $string\n");
        $expect = "'some str: Great\n'";
        $this->assertEquals($actual, $expect);
    }

    public function testQuoteIdentifier()
    {
        $this->assertEquals($this->db->quoteIdentifier('a'), '`a`');
        $this->assertEquals($this->db->quoteIdentifier('a.b'), '`a`.`b`');
    }

    public function testTransaction()
    {

        $this->db->beginTransaction();
        $this->db->commit();

        $profiler = $this->db->getProfiler();
        $profiles = $profiler->getProfiles();
        $this->assertEquals($profiles[1]['sql'], 'begin transaction');
        $this->assertEquals($profiles[2]['sql'], 'commit transaction');

        try {
            $this->db->rollBack();
            $this->assertEquals($profiles[3]['sql'], 'rollback transaction');
        } catch (\PDOException $e) {
            $this->assertTrue(!in_array(3, $profiles));
        }
    }

    public function testLastInsertId()
    {
        $pdo = $this->db->getConnection();
        $this->makeTestTable($pdo);
        $pdo->query("insert into users (name) values ('Name3')");
        $result = $this->db->lastInsertId();
        $this->assertEquals($result, 3);
    }

    public function testExecute()
    {
        $this->makeTestTable($this->db->getConnection());
        $count = $this->db->execute('DELETE FROM users WHERE name = :name', [':name' => 'Name1']);
        $this->assertEquals($count, 1);
        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'DELETE FROM users WHERE name = :name');
        $this->assertEquals($profile['params'], [':name' => 'Name1']);
    }

    public function testFindRows()
    {
        $this->makeTestTable($this->db->getConnection());
        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);

        $this->assertEquals($result, [['id' => 1,'name' => 'Name1']]);
        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'SELECT * FROM users WHERE name = :name');
        $this->assertEquals($profile['params'], [':name' => 'Name1']);
    }

    public function testFindRow()
    {
        $this->makeTestTable($this->db->getConnection());
        $result = $this->db->findRow('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);

        $this->assertEquals($result, ['id' => 1,'name' => 'Name1']);
        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'SELECT * FROM users WHERE name = :name');
        $this->assertEquals($profile['params'], [':name' => 'Name1']);
    }

    public function testFindFirstColumn()
    {
        $this->makeTestTable($this->db->getConnection());
        $result = $this->db->findFirstColumn('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
        $this->assertEquals($result, ['0' => 1]);
        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'SELECT * FROM users WHERE name = :name');
        $this->assertEquals($profile['params'], [':name' => 'Name1']);
    }

    public function testFindOne()
    {
        $this->makeTestTable($this->db->getConnection());
        $result = $this->db->findOne('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
        $this->assertEquals($result, 1);
        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'SELECT * FROM users WHERE name = :name');
        $this->assertEquals($profile['params'], [':name' => 'Name1']);
    }

    public function testInsert()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->insert('users', ['name' => 'New Name']);
        $this->assertEquals($res, 1);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'INSERT INTO `users` (`name`) VALUES (?)');
        $this->assertEquals($profile['params'], ['name' => 'New Name']);

        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'New Name']);
        $this->assertEquals($result, [['id' => 3,'name' => 'New Name']]);
    }

    public function testUpdate()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->update('users', ['name' => 'New Name'], ['id' => 1]);
        $this->assertEquals($res, 1);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'UPDATE `users` SET `name` = ? WHERE `id` = ?');
        $this->assertEquals($profile['params'], [
            'params' => [
                'name' => 'New Name'
            ],
            'where' => [
                'id' => 1
            ]
        ]);

        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'New Name']);
        $this->assertEquals($result, [['id' => 1,'name' => 'New Name']]);
    }

    public function testDelete()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->delete('users', ['name' => 'Name1']);
        $this->assertEquals($res, 1);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'DELETE FROM `users` WHERE `name` = ?');
        $this->assertEquals($profile['params'], ['where' => ['name' => 'Name1']]);
        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
        $this->assertEquals($result, []);
    }
}
