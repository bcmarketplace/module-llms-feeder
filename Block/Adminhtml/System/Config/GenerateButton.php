<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateButton extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        // Get current scope parameters
        $storeId = $this->getRequest()->getParam('store');
        $websiteId = $this->getRequest()->getParam('website');
        
        // Build URL with scope parameters
        $url = $this->getUrl('llmsgenerator/generate/run');
        if ($storeId) {
            $url .= '?store=' . $storeId;
        } elseif ($websiteId) {
            $url .= '?website=' . $websiteId;
        }
        
        $buttonId = 'bcmarketplace_llmsfeeder_generate_now';
        $label = __('Generate Now');
        return sprintf(
            '<button id="%s" type="button" class="action-default" onclick="setLocation(\'%s\')"><span>%s</span></button>',
            $buttonId,
            $url,
            $label
        );
    }
}
