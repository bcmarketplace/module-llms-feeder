<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Frequency options for cron configuration
 */
class Frequency implements OptionSourceInterface
{
    public const DAILY = 'D';
    public const WEEKLY = 'W';
    public const MONTHLY = 'M';

    /**
     * Get frequency options for cron configuration
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::DAILY, 'label' => __('Daily')],
            ['value' => self::WEEKLY, 'label' => __('Weekly')],
            ['value' => self::MONTHLY, 'label' => __('Monthly')],
        ];
    }
}
