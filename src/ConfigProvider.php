<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Verdient\Hyperf3\Crontab\CrontabRegisterListener as CrontabCrontabRegisterListener;
use Hyperf\Crontab\Listener\CrontabRegisterListener;
use Hyperf\Crontab\LoggerInterface;
use Hyperf\Logger\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                CrontabRegisterListener::class => CrontabCrontabRegisterListener::class,
                LoggerInterface::class => function () {
                    $handler = new RotatingFileHandler(constant('BASE_PATH') . '/runtime/logs/crontab/log.log', 30, Level::Info);
                    $handler->setFormatter(new LineFormatter('[%datetime%] %level_name% %message%' . PHP_EOL, 'Y-m-d H:i:s', true, true));
                    return new Logger('crontab', [$handler]);
                }
            ],
            'commands' => [
                CrontabListCommand::class,
                CrontabExecuteCommand::class
            ],
            'listeners' => [
                FailToExecuteCrontabListener::class
            ]
        ];
    }
}
