<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Plugin;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Router\Standard;
use Psr\Log\LoggerInterface;

/**
 * Plugin for standard router to intercept llms.txt requests
 */
class RouterPlugin
{
    private ActionFactory $actionFactory;
    private LoggerInterface $logger;

    public function __construct(
        ActionFactory $actionFactory,
        LoggerInterface $logger
    ) {
        $this->actionFactory = $actionFactory;
        $this->logger = $logger;
    }

    /**
     * Intercept the match method to handle llms.txt requests
     *
     * @param Standard $subject
     * @param callable $proceed
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function aroundMatch(Standard $subject, callable $proceed, RequestInterface $request)
    {
        // Check if this is an llms.txt request
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $identifier = trim($requestUri, '/');
        
        if ($identifier === 'llms.txt') {
            // Set the request parameters
            $request->setModuleName('llms')
                   ->setActionName('index')
                   ->setParams(['identifier' => 'llms.txt']);
            
            // Return the action
            return $this->actionFactory->create(\BCMarketplace\LLMsFeeder\Controller\Index\Index::class);
        }
        
        // Let the original method proceed
        return $proceed($request);
    }
}
