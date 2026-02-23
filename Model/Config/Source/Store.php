<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Store source model for configuration dropdown
 */
class Store implements OptionSourceInterface
{
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        
        try {
            $stores = $this->storeManager->getStores();
            
            foreach ($stores as $store) {
                $options[] = [
                    'value' => $store->getId(),
                    'label' => $store->getName() . ' (' . $store->getCode() . ')'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('[LLMsFeeder] Failed to load stores for configuration', [
                'error' => $e->getMessage()
            ]);
            // If there's an error getting stores, return empty array
            $options = [];
        }
        
        return $options;
    }
}
