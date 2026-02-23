<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Model\Config\Source;

use BCMarketplace\LLMsFeeder\Model\Config\Source\ContentTypes;
use PHPUnit\Framework\TestCase;

class ContentTypesTest extends TestCase
{
    /**
     * ContentTypes source model instance
     *
     * @var ContentTypes
     */
    private ContentTypes $contentTypes;

    protected function setUp(): void
    {
        $this->contentTypes = new ContentTypes();
    }

    public function testToOptionArray(): void
    {
        $options = $this->contentTypes->toOptionArray();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        // Check CMS Page option
        $this->assertEquals('cms_page', $options[0]['value']);
        $this->assertEquals('CMS Page', $options[0]['label']);

        // Check Product option
        $this->assertEquals('product', $options[1]['value']);
        $this->assertEquals('Product', $options[1]['label']);

        // Check Category option
        $this->assertEquals('category', $options[2]['value']);
        $this->assertEquals('Category', $options[2]['label']);
    }

    public function testConstants(): void
    {
        $this->assertEquals('cms_page', ContentTypes::CMS_PAGE);
        $this->assertEquals('product', ContentTypes::PRODUCT);
        $this->assertEquals('category', ContentTypes::CATEGORY);
    }
}
