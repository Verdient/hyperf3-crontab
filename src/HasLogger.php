<?php

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\Logger;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Stringable\Str;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Psr\Log\LoggerInterface;

use function Hyperf\Config\config;

/**
 * 包含记录器
 * @author Verdient。
 */
trait HasLogger
{
    /**
     * 记录器
     * @author Verdient。
     */
    protected ?LoggerInterface $logger = null;

    /**
     * 日志存储路径
     * @return string
     * @author Verdient。
     */
    protected function logDir(): string
    {
        $dirs = [
            'crontab',
            ...explode('\\', Utils::simplifyName(static::class))
        ];

        $dirs = array_map(function ($value) {
            return Str::kebab($value);
        }, $dirs);

        return implode(DIRECTORY_SEPARATOR, [
            constant('BASE_PATH'),
            'runtime',
            'logs',
            ...$dirs
        ]);
    }

    /**
     * 日志文件名称
     * @return string
     * @author Verdient。
     */
    protected function logFilename()
    {
        return 'log.log';
    }

    /**
     * 获取日志组件
     * @return LoggerInterface
     * @author Verdient。
     */
    public function logger(): LoggerInterface
    {
        if (!$this->logger) {
            if (ApplicationContext::hasContainer()) {
                $group = static::class;
                $loggerConfig = config('logger');
                if (isset($loggerConfig[$group])) {
                    /** @var LoggerFactory|null */
                    $loggerFactory = ApplicationContext::getContainer()->get(LoggerFactory::class);
                    if ($loggerFactory) {
                        $this->logger = $loggerFactory->get(static::class, $group);
                    }
                }
            }
            if (!$this->logger) {
                $this->logger = (new Logger(static::class, [
                    (new RotatingFileHandler(
                        $this->logDir() . DIRECTORY_SEPARATOR . $this->logFilename(),
                        0,
                        config('app_env') === 'dev' ? Level::Debug : Level::Info
                    ))->setFormatter(new LineFormatter("[%datetime%] %level_name% %message%\n", 'Y-m-d H:i:s', true))
                ]));
            }
        }
        return $this->logger;
    }

    /**
     * 设置记录器
     * @param LoggerInterface 记录器
     * @return static
     * @author Verdient。
     */
    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }
}
