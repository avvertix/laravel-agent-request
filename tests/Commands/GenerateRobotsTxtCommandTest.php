<?php

use Avvertix\AgentRequest\LaravelAgentRequest\Actions\DetectAgent;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Flatten all categories from DetectAgent::$robotsUserAgents into one list.
 *
 * @return list<string>
 */
function allRobotsAgents(): array
{
    return array_merge(...array_values(DetectAgent::$robotsUserAgents));
}

beforeEach(function () {
    // Point public_path() at a temp directory so we never touch the real filesystem.
    $this->publicDir = sys_get_temp_dir().'/agent-request-test-'.uniqid();
    mkdir($this->publicDir);
    app()->usePublicPath($this->publicDir);
});

afterEach(function () {
    $robotsTxt = $this->publicDir.'/robots.txt';

    if (file_exists($robotsTxt)) {
        unlink($robotsTxt);
    }

    rmdir($this->publicDir);
});

// ---------------------------------------------------------------------------
// Default (all categories)
// ---------------------------------------------------------------------------

it('creates a robots.txt with all known agents', function () {
    $this->artisan('agent-request:robots-txt')->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    foreach (allRobotsAgents() as $agent) {
        expect($content)->toContain("User-agent: {$agent}");
    }
});

it('includes Disallow: / for each agent', function () {
    $this->artisan('agent-request:robots-txt')->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    expect(substr_count($content, 'Disallow: /'))->toBe(count(allRobotsAgents()));
});

it('does not add duplicate entries when --merge is used', function () {
    $this->artisan('agent-request:robots-txt')->assertSuccessful();

    $contentAfterFirst = file_get_contents($this->publicDir.'/robots.txt');

    $this->artisan('agent-request:robots-txt', ['--merge' => true])->assertSuccessful();

    $contentAfterSecond = file_get_contents($this->publicDir.'/robots.txt');

    expect($contentAfterSecond)->toBe($contentAfterFirst);
});

it('appends only missing entries when --merge is used with a partial file', function () {
    $firstAgent = allRobotsAgents()[0];

    file_put_contents(
        $this->publicDir.'/robots.txt',
        "User-agent: {$firstAgent}\nDisallow: /\n",
    );

    $this->artisan('agent-request:robots-txt', ['--merge' => true])->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    expect(substr_count($content, "User-agent: {$firstAgent}"))->toBe(1);

    foreach (array_slice(allRobotsAgents(), 1) as $agent) {
        expect($content)->toContain("User-agent: {$agent}");
    }
});

// ---------------------------------------------------------------------------
// --category filtering
// ---------------------------------------------------------------------------

it('writes only assistant agents when --category=assistant is given', function () {
    $this->artisan('agent-request:robots-txt', ['--category' => ['assistant']])->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    foreach (DetectAgent::$robotsUserAgents['assistant'] as $agent) {
        expect($content)->toContain("User-agent: {$agent}");
    }

    foreach ([...DetectAgent::$robotsUserAgents['crawler'], ...DetectAgent::$robotsUserAgents['tool']] as $agent) {
        expect($content)->not->toContain("User-agent: {$agent}");
    }
});

it('writes only crawler agents when --category=crawler is given', function () {
    $this->artisan('agent-request:robots-txt', ['--category' => ['crawler']])->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    foreach (DetectAgent::$robotsUserAgents['crawler'] as $agent) {
        expect($content)->toContain("User-agent: {$agent}");
    }

    foreach ([...DetectAgent::$robotsUserAgents['assistant'], ...DetectAgent::$robotsUserAgents['tool']] as $agent) {
        expect($content)->not->toContain("User-agent: {$agent}");
    }
});

it('writes only tool agents when --category=tool is given', function () {
    $this->artisan('agent-request:robots-txt', ['--category' => ['tool']])->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    foreach (DetectAgent::$robotsUserAgents['tool'] as $agent) {
        expect($content)->toContain("User-agent: {$agent}");
    }

    foreach ([...DetectAgent::$robotsUserAgents['assistant'], ...DetectAgent::$robotsUserAgents['crawler']] as $agent) {
        expect($content)->not->toContain("User-agent: {$agent}");
    }
});

it('writes agents from multiple specified categories', function () {
    $this->artisan('agent-request:robots-txt', ['--category' => ['assistant', 'tool']])->assertSuccessful();

    $content = file_get_contents($this->publicDir.'/robots.txt');

    foreach ([...DetectAgent::$robotsUserAgents['assistant'], ...DetectAgent::$robotsUserAgents['tool']] as $agent) {
        expect($content)->toContain("User-agent: {$agent}");
    }

    foreach (DetectAgent::$robotsUserAgents['crawler'] as $agent) {
        expect($content)->not->toContain("User-agent: {$agent}");
    }
});

it('exits with failure and an error message when an unknown category is given', function () {
    $this->artisan('agent-request:robots-txt', ['--category' => ['unknown']])
        ->assertFailed()
        ->expectsOutputToContain('Unknown category');
});
