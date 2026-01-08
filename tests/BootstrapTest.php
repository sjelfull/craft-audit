<?php

use superbig\audit\Audit;

it('boots Craft and installs the audit plugin', function () {
    expect(Audit::$plugin)->toBeInstanceOf(Audit::class);
})->skip('Plugin bootstrap needs configuration');
