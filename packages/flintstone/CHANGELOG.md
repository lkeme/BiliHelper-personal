Change Log
==========

### 19/01/2021 - 2.3
* Bump minimum PHP version to 7.3
* Update PHPUnit to version 9 (ensure Flintstone is compatible with PHP 8)

### 12/03/2019 - 2.2
* Bump minimum PHP version to 7.0
* Update PHPUnit to version 6
* Removed data type validation for storing
* Added param and return types

### 09/06/2017 - 2.1.1
* Update `Database::writeTempToFile` to correctly close the file pointer and free up memory

### 24/05/2017 - 2.1
* Bump minimum PHP version to 5.6
* Tidy up of Flintstone class, moved some code into `Database`
* Added `Line` and `Validation` classes
* Closed off public methods `Database::openFile` and `Database::closeFile`

### 20/01/2016 - 2.0
* Major refactor, class names have changed and the whole codebase is much more extensible
* Removed the static `load` and `unload` methods and the `FlinstoneDB` class
* The `replace` method is no longer public
* The `getFile` method has been removed
* Default swap memory limit has been increased to 2MB
* Ability to pass any instance for cache that implements `Flintstone\Cache\CacheInterface`

### 25/03/2015 - 1.9
* Added `getAll` method and some refactoring

### 15/10/2014 - 1.8
* Added formatter option so that you can control how data is encoded/decoded (default is serialize but also ships with json)

### 09/10/2014 - 1.7
* Moved from fopen to SplFileObject
* Moved composer loader from PSR-0 to PSR-4
* Code is now PSR-2 compliant
* Added PHP 5.6 to travis

### 30/09/2014 - 1.6
* Updated limits on valid characters in key name and size
* Improved unit tests

### 29/05/2014 - 1.5
* Reduced some internal complexity
* Fixed gzip compression
* Unit tests now running against all options
* Removed `setOptions` method, must be passed into the `load` method

### 11/03/2014 - 1.4
* Now using Composer

### 16/07/2013 - 1.3
* Changed the load method to static so that multiple instances can be loaded without conflict (use Flintstone::load now instead of $db->load)
* Exception thrown is now FlintstoneException

### 23/01/2013 - 1.2
* Removed the multibyte unserialize method as it seems to work without

### 22/06/2012 - 1.1
* Added new method getKeys() to return an array of keys in the database

### 17/06/2011 - 1.0
* Initial release