<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Model;

use BCMarketplace\LLMsFeeder\Model\SitemapProcessor;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Sitemap\Model\SitemapFactory;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SitemapProcessorTest extends TestCase
{
    /**
     * Curl mock
     *
     * @var Curl
     */
    private Curl $curl;

    /**
     * Logger mock
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Sitemap factory mock
     *
     * @var SitemapFactory
     */
    private SitemapFactory $sitemapFactory;

    /**
     * Filesystem mock
     *
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * Directory list mock
     *
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * Sitemap processor instance
     *
     * @var SitemapProcessor
     */
    private SitemapProcessor $sitemapProcessor;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sitemapFactory = $this->createMock(SitemapFactory::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->directoryList = $this->createMock(DirectoryList::class);

        $this->sitemapProcessor = new SitemapProcessor(
            $this->curl,
            $this->logger,
            $this->sitemapFactory,
            $this->filesystem,
            $this->directoryList
        );
    }

    public function testGetCompanyLinksFromSitemapWithValidSitemap(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $sitemap = $this->getMockBuilder(\Magento\Sitemap\Model\Sitemap::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'load'])
            ->addMethods(['getSitemapPath', 'getSitemapFilename'])
            ->getMock();
        $sitemap->method('getId')->willReturn(1);
        $sitemap->method('getSitemapPath')->willReturn('sitemap');
        $sitemap->method('getSitemapFilename')->willReturn('sitemap.xml');

        $this->sitemapFactory->method('create')->willReturn($sitemap);
        $sitemap->method('load')->with(1, 'store_id')->willReturnSelf();

        $directory = $this->createMock(\Magento\Framework\Filesystem\Directory\ReadInterface::class);
        $directory->method('isExist')->willReturn(true);
        $directory->method('readFile')->willReturn('<?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url>
                    <loc>https://example.com/about-us</loc>
                    <lastmod>2023-01-01</lastmod>
                </url>
                <url>
                    <loc>https://example.com/contact-us</loc>
                    <lastmod>2023-01-01</lastmod>
                </url>
            </urlset>');

        $this->filesystem->method('getDirectoryRead')
            ->with(DirectoryList::ROOT)
            ->willReturn($directory);

        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        $result = $this->sitemapProcessor->getCompanyLinksFromSitemap($store);

        $this->assertIsArray($result);
    }

    public function testGetCompanyLinksFromSitemapWithNoSitemap(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $sitemap = $this->getMockBuilder(\Magento\Sitemap\Model\Sitemap::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'load'])
            ->addMethods(['getSitemapPath', 'getSitemapFilename'])
            ->getMock();
        $sitemap->method('getId')->willReturn(null);

        $this->sitemapFactory->method('create')->willReturn($sitemap);
        $sitemap->method('load')->with(1, 'store_id')->willReturnSelf();

        $result = $this->sitemapProcessor->getCompanyLinksFromSitemap($store);

        $this->assertEquals([], $result);
    }

    public function testSitemapExists(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);

        $sitemap = $this->getMockBuilder(\Magento\Sitemap\Model\Sitemap::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'load'])
            ->addMethods(['getSitemapPath', 'getSitemapFilename'])
            ->getMock();
        $sitemap->method('getId')->willReturn(1);
        $sitemap->method('getSitemapPath')->willReturn('sitemap');
        $sitemap->method('getSitemapFilename')->willReturn('sitemap.xml');

        $this->sitemapFactory->method('create')->willReturn($sitemap);
        $sitemap->method('load')->with(1, 'store_id')->willReturnSelf();

        $directory = $this->createMock(\Magento\Framework\Filesystem\Directory\ReadInterface::class);
        $directory->method('isExist')->willReturn(true);

        $this->filesystem->method('getDirectoryRead')
            ->with(DirectoryList::ROOT)
            ->willReturn($directory);

        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        $result = $this->sitemapProcessor->sitemapExists($store);

        $this->assertTrue($result);
    }
}
