<?php

declare(strict_types=1);

namespace Imi\Nacos\Test;

use function Imi\env;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Yurun\Nacos\Client;
use Yurun\Nacos\ClientConfig;
use Yurun\Util\HttpRequest;

abstract class BaseTest extends TestCase
{
    protected static Process $process;

    protected static string $httpHost = '';

    protected static function __startServer(): void
    {
        throw new \RuntimeException('You must implement the __startServer() method');
    }

    /**
     * {@inheritDoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$httpHost = env('HTTP_SERVER_HOST', 'http://127.0.0.1:8080/');
        static::__startServer();
        $httpRequest = new HttpRequest();
        for ($i = 0; $i < 20; ++$i)
        {
            sleep(1);
            if ('imi' === $httpRequest->timeout(3000)->get(self::$httpHost)->body())
            {
                sleep(3); // 等待心跳

                return;
            }
        }
        throw new \RuntimeException('Server started failed');
    }

    /**
     * {@inheritDoc}
     */
    public static function tearDownAfterClass(): void
    {
        if (isset(self::$process))
        {
            self::$process->stop(10, \SIGTERM);
        }
    }

    public function testSetAndGet(): void
    {
        $httpRequest = new HttpRequest();
        $value = ['value' => uniqid('', true)];
        $response = $httpRequest->post(self::$httpHost . '/set', [
            'name'  => 'imi-nacos-key1',
            'group' => 'imi',
            'value' => json_encode($value),
            'type'  => 'json',
        ]);

        $cacheFileName = \dirname(__DIR__) . '/example/.runtime/config-cache/imi/imi-nacos-key1';
        for ($i = 0; $i < 15; ++$i)
        {
            sleep(1);
            if (is_file($cacheFileName))
            {
                unlink($cacheFileName);
            }
            $response = $httpRequest->get(self::$httpHost . '/get');
            if ([
                'config' => $value,
            ] === $response->json(true))
            {
                $this->assertTrue(true);

                return;
            }
        }

        $this->assertTrue(false);
    }

    public function testNacosServiceRegistry(): void
    {
        $config = new ClientConfig([
            // 注册中心客户端连接配置，每个驱动不同
            'host'                => env('IMI_NACOS_HOST', '127.0.0.1'), // 主机名
            'port'                => env('IMI_NACOS_PORT', 8848), // 端口号
            'prefix'              => env('IMI_NACOS_PREFIX', '/'), // 前缀
            'username'            => env('IMI_NACOS_USERNAME', 'nacos'), // 用户名
            'password'            => env('IMI_NACOS_PASSWORD', 'nacos'), // 密码
            'timeout'             => 60000, // 网络请求超时时间，单位：毫秒
            'ssl'                 => false, // 是否使用 ssl(https) 请求
            'authorizationBearer' => false, // 是否使用请求头 Authorization: Bearer {accessToken} 方式传递 Token，旧版本 Nacos 需要设为 true
        ]);
        $client = new Client($config);
        $response = $client->instance->list('main_test', 'DEFAULT_GROUP', '', '', true);
        $this->assertCount(1, $response->getHosts());

        $response1 = $client->instance->list('main', 'DEFAULT_GROUP', '', '', true);
        $response2 = $client->instance->list('http', 'DEFAULT_GROUP', '', '', true);
        $this->assertTrue(1 === \count($response1->getHosts()) || 1 === \count($response2->getHosts()), sprintf('$response1=%d, $response2=%d', \count($response1->getHosts()), \count($response2->getHosts())));
    }
}
