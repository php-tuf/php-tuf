<?php

namespace Tuf;

use GuzzleHttp\Promise\PromiseInterface;

interface RepositoryInterface
{
    public function getRoot(int $version): PromiseInterface;

    public function getTimestamp(): PromiseInterface;

    public function getSnapshot(?int $version): PromiseInterface;

    public function getTargets(?int $version, string $role = 'targets'): PromiseInterface;
}
