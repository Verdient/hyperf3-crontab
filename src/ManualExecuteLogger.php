<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Crontab;

use Override;
use Psr\Log\LoggerInterface;
use Stringable;
use Verdient\Hyperf3\Logger\StdoutLogger;

/**
 * 手动执行记录器
 *
 * @author Verdient。
 */
class ManualExecuteLogger extends StdoutLogger
{
    /**
     * @param LoggerInterface $logger 记录器
     *
     * @author Verdient。
     */
    public function __construct(protected LoggerInterface $logger) {}

    /**
     * @author Verdient。
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        parent::log($level, $message, $context);
        $this->logger->log($level, $message, $context);
    }
}
