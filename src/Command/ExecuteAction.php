<?php

/**
 * 2019-2025 Team Ever
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 *  @author    Team Ever <https://www.team-ever.com/>
 *  @copyright 2019-2025 Team Ever
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Everblocklight\Tools\Command;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;
use Currency;
use Everblocklight\Tools\Service\EverblocklightCache;
use Everblocklight\Tools\Service\EverblocklightTools;
use PrestaShop\PrestaShop\Adapter\LegacyContext as ContextAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ExecuteAction extends Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;
    public const ABORTED = 3;

    private $allowedActions = [
        'refreshtokens' => [
            'label' => 'Refresh Instagram token',
            'description' => 'Renews the Instagram token and clears the related cache.',
        ],
        'fetchinstagramimages' => [
            'label' => 'Download Instagram medias',
            'description' => 'Downloads configured Instagram media files and stores them locally.',
        ],
        'fetchwordpressposts' => [
            'label' => 'Fetch WordPress posts',
            'description' => 'Fetches the configured WordPress posts.',
        ],
        'checkdatabase' => [
            'label' => 'Check module database',
            'description' => 'Installs missing module tables.',
        ],
        'clearcache' => [
            'label' => 'Clear Everblocklight cache',
            'description' => 'Flushes module cache entries without clearing the whole shop cache.',
        ],
    ];

    public function __construct(KernelInterface $kernel)
    {
        unset($kernel);
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('everblocklight:tools:execute');
        $this->setDescription('Execute action (use --list to display the available actions)');
        $this->addArgument('action', InputArgument::OPTIONAL, sprintf('Action to execute (Allowed actions: %s).', implode(' / ', array_keys($this->allowedActions))));
        $this->addOption('list', null, InputOption::VALUE_NONE, 'List available actions and exit.');
        $help = "Use the --list option to display the available actions.\n";
        foreach ($this->allowedActions as $name => $action) {
            $help .= sprintf("- %s: %s\n", $name, $action['description']);
        }
        $this->setHelp($help);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('list')) {
            $table = new Table($output);
            $table->setHeaders(['Action', 'Description']);
            foreach ($this->allowedActions as $name => $action) {
                $table->addRow([$name, sprintf('%s — %s', $action['label'], $action['description'])]);
            }
            $table->render();

            return self::SUCCESS;
        }

        $action = $input->getArgument('action');
        if (!$action) {
            $output->writeln('<warning>No action provided. Use the --list option to display available actions.</warning>');

            return self::ABORTED;
        }
        if (!array_key_exists($action, $this->allowedActions)) {
            $output->writeln(sprintf('<warning>Unknown action "%s". Use the --list option to display available actions.</warning>', $action));

            return self::ABORTED;
        }

        $context = (new ContextAdapter())->getContext();
        $context->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));

        switch ($action) {
            case 'refreshtokens':
                $newToken = EverblocklightTools::refreshInstagramToken();
                if (!$newToken) {
                    $output->writeln('<warning>Instagram token reset failed</warning>');

                    return self::FAILURE;
                }
                EverblocklightCache::cacheDropByPattern('fetchInstagramImages');
                $output->writeln('<success>Instagram token refreshed</success>');

                return self::SUCCESS;
            case 'fetchinstagramimages':
                $output->writeln('<comment>Fetching Instagram medias…</comment>');
                $images = EverblocklightTools::fetchInstagramImages();
                $output->writeln(sprintf('<success>%d media files processed</success>', is_array($images) ? count($images) : 0));

                return self::SUCCESS;
            case 'fetchwordpressposts':
                EverblocklightTools::fetchWordpressPosts();
                $output->writeln('<success>WordPress posts fetched</success>');

                return self::SUCCESS;
            case 'checkdatabase':
                EverblocklightTools::checkAndFixDatabase();
                $output->writeln('<success>Database schema verified successfully</success>');

                return self::SUCCESS;
            case 'clearcache':
                EverblocklightCache::clearAllModuleCache();
                $output->writeln('<success>Everblocklight cache cleared</success>');

                return self::SUCCESS;
        }

        return self::ABORTED;
    }
}
