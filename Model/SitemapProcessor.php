<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Sitemap\Model\SitemapFactory;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class SitemapProcessor
{
    private Curl $curl;
    private LoggerInterface $logger;
    private SitemapFactory $sitemapFactory;
    private Filesystem $filesystem;
    private DirectoryList $directoryList;

    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        SitemapFactory $sitemapFactory,
        Filesystem $filesystem,
        DirectoryList $directoryList
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->sitemapFactory = $sitemapFactory;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
    }

    /**
     * Check if a sitemap exists for the given store
     */
    public function sitemapExists(StoreInterface $store): bool
    {
        $sitemapData = $this->getSitemapData($store);
        if (!$sitemapData) {
            return false;
        }

        $sitemapPath = $this->getSitemapFilePath($sitemapData);

        try {
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
            $exists = $directory->isExist($sitemapPath);


            return $exists;
        } catch (\Throwable $e) {
            $this->logger->warning('[LLMsGenerator] Sitemap check failed', [
                'path' => $sitemapPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract company links from sitemap
     *
     * @return array<int, array{short_description:string,url:string,keywords:string}>
     */
    public function getCompanyLinksFromSitemap(StoreInterface $store): array
    {
        $sitemapData = $this->getSitemapData($store);
        if (!$sitemapData) {
            return [];
        }

        $sitemapPath = $this->getSitemapFilePath($sitemapData);

        try {
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::ROOT);
            if (!$directory->isExist($sitemapPath)) {
                return [];
            }

            $content = $directory->readFile($sitemapPath);
            return $this->parseSitemapContent($content, $store);
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsGenerator] Failed to parse sitemap', [
                'path' => $sitemapPath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get sitemap data from database for the given store
     */
    private function getSitemapData(StoreInterface $store): ?array
    {
        try {
            $sitemap = $this->sitemapFactory->create();
            $sitemap->load($store->getId(), 'store_id');

            if (!$sitemap->getId()) {
                return null;
            }

            return [
                'sitemap_path' => $sitemap->getSitemapPath(),
                'sitemap_filename' => $sitemap->getSitemapFilename()
            ];
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsGenerator] Failed to load sitemap data', [
                'store_id' => $store->getId(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get the full sitemap file path
     */
    private function getSitemapFilePath(array $sitemapData): string
    {
        $sitemapPath = $sitemapData['sitemap_path'] ?? '';
        $sitemapFilename = $sitemapData['sitemap_filename'] ?? '';

        // Remove leading slash if present
        $sitemapPath = ltrim($sitemapPath, '/');

        // Combine path and filename
        $relativePath = $sitemapFilename;
        if (!empty($sitemapPath) && !empty($sitemapFilename)) {
            $relativePath = rtrim($sitemapPath, '/') . '/' . $sitemapFilename;
        } elseif (!empty($sitemapPath)) {
            $relativePath = rtrim($sitemapPath, '/');
        }

        // Get absolute base path to 'pub' directory
        $pubPath = $this->directoryList->getPath(DirectoryList::PUB);

        // Return full absolute path
        return rtrim($pubPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
    }

    /**
     * Parse sitemap XML content to extract company-related links
     *
     * @return array<int, array{short_description:string,url:string,keywords:string}>
     */
    private function parseSitemapContent(string $content, StoreInterface $store): array
    {
        $links = [];

        try {
            $xml = new \SimpleXMLElement($content);

            // Handle both sitemap index and regular sitemap
            if (isset($xml->sitemap)) {
                // This is a sitemap index, get the first sitemap
                $firstSitemap = $xml->sitemap[0]->loc ?? null;
                if ($firstSitemap) {
                    return $this->parseSitemapContent($this->fetchSitemapContent((string) $firstSitemap), $store);
                }
            }

            // Parse URLs from sitemap
            if (isset($xml->url)) {
                foreach ($xml->url as $urlElement) {
                    $url = (string) ($urlElement->loc ?? '');
                    if (empty($url)) {
                        continue;
                    }

                    // Check if this looks like a company-related page
                    if ($this->isCompanyPage($url)) {
                        $links[] = [
                            'short_description' => $this->extractPageTitle($url),
                            'url' => $url,
                            'keywords' => $this->extractKeywords($url)
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[LLMsGenerator] XML parsing failed', [
                'error' => $e->getMessage()
            ]);
        }

        return $links;
    }

    /**
     * Fetch content from a sitemap URL
     */
    private function fetchSitemapContent(string $url): string
    {
        $this->curl->setTimeout(30);
        $this->curl->get($url);
        return (string) $this->curl->getBody();
    }

    /**
     * Check if a URL looks like a company-related page
     */
    private function isCompanyPage(string $url): bool
    {
        $companyKeywords = [
            'about', 'about-us', 'aboutus',
            'contact', 'contact-us', 'contactus',
            'company', 'corporate',
            'team', 'leadership',
            'careers', 'jobs',
            'press', 'news', 'media',
            'privacy', 'terms', 'legal'
        ];

        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            return false;
        }

        $path = strtolower(trim($path, '/'));

        foreach ($companyKeywords as $keyword) {
            if (str_contains($path, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a readable title from the URL
     */
    private function extractPageTitle(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            return 'Company Page';
        }

        $path = trim($path, '/');
        $segments = explode('/', $path);
        $lastSegment = end($segments);

        if (empty($lastSegment)) {
            return 'Company Page';
        }

        // Convert URL-friendly format to readable title
        $title = str_replace(['-', '_'], ' ', $lastSegment);
        $title = ucwords($title);

        return $title;
    }

    /**
     * Extract keywords from the URL
     */
    private function extractKeywords(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            return '';
        }

        $path = trim($path, '/');
        $segments = explode('/', $path);

        // Use the last two segments as keywords
        $keywords = array_slice($segments, -2);
        $keywords = array_filter($keywords, static function ($segment) {
            return !empty($segment) && !is_numeric($segment);
        });

        return implode(' ', $keywords);
    }
}
