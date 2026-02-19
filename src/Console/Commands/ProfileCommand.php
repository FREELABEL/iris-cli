<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\Client;

class ProfileCommand extends Command
{
    protected static $defaultName = 'profile';

    protected function configure(): void
    {
        $this
            ->setName('profile')
            ->setDescription('Manage profiles')
            ->setHelp('Update profile fields easily from the CLI')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: update, show')
            ->addArgument('profile', InputArgument::OPTIONAL, 'Profile slug or PK')
            ->addOption('field', 'f', InputOption::VALUE_REQUIRED, 'Field to update (photo, email, phone, bio, etc.)')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'New value for the field')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');

        switch ($action) {
            case 'update':
                return $this->handleUpdate($input, $output);
            case 'show':
                return $this->handleShow($input, $output);
            default:
                $output->writeln("<error>Unknown action: {$action}</error>");
                $output->writeln('Available actions: update, show');
                return Command::FAILURE;
        }
    }

    protected function handleUpdate(InputInterface $input, OutputInterface $output): int
    {
        $profileSlug = $input->getArgument('profile');
        $field = $input->getOption('field');
        $value = $input->getOption('value');
        $force = $input->getOption('force');

        if (!$profileSlug) {
            $output->writeln('<error>Profile slug/PK is required</error>');
            $output->writeln('Usage: iris profile update <profile> --field=photo --value="https://..."');
            return Command::FAILURE;
        }

        if (!$field) {
            $output->writeln('<error>--field option is required</error>');
            return Command::FAILURE;
        }

        if (!$value) {
            $output->writeln('<error>--value option is required</error>');
            return Command::FAILURE;
        }

        try {
            $client = Client::getInstance();

            // Fetch current profile
            $response = $client->get("/api/v1/profile/{$profileSlug}");

            if (!isset($response['data'])) {
                $output->writeln("<error>Profile '{$profileSlug}' not found</error>");
                return Command::FAILURE;
            }

            $profile = $response['data'];
            $currentValue = $profile[$field] ?? '(empty)';

            // Show what will change
            $output->writeln('');
            $output->writeln("<info>Profile:</info> {$profile['name']} (slug: {$profile['id']}, pk: {$profile['pk']})");
            $output->writeln("<info>Field:</info> {$field}");
            $output->writeln("<info>Current value:</info> {$currentValue}");
            $output->writeln("<info>New value:</info> {$value}");
            $output->writeln('');

            // Confirm unless --force
            if (!$force) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('Update this field? (y/n) ', false);

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('<comment>Cancelled.</comment>');
                    return Command::SUCCESS;
                }
            }

            // Update the profile
            $updateResponse = $client->post("/api/v1/profile/{$profile['pk']}/update", [
                $field => $value
            ]);

            if (isset($updateResponse['success']) && $updateResponse['success']) {
                $output->writeln('<info>✓ Successfully updated ' . $field . '</info>');

                // Fetch updated value
                $updatedResponse = $client->get("/api/v1/profile/{$profileSlug}");
                if (isset($updatedResponse['data'][$field])) {
                    $updatedValue = $updatedResponse['data'][$field];
                    $output->writeln("<info>Updated value:</info> {$updatedValue}");
                }

                return Command::SUCCESS;
            } else {
                $output->writeln('<error>Failed to update profile</error>');
                if (isset($updateResponse['message'])) {
                    $output->writeln('<error>' . $updateResponse['message'] . '</error>');
                }
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    protected function handleShow(InputInterface $input, OutputInterface $output): int
    {
        $profileSlug = $input->getArgument('profile');

        if (!$profileSlug) {
            $output->writeln('<error>Profile slug/PK is required</error>');
            $output->writeln('Usage: iris profile show <profile>');
            return Command::FAILURE;
        }

        try {
            $client = Client::getInstance();
            $response = $client->get("/api/v1/profile/{$profileSlug}");

            if (!isset($response['data'])) {
                $output->writeln("<error>Profile '{$profileSlug}' not found</error>");
                return Command::FAILURE;
            }

            $profile = $response['data'];

            $output->writeln('');
            $output->writeln('<info>=== Profile Information ===</info>');
            $output->writeln("<info>Name:</info> {$profile['name']}");
            $output->writeln("<info>Slug:</info> {$profile['id']}");
            $output->writeln("<info>PK:</info> {$profile['pk']}");
            $output->writeln("<info>Bio:</info> " . ($profile['bio'] ?? '(empty)'));
            $output->writeln("<info>Email:</info> " . ($profile['email'] ?? '(empty)'));
            $output->writeln("<info>Phone:</info> " . ($profile['phone'] ?? '(empty)'));
            $output->writeln("<info>Photo:</info> " . ($profile['photo'] ?? '(empty)'));
            $output->writeln("<info>Instagram:</info> " . ($profile['instagram'] ?? '(empty)'));
            $output->writeln("<info>Twitter:</info> " . ($profile['twitter'] ?? '(empty)'));
            $output->writeln("<info>TikTok:</info> " . ($profile['tiktok'] ?? '(empty)'));
            $output->writeln("<info>Website:</info> " . ($profile['website_url'] ?? '(empty)'));
            $output->writeln('');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
