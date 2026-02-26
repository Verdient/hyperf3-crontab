<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Verdient\Hyperf3\Crontab\CrontabRegisterListener as CrontabCrontabRegisterListener;
use Hyperf\Crontab\Listener\CrontabRegisterListener;
use Hyperf\Crontab\LoggerInterface;
use Hyperf\Crontab\Process\CrontabDispatcherProcess;
use Hyperf\Crontab\Strategy\Executor as StrategyExecutor;
use Hyperf\Logger\Logger;
use Hyperf\Stringable\Str;
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
                StrategyExecutor::class => Executor::class,
                LoggerInterface::class => function () {
                    $handler = new RotatingFileHandler(constant('BASE_PATH') . '/runtime/logs/crontab/log.log', 30, Level::Info);
                    $handler->setFormatter(new LineFormatter('[%datetime%] %level_name% %message%' . PHP_EOL, 'Y-m-d H:i:s', true, true));
                    return new Logger('crontab', [$handler]);
                }
            ],
            'commands' => [
                CrontabListCommand::class,
                CrontabExecuteCommand::class,
                CrontabUnlockCommand::class
            ],
            'listeners' => [
                FailToExecuteCrontabListener::class
            ],
            'logger' => [
                CrontabInterface::class => (function (string $name) {
                    $nameParts = array_map([Str::class, 'kebab'], explode('\\', Utils::simplifyName($name)));

                    $filename = BASE_PATH . '/runtime/logs/crontab/' . implode('/', $nameParts) . '/.log';

                    return [
                        'handler' => [
                            'class' => RotatingFileHandler::class,
                            'constructor' => [
                                'filename' => $filename,
                                'filenameFormat' => '{date}'
                            ],
                        ],
                        'formatter' => [
                            'class' => LineFormatter::class,
                            'constructor' => [
                                'format' => "%datetime% [%level_name%] %message%\n",
                                'dateFormat' => 'Y-m-d H:i:s',
                                'allowInlineLineBreaks' => true,
                            ],
                        ]
                    ];
                })->bindTo(null)
            ],
            'processes' => [
                CrontabDispatcherProcess::class
            ]
        ];
    }
}
