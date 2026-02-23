<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Model;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Main generator class for LLMs data
 */
class Generator
{
    /**
     * Configuration helper instance
     *
     * @var ConfigHelper
     */
    private ConfigHelper $configHelper;

    /**
     * Data processor instance
     *
     * @var DataProcessor
     */
    private DataProcessor $dataProcessor;

    /**
     * Markdown generator instance
     *
     * @var MarkdownGenerator
     */
    private MarkdownGenerator $markdownGenerator;

    /**
     * Store manager interface
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Store emulation instance
     *
     * @var Emulation
     */
    private Emulation $emulation;

    /**
     * Directory list instance
     *
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * File I/O instance
     *
     * @var IoFile
     */
    private IoFile $ioFile;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param ConfigHelper $configHelper
     * @param DataProcessor $dataProcessor
     * @param MarkdownGenerator $markdownGenerator
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param DirectoryList $directoryList
     * @param IoFile $ioFile
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigHelper $configHelper,
        DataProcessor $dataProcessor,
        MarkdownGenerator $markdownGenerator,
        StoreManagerInterface $storeManager,
        Emulation $emulation,
        DirectoryList $directoryList,
        IoFile $ioFile,
        LoggerInterface $logger
    ) {
        $this->configHelper = $configHelper;
        $this->dataProcessor = $dataProcessor;
        $this->markdownGenerator = $markdownGenerator;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
        $this->directoryList = $directoryList;
        $this->ioFile = $ioFile;
        $this->logger = $logger;
    }

    /**
     * Execute the generation process
     *
     * @param array|null $storeIds Optional array of store IDs to generate for. If null, generates for all stores.
     * @return void
     * @throws \RuntimeException
     */
    public function execute(?array $storeIds = null): void
    {
        if (!$this->configHelper->isEnabled()) {
            throw new \RuntimeException('LLMsGenerator Module is disabled. Skipping generation.');
        }

        try {
            $storeContents = $this->markdownGenerator->generateMarkdown($storeIds);
            $this->saveStoreContentsToFiles($storeContents);
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsGenerator] Generation failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }



    /**
     * Save the generated content for each store to separate files
     *
     * @param array $storeContents Array mapping store codes to their LLMs content
     * @return void
     */
    private function saveStoreContentsToFiles(array $storeContents): void
    {
        $pubPath = $this->directoryList->getPath(DirectoryList::PUB);
        $llmsBaseDir = $pubPath . DIRECTORY_SEPARATOR . 'llms';

        // Create base llms directory if it doesn't exist
        if (!is_dir($llmsBaseDir)) {
            $this->ioFile->mkdir($llmsBaseDir, 0755);
        }

        foreach ($storeContents as $storeCode => $content) {
            // Create store-specific directory
            $storeDir = $llmsBaseDir . DIRECTORY_SEPARATOR . $storeCode;
            if (!is_dir($storeDir)) {
                $this->ioFile->mkdir($storeDir, 0755);
            }

            // Save the content to llms.txt in the store directory
            $targetFile = $storeDir . DIRECTORY_SEPARATOR . ConfigHelper::LLMS_FILENAME;
            $this->ioFile->write($targetFile, $content);

        }
    }
}
