<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Block\Adminhtml\System\Config;

use BCMarketplace\LLMsFeeder\Model\SitemapProcessor;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;

class CompanyInfoPages extends Field
{
    private StoreManagerInterface $storeManager;
    private SitemapProcessor $sitemapProcessor;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        SitemapProcessor $sitemapProcessor,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
        $this->sitemapProcessor = $sitemapProcessor;
    }

    /**
     * Render the field with dynamic behavior
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $store = $this->storeManager->getStore();
        $sitemapExists = $this->sitemapProcessor->sitemapExists($store);
        
        if ($sitemapExists) {
            // Disable the field and add notice
            $element->setDisabled(true);
            $element->setReadonly(true);
            
            $notice = '<div class="admin__field-note" style="color: #e22626; margin-top: 5px;">';
            $notice .= '<strong>' . __('Notice:') . '</strong> ';
            $notice .= __('A sitemap was detected. Company information pages will be automatically derived from the sitemap.');
            $notice .= '</div>';
            
            return parent::_getElementHtml($element) . $notice;
        }
        
        return parent::_getElementHtml($element);
    }

    /**
     * Remove the scope label when sitemap is detected
     */
    protected function _renderScopeLabel(AbstractElement $element): string
    {
        $store = $this->storeManager->getStore();
        $sitemapExists = $this->sitemapProcessor->sitemapExists($store);
        
        if ($sitemapExists) {
            return '';
        }
        
        return parent::_renderScopeLabel($element);
    }

    /**
     * Get the comment when sitemap is detected
     */
    protected function _renderValue(AbstractElement $element): string
    {
        $store = $this->storeManager->getStore();
        $sitemapExists = $this->sitemapProcessor->sitemapExists($store);
        
        if ($sitemapExists) {
            $element->setComment(__('This field is disabled because a sitemap was detected. Company information pages will be automatically derived from the sitemap.'));
        }
        
        return parent::_renderValue($element);
    }
}
