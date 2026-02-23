<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Plugin;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\FrontController;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin for front controller to intercept llms.txt requests
 */
class FrontControllerPlugin
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
     * Intercept the dispatch method to handle llms.txt requests
     *
     * @param FrontController $subject
     * @param callable $proceed
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function aroundDispatch(FrontController $subject, callable $proceed, RequestInterface $request)
    {
        // Check if this is an llms.txt request
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $identifier = trim($requestUri, '/');
        
        if ($identifier === 'llms.txt') {
            // Set the request parameters
            $request->setModuleName('llms')
                   ->setActionName('index')
                   ->setParams(['identifier' => 'llms.txt']);
            
            // Create and execute our custom action
            $action = $this->actionFactory->create(\BCMarketplace\LLMsFeeder\Controller\Index\Index::class);
            return $action->execute();
        }
        
        // Let the original method proceed
        return $proceed($request);
    }
}
