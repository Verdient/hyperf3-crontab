<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab as AnnotationCrontab;
use Hyperf\Crontab\Crontab as CrontabCrontab;
use Hyperf\Crontab\CrontabManager;
use Hyperf\Crontab\Listener\CrontabRegisterListener as ListenerCrontabRegisterListener;
use Hyperf\Crontab\LoggerInterface;
use Hyperf\Crontab\Schedule;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use Hyperf\Stringable\Str;
use Override;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionException;

use function Hyperf\Support\env;

/**
 * 定时任务注册监听器
 *
 * @author Verdient。
 */
class CrontabRegisterListener extends ListenerCrontabRegisterListener
{
    /**
     * 注释解析器
     *
     * @author Verdient。
     */
    protected ?DocBlockFactory $docBlockFactory = null;

    /**
     * @author Verdient。
     */
    #[Override]
    public function process(object $event): void
    {
        $command = $_SERVER['argv'][1] ?? null;

        if ($command !== 'start' && !str_starts_with((string) $command, 'crontab:')) {
            return;
        }

        $this->config = $this->container->get(ConfigInterface::class);

        if (!$this->config->get('crontab.enable', false)) {
            return;
        }

        /** @var EnablerManager */
        $enablerManager = $this->container->get(EnablerManager::class);

        /** @var array<string,Crontab> */
        $classCrontabs = AnnotationCollector::getClassesByAnnotation(Crontab::class);

        foreach ($classCrontabs as $class => $crontab) {

            if (!$crontab->memo) {
                $reflectionClass = ReflectionManager::reflectClass($class);

                $summary = $crontab->name;

                if ($docComment = $reflectionClass->getDocComment()) {
                    $summary = $this->getDocBlockParser()->create($docComment)->getSummary();

                    if ($summary) {
                        $crontab->memo = $summary;
                    }
                }
            }

            if ($crontab->enable === []) {
                $envName = $this->getEnvName($class);
                $enablerManager->collect($crontab->name, $envName);
                $crontab->enable = env($envName, false);
            }

            if (!$crontab->callback && is_subclass_of($class, CrontabInterface::class)) {
                $crontab->callback = 'handle';
            }

            AnnotationCollector::collectClass($class, AnnotationCrontab::class, $crontab->toBase($class));
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

        $this->crontabManager = $this->container->get(CrontabManager::class);


        $this->logger = $command === 'start' ? match (true) {
            $this->container->has(LoggerInterface::class) => $this->container->get(LoggerInterface::class),
            $this->container->has(StdoutLoggerInterface::class) => $this->container->get(StdoutLoggerInterface::class),
            default => null,
        } : null;

        $crontabs = $this->parseCrontabs();

        $environment = (string) $this->config->get('app_env', '');

        foreach ($crontabs as $crontab) {
            if (!$crontab instanceof CrontabCrontab) {
                continue;
            }

            if (!$crontab->isEnable()) {
                $this->logger?->warning(sprintf('Crontab %s is disabled.', $crontab->getName()));
                continue;
            }

            if (!$crontab->runsInEnvironment($environment)) {
                $this->logger?->warning(sprintf('Crontab %s is disabled in %s environment.', $crontab->getName(), $environment));
                continue;
            }

            if (!$this->crontabManager->isValidCrontab($crontab)) {
                $this->logger?->warning(sprintf('Crontab %s is invalid.', $crontab->getName()));
                continue;
            }

            if ($this->crontabManager->register($crontab)) {
                $this->logger?->debug(sprintf('Crontab %s have been registered.', $crontab->getName()));
            }
        }
    }

    /**
     * @author Verdient。
     */
    protected function parseCrontabs(): array
    {
        $configCrontabs = $this->config->get('crontab.crontab', []);
        $annotationCrontabs = AnnotationCollector::getClassesByAnnotation(AnnotationCrontab::class);
        $methodCrontabs = $this->getCrontabsFromMethod();

        Schedule::load();
        $pendingCrontabs = Schedule::getCrontabs();

        $crontabs = [];

        foreach (array_merge($configCrontabs, $annotationCrontabs, $methodCrontabs, $pendingCrontabs) as $crontab) {
            if ($crontab instanceof AnnotationCrontab) {
                $crontab = $this->buildCrontabByAnnotation($crontab);
            }
            if ($crontab instanceof CrontabCrontab) {
                $crontabs[$crontab->getName()] = $crontab;
            }
        }

        return array_values($crontabs);
    }

    /**
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
     * @author Verdient。
     */
    protected function buildCrontabByAnnotation(AnnotationCrontab $annotation): CrontabCrontab
    {
        $crontab = new CrontabCrontab();
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

                $container = ApplicationContext::getContainer();
                if ($container->has($className)) {
                    return $container->get($className)->{$method}();
                }
            }

            $this->logger?->info('Crontab enable method is not public, skip register.');
        } catch (ReflectionException $e) {
            $this->logger?->error('Resolve crontab enable failed, skip register.' . $e);
        }

        return false;
    }

    /**
     * 获取环境变量名称
     *
     * @param string $value 值
     *
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
     *
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
