<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Controller\Index;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * LLMs content controller
 */
class Index implements HttpGetActionInterface
{
    private RequestInterface $request;
    private ResultFactory $resultFactory;
    private StoreManagerInterface $storeManager;
    private DirectoryList $directoryList;
    private IoFile $ioFile;
    private LoggerInterface $logger;
    private ConfigHelper $configHelper;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        IoFile $ioFile,
        LoggerInterface $logger,
        ConfigHelper $configHelper
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->ioFile = $ioFile;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
    }

    /**
     * Execute the action
     *
     * @return Raw
     */
    public function execute()
    {
        
        try {
            // Get the store code from various sources
            $storeCode = $this->getStoreCode();
            
            
            // Get the content for the store
            $content = $this->getStoreContent($storeCode);
            
            if ($content === null) {
                // Return 404 if content not found
                return $this->createResponse('LLMs content not found', 404);
            }
            
            // Return the content
            return $this->createResponse($content, 200);
            
        } catch (\Exception $e) {
            $this->logger->error('[LLMsFeeder] Error serving llms.txt: ' . $e->getMessage(), ['exception' => $e]);
            return $this->createResponse('Error serving LLMs content', 500);
        }
    }

    /**
     * Get store code from configuration
     *
     * @return string
     */
    private function getStoreCode(): string
    {
        try {
            // Get the selected store ID from configuration
            $selectedStoreId = $this->configHelper->getSelectedStoreId();
            
            // Get the selected store
            $selectedStore = $this->storeManager->getStore($selectedStoreId);
            $storeCode = $selectedStore->getCode();
            
            
            return $storeCode;
        } catch (\Exception $e) {
            $this->logger->error('[LLMsFeeder] Error getting selected store: ' . $e->getMessage(), ['exception' => $e]);
            
            // Fallback to default store
            try {
                $defaultStore = $this->storeManager->getDefaultStoreView();
                return $defaultStore->getCode();
            } catch (\Exception $fallbackException) {
                $this->logger->error('[LLMsFeeder] Error getting default store: ' . $fallbackException->getMessage(), ['exception' => $fallbackException]);
                return 'default';
            }
        }
    }

    /**
     * Get content for a specific store
     *
     * @param string $storeCode
     * @return string|null
     */
    private function getStoreContent(string $storeCode): ?string
    {
        // Define the path to store-specific LLMs file
        $llmsDir = $this->directoryList->getPath(DirectoryList::PUB) . DIRECTORY_SEPARATOR . 'llms';
        $storeLLMsFile = $llmsDir . DIRECTORY_SEPARATOR . $storeCode . DIRECTORY_SEPARATOR . 'llms.txt';

        // Check if the store-specific file exists
        if ($this->ioFile->fileExists($storeLLMsFile)) {
            try {
                $content = $this->ioFile->read($storeLLMsFile);
                if ($content !== false) {
                    return $content;
                }
            } catch (\Exception $e) {
                $this->logger->warning('[LLMsFeeder] Error reading store file: ' . $storeLLMsFile, ['exception' => $e]);
            }
        }

        // Fallback to default store
        $defaultLLMsFile = $llmsDir . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'llms.txt';
        if ($this->ioFile->fileExists($defaultLLMsFile)) {
            try {
                $content = $this->ioFile->read($defaultLLMsFile);
                if ($content !== false) {
                    return $content;
                }
            } catch (\Exception $e) {
                $this->logger->warning('[LLMsFeeder] Error reading default file: ' . $defaultLLMsFile, ['exception' => $e]);
            }
        }

        return null;
    }

    /**
     * Create response with content and status code
     *
     * @param string $content
     * @param int $statusCode
     * @return Raw
     */
    private function createResponse(string $content, int $statusCode): Raw
    {
        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        
        $result->setContents($content)
               ->setHeader('Content-Type', 'text/plain; charset=utf-8')
               ->setHeader('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
        
        if ($statusCode !== 200) {
            $result->setHttpResponseCode($statusCode);
        }
        
        return $result;
    }
}
