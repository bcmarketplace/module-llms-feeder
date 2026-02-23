<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Controller\Adminhtml\Generate;

use BCMarketplace\LLMsFeeder\Model\Generator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Admin controller for running LLMs data generation
 */
class Run extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BCMarketplace_LLMsFeeder::config';

    /**
     * Generator instance
     *
     * @var Generator
     */
    private Generator $generator;

    /**
     * Store manager interface
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Generator $generator
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Generator $generator,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->generator = $generator;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Execute the controller action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            // Get scope parameters from request
            $storeId = $this->getRequest()->getParam('store');
            $websiteId = $this->getRequest()->getParam('website');
            
            // Determine which stores to generate for based on scope
            $storeIds = $this->getStoreIdsForScope($storeId, $websiteId);
            
            if (empty($storeIds)) {
                throw new \RuntimeException('No stores found for the specified scope.');
            }
            
            $this->generator->execute($storeIds);
            $this->messageManager->addSuccessMessage(
                __('LLMs data generation has been successfully initiated for %1 store(s).', count($storeIds))
            );
        } catch (\RuntimeException $e) {
            $this->logger->error('[LLMsFeeder] Manual generation failed with runtime exception', [
                'error' => $e->getMessage()
            ]);
            $this->messageManager->addErrorMessage(__($e->getMessage()));
        } catch (\Throwable $e) {
            $this->logger->error(
                '[LLMsFeeder] Manual generation failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
            $this->messageManager->addErrorMessage(
                __('An error occurred while generating the data. Please check the logs for details.')
            );
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('adminhtml/system_config/edit/section/bcmarketplace_llmsfeeder');
        return $resultRedirect;
    }

    /**
     * Get store IDs for the specified scope
     *
     * @param string|null $storeId
     * @param string|null $websiteId
     * @return array
     */
    private function getStoreIdsForScope(?string $storeId, ?string $websiteId): array
    {
        $storeIds = [];
        
        if ($storeId) {
            // Specific store scope
            $storeIds[] = (int) $storeId;
        } elseif ($websiteId) {
            // Website scope - get all stores for this website
            $website = $this->storeManager->getWebsite($websiteId);
            foreach ($website->getStores() as $store) {
                $storeIds[] = (int) $store->getId();
            }
        } else {
            // Default scope - get all stores
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $storeIds[] = (int) $store->getId();
            }
        }
        
        return $storeIds;
    }
}
