<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Context\ApplicationContext;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Verdient\Hyperf3\Exception\ExceptionOccurredEvent;

/**
 * 定时任务任务执行失败监听器
 * @author Verdient。
 */
class FailToExecuteCrontabListener implements ListenerInterface
{
    /**
     * @inheritdoc
     * @author Verdient。
     */
    public function listen(): array
    {
        return [
            FailToExecute::class,
        ];
    }

    /**
     * @inheritdoc
     * @param FailToExecute $event
     * @author Verdient。
     */
    public function process(object $event): void
    {
        if (ApplicationContext::hasContainer()) {
            /** @var EventDispatcherInterface */
            if ($eventDispatcher = ApplicationContext::getContainer()
                ->get(EventDispatcherInterface::class)
            ) {
                $eventDispatcher->dispatch(new ExceptionOccurredEvent($event->throwable));
            }
        }
    }
}
