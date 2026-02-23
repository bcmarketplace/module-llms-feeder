<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Block\Adminhtml\System\Config;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class FileStatus extends Field
{
    private Filesystem $filesystem;
    private StoreManagerInterface $storeManager;
    private ScopeConfigInterface $scopeConfig;
    private ConfigHelper $configHelper;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $configHelper,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    /**
     * Render element HTML
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element): string
    {
        try {
            $pubDirectory = $this->filesystem->getDirectoryRead(DirectoryList::PUB);

            // Get the selected store ID from configuration
            $selectedStoreId = $this->configHelper->getSelectedStoreId();
            
            // Get the selected store
            $selectedStore = $this->storeManager->getStore($selectedStoreId);
            $selectedStoreCode = $selectedStore->getCode();
            
            // Check if file exists for the selected store
            $filePath = 'llms' . DIRECTORY_SEPARATOR . $selectedStoreCode . DIRECTORY_SEPARATOR . ConfigHelper::LLMS_FILENAME;
            
            if ($pubDirectory->isExist($filePath)) {
                $html = '<div style="color: #008000; font-weight: bold;">✓ File exists for selected store: ' . $selectedStore->getName() . '</div>';

                // Get the selected store's base URL
                $baseUrl = $selectedStore->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
                $fileUrl = $baseUrl . ConfigHelper::LLMS_FILENAME;

                $html .= sprintf(
                    '<div style="margin-top: 5px;"><a href="%s" target="_blank" style="color: #0066cc;">View File</a></div>',
                    $fileUrl
                );

                return $html;
            } else {
                return '<div style="color: #e02b27; font-weight: bold;">✗ File has not been generated yet for selected store: ' . $selectedStore->getName() . '</div>';
            }
        } catch (\Exception $e) {
            $this->logger->error('[LLMsFeeder] Error checking file status', [
                'error' => $e->getMessage()
            ]);
            return '<div style="color: #e02b27; font-weight: bold;">✗ Error checking file status: ' . $e->getMessage() . '</div>';
        }
    }

}
