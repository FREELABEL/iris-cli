<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;

/**
 * BloqMembersCommand — Manage bloq team members and sharing permissions
 *
 * Commands:
 *   iris bloqs:members list <bloq_id>                                    - List members
 *   iris bloqs:members add <bloq_id> <user_id> --permission=editor       - Add member by user ID
 *   iris bloqs:members invite <bloq_id> --email=x --permission=editor    - Invite by email
 *   iris bloqs:members update <bloq_id> <user_id> --permission=owner     - Update permission
 *   iris bloqs:members remove <bloq_id> <user_id>                        - Remove member
 *
 * Aliases: iris members ..., iris team ...
 */
class BloqMembersCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('bloqs:members')
            ->setAliases(['members', 'team'])
            ->setDescription('Manage bloq team members and sharing permissions')
            ->setHelp('Commands: overview, list, add, invite, update, remove')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: overview|list|add|invite|update|remove')
            ->addArgument('bloq-id', InputArgument::OPTIONAL, 'Bloq ID (required for all actions except overview)')
            ->addArgument('target-id', InputArgument::OPTIONAL, 'Target user ID (for add/update/remove)')
            ->addOption('permission', 'p', InputOption::VALUE_REQUIRED, 'Permission: viewer|editor|owner', 'viewer')
            ->addOption('email', 'e', InputOption::VALUE_REQUIRED, 'Email address (for invite)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name (for invite)')
            ->addOption('no-email', null, InputOption::VALUE_NONE, 'Skip sending invitation email')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show all bloqs including those with no members (overview)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        try {
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int) $userId;
            }

            $iris = new IRIS($configOptions);

            switch ($action) {
                case 'overview':
                case 'summary':
                    return $this->overviewMembers($iris, $input, $io);
                case 'list':
                case 'ls':
                    return $this->listMembers($iris, $input, $io);
                case 'add':
                case 'share':
                    return $this->addMember($iris, $input, $io);
                case 'invite':
                    return $this->inviteMember($iris, $input, $io);
                case 'update':
                case 'set-permission':
                    return $this->updatePermission($iris, $input, $io);
                case 'remove':
                case 'rm':
                case 'unshare':
                    return $this->removeMember($iris, $input, $io);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text('Available actions: overview, list, add, invite, update, remove');

                    return Command::FAILURE;
            }
        } catch (\IRIS\SDK\Exceptions\AuthenticationException $e) {
            $io->error('Authentication failed. Run "iris config" to check your API key.');

            return Command::FAILURE;
        } catch (\IRIS\SDK\Exceptions\IRISException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Scan all bloqs and show a cross-bloq member summary.
     */
    private function overviewMembers(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $showAll = $input->getOption('all');

        $io->text('Scanning bloqs...');

        $collection = $iris->bloqs->list();
        $bloqs = $collection->toArray();

        if (empty($bloqs)) {
            $io->warning('No bloqs found.');

            return Command::SUCCESS;
        }

        $withMembers = [];
        $withoutMembers = [];

        $io->progressStart(count($bloqs));

        foreach ($bloqs as $bloq) {
            $bloqId = $bloq['id'] ?? null;
            if (! $bloqId) {
                $io->progressAdvance();
                continue;
            }

            try {
                $membersResponse = $iris->bloqs->getSharedUsers($bloqId);
                $members = $membersResponse['shared_users'] ?? $membersResponse['data'] ?? $membersResponse;

                // Normalize single-object response
                if (! empty($members) && ! isset($members[0]) && isset($members['id'])) {
                    $members = [$members];
                }

                $count = is_array($members) ? count($members) : 0;
                $entry = [
                    'id' => $bloqId,
                    'name' => mb_substr($bloq['name'] ?? $bloq['title'] ?? '-', 0, 45),
                    'members' => $count,
                ];

                if ($count > 0) {
                    $withMembers[] = $entry;
                } else {
                    $withoutMembers[] = $entry;
                }
            } catch (\Exception $e) {
                $withoutMembers[] = [
                    'id' => $bloqId,
                    'name' => mb_substr($bloq['name'] ?? $bloq['title'] ?? '-', 0, 45),
                    'members' => 0,
                ];
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        if ($input->getOption('json')) {
            $io->writeln(json_encode([
                'with_members' => $withMembers,
                'without_members' => $withoutMembers,
                'summary' => [
                    'total_bloqs' => count($bloqs),
                    'with_members' => count($withMembers),
                    'without_members' => count($withoutMembers),
                ],
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title('Bloq Member Overview');

        // Sort by member count descending
        usort($withMembers, fn($a, $b) => $b['members'] <=> $a['members']);

        if (! empty($withMembers)) {
            $table = new Table($io);
            $table->setHeaders(['ID', 'Bloq', 'Members']);

            foreach ($withMembers as $entry) {
                $table->addRow([$entry['id'], $entry['name'], $entry['members']]);
            }

            if (! $showAll && count($withoutMembers) > 0) {
                $table->addRow(new \Symfony\Component\Console\Helper\TableSeparator());
                $table->addRow(['', '<fg=gray>' . count($withoutMembers) . ' bloqs with no members (use --all to show)</>', '']);
            }

            $table->render();
        }

        if ($showAll && ! empty($withoutMembers)) {
            $io->section('Bloqs Without Members');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Bloq', 'Members']);

            foreach ($withoutMembers as $entry) {
                $table->addRow([$entry['id'], '<fg=gray>' . $entry['name'] . '</>', '<fg=gray>0</>']);
            }

            $table->render();
        }

        $io->text(count($withMembers) . ' bloqs with members, ' . count($withoutMembers) . ' without.');

        return Command::SUCCESS;
    }

    /**
     * List all members of a bloq.
     */
    private function listMembers(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $bloqId = $input->getArgument('bloq-id');
        if (! $bloqId) {
            $io->error('Bloq ID required. Usage: iris team list <bloq_id>');

            return Command::FAILURE;
        }
        $bloqId = (int) $bloqId;

        $response = $iris->bloqs->getSharedUsers($bloqId);
        $members = $response['shared_users'] ?? $response['data'] ?? $response;

        // Normalize: if response is a flat array of user objects
        if (! empty($members) && ! isset($members[0]) && isset($members['id'])) {
            $members = [$members];
        }

        if ($input->getOption('json')) {
            $io->writeln(json_encode($members, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if (empty($members)) {
            $io->info("No members on bloq #{$bloqId}.");
            $io->text('Add members with: iris team invite ' . $bloqId . ' --email=user@example.com --permission=editor');

            return Command::SUCCESS;
        }

        $io->title("Team Roster — Bloq #{$bloqId}");
        $this->renderMembersTable($io, $members);
        $io->text(count($members) . ' member(s).');

        return Command::SUCCESS;
    }

    /**
     * Add an existing user to a bloq by user ID.
     */
    private function addMember(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $bloqId = (int) $input->getArgument('bloq-id');
        $targetId = $input->getArgument('target-id');

        if (! $targetId || ! is_numeric($targetId)) {
            $io->error('User ID required. Usage: iris team add <bloq_id> <user_id> --permission=editor');
            $io->text('Search users first: iris users search "name or email"');

            return Command::FAILURE;
        }

        $targetId = (int) $targetId;
        $permission = $input->getOption('permission');

        if (! in_array($permission, ['viewer', 'editor', 'owner'])) {
            $io->error("Invalid permission: {$permission}. Use: viewer, editor, or owner.");

            return Command::FAILURE;
        }

        $response = $iris->bloqs->share($bloqId, $targetId, $permission);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $message = $response['message'] ?? 'Member added successfully';
        $io->success($message);

        // Show the updated member from shared_users if available
        $sharedUsers = $response['shared_users'] ?? [];
        foreach ($sharedUsers as $member) {
            if (($member['id'] ?? null) == $targetId) {
                $io->definitionList(
                    ['Name' => $member['name'] ?? '-'],
                    ['Email' => $member['email'] ?? '-'],
                    ['Permission' => $permission],
                    ['Status' => $member['status'] ?? 'active'],
                );
                break;
            }
        }

        $io->text('A human agent was automatically created for this member.');

        return Command::SUCCESS;
    }

    /**
     * Invite a user to a bloq by email address.
     */
    private function inviteMember(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $bloqId = (int) $input->getArgument('bloq-id');
        $email = $input->getOption('email');

        if (! $email) {
            $io->error('Email required. Usage: iris team invite <bloq_id> --email=user@example.com');

            return Command::FAILURE;
        }

        $permission = $input->getOption('permission');
        $name = $input->getOption('name');
        $sendEmail = ! $input->getOption('no-email');

        if (! in_array($permission, ['viewer', 'editor', 'owner'])) {
            $io->error("Invalid permission: {$permission}. Use: viewer, editor, or owner.");

            return Command::FAILURE;
        }

        $response = $iris->bloqs->invite($bloqId, $email, $permission, $name, $sendEmail);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $isPending = $response['pending'] ?? false;
        $user = $response['user'] ?? [];

        if ($isPending) {
            $io->success("Invitation sent to {$email} (new user account created)");
        } else {
            $io->success("Bloq shared with existing user {$email}");
        }

        $io->definitionList(
            ['User ID' => $user['id'] ?? '-'],
            ['Name' => $user['name'] ?? $name ?? '-'],
            ['Email' => $user['email'] ?? $email],
            ['Permission' => $permission],
            ['Status' => $isPending ? 'pending' : 'active'],
            ['Email Sent' => $sendEmail ? 'Yes' : 'No'],
        );

        $io->text('A human agent was automatically created for this member.');

        return Command::SUCCESS;
    }

    /**
     * Update a member's permission level.
     */
    private function updatePermission(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $bloqId = (int) $input->getArgument('bloq-id');
        $targetId = $input->getArgument('target-id');

        if (! $targetId || ! is_numeric($targetId)) {
            $io->error('User ID required. Usage: iris team update <bloq_id> <user_id> --permission=editor');

            return Command::FAILURE;
        }

        $targetId = (int) $targetId;
        $permission = $input->getOption('permission');

        if (! in_array($permission, ['viewer', 'editor', 'owner'])) {
            $io->error("Invalid permission: {$permission}. Use: viewer, editor, or owner.");

            return Command::FAILURE;
        }

        $response = $iris->bloqs->updateSharePermission($bloqId, $targetId, $permission);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->success("Permission updated to '{$permission}' for user #{$targetId} on bloq #{$bloqId}.");

        return Command::SUCCESS;
    }

    /**
     * Remove a member from a bloq.
     */
    private function removeMember(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $bloqId = (int) $input->getArgument('bloq-id');
        $targetId = $input->getArgument('target-id');

        if (! $targetId || ! is_numeric($targetId)) {
            $io->error('User ID required. Usage: iris team remove <bloq_id> <user_id>');

            return Command::FAILURE;
        }

        $targetId = (int) $targetId;

        if (! $io->confirm("Remove user #{$targetId} from bloq #{$bloqId}? Their linked agents will be deactivated.", false)) {
            $io->text('Cancelled.');

            return Command::SUCCESS;
        }

        $iris->bloqs->unshare($bloqId, $targetId);

        $io->success("User #{$targetId} removed from bloq #{$bloqId}. Linked agents deactivated.");

        return Command::SUCCESS;
    }

    /**
     * Render a formatted table of members.
     */
    private function renderMembersTable(SymfonyStyle $io, array $members): void
    {
        $table = new Table($io);
        $table->setHeaders(['ID', 'Name', 'Email', 'Permission', 'Status', 'Shared At']);

        foreach ($members as $member) {
            $status = 'active';
            if (isset($member['status']) && $member['status'] === 'pending') {
                $status = 'pending';
            } elseif (empty($member['email_verified_at']) && isset($member['invitation_sent_at'])) {
                $status = 'invited';
            }

            $sharedAt = '-';
            if (! empty($member['shared_at'])) {
                $sharedAt = mb_substr($member['shared_at'], 0, 10);
            }

            $table->addRow([
                $member['id'] ?? '-',
                mb_substr($member['name'] ?? '-', 0, 25),
                $member['email'] ?? '-',
                $member['permission'] ?? 'viewer',
                $status,
                $sharedAt,
            ]);
        }

        $table->render();
    }
}
