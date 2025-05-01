<?php

it('can test', function () {
    expect(true)->toBeTrue();
    expect(config('ai-jobs.test_it'))->toBe(5);
});
