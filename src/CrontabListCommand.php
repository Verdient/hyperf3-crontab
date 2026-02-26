<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Command\Command;
use Hyperf\Contract\ContainerInterface;
use phpDocumentor\Reflection\DocBlockFactory;
use Verdient\cli\Console;

/**
 * 定时任务列表
 *
 * @author Verdient。
 */
class CrontabListCommand extends Command
{
    use ParseCrontabs;

    /**
     * 注释解析器
     *
     * @author Verdient。
     */
    protected ?DocBlockFactory $docBlockFactory = null;

    /**
     * 构造函数
     *
     * @author Verdient。
     */
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('crontab:list');
        $this->setDescription('展示定时任务列表');
    }

    /**
     * 处理函数
     *
     * @author Verdient。
     */
    public function handle()
    {
        $crontabs = $this->parseCrontabs();

        if (empty($crontabs)) {
            return $this->error('定时任务为空');
        }

        /** @var EnablerManager */
        $enablerManager = $this->container->get(EnablerManager::class);

        $data = [];

        foreach ($crontabs as $crontab) {
            $description = '';
            $name = str_replace('\\', '.', $crontab->getName());
            if ($crontab->getName() !== $crontab->getMemo()) {
                $description = $crontab->getMemo();
            }
            $data[] = [
                $name,
                $description,
                $crontab->getRule(),
                $crontab->getType(),
                $crontab->isEnable() ? '是' : '否',
                $enablerManager->getEnablerName($crontab->getName())
            ];
        }

        Console::table($data, ['名称', '描述', '规则', '类型', '启用', '开关名称（环境变量）']);
    }
}
