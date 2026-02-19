<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;
use IRIS\SDK\IRIS;
use IRIS\SDK\Resources\Agents\SkillConfig;

/**
 * Skills Management Command
 * 
 * Provides CLI tools for managing agent skills.
 */
class SkillsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('skills')
            ->setDescription('Manage agent skills (V6 Agentic System)')
            ->setHelp('Train agents with specific skills and capabilities')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, create, update, delete, show, activate, deactivate', 'list')
            ->addArgument('agent_id', InputArgument::OPTIONAL, 'Agent ID')
            ->addArgument('skill_id', InputArgument::OPTIONAL, 'Skill ID (for show, update, delete, activate, deactivate)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Skill name')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Skill description')
            ->addOption('instructions', null, InputOption::VALUE_REQUIRED, 'Detailed instructions')
            ->addOption('tools', null, InputOption::VALUE_REQUIRED, 'Tool mappings (comma-separated)')
            ->addOption('triggers', null, InputOption::VALUE_REQUIRED, 'Trigger conditions (comma-separated)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';
        
        try {
            // Initialize SDK
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }
            
            $iris = new IRIS($configOptions);
            
            // Route to appropriate handler
            switch ($action) {
                case 'list':
                    return $this->listSkills($iris, $io, $input);
                case 'create':
                    return $this->createSkill($iris, $io, $input, $output);
                case 'show':
                    return $this->showSkill($iris, $io, $input);
                case 'update':
                    return $this->updateSkill($iris, $io, $input, $output);
                case 'delete':
                    return $this->deleteSkill($iris, $io, $input);
                case 'activate':
                    return $this->activateSkill($iris, $io, $input);
                case 'deactivate':
                    return $this->deactivateSkill($iris, $io, $input);
                case 'training-prompt':
                    return $this->showTrainingPrompt($iris, $io, $input);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: list, create, show, update, delete, activate, deactivate, training-prompt");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
    
    private function listSkills(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getArgument('agent_id');
        
        if (!$agentId) {
            $io->error('Agent ID is required. Usage: iris skills list <agent_id>');
            return Command::FAILURE;
        }
        
        $skills = $iris->agents->skills((int)$agentId)->list();
        
        if (empty($skills)) {
            $io->warning("No skills found for agent #{$agentId}");
            return Command::SUCCESS;
        }
        
        $io->title("Skills for Agent #{$agentId}");
        
        $table = new Table($io);
        $table->setHeaders(['ID', 'Name', 'Active', 'Usage', 'Tools', 'Created']);
        
        foreach ($skills as $skill) {
            $table->addRow([
                $skill->id,
                $skill->skill_name,
                $skill->active ? '✓' : '✗',
                $skill->usage_count,
                implode(', ', $skill->tool_mappings ?? []),
                date('Y-m-d', strtotime($skill->created_at)),
            ]);
        }
        
        $table->render();
        
        return Command::SUCCESS;
    }
    
    private function showSkill(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getArgument('agent_id');
        $skillId = $input->getArgument('skill_id');
        
        if (!$agentId || !$skillId) {
            $io->error('Agent ID and Skill ID are required. Usage: iris skills show <agent_id> <skill_id>');
            return Command::FAILURE;
        }
        
        $skill = $iris->agents->skills((int)$agentId)->get((int)$skillId);
        
        $io->title("Skill: {$skill->skill_name}");
        $io->definitionList(
            ['ID' => $skill->id],
            ['Name' => $skill->skill_name],
            ['Description' => $skill->description],
            ['Instructions' => $skill->instructions ?? 'None'],
            ['Tool Mappings' => implode(', ', $skill->tool_mappings ?? [])],
            ['Trigger Conditions' => implode(', ', $skill->trigger_conditions ?? [])],
            ['Active' => $skill->active ? 'Yes' : 'No'],
            ['Usage Count' => $skill->usage_count],
            ['Last Used' => $skill->last_used_at ?? 'Never'],
            ['Created' => $skill->created_at],
        );
        
        if (!empty($skill->examples)) {
            $io->section('Examples');
            foreach ($skill->examples as $idx => $example) {
                if (is_array($example)) {
                    $io->text(($idx + 1) . ". Input: {$example['input']} → Output: {$example['output']}");
                } else {
                    $io->text(($idx + 1) . ". {$example}");
                }
            }
        }
        
        return Command::SUCCESS;
    }
    
    private function createSkill(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        $agentId = $input->getArgument('agent_id');
        
        if (!$agentId) {
            $io->error('Agent ID is required. Usage: iris skills create <agent_id>');
            return Command::FAILURE;
        }
        
        $helper = $this->getHelper('question');
        
        // Gather skill information
        $name = $input->getOption('name');
        if (!$name) {
            $question = new Question('Skill name: ');
            $name = $helper->ask($input, $output, $question);
        }
        
        $description = $input->getOption('description');
        if (!$description) {
            $question = new Question('Description: ');
            $description = $helper->ask($input, $output, $question);
        }
        
        $instructions = $input->getOption('instructions');
        if (!$instructions) {
            $question = new Question('Instructions (optional): ');
            $instructions = $helper->ask($input, $output, $question);
        }
        
        $toolsStr = $input->getOption('tools');
        $tools = $toolsStr ? explode(',', $toolsStr) : null;
        
        $triggersStr = $input->getOption('triggers');
        $triggers = $triggersStr ? explode(',', $triggersStr) : null;
        
        $config = new SkillConfig(
            skill_name: $name,
            description: $description,
            instructions: $instructions ?: null,
            tool_mappings: $tools,
            trigger_conditions: $triggers
        );
        
        $skill = $iris->agents->skills((int)$agentId)->create($config);
        
        $io->success("Skill created successfully!");
        $io->definitionList(
            ['ID' => $skill->id],
            ['Name' => $skill->skill_name],
            ['Description' => $skill->description],
        );
        
        return Command::SUCCESS;
    }
    
    private function updateSkill(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        $agentId = $input->getArgument('agent_id');
        $skillId = $input->getArgument('skill_id');
        
        if (!$agentId || !$skillId) {
            $io->error('Agent ID and Skill ID are required. Usage: iris skills update <agent_id> <skill_id>');
            return Command::FAILURE;
        }
        
        $updates = [];
        
        if ($name = $input->getOption('name')) {
            $updates['skill_name'] = $name;
        }
        if ($description = $input->getOption('description')) {
            $updates['description'] = $description;
        }
        if ($instructions = $input->getOption('instructions')) {
            $updates['instructions'] = $instructions;
        }
        if ($toolsStr = $input->getOption('tools')) {
            $updates['tool_mappings'] = explode(',', $toolsStr);
        }
        if ($triggersStr = $input->getOption('triggers')) {
            $updates['trigger_conditions'] = explode(',', $triggersStr);
        }
        
        if (empty($updates)) {
            $io->error('No updates provided. Use --name, --description, --instructions, --tools, or --triggers');
            return Command::FAILURE;
        }
        
        $skill = $iris->agents->skills((int)$agentId)->update((int)$skillId, $updates);
        
        $io->success("Skill updated successfully!");
        $io->definitionList(
            ['ID' => $skill->id],
            ['Name' => $skill->skill_name],
        );
        
        return Command::SUCCESS;
    }
    
    private function deleteSkill(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getArgument('agent_id');
        $skillId = $input->getArgument('skill_id');
        
        if (!$agentId || !$skillId) {
            $io->error('Agent ID and Skill ID are required. Usage: iris skills delete <agent_id> <skill_id>');
            return Command::FAILURE;
        }
        
        $success = $iris->agents->skills((int)$agentId)->delete((int)$skillId);
        
        if ($success) {
            $io->success("Skill #{$skillId} deleted successfully!");
        } else {
            $io->error("Failed to delete skill");
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function activateSkill(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getArgument('agent_id');
        $skillId = $input->getArgument('skill_id');
        
        if (!$agentId || !$skillId) {
            $io->error('Agent ID and Skill ID are required. Usage: iris skills activate <agent_id> <skill_id>');
            return Command::FAILURE;
        }
        
        $skill = $iris->agents->skills((int)$agentId)->activate((int)$skillId);
        
        $io->success("Skill '{$skill->skill_name}' activated!");
        
        return Command::SUCCESS;
    }
    
    private function deactivateSkill(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getArgument('agent_id');
        $skillId = $input->getArgument('skill_id');
        
        if (!$agentId || !$skillId) {
            $io->error('Agent ID and Skill ID are required. Usage: iris skills deactivate <agent_id> <skill_id>');
            return Command::FAILURE;
        }
        
        $skill = $iris->agents->skills((int)$agentId)->deactivate((int)$skillId);
        
        $io->success("Skill '{$skill->skill_name}' deactivated!");
        
        return Command::SUCCESS;
    }
    
    private function showTrainingPrompt(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getArgument('agent_id');
        
        if (!$agentId) {
            $io->error('Agent ID is required. Usage: iris skills training-prompt <agent_id>');
            return Command::FAILURE;
        }
        
        $prompt = $iris->agents->skills((int)$agentId)->getTrainingPrompt();
        
        $io->title("Training Prompt for Agent #{$agentId}");
        $io->text($prompt);
        
        return Command::SUCCESS;
    }
}
