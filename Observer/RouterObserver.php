<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Observer;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer to intercept llms.txt requests
 */
class RouterObserver implements ObserverInterface
{
    private RequestInterface $request;
    private ResponseInterface $response;
    private ActionFactory $actionFactory;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        ResponseInterface $response,
        ActionFactory $actionFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->actionFactory = $actionFactory;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $identifier = trim($requestUri, '/');
        
        // Check if this is an llms.txt request
        if ($identifier === 'llms.txt') {
            // Set the request parameters
            $this->request->setModuleName('llms')
                         ->setActionName('index')
                         ->setParams(['identifier' => 'llms.txt']);
            
            // Stop the current request processing
            $this->response->setNoCacheHeaders();
            
            // Create and dispatch our custom action
            $action = $this->actionFactory->create(Forward::class);
            $action->dispatch($this->request);
            
            // Exit to prevent further processing
            exit;
        }
    }
}
