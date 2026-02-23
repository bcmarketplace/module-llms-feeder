<?php
declare(strict_types=1);

namespace BCMarketplace\LLMsFeeder\Console\Command;

use BCMarketplace\LLMsFeeder\Helper\Data as ConfigHelper;
use BCMarketplace\LLMsFeeder\Model\Generator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    private const OPTION_FORCE = 'force';
    private const OPTION_STORES = 'stores';

    private Generator $generator;
    private LoggerInterface $logger;
    private ConfigHelper $configHelper;

    public function __construct(
        Generator $generator,
        LoggerInterface $logger,
        ConfigHelper $configHelper,
        string $name = null
    ) {
        parent::__construct($name);
        $this->generator = $generator;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
    }

    protected function configure(): void
    {
        $this->setName('llms:generate')
            ->setDescription('Generate LLMs data and save to store-specific directories under pub/llms/. Also installs LLMs renderer at pub/' . ConfigHelper::LLMS_FILENAME)
            ->addOption(
                self::OPTION_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Force generation even if module is disabled'
            )
            ->addOption(
                self::OPTION_STORES,
                's',
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of store IDs to generate for (e.g., "1,2,3"). If not specified, generates for all stores.'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting LLMs data generation...</info>');

        try {
            // Parse store IDs if provided
            $storeIds = null;
            $storesOption = $input->getOption(self::OPTION_STORES);
            if ($storesOption) {
                $storeIds = array_map('intval', array_filter(explode(',', $storesOption)));
                $output->writeln('<info>Generating for specific stores: ' . implode(', ', $storeIds) . '</info>');
            } else {
                $output->writeln('<info>Generating for all stores</info>');
            }

            $this->generator->execute($storeIds);
            $output->writeln('<info>LLMs data generation completed successfully!</info>');
            $output->writeln('<info>Files saved to: pub/llms/{store_code}/' . ConfigHelper::LLMS_FILENAME . '</info>');
            $output->writeln('<info>LLMs renderer installed at: pub/' . ConfigHelper::LLMS_FILENAME . '</info>');
            $output->writeln('<info>Access LLMs content via: https://yourdomain.com/' . ConfigHelper::LLMS_FILENAME . '</info>');
            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->logger->error('[LLMsFeeder] Console generation failed with runtime exception', [
                'error' => $e->getMessage()
            ]);
            $output->writeln('<error>Generation failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->logger->error('[LLMsFeeder] Console generation failed: ' . $e->getMessage(), ['exception' => $e]);
            $output->writeln('<error>An unexpected error occurred. Please check the logs for details.</error>');
            return Command::FAILURE;
        }
    }
}
