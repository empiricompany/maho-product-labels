<?php

/**
 * SPDX-FileCopyrightText: 2026 Empiricompany
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'productlabels:reindex',
    description: 'Rebuild the product labels index for all rules or a single one',
)]
class ProductLabelsReindex extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('rule', 'r', InputOption::VALUE_OPTIONAL, 'Reindex a single rule id');
        $this->addOption('product', 'p', InputOption::VALUE_OPTIONAL, 'Reindex a single product id');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $start = microtime(true);

        /** @var \MM_ProductLabels_Model_Indexer $indexer */
        $indexer = Mage::getSingleton('productlabels/indexer');

        $ruleId = $input->getOption('rule');
        $productId = $input->getOption('product');
        if ($productId !== null) {
            $rows = $indexer->reindexList([(int) $productId]);
        } elseif ($ruleId !== null) {
            $rule = Mage::getModel('productlabels/rule');
            $rule->load((int) $ruleId);
            if (!$rule->getId()) {
                $output->writeln("<error>Rule {$ruleId} not found</error>");
                return Command::FAILURE;
            }
            $rows = $indexer->reindexRule($rule);
        } else {
            $rows = $indexer->reindexAll();
        }

        $output->writeln(sprintf(
            '<info>%d index rows written in %.2fs</info>',
            $rows,
            microtime(true) - $start,
        ));
        return Command::SUCCESS;
    }
}
