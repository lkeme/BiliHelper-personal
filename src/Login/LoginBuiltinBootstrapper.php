<?php declare(strict_types=1);

namespace Bhp\Login;

use Bhp\Plugin\Plugin;

final class LoginBuiltinBootstrapper
{
    public function ensureRegistered(Plugin $plugin): void
    {
        if (array_key_exists('Login', Plugin::getPlugins())) {
            return;
        }

        new Login($plugin);
    }
}
