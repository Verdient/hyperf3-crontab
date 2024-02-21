<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Attribute;
use Hyperf\Crontab\Annotation\Crontab as AnnotationCrontab;

/**
 * 定时任务
 * @author Verdient。
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Crontab extends AnnotationCrontab
{
    /**
     * @inheritdoc
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
     * 转换为父类对象
     * @return AnnotationCrontab
     * @author Verdient。
     */
    public function toBase(): AnnotationCrontab
    {
        $annotation2 = new AnnotationCrontab();

        foreach (array_keys(get_object_vars($this)) as $name) {
            $annotation2->{$name} = $this->{$name};
        }

        return $annotation2;
    }
}
