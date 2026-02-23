<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Helper;

use BCMarketplace\LLMsFeeder\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    /**
     * Context mock
     *
     * @var Context
     */
    private Context $context;

    /**
     * Encryptor mock
     *
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * Helper instance
     *
     * @var Data
     */
    private Data $helper;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->context = $this->createMock(Context::class);
        $this->context->method('getScopeConfig')->willReturn($scopeConfig);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->helper = new Data($this->context, $this->encryptor);
    }

    public function testIsEnabledWithModuleEnabled(): void
    {
        // Get the scopeConfig from context and set up the expectation
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('1');

        $result = $this->helper->isEnabled(1);
        $this->assertTrue($result);
    }

    public function testIsEnabledWithModuleDisabled(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('0');

        $result = $this->helper->isEnabled(1);
        $this->assertFalse($result);
    }

    public function testIsEnabledWithNullValue(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(null);

        $result = $this->helper->isEnabled(1);
        $this->assertFalse($result);
    }

    public function testIsEnabledWithEmptyString(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('');

        $result = $this->helper->isEnabled(1);
        $this->assertFalse($result);
    }

    public function testIsEnabledWithDifferentStoreId(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/enabled', ScopeInterface::SCOPE_STORE, 2)
            ->willReturn('1');

        $result = $this->helper->isEnabled(2);
        $this->assertTrue($result);
    }

    public function testGetSiteTitle(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/site_title', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('My Test Site');

        $result = $this->helper->getSiteTitle(1);
        $this->assertEquals('My Test Site', $result);
    }

    public function testGetLlmInstruction(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/llm_instruction', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Use a friendly tone and focus on customer satisfaction.');

        $result = $this->helper->getLlmInstruction(1);
        $this->assertEquals('Use a friendly tone and focus on customer satisfaction.', $result);
    }

    public function testGetContentTypesWithMultipleValues(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/content_types', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('cms_page,product,category');

        $result = $this->helper->getContentTypes(1);
        $this->assertEquals(['cms_page', 'product', 'category'], $result);
    }

    public function testGetContentTypesWithEmptyValue(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/content_types', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('');

        $result = $this->helper->getContentTypes(1);
        $this->assertEquals([], $result);
    }

    public function testGetContentTypesWithSingleValue(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/content_types', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('product');

        $result = $this->helper->getContentTypes(1);
        $this->assertEquals(['product'], $result);
    }

    public function testGetCustomFileDirectives(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/custom_file_directives', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Please refer to our terms of service for additional information.');

        $result = $this->helper->getCustomFileDirectives(1);
        $this->assertEquals('Please refer to our terms of service for additional information.', $result);
    }

    public function testGetSelectedStoreId(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/selected_store', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->willReturn('2');

        $result = $this->helper->getSelectedStoreId();
        $this->assertEquals(2, $result);
    }

    public function testGetSelectedStoreIdWithStringValue(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/selected_store', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->willReturn('3');

        $result = $this->helper->getSelectedStoreId();
        $this->assertEquals(3, $result);
    }

    public function testGetSelectedStoreIdWithNullValue(): void
    {
        $scopeConfig = $this->context->getScopeConfig();
        $scopeConfig->method('getValue')
            ->with('bcmarketplace_llmsfeeder/settings/selected_store', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            ->willReturn(null);

        $result = $this->helper->getSelectedStoreId();
        $this->assertEquals(0, $result);
    }
}
