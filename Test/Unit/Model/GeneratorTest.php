<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Model;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use BCMarketplace\LLMsFeeder\Model\DataProcessor;
use BCMarketplace\LLMsFeeder\Model\Generator;
use BCMarketplace\LLMsFeeder\Model\MarkdownGenerator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeneratorTest extends TestCase
{
    /**
     * Configuration helper mock
     *
     * @var ConfigHelper
     */
    private ConfigHelper $configHelper;

    /**
     * Data processor mock
     *
     * @var DataProcessor
     */
    private DataProcessor $dataProcessor;

    /**
     * Markdown generator mock
     *
     * @var MarkdownGenerator
     */
    private MarkdownGenerator $markdownGenerator;

    /**
     * Store manager mock
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Emulation mock
     *
     * @var Emulation
     */
    private Emulation $emulation;

    /**
     * Directory list mock
     *
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * File I/O mock
     *
     * @var IoFile
     */
    private IoFile $ioFile;

    /**
     * Logger mock
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Generator instance
     *
     * @var Generator
     */
    private Generator $generator;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->dataProcessor = $this->createMock(DataProcessor::class);
        $this->markdownGenerator = $this->createMock(MarkdownGenerator::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->emulation = $this->createMock(Emulation::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->ioFile = $this->createMock(IoFile::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->generator = new Generator(
            $this->configHelper,
            $this->dataProcessor,
            $this->markdownGenerator,
            $this->storeManager,
            $this->emulation,
            $this->directoryList,
            $this->ioFile,
            $this->logger
        );
    }

    public function testExecuteWithModuleEnabled(): void
    {
        $this->configHelper->method('isEnabled')->willReturn(true);
        
        $this->markdownGenerator->method('generateMarkdown')
            ->willReturn(['default' => '# Test Store']);
        
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');
        
        $this->ioFile->method('mkdir')
            ->willReturn(true);
        
        $this->ioFile->expects($this->once())
            ->method('write')
            ->with('/var/www/html/pub/llms/default/llms.txt', '# Test Store')
            ->willReturn(true);
        
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function($message) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertStringContainsString('[LLMsGenerator] Saved LLMs content for store: default', $message);
                } elseif ($callCount === 2) {
                    $this->assertStringContainsString('[LLMsGenerator] Generation complete and files saved for 1 stores.', $message);
                }
            });

        $this->generator->execute();
    }

    public function testExecuteWithSpecificStoreIds(): void
    {
        $this->configHelper->method('isEnabled')->willReturn(true);
        
        $storeIds = [1, 2];
        $expectedStoreContents = [
            'store1' => '# Store 1 Content',
            'store2' => '# Store 2 Content'
        ];
        
        $this->markdownGenerator->expects($this->once())
            ->method('generateMarkdown')
            ->with($storeIds)
            ->willReturn($expectedStoreContents);
        
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');
        
        $this->ioFile->method('mkdir')
            ->willReturn(true);
        
        $this->ioFile->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(function($path, $content) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals('/var/www/html/pub/llms/store1/llms.txt', $path);
                    $this->assertEquals('# Store 1 Content', $content);
                } elseif ($callCount === 2) {
                    $this->assertEquals('/var/www/html/pub/llms/store2/llms.txt', $path);
                    $this->assertEquals('# Store 2 Content', $content);
                }
                return true;
            });
        
        $this->logger->expects($this->exactly(3))
            ->method('info')
            ->willReturnCallback(function($message) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertStringContainsString('[LLMsGenerator] Saved LLMs content for store: store1', $message);
                } elseif ($callCount === 2) {
                    $this->assertStringContainsString('[LLMsGenerator] Saved LLMs content for store: store2', $message);
                } elseif ($callCount === 3) {
                    $this->assertStringContainsString('[LLMsGenerator] Generation complete and files saved for 2 stores.', $message);
                }
            });

        $this->generator->execute($storeIds);
    }

    public function testExecuteWithModuleDisabled(): void
    {
        $this->configHelper->method('isEnabled')->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LLMsGenerator Module is disabled. Skipping generation.');

        $this->generator->execute();
    }

    public function testExecuteWithGenerationException(): void
    {
        $this->configHelper->method('isEnabled')->willReturn(true);
        
        $this->markdownGenerator->method('generateMarkdown')
            ->willThrowException(new \Exception('Generation failed'));
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('[LLMsGenerator] Generation failed: Generation failed'),
                $this->arrayHasKey('exception')
            );

        $this->generator->execute();
    }

    public function testExecuteWithFileWriteException(): void
    {
        $this->configHelper->method('isEnabled')->willReturn(true);
        
        $this->markdownGenerator->method('generateMarkdown')
            ->willReturn(['default' => '# Test Store']);
        
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');
        
        $this->ioFile->method('write')
            ->willThrowException(new \Exception('File write failed'));
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('[LLMsGenerator] Generation failed: File write failed'),
                $this->arrayHasKey('exception')
            );

        $this->generator->execute();
    }
}
