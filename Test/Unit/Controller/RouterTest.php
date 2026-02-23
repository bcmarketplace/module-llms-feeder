<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Controller;

use BCMarketplace\LLMsFeeder\Controller\Router;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RouterTest extends TestCase
{
    /**
     * Router instance
     *
     * @var Router
     */
    private Router $router;

    /**
     * ActionFactory mock
     *
     * @var ActionFactory
     */
    private ActionFactory $actionFactory;

    /**
     * Request mock
     *
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * Response mock
     *
     * @var ResponseInterface
     */
    private ResponseInterface $response;

    /**
     * Logger mock
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Forward action mock
     *
     * @var Forward
     */
    private Forward $forwardAction;

    protected function setUp(): void
    {
        $this->actionFactory = $this->createMock(ActionFactory::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->forwardAction = $this->createMock(Forward::class);

        $this->router = new Router(
            $this->actionFactory,
            $this->response,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        // Clean up $_SERVER variable
        if (isset($_SERVER['REQUEST_URI'])) {
            unset($_SERVER['REQUEST_URI']);
        }
    }

    public function testMatchWithLlmsTxtRequest(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/llms.txt';
        
        // Mock request module name
        $this->request->method('getModuleName')
            ->willReturn(null);

        // Mock request parameter setting
        $this->request->method('setModuleName')
            ->with('llms')
            ->willReturnSelf();

        $this->request->method('setActionName')
            ->with('index')
            ->willReturnSelf();

        $this->request->method('setParams')
            ->with(['identifier' => 'llms.txt'])
            ->willReturnSelf();

        // Mock action factory
        $this->actionFactory->method('create')
            ->with(\BCMarketplace\LLMsFeeder\Controller\Index\Index::class)
            ->willReturn($this->forwardAction);

        // Mock logger
        $this->logger->expects($this->once())
            ->method('info')
            ->with('[LLMsFeeder] Router intercepted llms.txt request');

        // Execute
        $result = $this->router->match($this->request);

        // Assert
        $this->assertSame($this->forwardAction, $result);
    }

    public function testMatchWithNonLlmsTxtRequest(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/some-other-path';
        
        // Mock request module name
        $this->request->method('getModuleName')
            ->willReturn(null);

        // Mock logger - should not be called
        $this->logger->expects($this->never())
            ->method('info');

        // Execute
        $result = $this->router->match($this->request);

        // Assert
        $this->assertNull($result);
    }

    public function testMatchWithLlmsTxtWithLeadingSlash(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '/llms.txt';
        
        // Mock request module name
        $this->request->method('getModuleName')
            ->willReturn(null);

        // Mock request parameter setting
        $this->request->method('setModuleName')
            ->with('llms')
            ->willReturnSelf();

        $this->request->method('setActionName')
            ->with('index')
            ->willReturnSelf();

        $this->request->method('setParams')
            ->with(['identifier' => 'llms.txt'])
            ->willReturnSelf();

        // Mock action factory
        $this->actionFactory->method('create')
            ->with(\BCMarketplace\LLMsFeeder\Controller\Index\Index::class)
            ->willReturn($this->forwardAction);

        // Mock logger
        $this->logger->expects($this->once())
            ->method('info')
            ->with('[LLMsFeeder] Router intercepted llms.txt request');

        // Execute
        $result = $this->router->match($this->request);

        // Assert
        $this->assertSame($this->forwardAction, $result);
    }

    public function testMatchWithLlmsTxtWithTrailingSlash(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = 'llms.txt/';
        
        // Mock request module name
        $this->request->method('getModuleName')
            ->willReturn(null);

        // Mock request parameter setting
        $this->request->method('setModuleName')
            ->with('llms')
            ->willReturnSelf();

        $this->request->method('setActionName')
            ->with('index')
            ->willReturnSelf();

        $this->request->method('setParams')
            ->with(['identifier' => 'llms.txt'])
            ->willReturnSelf();

        // Mock action factory
        $this->actionFactory->method('create')
            ->with(\BCMarketplace\LLMsFeeder\Controller\Index\Index::class)
            ->willReturn($this->forwardAction);

        // Mock logger
        $this->logger->expects($this->once())
            ->method('info')
            ->with('[LLMsFeeder] Router intercepted llms.txt request');

        // Execute
        $result = $this->router->match($this->request);

        // Assert
        $this->assertSame($this->forwardAction, $result);
    }

    public function testMatchWithEmptyPathInfo(): void
    {
        // Set up $_SERVER for testing
        $_SERVER['REQUEST_URI'] = '';
        
        // Mock request module name
        $this->request->method('getModuleName')
            ->willReturn(null);

        // Mock logger - should not be called
        $this->logger->expects($this->never())
            ->method('info');

        // Execute
        $result = $this->router->match($this->request);

        // Assert
        $this->assertNull($result);
    }
}
