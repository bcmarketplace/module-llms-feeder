<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Cron;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use BCMarketplace\LLMsFeeder\Model\Generator;
use Psr\Log\LoggerInterface;

class Generate
{
    private Generator $generator;
    private ConfigHelper $configHelper;
    private LoggerInterface $logger;

    public function __construct(
        Generator $generator,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->generator = $generator;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->configHelper->isEnabled()) {
            return;
        }

        try {
            $this->generator->execute();
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Cron execution failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
