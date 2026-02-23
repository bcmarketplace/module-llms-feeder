<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Helper\Page as CmsPageHelper;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Data processor for LLMs Feeder
 *
 * Optimized for performance with intelligent caching and memory-efficient data processing
 */
class DataProcessor
{
    private const CACHE_TAG = 'llms_feeder';
    private const CACHE_LIFETIME = 3600; // 1 hour
    private const MAX_DESCRIPTION_LENGTH = 250;
    private const MAX_CMS_DESCRIPTION_LENGTH = 100;

    /**
     * Factory for creating product collections
     *
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * Product visibility configuration
     *
     * @var Visibility
     */
    private Visibility $productVisibility;

    /**
     * Factory for creating category collections
     *
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * Factory for creating page collections
     *
     * @var PageCollectionFactory
     */
    private PageCollectionFactory $pageCollectionFactory;

    /**
     * Helper for CMS page operations
     *
     * @var CmsPageHelper
     */
    private CmsPageHelper $cmsPageHelper;

    /**
     * Store manager for multi-store operations
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Scope configuration interface
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Logger interface
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Serializer interface
     *
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Cache frontend pool
     *
     * @var FrontendPool
     */
    private FrontendPool $cacheFrontendPool;

    /**
     * Constructor
     *
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Visibility $productVisibility
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param PageCollectionFactory $pageCollectionFactory
     * @param CmsPageHelper $cmsPageHelper
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Visibility $productVisibility,
        CategoryCollectionFactory $categoryCollectionFactory,
        PageCollectionFactory $pageCollectionFactory,
        CmsPageHelper $cmsPageHelper,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        SerializerInterface $serializer,
        FrontendPool $cacheFrontendPool
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productVisibility = $productVisibility;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->cmsPageHelper = $cmsPageHelper;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Get products data with optimized collection loading
     *
     * @param int|null $storeId
     * @return array<int, array{name:string,short_description:string,url:string}>
     * @throws LocalizedException
     */
    public function getProductsData(?int $storeId = null): array
    {
        $storeId = $storeId ?? $this->getCurrentStoreId();
        $cacheKey = "products_data_{$storeId}";

        return $this->getCachedData($cacheKey, function () use ($storeId): array {
            $data = $this->loadProductsData($storeId);
            $this->logger->info(
                '[LLMsFeeder] Loaded products data',
                ['store_id' => $storeId, 'count' => count($data)]
            );
            return $data;
        });
    }

    /**
     * Get company data with configuration fallback
     *
     * @param int|null $storeId
     * @return array{description:string,urls:array<int, array{short_description:string,url:string,keywords:string}>}
     * @throws LocalizedException
     */
    public function getCompanyData(?int $storeId = null): array
    {
        $storeId = $storeId ?? $this->getCurrentStoreId();
        $cacheKey = "company_data_{$storeId}";

        $data = $this->getCachedData($cacheKey, function () use ($storeId): array {
            return $this->loadCompanyData($storeId);
        });

        $this->logger->info(
            '[LLMsFeeder] Loaded company data',
            ['store_id' => $storeId, 'urls_count' => count($data['urls'])]
        );

        return $data;
    }

    /**
     * Get categories data with optimized filtering
     *
     * @param int|null $storeId
     * @return array<int, array{name:string,short_description:string,url:string}>
     * @throws LocalizedException
     */
    public function getCategoriesData(?int $storeId = null): array
    {
        $storeId = $storeId ?? $this->getCurrentStoreId();
        $cacheKey = "categories_data_{$storeId}";

        return $this->getCachedData($cacheKey, function () use ($storeId): array {
            return $this->loadCategoriesData($storeId);
        });
    }

    /**
     * Get CMS pages data excluding company pages
     *
     * @param int|null $storeId
     * @return array<int, array{name:string,short_description:string,url:string}>
     * @throws LocalizedException
     */
    public function getCmsPagesData(?int $storeId = null): array
    {
        $storeId = $storeId ?? $this->getCurrentStoreId();
        $cacheKey = "cms_pages_data_{$storeId}";

        return $this->getCachedData($cacheKey, function () use ($storeId): array {
            return $this->loadCmsPagesData($storeId);
        });
    }

    /**
     * Load products data with optimized collection
     *
     * @param int $storeId
     * @return array<int, array{name:string,short_description:string,url:string}>
     * @throws LocalizedException
     */
    private function loadProductsData(int $storeId): array
    {
        try {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['name', 'short_description', 'url_key','meta_description']);
            $collection->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', ['in' => $this->productVisibility->getVisibleInSiteIds()]);

            // Optimize collection loading
            $collection->setPageSize(1000); // Process in chunks to avoid memory issues

            $data = [];
            $page = 1;

            do {
                $collection->setCurPage($page);
                $collection->load();

                foreach ($collection as $product) {
                    $data[] = [
                        'name' => $this->sanitizeString($product->getName()),
                        'short_description' => $this->stripAndTruncate(
                            $product->getShortDescription(),
                            self::MAX_DESCRIPTION_LENGTH
                        ),
                        'meta_description' => $this->stripAndTruncate(
                            $product->getMetaDescription(),
                            self::MAX_DESCRIPTION_LENGTH
                        ),
                        'url' => $this->sanitizeString($product->getProductUrl()),
                    ];
                }

                $page++;
            } while ($collection->getCurPage() < $collection->getLastPageNumber());


            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to load products data', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            throw new LocalizedException(__('Failed to load products data: %1', $e->getMessage()));
        }
    }

    /**
     * Load company data from configuration
     *
     * @param int $storeId
     * @return array{description:string,urls:array<int, array{short_description:string,url:string,keywords:string}>}
     * @throws LocalizedException
     */
    private function loadCompanyData(int $storeId): array
    {
        try {
            $description = $this->scopeConfig->getValue(
                'bcmarketplace_llmsfeeder/settings/site_description',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            $companyPageIds = $this->getCompanyInfoPageIds($storeId);
            $urls = $this->loadCompanyPageUrls($companyPageIds);

            $data = [
                'description' => $this->sanitizeString($description),
                'urls' => $urls
            ];


            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to load company data', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            throw new LocalizedException(__('Failed to load company data: %1', $e->getMessage()));
        }
    }

    /**
     * Load categories data with optimized collection
     *
     * @param int $storeId
     * @return array<int, array{name:string,short_description:string,url:string}>
     * @throws LocalizedException
     */
    private function loadCategoriesData(int $storeId): array
    {
        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addAttributeToSelect(['name', 'description', 'is_active', 'url_key', 'meta_description']);
            $collection->addAttributeToFilter('is_active', 1);
            $collection->addAttributeToFilter('level', ['gt' => 1]);

            // Optimize collection loading
            $collection->setPageSize(1000);

            $data = [];
            $page = 1;

            do {
                $collection->setCurPage($page);
                $collection->load();

                foreach ($collection as $category) {
                    $data[] = [
                        'name' => $this->sanitizeString($category->getName()),
                        'short_description' => $this->stripAndTruncate(
                            $category->getDescription(),
                            self::MAX_DESCRIPTION_LENGTH
                        ),
                        'meta_description' => $this->stripAndTruncate(
                            $category->getMetaDescription(),
                            self::MAX_DESCRIPTION_LENGTH
                        ),
                        'url' => $this->sanitizeString($category->getUrl()),
                    ];
                }

                $page++;
            } while ($collection->getCurPage() < $collection->getLastPageNumber());


            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to load categories data', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            throw new LocalizedException(__('Failed to load categories data: %1', $e->getMessage()));
        }
    }

    /**
     * Load CMS pages data excluding company pages
     *
     * @param int $storeId
     * @return array<int, array{name:string,short_description:string,url:string}>
     * @throws LocalizedException
     */
    private function loadCmsPagesData(int $storeId): array
    {
        try {
            $companyPageIds = $this->getCompanyInfoPageIds($storeId);

            $collection = $this->pageCollectionFactory->create();
            $collection->addFieldToSelect(['title', 'content', 'page_id', 'identifier','meta_description']);
            $collection->addStoreFilter($storeId);
            $collection->addFieldToFilter('is_active', 1);

            // Exclude company pages
            if (!empty($companyPageIds)) {
                $collection->addFieldToFilter('page_id', ['nin' => $companyPageIds]);
            }

            // Optimize collection loading
            $collection->setPageSize(1000);

            $data = [];
            $page = 1;
            $totalProcessed = 0;

            do {
                $collection->setCurPage($page);
                $collection->load();

                foreach ($collection as $cmsPage) {
                    $pageId = (int) $cmsPage->getId();

                    // Double-check exclusion (defensive programming)
                    if (in_array($pageId, $companyPageIds, true)) {
                        continue;
                    }

                    $data[] = [
                        'name' => $this->sanitizeString($cmsPage->getTitle()),
                        'short_description' => $this->stripAndTruncate(
                            $cmsPage->getContent(),
                            self::MAX_CMS_DESCRIPTION_LENGTH
                        ),
                        'meta_description' => $this->stripAndTruncate($cmsPage->getMetaDescription(), self::MAX_DESCRIPTION_LENGTH),
                        'url' => $this->sanitizeString($this->cmsPageHelper->getPageUrl($pageId)),
                    ];
                    $totalProcessed++;
                }

                $page++;
            } while ($collection->getCurPage() < $collection->getLastPageNumber());


            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to load CMS pages data', [
                'store_id' => $storeId,
                'error' => $e->getMessage()
            ]);
            throw new LocalizedException(__('Failed to load CMS pages data: %1', $e->getMessage()));
        }
    }

    /**
     * Load company page URLs with optimized batch loading
     *
     * @param array<int> $pageIds
     * @return array<int, array{short_description:string,url:string,keywords:string}>
     */
    private function loadCompanyPageUrls(array $pageIds): array
    {
        if (empty($pageIds)) {
            return [];
        }

        try {
            $collection = $this->pageCollectionFactory->create();
            $collection->addFieldToSelect(['title', 'identifier', 'page_id']);
            $collection->addFieldToFilter('page_id', ['in' => $pageIds]);

            $urls = [];
            foreach ($collection as $page) {
                $pageId = (int) $page->getId();
                $urls[] = [
                    'short_description' => $this->sanitizeString($page->getTitle() ?? 'Company Page'),
                    'meta_description' => $this->sanitizeString($page->getMetaDescription() ?? ''),
                    'url' => $this->sanitizeString($this->cmsPageHelper->getPageUrl($pageId)),
                    'keywords' => $this->extractKeywordsFromIdentifier($page->getIdentifier())
                ];
            }

            return $urls;
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to load company page URLs', [
                'page_ids' => $pageIds,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get cached data or compute and cache it
     *
     * @param string $cacheKey
     * @param callable $callback
     * @return mixed
     */
    private function getCachedData(string $cacheKey, callable $callback)
    {
        try {
            // Use the default cache frontend
            $cache = $this->cacheFrontendPool->get('default');

            $cachedData = $cache->load($cacheKey);

            if ($cachedData !== false) {
                try {
                    return $this->serializer->unserialize($cachedData);
                } catch (\Throwable $e) {
                    $this->logger->error('[LLMsFeeder] Failed to unserialize cached data', [
                        'cache_key' => $cacheKey,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $data = $callback();

            try {
                $serializedData = $this->serializer->serialize($data);
                $cache->save($serializedData, $cacheKey, [self::CACHE_TAG], self::CACHE_LIFETIME);
            } catch (\Throwable $e) {
                $this->logger->error('[LLMsFeeder] Failed to cache data', [
                    'cache_key' => $cacheKey,
                    'error' => $e->getMessage()
                ]);
            }

            return $data;
        } catch (\Throwable $e) {
            // If caching fails, just return the data without caching
            return $callback();
        }
    }

    /**
     * Get current store ID
     *
     * @return int
     */
    private function getCurrentStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * Get company info page IDs from configuration using CMS identifiers
     *
     * @param int $storeId
     * @return array<int>
     */
    private function getCompanyInfoPageIds(int $storeId): array
    {
        $value = $this->scopeConfig->getValue(
            'bcmarketplace_llmsfeeder/settings/company_info_pages',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($value)) {
            return [];
        }
        $rawIdentifiers = array_map('trim', explode(',', $value));
        $validIdentifiers = array_filter(
            $rawIdentifiers,
            static function ($v) {
                return $v !== '' && is_string($v);
            }
        );

        $invalidIdentifiers = array_diff($rawIdentifiers, $validIdentifiers);

        if (empty($validIdentifiers)) {
            return [];
        }

        try {
            // Load CMS pages by identifiers
            $collection = $this->pageCollectionFactory->create();
            $collection->addFieldToSelect(['page_id', 'identifier', 'title']);
            $collection->addStoreFilter($storeId);
            $collection->addFieldToFilter('is_active', 1);
            $collection->addFieldToFilter('identifier', ['in' => $validIdentifiers]);

            $pageIds = [];
            $foundIdentifiers = [];
            $notFoundIdentifiers = [];

            foreach ($collection as $page) {
                $pageId = (int) $page->getId();
                $identifier = $page->getIdentifier();
                $title = $page->getTitle();

                $pageIds[] = $pageId;
                $foundIdentifiers[] = $identifier;

            }

            // Check for identifiers that were not found
            $notFoundIdentifiers = array_diff($validIdentifiers, $foundIdentifiers);


            return $pageIds;

        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to resolve company page IDs from identifiers', [
                'store_id' => $storeId,
                'identifiers' => $validIdentifiers,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract keywords from CMS page identifier
     *
     * @param string|null $identifier
     * @return string
     */
    private function extractKeywordsFromIdentifier(?string $identifier): string
    {
        if (empty($identifier)) {
            return '';
        }

        return str_replace(['-', '_'], ' ', $identifier);
    }

    /**
     * Strip HTML tags and truncate text
     *
     * @param string|null $html
     * @param int $limit
     * @return string
     */
    private function stripAndTruncate(?string $html, int $limit): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove HTML tags and decode entities
        $text = strip_tags($html);
        $text = $this->decodeHtmlEntities($text);

        // Remove extra whitespace and normalize
        $text = preg_replace('/\s+/', ' ', trim($text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
    }

    /**
     * Decode HTML entities safely
     *
     * @param string $text
     * @return string
     */
    private function decodeHtmlEntities(string $text): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize string value
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeString($value): string
    {
        return (string) ($value ?? '');
    }

    /**
     * Clear cache for all LLMs Feeder data
     *
     * @return void
     */
    public function clearCache(): void
    {
        try {
            $cache = $this->cacheFrontendPool->get('default');
            $cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, [self::CACHE_TAG]);
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Failed to clear cache', ['error' => $e->getMessage()]);
            return;
        }

        $this->logger->info('[LLMsFeeder] Cache cleared successfully');
    }
}
