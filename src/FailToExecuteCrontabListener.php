<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Event\Contract\ListenerInterface;
use Override;
use Verdient\Hyperf3\Di\Container;
use Verdient\Hyperf3\Event\Event;
use Verdient\Hyperf3\Exception\ExceptionOccurredEvent;

/**
 * 定时任务任务执行失败监听器
 *
 * @author Verdient。
 */
class FailToExecuteCrontabListener implements ListenerInterface
{
    /**
     * @author Verdient。
     */
    #[Override]
    public function listen(): array
    {
        return [
            FailToExecute::class,
        ];
    }

    /**
     * @param FailToExecute $event
     *
     * @author Verdient。
     */
    #[Override]
    public function process(object $event): void
    {
        $options = $event->crontab->getOptions();

        if (isset($options['IS_MANUAL_EXECUTE'])) {
            Container::getOrNull(StdoutLoggerInterface::class)?->error($event->throwable);
        }

        Event::dispatch(new ExceptionOccurredEvent($event->throwable));
    }
}
