<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Carbon\Carbon;
use Closure;
use Hyperf\Contract\ApplicationInterface;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Event\AfterExecute;
use Hyperf\Crontab\Event\BeforeExecute;
use Hyperf\Crontab\Event\FailToExecute;
use Hyperf\Crontab\Strategy\Executor as StrategyExecutor;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use InvalidArgumentException;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Throwable;
use Verdient\Hyperf3\Di\Container;
use Verdient\Hyperf3\Logger\Logger;

use function Hyperf\Support\make;

/**
 * 执行监听器
 *
 * @author Verdient。
 */
class Executor extends StrategyExecutor
{
    /**
     * @author Verdient。
     */
    #[Override]
    public function execute(Crontab $crontab)
    {
        try {
            $options = $crontab->getOptions();
            if (isset($options['IS_MANUAL_EXECUTE'])) {
                $this->toRunnable($crontab)(false);
            } else {
                $diff = Carbon::now()->diffInRealSeconds($crontab->getExecuteTime(), false);
                $this->timer->after(max($diff, 0), $this->toRunnable($crontab));
            }
        } catch (Throwable $exception) {
            $crontab->close();
            throw $exception;
        }
    }

    /**
     * 转换为可执行的闭包
     *
     * @param Crontab $crontab 定时任务
     *
     * @author Verdient。
     */
    protected function toRunnable(Crontab $crontab): Closure
    {
        return function ($isClosing) use ($crontab) {
            if ($isClosing) {
                $crontab->close();
                return;
            }
            try {
                $this->decorateRunnable($crontab, $this->toExecutor($crontab))();
            } finally {
                $crontab->complete();
            }
        };
    }

    /**
     * 转换为执行器
     *
     * @param Crontab $crontab 定时任务
     *
     * @author Verdient。
     */
    protected function toExecutor(Crontab $crontab): callable
    {
        return function () use ($crontab) {
            $closure = $this->toClosure($crontab, $crontabLogger);
            $startAt = microtime(true);
            try {
                $this->dispatcher?->dispatch(new BeforeExecute($crontab));
                if (!$closure) {
                    throw new InvalidArgumentException('The crontab task is invalid.');
                }
                $startAt = microtime(true);
                $closure();
                $this->logExecutionResult($crontab, true, microtime(true) - $startAt, null, $crontabLogger);
                $this->dispatcher?->dispatch(new AfterExecute($crontab));
            } catch (Throwable $throwable) {
                $this->logExecutionResult($crontab, false, microtime(true) - $startAt, $throwable, $crontabLogger);
                $this->dispatcher?->dispatch(new FailToExecute($crontab, $throwable));
            }
        };
    }

    /**
     * 将定时任务转换为可执行的回调
     *
     * @param Crontab $crontab 定时任务
     * @param ?LoggerInterface $logger 记录器
     *
     * @author Verdient。
     */
    protected function toClosure(Crontab $crontab, ?LoggerInterface &$logger = null): ?Closure
    {
        switch ($crontab->getType()) {
            case 'closure':
                return $crontab->getCallback();
            case 'callback':
                [$class, $method] = $crontab->getCallback();
                $parameters = $crontab->getCallback()[2] ?? null;
                if ($class && $method && class_exists($class) && method_exists($class, $method)) {
                    $instance = make($class);
                    if (method_exists($instance, 'logger')) {
                        $logger = new Logger($instance->logger(), $this->isManualExecute($crontab) ? '[Manual]' : '[Dispatcher]');
                    }
                    return function () use ($instance, $method, $parameters) {
                        if ($parameters && is_array($parameters)) {
                            $instance->{$method}(...$parameters);
                        } else {
                            $instance->{$method}();
                        }
                    };
                }
                return null;
            case 'command':
                $input = make(ArrayInput::class, [$crontab->getCallback()]);
                $output = make(NullOutput::class);
                /** @var Application */
                $application = $this->container->get(ApplicationInterface::class);
                $application->setAutoExit(false);
                $application->setCatchExceptions(false);
                return function () use ($application, $input, $output) {
                    if ($application->run($input, $output) !== 0) {
                        throw new RuntimeException('Crontab task failed to execute.');
                    }
                };
            case 'eval':
                return fn() => eval($crontab->getCallback());
            default:
                throw new InvalidArgumentException(sprintf('Crontab task type [%s] is invalid.', $crontab->getType()));
        }
    }

    /**
     * 记录执行结果
     *
     * @param Crontab $crontab 定时任务
     * @param bool $result 执行结果
     * @param float $timeCost 执行耗时
     * @param ?Throwable $throwable 异常
     * @param ?LoggerInterface $logger 日志组件
     *
     * @author Verdient。
     */
    protected function logExecutionResult(Crontab $crontab, bool $result, float $timeCost, ?Throwable $throwable, ?LoggerInterface $logger): void
    {
        if ($executorLogger = $this->newLogger($crontab)) {
            if ($result) {
                $executorLogger?->info(sprintf('任务 %s 执行成功，耗时 %.4f 秒。', $crontab->getName(), $timeCost));
            } else {
                $executorLogger?->error(sprintf('任务 %s 执行失败，耗时 %.4f 秒。', $crontab->getName(), $timeCost));
            }
        }

        if ($logger) {
            if ($result) {
                $logger->info(sprintf('任务执行成功，耗时 %.4f 秒。', $timeCost));
                return;
            }

            $formatter = Container::getOrNull(FormatterInterface::class);
            $logger->error($formatter ? $formatter->format($throwable) : $throwable);

            $logger->error(sprintf('任务执行失败，耗时 %.4f 秒。', $timeCost));
        }
    }

    /**
     * @author Verdient。
     */
    #[Override]
    protected function logResult(Crontab $crontab, bool $isSuccess, ?Throwable $throwable = null) {}

    /**
     * 创建记录器
     *
     * @param Crontab $crontab 定时任务
     *
     * @author Verdient。
     */
    protected function newLogger(Crontab $crontab): ?Logger
    {
        if ($this->logger === null) {
            return null;
        }

        return new Logger($this->logger, $this->isManualExecute($crontab) ? '[Manual]' : '[Dispatcher]');
    }

    /**
     * 是否手动执行
     *
     * @param Crontab $crontab 定时任务
     *
     * @author Verdient。
     */
    protected function isManualExecute(Crontab $crontab): bool
    {
        $options = $crontab->getOptions();
        return isset($options['IS_MANUAL_EXECUTE']) && $options['IS_MANUAL_EXECUTE'] === true;
    }
}
