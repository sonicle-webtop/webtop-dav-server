<?php
/*
 * Copyright (c) 2012 Szurovecz János
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace lf4php\impl;

use lf4php\MDC;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit_Framework_TestCase;

/**
 * @author Szurovecz János <szjani@szjani.hu>
 */
class MonologLoggerWrapperTest extends PHPUnit_Framework_TestCase
{
    const A_LOGGER_NAME = 'foo';

    /**
     * @var MonologLoggerFactory
     */
    private $monologFactory;

    /**
     * @var \Monolog\Logger
     */
    private $monolog;

    public function setUp()
    {
        $this->monologFactory = new MonologLoggerFactory();
        $this->monolog = new \Monolog\Logger(self::A_LOGGER_NAME);
        $this->monologFactory->registerMonologLogger($this->monolog);
    }

    public function testRegisterLogger()
    {
        $found = $this->monologFactory->getLogger(self::A_LOGGER_NAME);
        self::assertSame($this->monolog, $found->getMonologLogger());
    }

    public function testDefaultLogger()
    {
        $found = $this->monologFactory->getLogger('notExists');
        self::assertEquals(MonologLoggerFactory::ROOT_LOGGER_NAME, $found->getName());
        self::assertEquals($found->getName(), $found->getMonologLogger()->getName());
    }

    public function testCheckAncestorFind()
    {
        $found = $this->monologFactory->getLogger('\foo\bar');
        self::assertSame($this->monolog, $found->getMonologLogger());
    }

    public function testTrace()
    {
        $logfile = __DIR__ . DIRECTORY_SEPARATOR . 'testTrace.log';
        $streamHandler = new \Monolog\Handler\StreamHandler($logfile);
        $this->monolog->pushHandler($streamHandler);
        $found = $this->monologFactory->getLogger(self::A_LOGGER_NAME);
        $found->trace('Hello {}! Ouch!', array('John'));

        $content = file_get_contents($logfile);
        self::assertRegExp('/Hello John!/', $content);
        self::assertRegExp('/MonologLoggerWrapperTest/', $content);
        $streamHandler->close();
        unlink($logfile);
    }

    /**
     * @test
     */
    public function shouldIgnoreIfLevelIsDisabled()
    {
        $logger = $this->monologFactory->getLogger(self::A_LOGGER_NAME);
        self::assertFalse($this->monolog->isHandling(Logger::DEBUG));
        self::assertFalse($logger->isDebugEnabled());
        self::assertFalse($this->monolog->isHandling(Logger::ERROR));
        self::assertFalse($logger->isErrorEnabled());
        self::assertFalse($this->monolog->isHandling(Logger::INFO));
        self::assertFalse($logger->isInfoEnabled());
        self::assertFalse($this->monolog->isHandling(Logger::WARNING));
        self::assertFalse($logger->isWarnEnabled());
        self::assertFalse($logger->isTraceEnabled());

        $this->monolog->pushHandler(new NullHandler(Logger::DEBUG));
        self::assertTrue($this->monolog->isHandling(Logger::DEBUG));
        self::assertTrue($logger->isDebugEnabled());
    }

    /**
     * @test
     */
    public function shouldMdcProcessorBeAdded()
    {
        $processor = $this->monolog->popProcessor();
        $record = array();
        $aKey1 = 'key1';
        $aKey2 = 'key2';
        $aValue1 = 'value1';
        $aValue2 = 'value2';
        MDC::put($aKey1, $aValue1);
        MDC::put($aKey2, $aValue2);
        $result = call_user_func($processor, $record);
        self::assertArrayHasKey(MonologLoggerAdapter::MONOLOG_EXTRA, $result);
        self::assertEquals($aValue1, $result[MonologLoggerAdapter::MONOLOG_EXTRA][$aKey1]);
        self::assertEquals($aValue2, $result[MonologLoggerAdapter::MONOLOG_EXTRA][$aKey2]);
    }

    /**
     * @test
     */
    public function shouldCallPsr3Warning()
    {
        $monologLogger = $this->getMock('\Monolog\Logger', array(), array(), '', false);
        $monologLogger
            ->expects(self::once())
            ->method('isHandling')
            ->with(\Monolog\Logger::WARNING)
            ->will(self::returnValue(true));
        $monologLogger
            ->expects(self::once())
            ->method('warning');
        $logger = new MonologLoggerAdapter($monologLogger);
        $logger->warn('message');
    }

    /**
     * @test
     */
    public function shouldCallPsr3Error()
    {
        $monologLogger = $this->getMock('\Monolog\Logger', array(), array(), '', false);
        $monologLogger
            ->expects(self::once())
            ->method('isHandling')
            ->with(\Monolog\Logger::ERROR)
            ->will(self::returnValue(true));
        $monologLogger
            ->expects(self::once())
            ->method('error');
        $logger = new MonologLoggerAdapter($monologLogger);
        $logger->error('message');
    }
}
