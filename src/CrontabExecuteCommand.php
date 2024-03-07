<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Command\Command;
use Hyperf\Contract\ApplicationInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Crontab\Exception\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\NullOutput;

use function Hyperf\Support\make;

/**
 * 执行定时任务
 * @author Verdient。
 */
class CrontabExecuteCommand extends Command
{
    use ParseCrontabs;

    /**
     * @inheritdoc
     * @author Verdient。
     */
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('crontab:execute');
        $this->setDescription('执行定时任务');
    }

    /**
     * @inheritdoc
     * @author Verdient。
     */
    public function handle()
    {
        $crontabs = [];

        foreach ($this->parseCrontabs() as $crontab) {
            $key = str_replace('\\', '.', $crontab->getName());
            $crontabs[$key] = $crontab;
        }

        if (empty($crontabs)) {
            return $this->error('没有可用的定时任务');
        }

        $name = $this->input->getArgument('name');
        if (empty($name)) {
            $choices = [];
            $maxLength = 0;
            foreach ($crontabs as $key => $crontab) {
                $length = strlen($key);
                if ($length > $maxLength) {
                    $maxLength = $length;
                }
            }
            $map = [];
            foreach ($crontabs as $key => $crontab) {
                if ($crontab->getName() === $crontab->getMemo()) {
                    $description = $key;
                } else {
                    $description = $key . '  ' . str_repeat(' ', $maxLength - strlen((string) $key)) . $crontab->getMemo();
                }
                $choices[] = $description;
                $map[$description] = $key;
            }
            $choice = $this->choice('请选择要执行的定时任务', $choices);

            $name = $map[$choice];
        } else {
            if (!isset($crontabs[$name])) {
                return $this->error('定时任务名称 ' . $name . ' 不匹配');
            }
        }

        $crontab = $crontabs[$name];

        switch ($crontab->getType()) {
            case 'closure':
                $runnable = $crontab->getCallback();
                break;
            case 'callback':
                [$class, $method] = $crontab->getCallback();
                $parameters = $crontab->getCallback()[2] ?? null;
                if ($class && $method && class_exists($class) && method_exists($class, $method)) {
                    $runnable = function () use ($class, $method, $parameters) {
                        $instance = make($class);
                        if ($parameters && is_array($parameters)) {
                            $instance->{$method}(...$parameters);
                        } else {
                            $instance->{$method}();
                        }
                    };
                }
                break;
            case 'command':
                $input = make(ArrayInput::class, [$crontab->getCallback()]);
                $output = make(NullOutput::class);
                /** @var \Symfony\Component\Console\Application */
                $application = $this->container->get(ApplicationInterface::class);
                $application->setAutoExit(false);
                $application->setCatchExceptions(false);
                $runnable = function () use ($application, $input, $output) {
                    if ($application->run($input, $output) !== 0) {
                        throw new RuntimeException('Crontab task failed to execute.');
                    }
                };
                break;
            case 'eval':
                $runnable = fn () => eval($crontab->getCallback());
                break;
            default:
                throw new InvalidArgumentException(sprintf('Crontab task type [%s] is invalid.', $crontab->getType()));
        }

        $this->info('执行定时任务: ' . implode(' ', array_unique([$crontab->getName(), $crontab->getMemo()])));

        call_user_func($runnable);
    }

    /**
     * @inheritdoc
     * @author Verdient。
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::OPTIONAL, '任务名称']
        ];
    }
}
