<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Command\Command;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Crontab\Event\CrontabDispatcherStarted;
use Hyperf\Crontab\Strategy\Executor;
use Symfony\Component\Console\Input\InputArgument;

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

        $crontab->setSingleton(false);
        $crontab->setOnOneServer(false);
        $crontab->setEnable(true);

        $executor = $this->container->get(Executor::class);

        $this->eventDispatcher?->dispatch(new CrontabDispatcherStarted());

        $this->info('执行定时任务: ' . implode(' ', array_unique([$crontab->getName(), $crontab->getMemo()])));

        $executor->execute($crontab);

        $crontab->wait();
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
