<?php

namespace ChameleonSystem\CoreBundle\Tests\Response;

use ChameleonSystem\CoreBundle\Interfaces\FlashMessageServiceInterface;
use ChameleonSystem\CoreBundle\Response\ResponseVariableReplacer;
use ChameleonSystem\CoreBundle\Security\AuthenticityToken\AuthenticityTokenManagerInterface;
use ChameleonSystem\CoreBundle\Security\AuthenticityToken\TokenInjectionFailedException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class ResponseVariableReplacerTest extends TestCase
{
    /**
     * @var AuthenticityTokenManagerInterface|ObjectProphecy
     */
    private $authenticityTokenManagerMock;
    /**
     * @var FlashMessageServiceInterface|ObjectProphecy
     */
    private $flashMessageServiceMock;
    /**
     * @var ResponseVariableReplacer
     */
    private $subject;
    /**
     * @var object|array|string
     */
    private $actualResult;

    protected function tearDown()
    {
        parent::tearDown();
        $this->authenticityTokenManagerMock = null;
        $this->flashMessageServiceMock = null;
        $this->subject = null;
        $this->actualResult = null;
    }

    /**
     * @dataProvider provideDataForTestReplaceVariables
     *
     * @param object|array|string $input
     * @param array               $placeholders
     * @param object|array|string $expectedResult
     */
    public function testReplaceVariables($input, array $placeholders, $expectedResult): void
    {
        $this->givenResponseVariableReplacer($placeholders);
        $this->whenReplaceVariablesIsCalled($input);
        $this->thenTheExpectedResultShouldBeReturned($expectedResult);
    }

    public function provideDataForTestReplaceVariables(): array
    {
        $testObject = new \stdClass();
        $testObject->foo = 'bar';
        $testObject->baz = '12345 [{my-placeholder}]67890';

        $placeholders = [
            'my-placeholder' => 'my-value',
        ];

        $expectedResultObject = new \stdClass();
        $expectedResultObject->foo = 'bar';
        $expectedResultObject->baz = '12345 my-value67890';

        return [
            'empty-string' => [
                '',
                [],
                '',
            ],
            'string-without-placeholders' => [
                'This is my string and I am proud of it.',
                [],
                'This is my string and I am proud of it.',
            ],
            'string-with-placeholders' => [
                'This is my string with [{two}] [{placeholders}] and I am proud of it.',
                [
                    'two' => 'nothing',
                    'placeholders' => 'to see',
                ],
                'This is my string with nothing to see and I am proud of it.',
            ],
            'string-with-unknown-placeholder' => [
                'This is my string with a [{valid-placeholder}] and an [{unknown-placeholder}] and I am proud of it.',
                [
                    'valid-placeholder' => 'very meaningful text',
                ],
                'This is my string with a very meaningful text and an [{unknown-placeholder}] and I am proud of it.',
            ],
            'string-with-authenticity-token' => [
                'This is my string with [{cmsauthenticitytoken}] and I am proud of it.',
                [],
                'This is my string with such-token and I am proud of it.',
            ],
            'array-with-placeholder' => [
                [
                    'such-key' => 'many-value',
                    'so-placeholder' => '<<< [{wow}] xxx',
                ],
                [
                    'wow' => 'woohooo',
                ],
                [
                    'such-key' => 'many-value',
                    'so-placeholder' => '<<< woohooo xxx',
                ],
            ],
            'object-with-placeholder' => [
                $testObject,
                $placeholders,
                $expectedResultObject,
            ],
        ];
    }

    public function testTokenInjectionFailedException(): void
    {
        $this->givenResponseVariableReplacer([]);
        $this->givenAuthenticityTokenManagerFailsOnTokenInjection();
        $this->thenExpectTokenInjectionFailedException();
        $this->whenReplaceVariablesIsCalled('xxx');
    }

    private function givenResponseVariableReplacer(array $placeholders): void
    {
        $this->authenticityTokenManagerMock = $this->prophesize(AuthenticityTokenManagerInterface::class);
        $this->authenticityTokenManagerMock->addTokenToForms(Argument::any())->will(function (array $args) {
            return $args[0];
        });
        $this->authenticityTokenManagerMock->getStoredToken()->willReturn('such-token');
        $this->flashMessageServiceMock = $this->prophesize(FlashMessageServiceInterface::class);
        $this->flashMessageServiceMock->injectMessageIntoString(Argument::any())->will(function (array $args) {
            return $args[0];
        });
        $this->subject = new ResponseVariableReplacer(
            $this->authenticityTokenManagerMock->reveal(),
            $this->flashMessageServiceMock->reveal()
        );

        foreach ($placeholders as $key => $value) {
            $this->subject->addVariable($key, $value);
        }
    }

    /**
     * @param object|array|string $input
     */
    private function whenReplaceVariablesIsCalled($input): void
    {
        $this->actualResult = $this->subject->replaceVariables($input);
    }

    private function thenTheExpectedResultShouldBeReturned($expectedResult): void
    {
        $this->assertEquals($expectedResult, $this->actualResult);
    }

    private function givenAuthenticityTokenManagerFailsOnTokenInjection(): void
    {
        $this->authenticityTokenManagerMock->addTokenToForms(Argument::any())->willThrow(new TokenInjectionFailedException('test'));
    }

    private function thenExpectTokenInjectionFailedException(): void
    {
        $this->expectException(TokenInjectionFailedException::class);
    }
}
