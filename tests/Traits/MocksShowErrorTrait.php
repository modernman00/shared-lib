<?php

namespace Tests\Traits;

use phpmock\phpunit\PHPMock;

trait MocksShowErrorTrait
{
    use PHPMock;

    /**
     * Call this in setUp() to automatically mock showError2().
     *
     * @param string $namespace The namespace where showError2() is called
     */
    protected function mockShowErrorFunctions(string $namespace = 'Src\functionality'): void
    {
        $showErrorMock = $this->getFunctionMock($namespace, 'showError2');
        $showErrorMock->expects($this->any())->willReturn(null);

              // Mock old showError (if still used)
        $showError = $this->getFunctionMock($namespace, 'showError');
        $showError->expects($this->any())->willReturn(null);
    }
}
