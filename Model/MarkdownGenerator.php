<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Markdown generator for LLMs data
 */
class MarkdownGenerator
{
    /**
     * Data processor instance
     *
     * @var DataProcessor
     */
    private DataProcessor $dataProcessor;

    /**
     * Configuration helper instance
     *
     * @var ConfigHelper
     */
    private ConfigHelper $configHelper;

    /**
     * Scope configuration interface
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Store manager interface
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Store emulation instance
     *
     * @var Emulation
     */
    private Emulation $emulation;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Sitemap processor instance
     *
     * @var SitemapProcessor
     */
    private SitemapProcessor $sitemapProcessor;

    /**
     * Constructor
     *
     * @param DataProcessor $dataProcessor
     * @param ConfigHelper $configHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param LoggerInterface $logger
     * @param SitemapProcessor $sitemapProcessor
     */
    public function __construct(
        DataProcessor $dataProcessor,
        ConfigHelper $configHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Emulation $emulation,
        LoggerInterface $logger,
        SitemapProcessor $sitemapProcessor
    ) {
        $this->dataProcessor = $dataProcessor;
        $this->configHelper = $configHelper;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
        $this->logger = $logger;
        $this->sitemapProcessor = $sitemapProcessor;
    }

    /**
     * Generate markdown content for LLMs by store
     *
     * @param array|null $storeIds Optional array of store IDs to generate for. If null, generates for the selected store only.
     * @return array Array mapping store codes to their LLMs content
     */
    public function generateMarkdown(?array $storeIds = null): array
    {
        $storeContents = [];

        // Get stores to process
        if ($storeIds !== null) {
            // Process only specified stores
            $stores = [];
            foreach ($storeIds as $storeId) {
                try {
                    $store = $this->storeManager->getStore($storeId);
                    $stores[] = $store;
                } catch (\Exception $e) {
                    $this->logger->warning('[LLMsFeeder] Store not found: ' . $storeId, ['exception' => $e]);
                }
            }
        } else {
            // Get the selected store from configuration
            $selectedStoreId = $this->configHelper->getSelectedStoreId();
            try {
                $selectedStore = $this->storeManager->getStore($selectedStoreId);
                $stores = [$selectedStore];
            } catch (\Exception $e) {
                $this->logger->error('[LLMsFeeder] Selected store not found: ' . $selectedStoreId, ['exception' => $e]);
                return [];
            }
        }

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();
            $storeCode = $store->getCode();

            // Check if the module is enabled for this specific store scope
            if (!$this->configHelper->isEnabled($storeId)) {
                continue; // Skip to the next store
            }

            try {
                // Get company data for this store
                $companyName = (string) $this->scopeConfig->getValue(
                    'general/store_information/name',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );

                $companyData = $this->dataProcessor->getCompanyData($storeId);
                $companyDescription = $companyData['description'] ?? '';
                $companyLinks = $companyData['urls'] ?? [];

                // Get data for this store
                $categoriesData = $this->dataProcessor->getCategoriesData($storeId);
                $productsData = $this->dataProcessor->getProductsData($storeId);
                $cmsPagesData = $this->dataProcessor->getCmsPagesData($storeId);


                // Generate markdown content for this store
                $storeContent = $this->buildMarkdown(
                    $storeId,
                    $companyName,
                    $companyDescription,
                    $companyLinks,
                    $categoriesData,
                    $productsData,
                    $cmsPagesData
                );

                $storeContents[$storeCode] = $storeContent;

            } catch (LocalizedException $e) {
                // Stop environment emulation
                $this->logger->error('[LLMsFeeder] Error during processing of content', [
                    'store_id' => $storeId,
                    'store_code' => $storeCode,
                    'error' => $e->getMessage()
                ]);
            }
        }


        return $storeContents;
    }

    /**
     * Build markdown content from collected data
     *
     * @param int $storeId
     * @param string $companyName
     * @param string $companyDescription
     * @param array $companyLinks
     * @param array $categoriesData
     * @param array $productsData
     * @param array $cmsPagesData
     * @return string
     */
    private function buildMarkdown(
        int $storeId,
        string $companyName,
        string $companyDescription,
        array $companyLinks,
        array $categoriesData,
        array $productsData,
        array $cmsPagesData
    ): string {
        $markdown = [];

        // Get configuration values for this store
        $siteTitle = $this->configHelper->getSiteTitle($storeId);
        $llmInstruction = $this->configHelper->getLlmInstruction($storeId);
        $contentTypes = $this->configHelper->getContentTypes($storeId);
        $customFileDirectives = $this->configHelper->getCustomFileDirectives($storeId);

        // Site Title (use configured title or fall back to company name)
        $title = !empty($siteTitle) ? $siteTitle : $companyName;
        $markdown[] = "# {$title}";
        $markdown[] = '';

        // Company Description
        if (!empty($companyDescription)) {
            $markdown[] = "> {$companyDescription}";
        } else {
            $markdown[] = "> Company Description";
        }
        $markdown[] = '';

        // LLM Instruction
        if (!empty($llmInstruction)) {
            $markdown[] = "## LLM Instructions";
            $markdown[] = '';
            $markdown[] = $llmInstruction;
            $markdown[] = '';
        }

        // Company Links
        $markdown[] = "## Company";
        $markdown[] = '';
        if (!empty($companyLinks)) {
            foreach ($companyLinks as $link) {
                $markdown[] = "- [{$link['short_description']}]({$link['url']}) {$link['keywords']}";
            }
        }
        $markdown[] = '';

        // CMS Pages
        if (empty($contentTypes) || in_array('cms_page', $contentTypes)) {
            $markdown[] = "## Pages";
            $markdown[] = '';
            if (!empty($cmsPagesData)) {
                // Group CMS pages by name
                $groupedCmsPages = [];
                foreach ($cmsPagesData as $page) {
                    $groupedCmsPages[$page['name']][] = $page;
                }

                foreach ($groupedCmsPages as $pageName => $pages) {

                    foreach ($pages as $page) {
                        $metaDescription = $page['meta_description'] ? " : ".$page['meta_description']: '';
                        $markdown[] = "- [{$pageName}]({$page['url']}){$metaDescription}";
                    }
                }
                $markdown[] = '';
            }
        }

        // Categories
        if (empty($contentTypes) || in_array('category', $contentTypes)) {
            $markdown[] = "## Categories";
            if (!empty($categoriesData)) {
                foreach ($categoriesData as $category) {
                    $metaDescription = $category['meta_description'] ? " : ".$category['meta_description']: '';
                    $markdown[] = "- [{$category['name']}]({$category['url']}){$metaDescription}";
                }
            }
            $markdown[] = '';
        }

        // Products
        if (empty($contentTypes) || in_array('product', $contentTypes)) {
            $markdown[] = "## Products Resources";
            $markdown[] = '';
            if (!empty($productsData)) {
                foreach ($productsData as $product) {
                    $metaDescription = $product['meta_description'] ? " : ".$product['meta_description']: '';
                    $markdown[] = "- [{$product['name']}]({$product['url']}){$metaDescription}";
                }
            }
        }

        // Custom File Directives
        if (!empty($customFileDirectives)) {
            $markdown[] = '';
            $markdown[] = "## Additional Information";
            $markdown[] = '';
            $markdown[] = $customFileDirectives;
        }

        return implode("\n", $markdown);
    }
}
