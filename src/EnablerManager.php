<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

/**
 * 开关管理器
 *
 * @author Verdient。
 */
class EnablerManager
{
    /**
     * @var array<string,string> 开关集合
     *
     * @author Verdient。
     */
    protected array $enablers = [];

    /**
     * 收集开关名称
     *
     * @param string $name 名称
     * @param string $enablerName 开关名称
     *
     * @author Verdient。
     */
    public function collect(string $name, string $enablerName): static
    {
        $this->enablers[$name] = $enablerName;
        return $this;
    }

    /**
     * 获取开关名称集合
     *
     * @return array<string,string>
     * @author Verdient。
     */
    public function getEnablers(): array
    {
        return $this->enablers;
    }

    /**
     * 获取开关名称
     *
     * @param string $name 名称
     *
     * @author Verdient。
     */
    public function getEnablerName($name): ?string
    {
        return $this->enablers[$name] ?? null;
    }
}
