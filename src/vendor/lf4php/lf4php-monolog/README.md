lf4php-monolog
==============

This is a Monolog binding for lf4php.

Using lf4php-monolog
--------------------

### Configuration

```php
<?php
// configuring monolog loggers
$monolog1 = new \Monolog\Logger('foo');
$monolog2 = new \Monolog\Logger('bar');

// registering them for lf4php
$loggerFactory = StaticLoggerBinder::$SINGLETON->getLoggerFactory();
$loggerFactory->setRootMonologLogger($monolog1);
$loggerFactory->registerMonologLogger($monolog2);
```

### Logging

```php
<?php
$logger = LoggerFactory::getLogger(__CLASS__);
$logger->info('Message');
$logger->debug('Hello {}!', array('John'));
$logger->error(new \Exception());
```

History
-------

### 3.1

MDC support.

### 3.0

Updated lf4php (3.0.x)
