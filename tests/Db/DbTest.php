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
use Tlumx\Db\Exception\DbException;

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

    public function testDbConnectException()
    {
        $db = new Db("bugsqlite::memory:", null, null, null, true);
        $this->expectException(DbException::class);
        $db->connect();
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

    public function testStartNoEnableProfiler()
    {
        $this->db->setEnabledProfiler(false);
        $this->assertNull($this->db->startProfiler('sql'));
    }

    public function testEndNoEnableProfiler()
    {
        $this->db->setEnabledProfiler(false);
        $this->assertNull($this->db->endProfiler('key'));
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
        $this->assertEquals($expect, $actual);
    }

    public function testQuoteNotStringValue()
    {
        $actual = $this->db->quoteValue(12);
        $this->assertEquals(12, $actual);
    } 

    public function testQuoteNotDriveSupport()
    {        
        $stubPDO = $this->createMock(\PDO::class);
        $stubPDO->method('quote')
             ->willReturn(false);

        $reflectionDb = new \ReflectionClass($this->db);
        $reflection_property = $reflectionDb->getProperty('dbh');
        $reflection_property->setAccessible(true);

        $reflection_property->setValue($this->db, $stubPDO);

        $string = 'Great';
        $actual = $this->db->quoteValue("some str: $string\n");
        $expect = "'some str: Great\\n'";
        $this->assertEquals($expect, $actual);
    }    

    public function quote($value)
    {
        return false;
    }

    public function testQuoteIdentifier()
    {
        $this->assertEquals($this->db->quoteIdentifier('a'), '`a`');
        $this->assertEquals($this->db->quoteIdentifier('a.b'), '`a`.`b`');
    }

    public function testDoQuoteIdentifier()
    {
        $this->assertEquals($this->db->quoteIdentifier('*'), '*');

        // for sqlsrv, mssql, dblib
        $db = new Db("sqlsrv::memory:", null, null, null, true);
        $this->assertEquals($db->quoteIdentifier('a.b'), '[a].[b]');

        // for default
        $db = new Db("bugsqlite::memory:", null, null, null, true);
        $this->assertEquals($db->quoteIdentifier('a.b'), '"a"."b"');
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

    public function testRollBackTransaction()
    {
        $this->db->beginTransaction();   

        $profiler = $this->db->getProfiler();
        $profiles = $profiler->getProfiles();
        $this->assertEquals($profiles[1]['sql'], 'begin transaction');

        $this->db->rollBack();

        $profiles = $profiler->getProfiles();
        $this->assertEquals($profiles[2]['sql'], 'rollback transaction');
    }

    public function testLastInsertId()
    {
        $pdo = $this->db->getConnection();
        $this->makeTestTable($pdo);
        $pdo->query("insert into users (name) values ('Name3')");
        $result = $this->db->lastInsertId();
        $this->assertEquals($result, 3);
    }

    public function testExceptiontLastInsertId()
    {
        $e = new DbException();

        $stubPDO = $this->createMock(\PDO::class);
        $stubPDO->method('lastInsertId')
                ->will($this->throwException(new \PDOException));

        $reflectionDb = new \ReflectionClass($this->db);
        $reflection_property = $reflectionDb->getProperty('dbh');
        $reflection_property->setAccessible(true);

        $reflection_property->setValue($this->db, $stubPDO);


        $this->expectException(DbException::class);
        $result = $this->db->lastInsertId();    
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

    public function testExceptionExecute()
    {
        $this->expectException("Error");
        $count = $this->db->execute('DELETE FROM users WHERE name = :name', [':name' => 'Name1']);       
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

    public function testExceptionFindRows()
    {
        $this->expectException("Error");
        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
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

    public function testExceptionFindRow()
    {
        $this->expectException("Error");
        $result = $this->db->findRow('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
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

    public function testExceptionFindFirstColumn()
    {
        $this->expectException("Error");
        $result = $this->db->findFirstColumn('SELECT * FROM users WHERE name = :name', 
            [':name' => 'Name1']
        );
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

    public function testExceptionFindOne()
    {
        $this->expectException("Error");
        $result = $this->db->findOne('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
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

    public function testErrorInsert()
    {
        $this->makeTestTable($this->db->getConnection());
        $this->expectException("Error");
        $res = $this->db->insert('fix-table', ['name' => 'New Name']);
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

    public function testUpdateWhereIsString()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->update('users', ['name' => 'New Name'], '`id` = 1');
        $this->assertEquals($res, 1);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'UPDATE `users` SET `name` = ? WHERE `id` = 1');
        $this->assertEquals($profile['params'], [
            'params' => [
                'name' => 'New Name'
            ],
            'where' => '`id` = 1'
        ]);

        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'New Name']);
        $this->assertEquals($result, [['id' => 1,'name' => 'New Name']]);
    }

    public function testUpdateCondIsNull()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->update('users', ['name' => 'New Name'], ['name' => null]);
        $this->assertEquals($res, 0);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'UPDATE `users` SET `name` = ? WHERE `name` IS NULL');
        $this->assertEquals($profile['params'], [
            'params' => [
                'name' => 'New Name'
            ],
            'where' => [
                'name' => null
            ]
        ]);

        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'New Name']);
        $this->assertEquals($result, []);
    }

    public function testErrorUpdate()
    {
        $this->makeTestTable($this->db->getConnection());
        $this->expectException("Error");
        $res = $this->db->update('fix-table', ['name' => 'New Name'], ['id' => 1]);
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

    public function testDeleteWhereIsString()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->delete('users', 'name = "Name1"');
        $this->assertEquals($res, 1);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'DELETE FROM `users` WHERE name = "Name1"');
        $this->assertEquals($profile['params'], ['where' => 'name = "Name1"']);
        $result = $this->db->findRows('SELECT * FROM users WHERE name = :name', [':name' => 'Name1']);
        $this->assertEquals($result, []);
    }

    public function testDeleteCondIsNull()
    {
        $this->makeTestTable($this->db->getConnection());
        $res = $this->db->delete('users', ['name'=>null]);
        $this->assertEquals($res, 0);

        $profiler = $this->db->getProfiler();
        $profile = $profiler->getProfile(1);
        $this->assertEquals($profile['sql'], 'DELETE FROM `users` WHERE `name` IS NULL');
        $this->assertEquals($profile['params'], ['where' => ['name' => null]]);
        $result = $this->db->findRows('SELECT * FROM users WHERE name IS NULL', []);
        $this->assertEquals($result, []);
    }

    public function testExceptionDelete()
    {
        $this->makeTestTable($this->db->getConnection());
        $this->expectException("Error");
        $res = $this->db->delete('fix-table', ['name' => 'Name1']);
    }
}
