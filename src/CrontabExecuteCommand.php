<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Command\Command;
use Hyperf\Crontab\Mutex\TaskMutex;
use Hyperf\Crontab\Strategy\Executor;
use Override;
use Symfony\Component\Console\Input\InputArgument;
use Verdient\Hyperf3\Di\Container;

/**
 * 执行定时任务
 *
 * @author Verdient。
 */
class CrontabExecuteCommand extends Command
{
    use ParseCrontabs;

    /**
     * 构造函数
     *
     * @author Verdient。
     */
    public function __construct()
    {
        parent::__construct('crontab:execute');
        $this->setDescription('执行定时任务');
    }

    /**
     * 处理函数
     *
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

        $this->info('执行定时任务: ' . implode(' ', array_unique([$crontab->getName(), $crontab->getMemo()])));

        pcntl_signal(SIGINT, function () use ($crontab) {
            $taskMutex = Container::get(TaskMutex::class);
            $taskMutex->remove($crontab);
            pcntl_signal(SIGINT, SIG_DFL);
            posix_kill(posix_getpid(), SIGINT);
        });

        $executor = Container::get(Executor::class);

        $crontab->setOptions($crontab->getOptions() + ['IS_MANUAL_EXECUTE' => true]);

        $executor->execute($crontab);
    }

    /**
     * @author Verdient。
     */
    #[Override]
    protected function getArguments()
    {
        return [
            ['name', InputArgument::OPTIONAL, '任务名称']
        ];
    }
}
