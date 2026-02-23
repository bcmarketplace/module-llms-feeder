<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\RouterInterface;
use Psr\Log\LoggerInterface;

/**
 * Custom router for llms.txt requests
 */
class Router implements RouterInterface
{
    private ActionFactory $actionFactory;
    private ResponseInterface $response;
    private LoggerInterface $logger;

    public function __construct(
        ActionFactory $actionFactory,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        $this->actionFactory = $actionFactory;
        $this->response = $response;
        $this->logger = $logger;
    }
    /**
     * Match request to our custom action
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(RequestInterface $request)
    {
        // Always check for llms.txt requests regardless of current module state
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $identifier = trim($requestUri, '/');
        
        // Check if this is an llms.txt request
        if ($identifier === 'llms.txt') {
            $this->logger->info('[LLMsFeeder] Router intercepted llms.txt request');
            // Set the request parameters
            $request->setModuleName('llms')
                   ->setActionName('index')
                   ->setParams(['identifier' => 'llms.txt']);
            
            // Return our custom controller action
            return $this->actionFactory->create(Index\Index::class);
        }
        
        return null;
    }
}
