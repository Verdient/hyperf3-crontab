<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Crontab\Annotation\Crontab as AnnotationCrontab;
use Hyperf\Crontab\Listener\CrontabRegisterListener as ListenerCrontabRegisterListener;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use Hyperf\Stringable\Str;
use phpDocumentor\Reflection\DocBlockFactory;

use function Hyperf\Support\env;

/**
 * 定时任务注册监听器
 * @author Verdient。
 */
class CrontabRegisterListener extends ListenerCrontabRegisterListener
{
    /**
     * 注释解析器
     * @author Verdient。
     */
    protected ?DocBlockFactory $docBlockFactory = null;

    /**
     * @inheritdoc
     * @author Verdient。
     */
    public function process(object $event): void
    {
        /** @var EnablerManager */
        $enablerManager = $this->container->get(EnablerManager::class);
        /** @var Crontab[] */
        $classCrontabs = AnnotationCollector::getClassesByAnnotation(Crontab::class);
        foreach ($classCrontabs as $class => $crontab) {

            if (!$crontab->memo) {
                $reflectionClass = ReflectionManager::reflectClass($class);
                $summary = $this->getDocBlockParser()->create($reflectionClass->getDocComment())->getSummary();
                $crontab->memo = empty($summary) ? $crontab->name : $summary;
            }

            if ($crontab->enable === []) {
                $envName = $this->getEnvName($class);
                $enablerManager->collect($crontab->name, $envName);
                $crontab->enable = env($envName, false);
            }

            AnnotationCollector::collectClass($class, AnnotationCrontab::class, $crontab->toBase());
        }

        $methodCrontabs = AnnotationCollector::getMethodsByAnnotation(Crontab::class);
        foreach ($methodCrontabs as $methodCrontab) {
            /** @var Crontab */
            $crontab = $methodCrontab['annotation'];

            if ($crontab->enable === []) {
                $envName = $this->getEnvName($methodCrontab['class'] . '\\' . $methodCrontab['method']);
                $enablerManager->collect($crontab->name, $envName);
                $crontab->enable = env($envName, false);
            }

            AnnotationCollector::collectMethod($methodCrontab['class'], $methodCrontab['method'], AnnotationCrontab::class, $crontab->toBase());
        }

        parent::process($event);
    }

    /**
     * 获取环境变量名称
     * @param string $value 值
     * @return string
     * @author Verdient。
     */
    protected function getEnvName(string $value): string
    {
        return 'CRONTAB_' . strtoupper(implode('_', array_map(function ($part) {
            return Str::snake($part);
        }, explode('\\', $value))));
    }

    /**
     * 获取注释解析器
     * @return DocBlockFactory
     * @author Verdient。
     */
    protected function getDocBlockParser(): DocBlockFactory
    {
        if (!$this->docBlockFactory) {
            $this->docBlockFactory = DocBlockFactory::createInstance();
        }
        return $this->docBlockFactory;
    }
}
