Upgrading from version 1.x to 2.x
=================================

As Flintstone is no longer loaded statically the major change required is to switch from using the static `load` method to just instantiating a new instance of Flinstone.

The `FlinstoneDB` class has also been removed and `Flintstone\FlintstoneException` is now `Flintstone\Exception`.

### Version 1.x:

```php
<?php
require 'vendor/autoload.php';

use Flintstone\Flintstone;
use Flintstone\FlintstoneException;

try {
    $users = Flintstone::load('users', array('dir' => '/path/to/database/dir/'));
}
catch (FlintstoneException $e) {

}
```

### Version 2.x:

```php
<?php
require 'vendor/autoload.php';

use Flintstone\Flintstone;
use Flintstone\Exception;

try {
    $users = new Flintstone('users', array('dir' => '/path/to/database/dir/'));
}
catch (Exception $e) {

}
```

See CHANGELOG.md for further changes.