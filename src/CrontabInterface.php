<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Verdient\Logger\LoggableInterface;

/**
 * 定时任务接口
 *
 * @author Verdient。
 */
interface CrontabInterface extends LoggableInterface
{
    /**
     * 处理定时任务
     *
     * @author Verdient。
     */
    public function handle(): void;
}
