<?php
namespace PharIo\Phive;

use PharIo\Phive\Cli\Output;

/**
 * @covers PharIo\Phive\HelpCommand
 */
class HelpCommandTest extends \PHPUnit_Framework_TestCase {

    public function testWritesExpectedTextToOutput() {
        $output = $this->getOutputMock();
        $output->expects($this->once())
            ->method('writeText')
            ->with($this->stringContains('help'));

        $command = new HelpCommand(
            $this->getEnvironmentMock(),
            $output
        );

        $command->execute();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Environment
     */
    private function getEnvironmentMock() {
        return $this->getMockWithoutInvokingTheOriginalConstructor(Environment::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Output
     */
    protected function getOutputMock() {
        return $this->getMockWithoutInvokingTheOriginalConstructor(Output::class);
    }

}
