<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

test('election.json exists and is valid JSON', function () {
    $path = realpath(__DIR__ . '/../../config/election.json');

    expect($path)->not->toBeFalse(); // confirms realpath worked

    $contents = File::get($path);

    expect(fn () => json_decode($contents, true))->not->toThrow(Exception::class);

    $json = json_decode($contents, true);
    expect($json)->toBeArray()
        ->and($json)->toHaveKeys(['positions', 'candidates']);
});

test('precinct.yaml exists and is valid YAML', function () {
    $path = realpath(__DIR__ . '/../../config/precinct.yaml');

    expect($path)->not->toBeFalse(); // confirms realpath worked

    $contents = File::get($path);

    expect(fn () => Yaml::parse($contents))->not->toThrow(Exception::class);

    $yaml = Yaml::parse($contents);
    expect($yaml)->toBeArray()
        ->and($yaml)->toHaveKeys(['code', 'location_name']);
});

test('mapping.yaml exists and is valid YAML', function () {
    $path = realpath(__DIR__ . '/../../config/mapping.yaml');

    expect($path)->not->toBeFalse(); // confirms realpath worked

    $contents = File::get($path);

    expect(fn () => Yaml::parse($contents))->not->toThrow(Exception::class);

    $yaml = Yaml::parse($contents);
    expect($yaml)->toBeArray()
        ->and($yaml)->toHaveKeys(['code', 'district', 'marks']);

    // Optional: check structure of marks
    foreach ($yaml['marks'] as $mark) {
        expect($mark)->toHaveKeys(['key', 'value']);
    }
});
