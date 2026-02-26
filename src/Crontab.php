<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Attribute;
use Hyperf\Crontab\Annotation\Crontab as AnnotationCrontab;
use Override;

/**
 * 定时任务
 *
 * @author Verdient。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Crontab extends AnnotationCrontab
{
    /**
     * 构造函数
     *
     * @author Verdient。
     */
    public function __construct(
        public ?string $rule = null,
        public ?string $name = null,
        public string $type = 'callback',
        public ?bool $singleton = true,
        public ?string $mutexPool = null,
        public ?int $mutexExpires = 120,
        public ?bool $onOneServer = null,
        public array|string|null $callback = null,
        public ?string $memo = null,
        public array|string|bool $enable = [],
        public ?string $timezone = null,
        public array|string $environments = [],
        public array $options = [],
    ) {
        parent::__construct(
            rule: $rule,
            name: $name,
            type: $type,
            singleton: $singleton,
            mutexPool: $mutexPool,
            mutexExpires: $mutexExpires,
            onOneServer: $onOneServer,
            callback: $callback,
            memo: $memo,
            enable: $enable,
            timezone: $timezone,
            environments: $environments,
            options: $options
        );
    }

    /**
     * @author Verdient。
     */
    #[Override]
    protected function parseCallback(string $className): void
    {
        if (!$this->callback) {
            if (is_subclass_of($className, CrontabInterface::class)) {
                $this->callback = [$className, 'handle'];
            }
        }

        parent::parseCallback($className);
    }

    /**
     * 转换为父类对象
     *
     * @author Verdient。
     */
    public function toBase(): AnnotationCrontab
    {
        $annotation2 = new AnnotationCrontab();

        foreach (get_object_vars($this) as $name => $value) {
            $annotation2->{$name} = $value;
        }

        return $annotation2;
    }
}
