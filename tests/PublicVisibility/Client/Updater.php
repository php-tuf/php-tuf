<?php


namespace Tuf\Tests\PublicVisibility\Client;

class Updater extends \Tuf\Client\Updater
{
    public function unitTest_checkRollbackAttack(...$args)
    {
        return $this->checkRollbackAttack(...$args);
    }

    public function unitTest_checkFreezeAttack(...$args)
    {
        return $this->checkFreezeAttack(...$args);
    }
}
