<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    public const XML_PATH_BASE = 'bcmarketplace_llmsfeeder/settings/';
    public const LLMS_FILENAME = 'llms.txt';
    
    private EncryptorInterface $encryptor;

    public function __construct(Context $context, EncryptorInterface $encryptor)
    {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSelectedStoreId(): int
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_BASE . 'selected_store', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        return (int) $value;
    }



    public function getSiteDescription(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'site_description', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSiteTitle(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'site_title', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getLlmInstruction(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'llm_instruction', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string[]
     */
    public function getContentTypes(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'content_types', ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === '') {
            return [];
        }
        $types = array_filter(array_map('trim', explode(',', $value)), static function ($v) {
            return $v !== '';
        });
        return array_values($types);
    }

    public function getCustomFileDirectives(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'custom_file_directives', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return int[]
     */
    public function getCompanyInfoPageIds(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'company_info_pages', ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === '') {
            return [];
        }
        $ids = array_filter(array_map('trim', explode(',', $value)), static function ($v) {
            return $v !== '';
        });
        return array_map('intval', $ids);
    }



    public function getFrequency(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'frequency', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getGenerationTime(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BASE . 'generation_time', ScopeInterface::SCOPE_STORE, $storeId);
    }
}
