<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Controller\Index;

use BCMarketplace\LLMsFeeder\Controller\Index\Index;
use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IndexTest extends TestCase
{
    /**
     * Controller instance
     *
     * @var Index
     */
    private Index $controller;

    /**
     * Request mock
     *
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * ResultFactory mock
     *
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;

    /**
     * StoreManager mock
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * DirectoryList mock
     *
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * IoFile mock
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
     * ConfigHelper mock
     *
     * @var ConfigHelper
     */
    private ConfigHelper $configHelper;

    /**
     * Raw result mock
     *
     * @var Raw
     */
    private Raw $rawResult;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->resultFactory = $this->createMock(ResultFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->directoryList = $this->createMock(DirectoryList::class);
        $this->ioFile = $this->createMock(IoFile::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->rawResult = $this->createMock(Raw::class);

        $this->controller = new Index(
            $this->request,
            $this->resultFactory,
            $this->storeManager,
            $this->directoryList,
            $this->ioFile,
            $this->logger,
            $this->configHelper
        );
    }

    protected function tearDown(): void
    {
        // Clean up $_SERVER variables
        if (isset($_SERVER['REQUEST_URI'])) {
            unset($_SERVER['REQUEST_URI']);
        }
        if (isset($_SERVER['HTTP_HOST'])) {
            unset($_SERVER['HTTP_HOST']);
        }
    }

    public function testExecuteWithStoreCodeFromUrlPath(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/store1/llms.txt';
        $_SERVER['HTTP_HOST'] = 'example.com';

        // Mock ConfigHelper
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);
        
        // Mock store manager
        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getCode')->willReturn('store1');
        $store->method('getName')->willReturn('Store 1');
        $this->storeManager->method('getStore')->with(1)->willReturn($store);

        // Mock result factory
        $this->resultFactory->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->rawResult);

        // Mock directory list
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        // Mock file operations
        $this->ioFile->method('fileExists')
            ->willReturnMap([
                ['/var/www/html/pub/llms/store1/llms.txt', true],
                ['/var/www/html/pub/llms/default/llms.txt', false]
            ]);

        $this->ioFile->method('read')
            ->with('/var/www/html/pub/llms/store1/llms.txt')
            ->willReturn('Store 1 content');

        // Mock raw result methods
        $this->rawResult->method('setContents')
            ->with('Store 1 content')
            ->willReturnSelf();

        $this->rawResult->method('setHeader')
            ->willReturnSelf();

        $this->rawResult->method('setHttpResponseCode')
            ->willReturnSelf();

        // Execute
        $result = $this->controller->execute();

        // Assert
        $this->assertSame($this->rawResult, $result);
    }

    public function testExecuteWithStoreCodeFromSubdomain(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/llms.txt';
        $_SERVER['HTTP_HOST'] = 'store1.example.com';

        // Mock ConfigHelper
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);
        
        // Mock store manager
        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getCode')->willReturn('store1');
        $store->method('getName')->willReturn('Store 1');
        $this->storeManager->method('getStore')->with(1)->willReturn($store);

        // Mock result factory
        $this->resultFactory->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->rawResult);

        // Mock directory list
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        // Mock file operations
        $this->ioFile->method('fileExists')
            ->willReturnMap([
                ['/var/www/html/pub/llms/store1/llms.txt', true],
                ['/var/www/html/pub/llms/default/llms.txt', false]
            ]);

        $this->ioFile->method('read')
            ->with('/var/www/html/pub/llms/store1/llms.txt')
            ->willReturn('Store 1 content');

        // Mock raw result methods
        $this->rawResult->method('setContents')
            ->with('Store 1 content')
            ->willReturnSelf();

        $this->rawResult->method('setHeader')
            ->willReturnSelf();

        $this->rawResult->method('setHttpResponseCode')
            ->willReturnSelf();

        // Execute
        $result = $this->controller->execute();

        // Assert
        $this->assertSame($this->rawResult, $result);
    }

    public function testExecuteWithStoreCodeFromQueryParam(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/llms.txt';
        $_SERVER['HTTP_HOST'] = 'example.com';

        $this->request->method('getParam')
            ->with('store')
            ->willReturn('store2');

        // Mock ConfigHelper
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);
        
        // Mock store manager
        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getCode')->willReturn('store1');
        $store->method('getName')->willReturn('Store 1');
        $this->storeManager->method('getStore')->with(1)->willReturn($store);

        // Mock result factory
        $this->resultFactory->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->rawResult);

        // Mock directory list
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        // Mock file operations
        $this->ioFile->method('fileExists')
            ->willReturnMap([
                ['/var/www/html/pub/llms/store1/llms.txt', true],
                ['/var/www/html/pub/llms/default/llms.txt', false]
            ]);

        $this->ioFile->method('read')
            ->with('/var/www/html/pub/llms/store1/llms.txt')
            ->willReturn('Store 1 content');

        // Mock raw result methods
        $this->rawResult->method('setContents')
            ->with('Store 1 content')
            ->willReturnSelf();

        $this->rawResult->method('setHeader')
            ->willReturnSelf();

        $this->rawResult->method('setHttpResponseCode')
            ->willReturnSelf();

        // Execute
        $result = $this->controller->execute();

        // Assert
        $this->assertSame($this->rawResult, $result);
    }

    public function testExecuteWithFallbackToDefault(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/llms.txt';
        $_SERVER['HTTP_HOST'] = 'example.com';

        // Mock ConfigHelper
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);
        
        // Mock store manager
        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getCode')->willReturn('store1');
        $store->method('getName')->willReturn('Store 1');
        $this->storeManager->method('getStore')->with(1)->willReturn($store);

        // Mock result factory
        $this->resultFactory->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->rawResult);

        // Mock directory list
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        // Mock file operations - store file doesn't exist, but default does
        $this->ioFile->method('fileExists')
            ->willReturnMap([
                ['/var/www/html/pub/llms/store1/llms.txt', false],
                ['/var/www/html/pub/llms/default/llms.txt', true]
            ]);

        $this->ioFile->method('read')
            ->with('/var/www/html/pub/llms/default/llms.txt')
            ->willReturn('Default content');

        // Mock raw result methods
        $this->rawResult->method('setContents')
            ->with('Default content')
            ->willReturnSelf();

        $this->rawResult->method('setHeader')
            ->willReturnSelf();

        $this->rawResult->method('setHttpResponseCode')
            ->willReturnSelf();

        // Execute
        $result = $this->controller->execute();

        // Assert
        $this->assertSame($this->rawResult, $result);
    }

    public function testExecuteWithContentNotFound(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/llms.txt';
        $_SERVER['HTTP_HOST'] = 'example.com';

        // Mock ConfigHelper
        $this->configHelper->method('getSelectedStoreId')->willReturn(1);
        
        // Mock store manager
        $store = $this->createMock(\Magento\Store\Api\Data\StoreInterface::class);
        $store->method('getCode')->willReturn('store1');
        $store->method('getName')->willReturn('Store 1');
        $this->storeManager->method('getStore')->with(1)->willReturn($store);

        // Mock result factory
        $this->resultFactory->method('create')
            ->with(ResultFactory::TYPE_RAW)
            ->willReturn($this->rawResult);

        // Mock directory list
        $this->directoryList->method('getPath')
            ->with(DirectoryList::PUB)
            ->willReturn('/var/www/html/pub');

        // Mock file operations - no files exist
        $this->ioFile->method('fileExists')
            ->willReturn(false);

        // Mock raw result methods
        $this->rawResult->method('setContents')
            ->with('LLMs content not found')
            ->willReturnSelf();

        $this->rawResult->method('setHeader')
            ->willReturnSelf();

        $this->rawResult->method('setHttpResponseCode')
            ->with(404)
            ->willReturnSelf();

        // Execute
        $result = $this->controller->execute();

        // Assert
        $this->assertSame($this->rawResult, $result);
    }
}
