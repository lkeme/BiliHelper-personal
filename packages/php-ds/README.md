# Data Structures for PHP

[![Build Status](https://github.com/php-ds/polyfill/workflows/CI/badge.svg)](https://github.com/php-ds/polyfill/actions?query=workflow%3A%22CI%22+branch%3Amaster)
[![Packagist](https://img.shields.io/packagist/v/php-ds/php-ds.svg)](https://packagist.org/packages/php-ds/php-ds)

This is a compatibility polyfill for the [extension](https://github.com/php-ds/extension). You should include this package as a dependency of your project
to ensure that your codebase would still be functional in an environment where the extension is not installed. The polyfill will not be loaded if the extension is installed and enabled.

## Install

```bash
composer require php-ds/php-ds
```

You can also *require* that the extension be installed using `ext-ds`. 

## Test

```
composer install
composer test
```

Make sure that the *ds* extension is not enabled, as the polyfill will not be loaded if it is. 
The test output will indicate whether the extension is active.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for more information.

### Credits

- [Rudi Theunissen](https://github.com/rtheunissen)
- [Joe Watkins](https://github.com/krakjoe)

### License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
