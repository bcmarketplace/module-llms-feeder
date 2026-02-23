<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Integration;

use BCMarketplace\LLMsFeeder\Model\DataProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for DataProcessor company page exclusion
 */
class DataProcessorIntegrationTest extends TestCase
{
    /**
     * Data processor instance for testing
     *
     * @var DataProcessor
     */
    private DataProcessor $dataProcessor;

    protected function setUp(): void
    {
        // Skip integration tests in unit test environment
        $this->markTestSkipped('Integration tests require full Magento environment');
    }

    /**
     * Test that company pages are excluded from CMS pages data
     *
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     */
    public function testCompanyPagesExcludedFromCmsPages(): void
    {
        // Get company data to see what pages are configured as company pages
        $companyData = $this->dataProcessor->getCompanyData();
        $companyPageUrls = array_column($companyData['urls'], 'url');
        
        // Get CMS pages data
        $cmsPagesData = $this->dataProcessor->getCmsPagesData();
        $cmsPageUrls = array_column($cmsPagesData, 'url');
        
        // Verify that no company page URLs appear in CMS pages data
        foreach ($companyPageUrls as $companyUrl) {
            $this->assertNotContains(
                $companyUrl,
                $cmsPageUrls,
                sprintf('Company page URL "%s" should not appear in CMS pages data', $companyUrl)
            );
        }
        
        // Log the results for debugging
        if (!empty($companyPageUrls)) {
            fwrite(STDERR, "\nCompany page URLs: " . implode(', ', $companyPageUrls));
        }
        if (!empty($cmsPageUrls)) {
            fwrite(STDERR, "\nCMS page URLs: " . implode(', ', $cmsPageUrls));
        }
    }

    /**
     * Test that the exclusion works when no company pages are configured
     *
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     */
    public function testCmsPagesDataWithoutCompanyPages(): void
    {
        // This test verifies that CMS pages data can be retrieved even when no company pages are configured
        $cmsPagesData = $this->dataProcessor->getCmsPagesData();
        
        // Should not throw an exception and should return an array
        $this->assertIsArray($cmsPagesData);
        
        // If there are CMS pages, they should have the expected structure
        if (!empty($cmsPagesData)) {
            $firstPage = $cmsPagesData[0];
            $this->assertArrayHasKey('name', $firstPage);
            $this->assertArrayHasKey('short_description', $firstPage);
            $this->assertArrayHasKey('url', $firstPage);
        }
    }

    /**
     * Test that company data can be retrieved
     *
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     */
    public function testCompanyDataRetrieval(): void
    {
        $companyData = $this->dataProcessor->getCompanyData();
        
        $this->assertIsArray($companyData);
        $this->assertArrayHasKey('description', $companyData);
        $this->assertArrayHasKey('urls', $companyData);
        $this->assertIsArray($companyData['urls']);
        
        // If there are company URLs, they should have the expected structure
        if (!empty($companyData['urls'])) {
            $firstUrl = $companyData['urls'][0];
            $this->assertArrayHasKey('short_description', $firstUrl);
            $this->assertArrayHasKey('url', $firstUrl);
            $this->assertArrayHasKey('keywords', $firstUrl);
        }
    }

    /**
     * Test that identifier-based company page resolution works correctly
     *
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     */
    public function testIdentifierBasedCompanyPageResolution(): void
    {
        // This test verifies that the system can resolve company page IDs from identifiers
        // and that the exclusion logic works with the resolved page IDs
        
        // Get company data to see what pages are configured
        $companyData = $this->dataProcessor->getCompanyData();
        $companyPageUrls = array_column($companyData['urls'], 'url');
        
        // Get CMS pages data
        $cmsPagesData = $this->dataProcessor->getCmsPagesData();
        $cmsPageUrls = array_column($cmsPagesData, 'url');
        
        // Verify that no company page URLs appear in CMS pages data
        foreach ($companyPageUrls as $companyUrl) {
            $this->assertNotContains(
                $companyUrl,
                $cmsPageUrls,
                sprintf('Company page URL "%s" should not appear in CMS pages data', $companyUrl)
            );
        }
        
        // Log the results for debugging
        if (!empty($companyPageUrls)) {
            fwrite(STDERR, "\nCompany page URLs (resolved from identifiers): " . implode(', ', $companyPageUrls));
        }
        if (!empty($cmsPageUrls)) {
            fwrite(STDERR, "\nCMS page URLs (excluding company pages): " . implode(', ', $cmsPageUrls));
        }
        
        // Verify that the system handles the identifier resolution gracefully
        $this->assertIsArray($companyData);
        $this->assertIsArray($cmsPagesData);
    }

    /**
     * Test that all data methods work together without conflicts
     *
     * @magentoAppArea adminhtml
     * @magentoDbIsolation enabled
     */
    public function testAllDataMethodsWorkTogether(): void
    {
        // Test that all data methods can be called without conflicts
        $companyData = $this->dataProcessor->getCompanyData();
        $cmsPagesData = $this->dataProcessor->getCmsPagesData();
        $categoriesData = $this->dataProcessor->getCategoriesData();
        $productsData = $this->dataProcessor->getProductsData();
        
        // All should return arrays
        $this->assertIsArray($companyData);
        $this->assertIsArray($cmsPagesData);
        $this->assertIsArray($categoriesData);
        $this->assertIsArray($productsData);
        
        // Verify that company URLs don't appear in CMS pages
        $companyPageUrls = array_column($companyData['urls'], 'url');
        $cmsPageUrls = array_column($cmsPagesData, 'url');
        
        foreach ($companyPageUrls as $companyUrl) {
            $this->assertNotContains(
                $companyUrl,
                $cmsPageUrls,
                'Company page URLs should not appear in CMS pages data'
            );
        }
    }
}
