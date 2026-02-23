<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Test\Unit\Model\Config\Source;

use BCMarketplace\LLMsFeeder\Model\Config\Source\Store;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StoreTest extends TestCase
{
    private Store $storeSource;
    private StoreManagerInterface|MockObject $storeManagerMock;
    private LoggerInterface|MockObject $loggerMock;

    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->storeSource = new Store($this->storeManagerMock, $this->loggerMock);
    }

    public function testToOptionArrayWithStores(): void
    {
        // Create mock stores
        $store1 = $this->createMock(StoreInterface::class);
        $store1->method('getId')->willReturn('1');
        $store1->method('getName')->willReturn('Main Store');
        $store1->method('getCode')->willReturn('main');

        $store2 = $this->createMock(StoreInterface::class);
        $store2->method('getId')->willReturn('2');
        $store2->method('getName')->willReturn('Secondary Store');
        $store2->method('getCode')->willReturn('secondary');

        $stores = [$store1, $store2];

        $this->storeManagerMock->method('getStores')->willReturn($stores);

        $result = $this->storeSource->toOptionArray();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        $this->assertEquals('1', $result[0]['value']);
        $this->assertEquals('Main Store (main)', $result[0]['label']);
        
        $this->assertEquals('2', $result[1]['value']);
        $this->assertEquals('Secondary Store (secondary)', $result[1]['label']);
    }

    public function testToOptionArrayWithException(): void
    {
        $this->storeManagerMock->method('getStores')->willThrowException(new \Exception('Store error'));

        $result = $this->storeSource->toOptionArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testToOptionArrayWithEmptyStores(): void
    {
        $this->storeManagerMock->method('getStores')->willReturn([]);

        $result = $this->storeSource->toOptionArray();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
