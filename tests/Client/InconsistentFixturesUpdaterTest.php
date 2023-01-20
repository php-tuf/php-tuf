<?php

namespace Tuf\Tests\Client;

class InconsistentFixturesUpdaterTest extends UpdaterTest
{
    protected static function getFixturePath(string $fixtureName, string $subPath = '', bool $isDir = true): string
    {
        return static::traitGetFixturePath($fixtureName, "inconsistent/$subPath", $isDir);
    }

    private function changeRootVersionExpectations(array $data): array
    {
        foreach ($data as &$scenario) {
            $scenario[2]['root'] = match ($scenario[0]) {
                'Delegated', 'NestedDelegated', 'NestedDelegatedErrors' => 3,
                'TerminatingDelegation', 'TopLevelTerminating', 'NestedTerminatingNonDelegatingDelegation', 'ThreeLevelDelegation' => 1,
                default => $scenario[2]['root'],
            };
        }
        return $data;
    }

    public function providerVerifiedDelegatedDownload(): array
    {
        return $this->changeRootVersionExpectations(parent::providerVerifiedDelegatedDownload());
    }

    public function providerDelegationErrors(): array
    {
        return $this->changeRootVersionExpectations(parent::providerDelegationErrors());
    }

    public function providerRefreshRepository(): array
    {
        $data = parent::providerRefreshRepository();
        $data['Delegated'][1]['root'] = 3;
        $data['NestedDelegated'][1]['root'] = 3;
        return $data;
    }

    public function providerFileNotFoundExceptions(): array
    {
        $data = $this->changeRootVersionExpectations(parent::providerFileNotFoundExceptions());
        foreach ($data as &$scenario) {
            if (preg_match('/^[0-9]+/', $scenario[1])) {
                $scenario[1] = ltrim($scenario[1], '0123456789.');
            }
        }
        return $data;
    }

    public function testUnsupportedRepo(bool $consistent = true): void
    {
        parent::testUnsupportedRepo(false);
    }

    public function providerKeyRotation(): array
    {
        $data = parent::providerKeyRotation();
        $data['not rotated'][1]['root'] = 1;
        return $data;
    }

    public function providerExceptionForInvalidMetadata(): array
    {
        $data = parent::providerExceptionForInvalidMetadata();
        foreach ($data as &$scenario) {
            if ($scenario[0] === '4.root.json') {
                $scenario[0] = '3.root.json';
                $scenario[4]['root'] = 2;
            }
            if ($scenario[0] === '4.snapshot.json') {
                $scenario[0] = 'snapshot.json';
            }
            if ($scenario[0] === '4.targets.json') {
                $scenario[0] = 'targets.json';
            }

            if ($scenario[4]['root'] === 4) {
                $scenario[4]['root'] = 3;
            }
        }
        return $data;
    }
}
