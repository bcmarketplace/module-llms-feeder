<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Model;

use BCMarketplace\LLMsFeeder\Model\DataProcessor;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Helper\Page as CmsPageHelper;
use Magento\Cms\Model\Page;
use Magento\Cms\Model\ResourceModel\Page\Collection as PageCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DataProcessorTest extends TestCase
{
    /**
     * Data processor instance for testing
     *
     * @var DataProcessor
     */
    private DataProcessor $dataProcessor;

    /**
     * Product collection factory mock
     *
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * Product visibility mock
     *
     * @var Visibility
     */
    private Visibility $productVisibility;

    /**
     * Category collection factory mock
     *
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * Page collection factory mock
     *
     * @var PageCollectionFactory
     */
    private PageCollectionFactory $pageCollectionFactory;

    /**
     * CMS page helper mock
     *
     * @var CmsPageHelper
     */
    private CmsPageHelper $cmsPageHelper;

    /**
     * Store manager mock
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Scope config mock
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Logger mock
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Serializer mock
     *
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Cache frontend pool mock
     *
     * @var FrontendPool
     */
    private FrontendPool $cacheFrontendPool;

    /**
     * Cache frontend mock
     *
     * @var FrontendInterface
     */
    private FrontendInterface $cache;

    /**
     * Store mock
     *
     * @var StoreInterface
     */
    private StoreInterface $store;

    protected function setUp(): void
    {
        $this->productCollectionFactory = $this->createMock(ProductCollectionFactory::class);
        $this->productVisibility = $this->createMock(Visibility::class);
        $this->categoryCollectionFactory = $this->createMock(CategoryCollectionFactory::class);
        $this->pageCollectionFactory = $this->createMock(PageCollectionFactory::class);
        $this->cmsPageHelper = $this->createMock(CmsPageHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->cacheFrontendPool = $this->createMock(FrontendPool::class);
        $this->cache = $this->createMock(FrontendInterface::class);
        $this->store = $this->createMock(StoreInterface::class);

        $this->store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->cacheFrontendPool->method('get')->with('default')->willReturn($this->cache);

        $this->dataProcessor = new DataProcessor(
            $this->productCollectionFactory,
            $this->productVisibility,
            $this->categoryCollectionFactory,
            $this->pageCollectionFactory,
            $this->cmsPageHelper,
            $this->storeManager,
            $this->scopeConfig,
            $this->logger,
            $this->serializer,
            $this->cacheFrontendPool
        );
    }

    public function testGetProductsDataWithCacheHit(): void
    {
        $cachedData = [
            [
                'name' => 'Test Product',
                'short_description' => 'Test description',
                'url' => 'https://example.com/test-product'
            ]
        ];

        $this->cache->method('load')
            ->with('products_data_1')
            ->willReturn('serialized_data');

        $this->serializer->method('unserialize')
            ->with('serialized_data')
            ->willReturn($cachedData);

        $result = $this->dataProcessor->getProductsData();

        $this->assertEquals($cachedData, $result);
        $this->productCollectionFactory->expects($this->never())->method('create');
    }

    public function testGetProductsDataWithCacheMiss(): void
    {
        $productData = [
            [
                'name' => 'Test Product',
                'short_description' => 'Test description',
                'url' => 'https://example.com/test-product',
                'meta_description' => 'Test meta description'
            ]
        ];

        $this->cache->method('load')
            ->with('products_data_1')
            ->willReturn(false);

        $this->productVisibility->method('getVisibleInSiteIds')
            ->willReturn([2, 3, 4]);

        $productCollection = $this->createMock(ProductCollection::class);
        $this->productCollectionFactory->method('create')
            ->willReturn($productCollection);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->addMethods(['getShortDescription', 'getMetaDescription'])
            ->onlyMethods(['getName', 'getProductUrl'])
            ->getMock();
        $product->method('getName')->willReturn('Test Product');
        $product->method('getShortDescription')->willReturn('Test description');
        $product->method('getProductUrl')->willReturn('https://example.com/test-product');
        $product->method('getMetaDescription')->willReturn('Test meta description');

        $productCollection->method('setStoreId')->willReturnSelf();
        $productCollection->method('addAttributeToSelect')->willReturnSelf();
        $productCollection->method('addAttributeToFilter')->willReturnSelf();
        $productCollection->method('setPageSize')->willReturnSelf();
        $productCollection->method('setCurPage')->willReturnSelf();
        $productCollection->method('load')->willReturnSelf();
        $productCollection->method('getCurPage')->willReturn(1);
        $productCollection->method('getLastPageNumber')->willReturn(1);
        $productCollection->method('getIterator')->willReturn(new \ArrayIterator([$product]));

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                '[LLMsFeeder] Loaded products data',
                ['store_id' => 1, 'count' => 1]
            );

        $result = $this->dataProcessor->getProductsData();

        $this->assertEquals($productData, $result);
    }

    public function testGetProductsDataWithException(): void
    {
        $this->cache->method('load')
            ->with('products_data_1')
            ->willReturn(false);

        $this->productCollectionFactory->method('create')
            ->willThrowException(new \Exception('Database error'));

        $this->logger->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->stringContains('[LLMsFeeder] Failed to load products data'),
                $this->arrayHasKey('store_id')
            );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to load products data: Database error');

        $this->dataProcessor->getProductsData();
    }

    public function testGetCompanyDataWithEmptyPages(): void
    {
        $this->cache->method('load')
            ->with('company_data_1')
            ->willReturn(false);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['bcmarketplace_llmsfeeder/settings/site_description', ScopeInterface::SCOPE_STORE, 1, 'Test Company Description'],
                ['bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1, '']
            ]);

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                '[LLMsFeeder] Loaded company data',
                ['store_id' => 1, 'urls_count' => 0]
            );

        $result = $this->dataProcessor->getCompanyData();

        $expected = [
            'description' => 'Test Company Description',
            'urls' => []
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetCompanyDataWithPages(): void
    {
        $this->cache->method('load')
            ->with('company_data_1')
            ->willReturn(false);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['bcmarketplace_llmsfeeder/settings/site_description', ScopeInterface::SCOPE_STORE, 1, 'Test Company Description'],
                ['bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1, '1,2']
            ]);

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page1 = $this->createMock(Page::class);
        $page1->method('getId')->willReturn(1);
        $page1->method('getTitle')->willReturn('About Us');
        $page1->method('getIdentifier')->willReturn('about-us');

        $page2 = $this->createMock(Page::class);
        $page2->method('getId')->willReturn(2);
        $page2->method('getTitle')->willReturn('Contact');
        $page2->method('getIdentifier')->willReturn('contact-us');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page1, $page2]));

        $this->cmsPageHelper->method('getPageUrl')
            ->willReturnMap([
                [1, 'https://example.com/about-us'],
                [2, 'https://example.com/contact-us']
            ]);

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $result = $this->dataProcessor->getCompanyData();

        $expected = [
            'description' => 'Test Company Description',
            'urls' => [
                [
                    'short_description' => 'About Us',
                    'url' => 'https://example.com/about-us',
                    'keywords' => 'about us',
                    'meta_description' => ''
                ],
                [
                    'short_description' => 'Contact',
                    'url' => 'https://example.com/contact-us',
                    'keywords' => 'contact us',
                    'meta_description' => ''
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetCategoriesData(): void
    {
        $this->cache->method('load')
            ->with('categories_data_1')
            ->willReturn(false);

        $categoryCollection = $this->createMock(CategoryCollection::class);
        $this->categoryCollectionFactory->method('create')
            ->willReturn($categoryCollection);

        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->addMethods(['getDescription', 'getMetaDescription'])
            ->onlyMethods(['getName', 'getUrl'])
            ->getMock();
        $category->method('getName')->willReturn('Electronics');
        $category->method('getDescription')->willReturn('Electronic products');
        $category->method('getUrl')->willReturn('https://example.com/electronics');
        $category->method('getMetaDescription')->willReturn('Electronics category meta description');

        $categoryCollection->method('setStoreId')->willReturnSelf();
        $categoryCollection->method('addAttributeToSelect')->willReturnSelf();
        $categoryCollection->method('addAttributeToFilter')->willReturnSelf();
        $categoryCollection->method('setPageSize')->willReturnSelf();
        $categoryCollection->method('setCurPage')->willReturnSelf();
        $categoryCollection->method('load')->willReturnSelf();
        $categoryCollection->method('getCurPage')->willReturn(1);
        $categoryCollection->method('getLastPageNumber')->willReturn(1);
        $categoryCollection->method('getIterator')->willReturn(new \ArrayIterator([$category]));

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $result = $this->dataProcessor->getCategoriesData();

        $expected = [
            [
                'name' => 'Electronics',
                'short_description' => 'Electronic products',
                'url' => 'https://example.com/electronics',
                'meta_description' => 'Electronics category meta description'
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetCmsPagesDataExcludingCompanyPages(): void
    {
        $this->cache->method('load')
            ->with('cms_pages_data_1')
            ->willReturn(false);

        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('1,2');

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page = $this->createMock(Page::class);
        $page->method('getId')->willReturn(3);
        $page->method('getTitle')->willReturn('Home');
        $page->method('getContent')->willReturn('Welcome to our store');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addStoreFilter')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('setPageSize')->willReturnSelf();
        $pageCollection->method('setCurPage')->willReturnSelf();
        $pageCollection->method('load')->willReturnSelf();
        $pageCollection->method('getCurPage')->willReturn(1);
        $pageCollection->method('getLastPageNumber')->willReturn(1);
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page]));

        $this->cmsPageHelper->method('getPageUrl')
            ->with(3)
            ->willReturn('https://example.com/home');

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $result = $this->dataProcessor->getCmsPagesData();

        $expected = [];

        $this->assertEquals($expected, $result);
    }

    public function testGetCmsPagesDataWithCompanyPageExclusion(): void
    {
        $this->cache->method('load')
            ->with('cms_pages_data_1')
            ->willReturn(false);

        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('about-us,contact-us');

        // Mock the company page IDs resolution (first call to pageCollectionFactory)
        $companyPageCollection = $this->createMock(PageCollection::class);
        
        $companyPage1 = $this->createMock(Page::class);
        $companyPage1->method('getId')->willReturn(1);
        $companyPage1->method('getIdentifier')->willReturn('about-us');
        $companyPage1->method('getTitle')->willReturn('About Us');

        $companyPage2 = $this->createMock(Page::class);
        $companyPage2->method('getId')->willReturn(2);
        $companyPage2->method('getIdentifier')->willReturn('contact-us');
        $companyPage2->method('getTitle')->willReturn('Contact Us');

        $companyPageCollection->method('addFieldToSelect')->willReturnSelf();
        $companyPageCollection->method('addStoreFilter')->willReturnSelf();
        $companyPageCollection->method('addFieldToFilter')->willReturnSelf();
        $companyPageCollection->method('getIterator')->willReturn(new \ArrayIterator([$companyPage1, $companyPage2]));

        // Mock the CMS pages collection (second call to pageCollectionFactory)
        $cmsPageCollection = $this->createMock(PageCollection::class);

        $page3 = $this->createMock(Page::class);
        $page3->method('getId')->willReturn(3); // Regular CMS page
        $page3->method('getTitle')->willReturn('Home');
        $page3->method('getContent')->willReturn('Welcome to our store');

        $page4 = $this->createMock(Page::class);
        $page4->method('getId')->willReturn(4); // Regular CMS page
        $page4->method('getTitle')->willReturn('Privacy Policy');
        $page4->method('getContent')->willReturn('Our privacy policy');

        $cmsPageCollection->method('addFieldToSelect')->willReturnSelf();
        $cmsPageCollection->method('addStoreFilter')->willReturnSelf();
        $cmsPageCollection->method('addFieldToFilter')->willReturnSelf();
        $cmsPageCollection->method('setPageSize')->willReturnSelf();
        $cmsPageCollection->method('setCurPage')->willReturnSelf();
        $cmsPageCollection->method('load')->willReturnSelf();
        $cmsPageCollection->method('getCurPage')->willReturn(1);
        $cmsPageCollection->method('getLastPageNumber')->willReturn(1);
        $cmsPageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page3, $page4]));

        // Set up the factory to return different collections based on call context
        $this->pageCollectionFactory->method('create')
            ->willReturnOnConsecutiveCalls($companyPageCollection, $cmsPageCollection);

        $this->cmsPageHelper->method('getPageUrl')
            ->willReturnMap([
                [3, 'https://example.com/home'],
                [4, 'https://example.com/privacy-policy']
            ]);

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $result = $this->dataProcessor->getCmsPagesData();

        // Should only contain non-company pages (3 and 4)
        $expected = [
            [
                'name' => 'Home',
                'short_description' => 'Welcome to our store',
                'url' => 'https://example.com/home',
                'meta_description' => ''
            ],
            [
                'name' => 'Privacy Policy',
                'short_description' => 'Our privacy policy',
                'url' => 'https://example.com/privacy-policy',
                'meta_description' => ''
            ]
        ];

        $this->assertEquals($expected, $result);
        $this->assertCount(2, $result);
        
        // Verify that company pages are not included
        foreach ($result as $page) {
            $this->assertNotContains($page['url'], [
                'https://example.com/about-us',
                'https://example.com/contact'
            ]);
        }
    }

    public function testGetCmsPagesDataWithNoCompanyPages(): void
    {
        $this->cache->method('load')
            ->with('cms_pages_data_1')
            ->willReturn(false);

        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('');

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page = $this->createMock(Page::class);
        $page->method('getId')->willReturn(1);
        $page->method('getTitle')->willReturn('Home');
        $page->method('getContent')->willReturn('Welcome to our store');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addStoreFilter')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('setPageSize')->willReturnSelf();
        $pageCollection->method('setCurPage')->willReturnSelf();
        $pageCollection->method('load')->willReturnSelf();
        $pageCollection->method('getCurPage')->willReturn(1);
        $pageCollection->method('getLastPageNumber')->willReturn(1);
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page]));

        $this->cmsPageHelper->method('getPageUrl')
            ->with(1)
            ->willReturn('https://example.com/home');

        $this->serializer->method('serialize')
            ->willReturn('serialized_data');

        $this->cache->method('save')->willReturn(true);

        $result = $this->dataProcessor->getCmsPagesData();

        $expected = [
            [
                'name' => 'Home',
                'short_description' => 'Welcome to our store',
                'url' => 'https://example.com/home',
                'meta_description' => ''
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetCompanyInfoPageIdsWithValidIds(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('about-us,contact-us,privacy-policy');

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page1 = $this->createMock(Page::class);
        $page1->method('getId')->willReturn(1);
        $page1->method('getIdentifier')->willReturn('about-us');
        $page1->method('getTitle')->willReturn('About Us');

        $page2 = $this->createMock(Page::class);
        $page2->method('getId')->willReturn(2);
        $page2->method('getIdentifier')->willReturn('contact-us');
        $page2->method('getTitle')->willReturn('Contact Us');

        $page3 = $this->createMock(Page::class);
        $page3->method('getId')->willReturn(3);
        $page3->method('getIdentifier')->willReturn('privacy-policy');
        $page3->method('getTitle')->willReturn('Privacy Policy');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addStoreFilter')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page1, $page2, $page3]));

        $result = $this->invokePrivateMethod('getCompanyInfoPageIds', [1]);

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testGetCompanyInfoPageIdsWithInvalidIds(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('about-us,,contact-us,');

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page1 = $this->createMock(Page::class);
        $page1->method('getId')->willReturn(1);
        $page1->method('getIdentifier')->willReturn('about-us');
        $page1->method('getTitle')->willReturn('About Us');

        $page2 = $this->createMock(Page::class);
        $page2->method('getId')->willReturn(2);
        $page2->method('getIdentifier')->willReturn('contact-us');
        $page2->method('getTitle')->willReturn('Contact Us');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addStoreFilter')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page1, $page2]));

        $result = $this->invokePrivateMethod('getCompanyInfoPageIds', [1]);

        $this->assertEquals([1, 2], $result);
    }

    public function testGetCompanyInfoPageIdsWithEmptyValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('');

        $result = $this->invokePrivateMethod('getCompanyInfoPageIds', [1]);

        $this->assertEquals([], $result);
    }

    public function testGetCompanyInfoPageIdsWithWhitespace(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(' about-us , contact-us , privacy-policy ');

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page1 = $this->createMock(Page::class);
        $page1->method('getId')->willReturn(1);
        $page1->method('getIdentifier')->willReturn('about-us');
        $page1->method('getTitle')->willReturn('About Us');

        $page2 = $this->createMock(Page::class);
        $page2->method('getId')->willReturn(2);
        $page2->method('getIdentifier')->willReturn('contact-us');
        $page2->method('getTitle')->willReturn('Contact Us');

        $page3 = $this->createMock(Page::class);
        $page3->method('getId')->willReturn(3);
        $page3->method('getIdentifier')->willReturn('privacy-policy');
        $page3->method('getTitle')->willReturn('Privacy Policy');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addStoreFilter')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page1, $page2, $page3]));

        $result = $this->invokePrivateMethod('getCompanyInfoPageIds', [1]);

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testGetCompanyInfoPageIdsWithNotFoundIdentifiers(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('about-us,non-existent-page,contact-us');

        $pageCollection = $this->createMock(PageCollection::class);
        $this->pageCollectionFactory->method('create')
            ->willReturn($pageCollection);

        $page1 = $this->createMock(Page::class);
        $page1->method('getId')->willReturn(1);
        $page1->method('getIdentifier')->willReturn('about-us');
        $page1->method('getTitle')->willReturn('About Us');

        $page2 = $this->createMock(Page::class);
        $page2->method('getId')->willReturn(2);
        $page2->method('getIdentifier')->willReturn('contact-us');
        $page2->method('getTitle')->willReturn('Contact Us');

        $pageCollection->method('addFieldToSelect')->willReturnSelf();
        $pageCollection->method('addStoreFilter')->willReturnSelf();
        $pageCollection->method('addFieldToFilter')->willReturnSelf();
        $pageCollection->method('getIterator')->willReturn(new \ArrayIterator([$page1, $page2]));

        $result = $this->invokePrivateMethod('getCompanyInfoPageIds', [1]);

        // Should only return page IDs for found identifiers
        $this->assertEquals([1, 2], $result);
    }

    public function testGetCompanyInfoPageIdsWithException(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/company_info_pages', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('about-us,contact-us');

        $this->pageCollectionFactory->method('create')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->invokePrivateMethod('getCompanyInfoPageIds', [1]);

        // Should return empty array on exception
        $this->assertEquals([], $result);
    }

    public function testStripAndTruncateWithShortText(): void
    {
        $result = $this->invokePrivateMethod('stripAndTruncate', ['<p>Short text</p>', 100]);
        $this->assertEquals('Short text', $result);
    }

    public function testStripAndTruncateWithLongText(): void
    {
        $longText = str_repeat('a', 300);
        $result = $this->invokePrivateMethod('stripAndTruncate', [$longText, 100]);
        $this->assertEquals(str_repeat('a', 99) . '…', $result);
    }

    public function testStripAndTruncateWithHtml(): void
    {
        $result = $this->invokePrivateMethod('stripAndTruncate', ['<p>Text with <strong>HTML</strong> tags</p>', 100]);
        $this->assertEquals('Text with HTML tags', $result);
    }

    public function testStripAndTruncateWithNull(): void
    {
        $result = $this->invokePrivateMethod('stripAndTruncate', [null, 100]);
        $this->assertEquals('', $result);
    }

    public function testExtractKeywordsFromIdentifier(): void
    {
        $result = $this->invokePrivateMethod('extractKeywordsFromIdentifier', ['about-us-page']);
        $this->assertEquals('about us page', $result);
    }

    public function testExtractKeywordsFromIdentifierWithNull(): void
    {
        $result = $this->invokePrivateMethod('extractKeywordsFromIdentifier', [null]);
        $this->assertEquals('', $result);
    }

    public function testSanitizeString(): void
    {
        $result = $this->invokePrivateMethod('sanitizeString', ['test']);
        $this->assertEquals('test', $result);
    }

    public function testSanitizeStringWithNull(): void
    {
        $result = $this->invokePrivateMethod('sanitizeString', [null]);
        $this->assertEquals('', $result);
    }

    public function testClearCache(): void
    {
        $this->cache->expects($this->once())
            ->method('clean')
            ->with(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['llms_feeder']);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('[LLMsFeeder] Cache cleared successfully');

        $this->dataProcessor->clearCache();
    }

    public function testClearCacheWithException(): void
    {
        $this->cache->method('clean')
            ->willThrowException(new \Exception('Cache error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('[LLMsFeeder] Failed to clear cache', ['error' => 'Cache error']);

        $this->dataProcessor->clearCache();
    }

    /**
     * Invoke private method for testing
     */
    private function invokePrivateMethod(string $methodName, array $parameters)
    {
        $reflection = new \ReflectionClass($this->dataProcessor);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->dataProcessor, $parameters);
    }
}
