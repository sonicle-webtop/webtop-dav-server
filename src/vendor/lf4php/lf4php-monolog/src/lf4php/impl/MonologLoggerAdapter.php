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

use Exception;
use lf4php\helpers\MessageFormatter;
use lf4php\LocationLogger;
use lf4php\MDC;
use Monolog\Logger as MonologLogger;

/**
 * @author Szurovecz János <szjani@szjani.hu>
 */
class MonologLoggerAdapter extends LocationLogger
{
    const MONOLOG_EXTRA = 'extra';

    /**
     * @var MonologLogger
     */
    private $monologLogger;

    /**
     * @param \Monolog\Logger $monologLogger
     */
    public function __construct(MonologLogger $monologLogger)
    {
        $this->monologLogger = $monologLogger;
        $this->monologLogger->pushProcessor(array(__CLASS__, 'monologMDCProcessor'));
        $this->setLocationPrefix('');
    }

    public static function monologMDCProcessor($record)
    {
        foreach (MDC::getCopyOfContextMap() as $key => $value) {
            $record[self::MONOLOG_EXTRA][$key] = $value;
        }
        return $record;
    }

    /**
     * @return MonologLogger
     */
    public function getMonologLogger()
    {
        return $this->monologLogger;
    }

    public function getName()
    {
        return $this->monologLogger->getName();
    }

    protected function getFormattedLocation()
    {
        return $this->getLocationPrefix() . $this->getShortLocation(self::DEFAULT_BACKTRACE_LEVEL + 1) . $this->getLocationSuffix();
    }

    protected function getExceptionString(Exception $e = null)
    {
        if ($e === null) {
            return '';
        }
        return PHP_EOL . $e->__toString();
    }

    public function debug($format, $params = array(), Exception $e = null)
    {
        if ($this->isDebugEnabled()) {
            $this->monologLogger->debug($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function error($format, $params = array(), Exception $e = null)
    {
        if ($this->isErrorEnabled()) {
            $this->monologLogger->error($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function info($format, $params = array(), Exception $e = null)
    {
        if ($this->isInfoEnabled()) {
            $this->monologLogger->info($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function trace($format, $params = array(), Exception $e = null)
    {
        if ($this->isTraceEnabled()) {
            $this->monologLogger->debug($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function warn($format, $params = array(), Exception $e = null)
    {
        if ($this->isWarnEnabled()) {
            $this->monologLogger->warning($this->getFormattedLocation() . MessageFormatter::format($format, $params) . $this->getExceptionString($e));
        }
    }

    public function isDebugEnabled()
    {
        return $this->monologLogger->isHandling(MonologLogger::DEBUG);
    }

    public function isErrorEnabled()
    {
        return $this->monologLogger->isHandling(MonologLogger::ERROR);
    }

    public function isInfoEnabled()
    {
        return $this->monologLogger->isHandling(MonologLogger::INFO);
    }

    public function isTraceEnabled()
    {
        return $this->monologLogger->isHandling(MonologLogger::DEBUG);
    }

    public function isWarnEnabled()
    {
        return $this->monologLogger->isHandling(MonologLogger::WARNING);
    }
}
