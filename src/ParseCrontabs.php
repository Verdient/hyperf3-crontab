<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Crontab\Annotation\Crontab as AnnotationCrontab;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Schedule;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use ReflectionException;

/**
 * 解析定时任务
 * @author Verdient。
 */
trait ParseCrontabs
{
    /**
     * 解析定时任务
     * @return Crontab[]
     * @author Verdient。
     */
    protected function parseCrontabs(): array
    {
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $configCrontabs = $config->get('crontab.crontab', []);
        $annotationCrontabs = AnnotationCollector::getClassesByAnnotation(AnnotationCrontab::class);
        $methodCrontabs = $this->getCrontabsFromMethod();

        Schedule::load();
        $pendingCrontabs = Schedule::getCrontabs();

        $crontabs = [];

        foreach (array_merge($configCrontabs, $annotationCrontabs, $methodCrontabs, $pendingCrontabs) as $crontab) {
            if ($crontab instanceof AnnotationCrontab) {
                $crontab = $this->buildCrontabByAnnotation($crontab);
            }
            if ($crontab instanceof Crontab) {
                $crontabs[$crontab->getName()] = $crontab;
            }
        }

        ksort($crontabs);

        return array_values($crontabs);
    }

    /**
     * 获取定义在方法上的定时任务
     * @return AnnotationCrontab[]
     * @author Verdient。
     */
    protected function getCrontabsFromMethod(): array
    {
        $result = AnnotationCollector::getMethodsByAnnotation(AnnotationCrontab::class);
        $crontabs = [];
        foreach ($result as $item) {
            $crontabs[] = $item['annotation'];
        }
        return $crontabs;
    }

    /**
     * 通过注解构造定时任务
     * @param AnnotationCrontab $annotation 注解
     * @return Crontab
     * @author Verdient。
     */
    protected function buildCrontabByAnnotation(AnnotationCrontab $annotation): Crontab
    {
        $crontab = new Crontab();
        isset($annotation->name) && $crontab->setName($annotation->name);
        isset($annotation->type) && $crontab->setType($annotation->type);
        isset($annotation->rule) && $crontab->setRule($annotation->rule);
        isset($annotation->singleton) && $crontab->setSingleton($annotation->singleton);
        isset($annotation->mutexPool) && $crontab->setMutexPool($annotation->mutexPool);
        isset($annotation->mutexExpires) && $crontab->setMutexExpires($annotation->mutexExpires);
        isset($annotation->onOneServer) && $crontab->setOnOneServer($annotation->onOneServer);
        isset($annotation->callback) && $crontab->setCallback($annotation->callback);
        isset($annotation->memo) && $crontab->setMemo($annotation->memo);
        isset($annotation->enable) && $crontab->setEnable($this->resolveCrontabEnableMethod($annotation->enable));
        isset($annotation->timezone) && $crontab->setTimezone($annotation->timezone);
        isset($annotation->environments) && $crontab->setEnvironments($annotation->environments);
        isset($annotation->options) && $crontab->setOptions($annotation->options);

        return $crontab;
    }

    /**
     * 判断定时任务是否启用
     * @param array|bool $enable 是否启用
     * @return bool
     * @author Verdient。
     */
    protected function resolveCrontabEnableMethod(array|bool $enable): bool
    {
        if (is_bool($enable)) {
            return $enable;
        }

        $className = reset($enable);
        $method = end($enable);

        try {
            $reflectionClass = ReflectionManager::reflectClass($className);
            $reflectionMethod = $reflectionClass->getMethod($method);

            if ($reflectionMethod->isPublic()) {
                if ($reflectionMethod->isStatic()) {
                    return $className::$method();
                }


                if ($this->container->has($className)) {
                    return $this->container->get($className)->{$method}();
                }
            }
        } catch (ReflectionException) {
        }

        return false;
    }
}
