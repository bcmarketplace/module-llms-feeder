<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

/**
 * Cron configuration backend model
 */
class Cron extends Value
{
    /**
     * Configuration value factory instance
     *
     * @var ValueFactory
     */
    private ValueFactory $configValueFactory;

    /**
     * Scope config interface
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configValueFactory = $configValueFactory;
        $this->scopeConfig = $config;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After save method to update cron configuration
     *
     * @return $this
     * @throws LocalizedException
     */
    public function afterSave()
    {
        $frequency = $this->getValue();
        $generationTime = $this->getGenerationTime();
        $cronExpression = $this->getCronExpression($frequency, $generationTime);

        // Check if the config path exists, if so update existing value, else save new record
        $configValue = $this->configValueFactory->create();
        $configValue->load('crontab/default/jobs/bcmarketplace_llmsfeeder_generate/schedule/cron_expr', 'path');

        if ($configValue->getId()) {
            // Update existing record
            $configValue->setValue($cronExpression);
            $configValue->save();
        } else {
            // Save new record
            $configValue->setPath('crontab/default/jobs/bcmarketplace_llmsfeeder_generate/schedule/cron_expr');
            $configValue->setValue($cronExpression);
            $configValue->save();
        }

        // Clear config cache to ensure changes are applied
        $this->cacheTypeList->cleanType('config');

        return parent::afterSave();
    }

    /**
     * Get generation time from configuration
     *
     * @return string
     */
    private function getGenerationTime(): string
    {
        $time = $this->scopeConfig->getValue(
            'bcmarketplace_llmsfeeder/settings/generation_time',
            ScopeInterface::SCOPE_STORE,
            $this->getScopeId()
        );

        // Default to 2 AM if no time is set
        return $time ?: '02:00';
    }

    /**
     * Get cron expression based on frequency and time
     *
     * @param string $frequency
     * @param string $time
     * @return string
     */
    private function getCronExpression(string $frequency, string $time): string
    {
        // Parse time (format: HH:MM)
        $timeParts = explode(',', $time);
        $hour = isset($timeParts[0]) ? (int) $timeParts[0] : 2;
        $minute = isset($timeParts[1]) ? (int) $timeParts[1] : 0;

        // Ensure valid hour and minute values
        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return match ($frequency) {
            'D' => "{$minute} {$hour} * * *", // Daily
            'W' => "{$minute} {$hour} * * 0", // Weekly on Sunday
            'M' => "{$minute} {$hour} 1 * *", // Monthly on 1st day
            default => "{$minute} {$hour} * * 0", // Default to weekly
        };
    }
}
