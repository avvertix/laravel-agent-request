<?php

declare(strict_types=1);

namespace Avvertix\AgentRequest\LaravelAgentRequest\Commands;

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;
use Illuminate\Console\Command;

class GenerateRobotsTxtCommand extends Command
{
    protected $signature = 'agent-request:robots-txt
                            {--merge : Append new entries to an existing robots.txt instead of overwriting}
                            {--category=* : Limit to specific agent categories (assistant, crawler, tool; default: all)}';

    protected $description = 'Generate a robots.txt file that disallows known AI agents.';

    public function handle(): int
    {
        $allCategories = array_keys(DetectAgent::$robotsUserAgents);
        $requested = $this->option('category');
        $categories = empty($requested) ? $allCategories : $requested;

        $invalid = array_values(array_diff($categories, $allCategories));

        if ($invalid !== []) {
            $this->error(
                'Unknown '.str('category')->plural(count($invalid)).': '.implode(', ', $invalid).'. '.
                'Valid values: '.implode(', ', $allCategories).'.'
            );

            return self::FAILURE;
        }

        $agents = array_merge(...array_values(
            array_intersect_key(DetectAgent::$robotsUserAgents, array_flip($categories))
        ));

        $path = public_path('robots.txt');

        $existingEntries = [];

        if ($this->option('merge') && file_exists($path)) {
            $existingEntries = $this->parseExistingUserAgents((string) file_get_contents($path));
        }

        $newLines = [];

        foreach ($agents as $agent) {
            if (in_array($agent, $existingEntries, true)) {
                continue;
            }

            $newLines[] = "User-agent: {$agent}";
            $newLines[] = 'Disallow: /';
            $newLines[] = '';
        }

        if ($newLines === []) {
            $this->info('No new entries to add — robots.txt is already up to date.');

            return self::SUCCESS;
        }

        $content = implode("\n", $newLines);

        if ($this->option('merge') && file_exists($path)) {
            file_put_contents($path, "\n".$content, FILE_APPEND);
        } else {
            file_put_contents($path, $content);
        }

        $this->info("robots.txt written to: {$path}");

        return self::SUCCESS;
    }

    /**
     * Parse User-agent values already present in the given robots.txt content.
     *
     * @return list<string>
     */
    private function parseExistingUserAgents(string $content): array
    {
        $agents = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (stripos($line, 'User-agent:') === 0) {
                $agents[] = trim(substr($line, strlen('User-agent:')));
            }
        }

        return $agents;
    }
}
