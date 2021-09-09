<?php
declare(strict_types=1);

namespace Kgbph\RedisCluster\Test\TestCase\Cache\Engine;

use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use Kgbph\RedisCluster\Cache\Engine\RedisClusterEngine;

/**
 * RedisClusterEngineTest class
 */
class RedisClusterEngineTest extends TestCase
{
    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->skipIf(
            !class_exists('RedisCluster'),
            'Redis extension is not installed or configured properly.'
        );

        for ($i = 0; $i < 6; $i++) {
            $node = (string)$i;
            $socket = fsockopen('redis-node-' . $node, 6379, $errno, $errstr, 1);
            $this->skipIf(!$socket, 'Redis node ' . $node . ' is not running.');
            fclose($socket);
        }

        Cache::enable();
        $this->configCache();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        Cache::drop('redis');
        Cache::drop('redis_groups');
        Cache::drop('redis_helper');
    }

    /**
     * Helper method for testing.
     *
     * @param array $config
     * @return void
     */
    protected function configCache($config = [])
    {
        $defaults = [
            'className' => 'Kgbph/RedisCluster.RedisCluster',
            'nodes' => $this->redisClusterNodes(),
        ];

        Cache::drop('redis');
        Cache::setConfig('redis', array_merge($defaults, $config));
    }

    /**
     * Redis cluster nodes
     *
     * @return string[]
     */
    protected function redisClusterNodes(): array
    {
        return [
            'redis-node-0:6379',
            'redis-node-1:6379',
            'redis-node-2:6379',
            'redis-node-3:6379',
            'redis-node-4:6379',
            'redis-node-5:6379',
        ];
    }

    /**
     * testConfig method
     *
     * @return void
     */
    public function testConfig()
    {
        $config = Cache::engine('redis')->getConfig();
        $expecting = [
            'duration' => 3600,
            'failover' => null,
            'groups' => [],
            'host' => null,
            'name' => null,
            'nodes' => $this->redisClusterNodes(),
            'password' => null,
            'persistent' => true,
            'prefix' => 'cake_',
            'read_timeout' => 0,
            'timeout' => 0,
        ];
        $this->assertEquals($expecting, $config);
    }

    /**
     * testConnect method
     *
     * @return void
     */
    public function testConnect()
    {
        $RedisCluster = new RedisClusterEngine();
        $this->assertTrue($RedisCluster->init(Cache::engine('redis')->getConfig()));
    }

    /**
     * test write numbers method
     *
     * @return void
     */
    public function testWriteNumbers()
    {
        $result = Cache::write('test-counter', 1, 'redis');
        $this->assertSame(1, Cache::read('test-counter', 'redis'));

        $result = Cache::write('test-counter', 0, 'redis');
        $this->assertSame(0, Cache::read('test-counter', 'redis'));

        $result = Cache::write('test-counter', -1, 'redis');
        $this->assertSame(-1, Cache::read('test-counter', 'redis'));
    }

    /**
     * testReadAndWriteCache method
     *
     * @return void
     */
    public function testReadAndWriteCache()
    {
        $this->configCache(['duration' => 1]);

        $result = Cache::read('test', 'redis');
        $expecting = '';
        $this->assertEquals($expecting, $result);

        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('test', $data, 'redis');
        $this->assertTrue($result);

        $result = Cache::read('test', 'redis');
        $expecting = $data;
        $this->assertEquals($expecting, $result);

        $data = [1, 2, 3];
        $this->assertTrue(Cache::write('array_data', $data, 'redis'));
        $this->assertEquals($data, Cache::read('array_data', 'redis'));

        Cache::delete('test', 'redis');
    }

    /**
     * testExpiry method
     *
     * @return void
     */
    public function testExpiry()
    {
        $this->configCache(['duration' => 1]);

        $result = Cache::read('test', 'redis');
        $this->assertFalse($result);

        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('other_test', $data, 'redis');
        $this->assertTrue($result);

        sleep(2);
        $result = Cache::read('other_test', 'redis');
        $this->assertFalse($result);

        $this->configCache(['duration' => '+1 second']);

        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('other_test', $data, 'redis');
        $this->assertTrue($result);

        sleep(2);
        $result = Cache::read('other_test', 'redis');
        $this->assertFalse($result);

        sleep(2);

        $result = Cache::read('other_test', 'redis');
        $this->assertFalse($result);

        $this->configCache(['duration' => '+29 days']);
        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('long_expiry_test', $data, 'redis');
        $this->assertTrue($result);

        sleep(2);
        $result = Cache::read('long_expiry_test', 'redis');
        $expecting = $data;
        $this->assertEquals($expecting, $result);
    }

    /**
     * testDeleteCache method
     *
     * @return void
     */
    public function testDeleteCache()
    {
        $data = 'this is a test of the emergency broadcasting system';
        $result = Cache::write('delete_test', $data, 'redis');
        $this->assertTrue($result);

        $result = Cache::delete('delete_test', 'redis');
        $this->assertTrue($result);
    }

    /**
     * testDecrement method
     *
     * @return void
     */
    public function testDecrement()
    {
        Cache::delete('test_decrement', 'redis');
        $result = Cache::write('test_decrement', 5, 'redis');
        $this->assertTrue($result);

        $result = Cache::decrement('test_decrement', 1, 'redis');
        $this->assertEquals(4, $result);

        $result = Cache::read('test_decrement', 'redis');
        $this->assertEquals(4, $result);

        $result = Cache::decrement('test_decrement', 2, 'redis');
        $this->assertEquals(2, $result);

        $result = Cache::read('test_decrement', 'redis');
        $this->assertEquals(2, $result);
    }

    /**
     * testIncrement method
     *
     * @return void
     */
    public function testIncrement()
    {
        Cache::delete('test_increment', 'redis');
        $result = Cache::increment('test_increment', 1, 'redis');
        $this->assertEquals(1, $result);

        $result = Cache::read('test_increment', 'redis');
        $this->assertEquals(1, $result);

        $result = Cache::increment('test_increment', 2, 'redis');
        $this->assertEquals(3, $result);

        $result = Cache::read('test_increment', 'redis');
        $this->assertEquals(3, $result);
    }

    /**
     * Test that increment() and decrement() can live forever.
     *
     * @return void
     */
    public function testIncrementDecrementForvever()
    {
        $this->configCache(['duration' => 0]);
        Cache::delete('test_increment', 'redis');
        Cache::delete('test_decrement', 'redis');

        $result = Cache::increment('test_increment', 1, 'redis');
        $this->assertEquals(1, $result);

        $result = Cache::decrement('test_decrement', 1, 'redis');
        $this->assertEquals(-1, $result);

        $this->assertEquals(1, Cache::read('test_increment', 'redis'));
        $this->assertEquals(-1, Cache::read('test_decrement', 'redis'));
    }

    /**
     * Test that increment and decrement set ttls.
     *
     * @return void
     */
    public function testIncrementDecrementExpiring()
    {
        $this->configCache(['duration' => 1]);
        Cache::delete('test_increment', 'redis');
        Cache::delete('test_decrement', 'redis');

        $this->assertSame(1, Cache::increment('test_increment', 1, 'redis'));
        $this->assertSame(-1, Cache::decrement('test_decrement', 1, 'redis'));

        sleep(2);

        $this->assertFalse(Cache::read('test_increment', 'redis'));
        $this->assertFalse(Cache::read('test_decrement', 'redis'));
    }

    /**
     * test clearing redis.
     *
     * @return void
     */
    public function testClear()
    {
        Cache::setConfig('redis2', [
            'className' => 'Kgbph/RedisCluster.RedisCluster',
            'duration' => 3600,
            'nodes' => $this->redisClusterNodes(),
            'prefix' => 'cake2_',
        ]);

        Cache::write('some_value', 'cache1', 'redis');
        $result = Cache::clear(true, 'redis');
        $this->assertTrue($result);
        $this->assertEquals('cache1', Cache::read('some_value', 'redis'));

        Cache::write('some_value', 'cache2', 'redis2');
        $result = Cache::clear(false, 'redis');
        $this->assertTrue($result);
        $this->assertFalse(Cache::read('some_value', 'redis'));
        $this->assertEquals('cache2', Cache::read('some_value', 'redis2'));

        Cache::clear(false, 'redis2');
    }

    /**
     * test that a 0 duration can successfully write.
     *
     * @return void
     */
    public function testZeroDuration()
    {
        $this->configCache(['duration' => 0]);
        $result = Cache::write('test_key', 'written!', 'redis');

        $this->assertTrue($result);
        $result = Cache::read('test_key', 'redis');
        $this->assertEquals('written!', $result);
    }

    /**
     * Tests that configuring groups for stored keys return the correct values when read/written
     * Shows that altering the group value is equivalent to deleting all keys under the same
     * group
     *
     * @return void
     */
    public function testGroupReadWrite()
    {
        Cache::setConfig('redis_groups', [
            'className' => 'Kgbph/RedisCluster.RedisCluster',
            'groups' => ['group_a', 'group_b'],
            'nodes' => $this->redisClusterNodes(),
            'prefix' => 'test_',
        ]);
        Cache::setConfig('redis_helper', [
            'className' => 'Kgbph/RedisCluster.RedisCluster',
            'nodes' => $this->redisClusterNodes(),
            'prefix' => 'test_',
        ]);
        $this->assertTrue(Cache::write('test_groups', 'value', 'redis_groups'));
        $this->assertEquals('value', Cache::read('test_groups', 'redis_groups'));

        Cache::increment('group_a', 1, 'redis_helper');
        $this->assertFalse(Cache::read('test_groups', 'redis_groups'));
        $this->assertTrue(Cache::write('test_groups', 'value2', 'redis_groups'));
        $this->assertEquals('value2', Cache::read('test_groups', 'redis_groups'));

        Cache::increment('group_b', 1, 'redis_helper');
        $this->assertFalse(Cache::read('test_groups', 'redis_groups'));
        $this->assertTrue(Cache::write('test_groups', 'value3', 'redis_groups'));
        $this->assertEquals('value3', Cache::read('test_groups', 'redis_groups'));
    }

    /**
     * Tests that deleting from a groups-enabled config is possible
     *
     * @return void
     */
    public function testGroupDelete()
    {
        Cache::setConfig('redis_groups', [
            'className' => 'Kgbph/RedisCluster.RedisCluster',
            'groups' => ['group_a', 'group_b'],
            'nodes' => $this->redisClusterNodes(),
        ]);
        $this->assertTrue(Cache::write('test_groups', 'value', 'redis_groups'));
        $this->assertEquals('value', Cache::read('test_groups', 'redis_groups'));
        $this->assertTrue(Cache::delete('test_groups', 'redis_groups'));

        $this->assertFalse(Cache::read('test_groups', 'redis_groups'));
    }

    /**
     * Test clearing a cache group
     *
     * @return void
     */
    public function testGroupClear()
    {
        Cache::setConfig('redis_groups', [
            'className' => 'Kgbph/RedisCluster.RedisCluster',
            'groups' => ['group_a', 'group_b'],
            'nodes' => $this->redisClusterNodes(),
        ]);

        $this->assertTrue(Cache::write('test_groups', 'value', 'redis_groups'));
        $this->assertTrue(Cache::clearGroup('group_a', 'redis_groups'));
        $this->assertFalse(Cache::read('test_groups', 'redis_groups'));

        $this->assertTrue(Cache::write('test_groups', 'value2', 'redis_groups'));
        $this->assertTrue(Cache::clearGroup('group_b', 'redis_groups'));
        $this->assertFalse(Cache::read('test_groups', 'redis_groups'));
    }

    /**
     * Test add
     *
     * @return void
     */
    public function testAdd()
    {
        Cache::delete('test_add_key', 'redis');

        $result = Cache::add('test_add_key', 'test data', 'redis');
        $this->assertTrue($result);

        $expected = 'test data';
        $result = Cache::read('test_add_key', 'redis');
        $this->assertEquals($expected, $result);

        $result = Cache::add('test_add_key', 'test data 2', 'redis');
        $this->assertFalse($result);
    }
}
