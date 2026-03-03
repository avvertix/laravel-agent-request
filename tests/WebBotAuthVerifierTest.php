<?php

use Avvertix\AgentRequest\LaravelAgentRequest\WebBotAuthVerifier;

// ---------------------------------------------------------------------------
// verify()
// ---------------------------------------------------------------------------

describe('verify', function () {
    it('returns false when the Signature-Agent header is absent', function () {
        expect(WebBotAuthVerifier::fromRequest(makeAgentRequest('GPTBot/1.0'))->verify())
            ->toBeFalse();
    });

    it('returns false when Signature-Agent is not a double-quoted string', function () {
        $request = makeRequestWithoutUA(['Signature-agent' => 'https://example.com']);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when Signature-Agent uses http instead of https', function () {
        $request = makeRequestWithoutUA(['Signature-agent' => '"http://example.com"']);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when the Signature-Input header is absent', function () {
        $request = makeRequestWithoutUA(['Signature-agent' => '"https://bot.example.com"']);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when Signature-Input is malformed', function () {
        $request = makeRequestWithoutUA([
            'Signature-agent' => '"https://bot.example.com"',
            'Signature-Input' => 'not-valid-format',
        ]);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when the Signature-Input tag is not web-bot-auth', function () {
        $request = makeRequestWithoutUA([
            'Signature-agent' => '"https://bot.example.com"',
            'Signature-Input' => 'sig1=("@method");tag="other-auth";keyid="abc";expires=9999999999;created=1000000000',
        ]);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when required Signature-Input params are missing', function () {
        // tag is correct but keyid / expires / created are absent
        $request = makeRequestWithoutUA([
            'Signature-agent' => '"https://bot.example.com"',
            'Signature-Input' => 'sig1=("@method");tag="web-bot-auth"',
        ]);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when the signature is expired', function () {
        $request = makeRequestWithoutUA([
            'Signature-agent' => '"https://bot.example.com"',
            'Signature-Input' => 'sig1=("@method");tag="web-bot-auth";keyid="abc";expires=1;created=0',
        ]);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });

    it('returns false when the Signature header is absent', function () {
        // All params are valid except the actual Signature bytes are missing.
        $request = makeRequestWithoutUA([
            'Signature-agent' => '"https://bot.example.com"',
            'Signature-Input' => 'sig1=("@method");tag="web-bot-auth";keyid="abc";expires=9999999999;created=1000000000',
        ]);

        expect(WebBotAuthVerifier::fromRequest($request)->verify())
            ->toBeFalse();
    });
});
