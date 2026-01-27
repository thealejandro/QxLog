<?php

use App\Support\TimeHelper;

test('isWithinTimeWindow handles standard windows', function () {
    // 09:00 to 17:00
    expect(TimeHelper::isWithinTimeWindow('12:00', '09:00', '17:00'))->toBeTrue();
    expect(TimeHelper::isWithinTimeWindow('09:00', '09:00', '17:00'))->toBeTrue();
    expect(TimeHelper::isWithinTimeWindow('17:00', '09:00', '17:00'))->toBeTrue();
    expect(TimeHelper::isWithinTimeWindow('08:59', '09:00', '17:00'))->toBeFalse();
    expect(TimeHelper::isWithinTimeWindow('17:01', '09:00', '17:00'))->toBeFalse();
});

test('isWithinTimeWindow handles midnight crossing windows', function () {
    // 22:00 to 05:00
    expect(TimeHelper::isWithinTimeWindow('23:00', '22:00', '05:00'))->toBeTrue();
    expect(TimeHelper::isWithinTimeWindow('02:00', '22:00', '05:00'))->toBeTrue();
    expect(TimeHelper::isWithinTimeWindow('22:00', '22:00', '05:00'))->toBeTrue();
    expect(TimeHelper::isWithinTimeWindow('05:00', '22:00', '05:00'))->toBeTrue();

    // Outside
    expect(TimeHelper::isWithinTimeWindow('21:00', '22:00', '05:00'))->toBeFalse();
    expect(TimeHelper::isWithinTimeWindow('06:00', '22:00', '05:00'))->toBeFalse();
});
