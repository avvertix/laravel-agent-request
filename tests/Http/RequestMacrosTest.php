<?php

use Illuminate\Http\Request;

it('registers the wantsMarkdown macro on Request', function () {
    expect(Request::hasMacro('wantsMarkdown'))->toBeTrue();
});

it('registers the expectsMarkdown macro on Request', function () {
    expect(Request::hasMacro('expectsMarkdown'))->toBeTrue();
});

it('registers the isAgent macro on Request', function () {
    expect(Request::hasMacro('isAgent'))->toBeTrue();
});

it('registers the isAiAssistant macro on Request', function () {
    expect(Request::hasMacro('isAiAssistant'))->toBeTrue();
});
