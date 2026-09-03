<?php

namespace IRIS\SDK\Console\Commands;

use IRIS\SDK\IRIS;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Make a bloq item public and print its URL. One command.
 *
 * The capability existed on three separate surfaces — an is_public column, a make-public
 * endpoint, and makePublic() on the model — and had no name on any of them. Someone asking
 * "how do I share this" found nothing, so the answer became "read the model", and the wrong
 * answer (set is_public yourself) leaves the item flagged public with a NULL url: published in
 * name, unreachable in fact.
 *
 * Client-level operations belong here rather than in artisan. Artisan means shelling into a
 * production container; this is the tool the work is actually done with.
 */
class AtlasPublishCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('atlas:publish')
            ->setAliases(['publish-item', 'bloq:publish'])
            ->setDescription('Make a bloq item public and print its shareable URL')
            ->setHelp(<<<'HELP'
<comment>Publish a bloq item</comment>

  <info>iris atlas:publish 183512</info>

      https://heyiris.io/n/e213d3be-b9e9-4bac-acfc-195692bca3c6

  Use this rather than setting <info>is_public</info> yourself. Assigning that flag directly
  does NOT mint the public_uuid the URL is built from, so the item ends up marked public with
  no address — which looks like success and is not.

<comment>Take it private again</comment>

  <info>iris atlas:publish 183512 --unpublish</info>

  The uuid is kept, so re-publishing later restores the SAME link instead of breaking wherever
  the old one was pasted.

<comment>Finding the item id</comment>

  <info>iris bloqs lists 612</info>          the lists in a bloq, with ids
  <info>iris bloqs item-add 2094 …</info>    prints the id of what it wrote
HELP)
            ->addArgument('item', InputArgument::REQUIRED, 'Bloq item id')
            ->addOption('unpublish', null, InputOption::VALUE_NONE, 'Take it private again')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configOptions = [];
        if ($apiKey = $input->getOption('api-key')) {
            $configOptions['api_key'] = $apiKey;
        }
        if ($optUser = $input->getOption('user-id')) {
            $configOptions['user_id'] = (int) $optUser;
        }

        $iris = new IRIS($configOptions);
        $itemId = (int) $input->getArgument('item');
        $userId = $iris->getConfig()->userId;

        if (!$userId) {
            $io->error('No user id. Set IRIS_USER_ID or pass --user-id.');
            return 1;
        }
        $verb = $input->getOption('unpublish') ? 'make-private' : 'make-public';

        try {
            $res = $iris->getHttpClient()->post("/api/v1/user/{$userId}/bloqs/list/item/{$itemId}/{$verb}");
        } catch (\Throwable $e) {
            // The egress gate refusing a classified record is the expected failure here and a
            // good one. Show what it said rather than a generic error.
            $io->error('Could not ' . str_replace('-', ' ', $verb) . ': ' . $e->getMessage());
            return 1;
        }

        $data = $res['data'] ?? $res;
        $url = $data['public_url'] ?? null;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return ($input->getOption('unpublish') || $url) ? 0 : 1;
        }

        if ($input->getOption('unpublish')) {
            $io->success("Item {$itemId} is private again. Its link resumes if you publish it later.");
            return 0;
        }

        if (!$url) {
            // Never report a publish without an address — that is the exact failure this
            // command exists to prevent, so it must not be able to produce it quietly.
            $io->error("Item {$itemId} reports public but returned no URL. That is a bug, not a publish.");
            return 1;
        }

        $io->writeln('');
        $io->writeln('  ' . $url);
        $io->writeln('');

        return 0;
    }
}
