<?php

namespace SmartCore\Laws;

/**
 * A law the app is built against. Laws with a check() fail the build when broken.
 */
interface Law
{
    public function code(): string;

    public function title(): string;

    public function check(string $basePath): LawResult;
}
