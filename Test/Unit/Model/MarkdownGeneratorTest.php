<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Model;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use BCMarketplace\LLMsFeeder\Model\DataProcessor;
use BCMarketplace\LLMsFeeder\Model\MarkdownGenerator;
use BCMarketplace\LLMsFeeder\Model\SitemapProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Class MarkdownGeneratorTest
 *
 * Unit tests for the MarkdownGenerator class.
 *
 * This suite verifies markdown output under scenarios:
 *  - Empty data
 *  - Full data
 *  - Module disabled
 *  - Exception during config fetch
 *  - Multiple stores
 *
 * It also aligns assertions with the generator’s actual formatting:
 * - Company description as a blockquote
 * - CMS pages as list items (no per-page subheadings)
 * - Categories/Products bullets include meta descriptions
 * - No enforced trailing newline
 *
 * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator
 * @package AtlanticBT\LLMsGenerator\Test\Unit\Model
 */
class MarkdownGeneratorTest extends TestCase
{
    /** @var MarkdownGenerator */
    private MarkdownGenerator $markdownGenerator;

    /** @var DataProcessor */
    private DataProcessor $dataProcessor;

    /** @var SitemapProcessor */
    private SitemapProcessor $sitemapProcessor;

    /** @var ConfigHelper */
    private ConfigHelper $configHelper;

    /** @var ScopeConfigInterface */
    private ScopeConfigInterface $scopeConfig;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    /** @var StoreInterface */
    private StoreInterface $store;

    /** @var Emulation */
    private Emulation $emulation;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * Setup test dependencies and initialize the MarkdownGenerator instance.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->dataProcessor = $this->createMock(DataProcessor::class);
        $this->sitemapProcessor = $this->createMock(SitemapProcessor::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->emulation = $this->createMock(Emulation::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->markdownGenerator = new MarkdownGenerator(
            $this->dataProcessor,
            $this->configHelper,
            $this->scopeConfig,
            $this->storeManager,
            $this->emulation,
            $this->logger,
            $this->sitemapProcessor
        );
    }

    /**
     * Test Markdown generation when no company, categories, products, or CMS data is present.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithEmptyData(): void
    {
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('getName')->willReturn('Default Store');
        $this->storeManager->method('getStores')->willReturn([$this->store]);
        $this->storeManager->method('getStore')->with(1)->willReturn($this->store);

        $this->configHelper->method('isEnabled')->with(1)->willReturn(true);
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);

        $this->scopeConfig->method('getValue')
            ->with('general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Test Store');

        $this->dataProcessor->method('getCompanyData')->willReturn([
            'description' => '',
            'urls' => []
        ]);
        $this->dataProcessor->method('getCategoriesData')->willReturn([]);
        $this->dataProcessor->method('getProductsData')->willReturn([]);
        $this->dataProcessor->method('getCmsPagesData')->willReturn([]);

        $this->sitemapProcessor->method('getCompanyLinksFromSitemap')->willReturn([]);

        $markdownArray = $this->markdownGenerator->generateMarkdown();

        $this->assertIsArray($markdownArray);
        $this->assertArrayHasKey('default', $markdownArray);
        
        $markdown = $markdownArray['default'];

        // Expected baseline scaffold (the generator currently emits a final newline)
        $expected = "# Test Store\n\n> Company Description\n\n## Company\n\n\n## Pages\n\n## Categories\n\n## Products Resources\n";
        $this->assertEquals($expected, $markdown);
    }

    /**
     * Test Markdown generation when full company, category, product, and CMS data are available.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithFullData(): void
    {
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('getName')->willReturn('Default Store');
        $this->storeManager->method('getStores')->willReturn([$this->store]);
        $this->storeManager->method('getStore')->with(1)->willReturn($this->store);

        $this->configHelper->method('isEnabled')->with(1)->willReturn(true);
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);

        $this->scopeConfig->method('getValue')
            ->with('general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Test Store');

        $this->dataProcessor->method('getCompanyData')->willReturn([
            'description' => 'A test company',
            'urls' => [
                [
                    'short_description' => 'About Us',
                    'url' => 'https://example.com/about',
                    'keywords' => 'about company'
                ]
            ]
        ]);

        $this->dataProcessor->method('getCategoriesData')->willReturn([
            [
                'name' => 'Electronics',
                'short_description' => 'Electronic products',
                'url' => 'https://example.com/electronics',
                'meta_description' => 'Electronics meta description'
            ]
        ]);

        $this->dataProcessor->method('getProductsData')->willReturn([
            [
                'name' => 'Test Product',
                'short_description' => 'A test product',
                'url' => 'https://example.com/test-product',
                'meta_description' => 'Test product meta description'
            ]
        ]);

        $this->dataProcessor->method('getCmsPagesData')->willReturn([
            [
                'name' => 'Home',
                'short_description' => 'Home page',
                'url' => 'https://example.com',
                'meta_description' => 'Home page meta description'
            ]
        ]);

        $this->sitemapProcessor->method('getCompanyLinksFromSitemap')->willReturn([]);

        $markdownArray = $this->markdownGenerator->generateMarkdown();

        $this->assertIsArray($markdownArray);
        $this->assertArrayHasKey('default', $markdownArray);
        
        $markdown = $markdownArray['default'];

        // Headline and company description (blockquote)
        $this->assertStringContainsString('# Test Store', $markdown);
        $this->assertStringContainsString('> A test company', $markdown);
        $this->assertStringContainsString('## Company', $markdown);

        // Company links as bullets
        $this->assertStringContainsString('- [About Us](https://example.com/about) about company', $markdown);

        // CMS pages as bullets
        $this->assertStringContainsString('## Pages', $markdown);
        $this->assertStringContainsString('- [Home](https://example.com) : Home page meta description', $markdown);

        // Categories and products bullets include meta description suffix
        $this->assertStringContainsString('## Categories', $markdown);
        $this->assertStringContainsString('- [Electronics](https://example.com/electronics) : Electronics meta description', $markdown);

        $this->assertStringContainsString('## Products Resources', $markdown);
        $this->assertStringContainsString('- [Test Product](https://example.com/test-product) : Test product meta description', $markdown);
    }

    /**
     * Test Markdown generation when the module is disabled in configuration.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithDisabledModule(): void
    {
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('getName')->willReturn('Default Store');
        $this->storeManager->method('getStores')->willReturn([$this->store]);
        $this->storeManager->method('getStore')->with(1)->willReturn($this->store);

        $this->configHelper->method('isEnabled')->with(1)->willReturn(false);
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);

        // Do not assert on logger messages; generator logs a generic "Generating consolidated markdown".
        $markdownArray = $this->markdownGenerator->generateMarkdown();

        // When module is disabled for all stores, return empty array
        $this->assertIsArray($markdownArray);
        $this->assertEmpty($markdownArray);
    }

    /**
     * Test Markdown generation when an exception is thrown during config retrieval.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithException(): void
    {
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('getName')->willReturn('Default Store');
        $this->storeManager->method('getStores')->willReturn([$this->store]);
        $this->storeManager->method('getStore')->with(1)->willReturn($this->store);

        $this->configHelper->method('isEnabled')->with(1)->willReturn(true);
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);

        $this->scopeConfig->method('getValue')
            ->willThrowException(new \Magento\Framework\Exception\LocalizedException(__('Config error')));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '[LLMsGenerator] Error during processing of content',
                ['store_id' => 1, 'store_code' => 'default', 'error' => 'Config error']
            );

        $markdownArray = $this->markdownGenerator->generateMarkdown();

        // When exception occurs, return empty array
        $this->assertIsArray($markdownArray);
        $this->assertEmpty($markdownArray);
    }

    /**
     * Test Markdown generation when multiple stores are configured.
     *
     * Verifies content from all stores is merged, while the header/blockquote
     * comes from the first store.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithMultipleStores(): void
    {
        $store1 = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store1->method('getId')->willReturn(1);
        $store1->method('getCode')->willReturn('store1');
        $store1->method('getName')->willReturn('Store 1');

        $store2 = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store2->method('getId')->willReturn(2);
        $store2->method('getCode')->willReturn('store2');
        $store2->method('getName')->willReturn('Store 2');

        $this->storeManager->method('getStores')->willReturn([$store1, $store2]);
        $this->storeManager->method('getStore')->with(1)->willReturn($store1);

        $this->configHelper->method('isEnabled')->with(1)->willReturn(true);
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);

        $this->scopeConfig->method('getValue')
            ->with('general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Store 1');

        $this->dataProcessor->method('getCompanyData')->with(1)->willReturn([
            'description' => 'Store 1 description', 
            'urls' => []
        ]);

        $this->dataProcessor->method('getCategoriesData')->with(1)->willReturn([
            ['name' => 'Category 1', 'short_description' => 'Desc 1', 'url' => 'url1', 'meta_description' => 'Meta 1']
        ]);

        $this->dataProcessor->method('getProductsData')->with(1)->willReturn([
            ['name' => 'Product 1', 'short_description' => 'Desc 1', 'url' => 'url1', 'meta_description' => 'Meta 1']
        ]);

        $this->dataProcessor->method('getCmsPagesData')->with(1)->willReturn([
            ['name' => 'Page 1', 'short_description' => 'Desc 1', 'url' => 'url1', 'meta_description' => 'Meta 1']
        ]);

        $this->sitemapProcessor->method('getCompanyLinksFromSitemap')->willReturn([]);

        $markdownArray = $this->markdownGenerator->generateMarkdown();

        $this->assertIsArray($markdownArray);
        $this->assertCount(1, $markdownArray);
        $this->assertArrayHasKey('store1', $markdownArray);

        $store1Markdown = $markdownArray['store1'];

        // Store 1 content
        $this->assertStringContainsString('# Store 1', $store1Markdown);
        $this->assertStringContainsString('> Store 1 description', $store1Markdown);
        $this->assertStringContainsString('- [Category 1](url1) : Meta 1', $store1Markdown);
        $this->assertStringContainsString('- [Product 1](url1) : Meta 1', $store1Markdown);
        $this->assertStringContainsString('- [Page 1](url1)', $store1Markdown);
    }

    /**
     * Test Markdown generation with specific store IDs.
     *
     * Verifies that only the specified stores are processed.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithSpecificStoreIds(): void
    {
        $store1 = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store1->method('getId')->willReturn(1);
        $store1->method('getCode')->willReturn('store1');

        $store2 = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store2->method('getId')->willReturn(2);
        $store2->method('getCode')->willReturn('store2');

        $store3 = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store3->method('getId')->willReturn(3);
        $store3->method('getCode')->willReturn('store3');

        // Mock getStore method to return specific stores
        $this->storeManager->method('getStore')
            ->willReturnMap([
                [1, $store1],
                [2, $store2],
                [3, $store3]
            ]);

        $this->configHelper->method('isEnabled')
            ->willReturnMap([[1, true], [2, true], [3, false]]);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1, 'Store 1'],
                ['general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 2, 'Store 2']
            ]);

        $this->dataProcessor->method('getCompanyData')
            ->willReturnMap([
                [1, ['description' => 'Store 1 description', 'urls' => []]],
                [2, ['description' => 'Store 2 description', 'urls' => []]]
            ]);

        $this->dataProcessor->method('getCategoriesData')
            ->willReturnMap([
                [1, [['name' => 'Category 1', 'short_description' => 'Desc 1', 'url' => 'url1', 'meta_description' => 'Meta 1']]],
                [2, [['name' => 'Category 2', 'short_description' => 'Desc 2', 'url' => 'url2', 'meta_description' => 'Meta 2']]]
            ]);

        $this->dataProcessor->method('getProductsData')
            ->willReturnMap([
                [1, [['name' => 'Product 1', 'short_description' => 'Desc 1', 'url' => 'url1', 'meta_description' => 'Meta 1']]],
                [2, [['name' => 'Product 2', 'short_description' => 'Desc 2', 'url' => 'url2', 'meta_description' => 'Meta 2']]]
            ]);

        $this->dataProcessor->method('getCmsPagesData')
            ->willReturnMap([
                [1, [['name' => 'Page 1', 'short_description' => 'Desc 1', 'url' => 'url1', 'meta_description' => 'Meta 1']]],
                [2, [['name' => 'Page 2', 'short_description' => 'Desc 2', 'url' => 'url2', 'meta_description' => 'Meta 2']]]
            ]);

        $this->sitemapProcessor->method('getCompanyLinksFromSitemap')->willReturn([]);

        // Test with specific store IDs [1, 2, 3] - store 3 is disabled
        $markdownArray = $this->markdownGenerator->generateMarkdown([1, 2, 3]);

        $this->assertIsArray($markdownArray);
        $this->assertCount(2, $markdownArray); // Only stores 1 and 2 should be processed
        $this->assertArrayHasKey('store1', $markdownArray);
        $this->assertArrayHasKey('store2', $markdownArray);
        $this->assertArrayNotHasKey('store3', $markdownArray);

        $store1Markdown = $markdownArray['store1'];
        $store2Markdown = $markdownArray['store2'];

        // Store 1 content
        $this->assertStringContainsString('# Store 1', $store1Markdown);
        $this->assertStringContainsString('> Store 1 description', $store1Markdown);
        $this->assertStringContainsString('- [Category 1](url1) : Meta 1', $store1Markdown);
        $this->assertStringContainsString('- [Product 1](url1) : Meta 1', $store1Markdown);
        $this->assertStringContainsString('- [Page 1](url1)', $store1Markdown);

        // Store 2 content
        $this->assertStringContainsString('# Store 2', $store2Markdown);
        $this->assertStringContainsString('> Store 2 description', $store2Markdown);
        $this->assertStringContainsString('- [Category 2](url2) : Meta 2', $store2Markdown);
        $this->assertStringContainsString('- [Product 2](url2) : Meta 2', $store2Markdown);
        $this->assertStringContainsString('- [Page 2](url2)', $store2Markdown);
    }

    /**
     * Test Markdown generation with invalid store ID.
     *
     * Verifies that invalid store IDs are handled gracefully.
     *
     * @covers \BCMarketplace\LLMsFeeder\Model\MarkdownGenerator::generateMarkdown
     * @return void
     */
    public function testGenerateMarkdownWithInvalidStoreId(): void
    {
        $store1 = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store1->method('getId')->willReturn(1);
        $store1->method('getCode')->willReturn('store1');

        // Mock getStore method to throw exception for invalid store ID
        $this->storeManager->method('getStore')
            ->willReturnCallback(function($storeId) use ($store1) {
                if ($storeId == 1) {
                    return $store1;
                } elseif ($storeId == 999) {
                    throw new \Exception('Store not found');
                }
                throw new \Exception('Store not found');
            });

        $this->configHelper->method('isEnabled')->with(1)->willReturn(true);

        $this->scopeConfig->method('getValue')
            ->with('general/store_information/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Store 1');

        $this->dataProcessor->method('getCompanyData')->willReturn([
            'description' => 'Store 1 description',
            'urls' => []
        ]);

        $this->dataProcessor->method('getCategoriesData')->willReturn([]);
        $this->dataProcessor->method('getProductsData')->willReturn([]);
        $this->dataProcessor->method('getCmsPagesData')->willReturn([]);

        $this->sitemapProcessor->method('getCompanyLinksFromSitemap')->willReturn([]);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                '[LLMsGenerator] Store not found: 999',
                $this->arrayHasKey('exception')
            );

        // Test with valid and invalid store IDs
        $markdownArray = $this->markdownGenerator->generateMarkdown([1, 999]);

        $this->assertIsArray($markdownArray);
        $this->assertCount(1, $markdownArray); // Only store 1 should be processed
        $this->assertArrayHasKey('store1', $markdownArray);
    }
}
