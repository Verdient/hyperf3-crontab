<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Verdient\Hyperf3\Logger\HasLogger;

/**
 * 抽象任务
 *
 * @author Verdient。
 */
abstract class AbstractCrontab implements CrontabInterface
{
    use HasLogger;

    /**
     * 创建默认的记录器的组名集合
     *
     * @return array<int|string,string>
     * @author Verdient。
     */
    protected function groupsForCreateDefaultLogger(): array
    {
        return [static::class => CrontabInterface::class];
    }
}
