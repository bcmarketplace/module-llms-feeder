<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Content types options for llms.txt generation
 */
class ContentTypes implements OptionSourceInterface
{
    public const CMS_PAGE = 'cms_page';
    public const PRODUCT = 'product';
    public const CATEGORY = 'category';

    /**
     * Get content types options for llms.txt generation
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::CMS_PAGE, 'label' => __('CMS Page')],
            ['value' => self::PRODUCT, 'label' => __('Product')],
            ['value' => self::CATEGORY, 'label' => __('Category')],
        ];
    }
}
