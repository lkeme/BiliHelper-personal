<?php declare(strict_types=1);

namespace Bhp\Plugin;

final class PluginClassNameResolver
{
    public function resolve(string $path, string $fallbackClassName): string
    {
        if (!is_file($path)) {
            return $fallbackClassName;
        }

        $code = file_get_contents($path);
        if (!is_string($code) || $code === '') {
            return $fallbackClassName;
        }

        $tokens = token_get_all($code);
        $namespace = '';
        $captureNamespace = false;
        $captureClass = false;

        foreach ($tokens as $token) {
            if (!is_array($token)) {
                if ($captureNamespace && ($token === ';' || $token === '{')) {
                    $captureNamespace = false;
                }
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                $captureNamespace = true;
                continue;
            }

            if ($captureNamespace && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $namespace .= $token[1];
                continue;
            }

            if ($token[0] === T_CLASS) {
                $captureClass = true;
                continue;
            }

            if ($captureClass && $token[0] === T_STRING) {
                return $namespace !== '' ? $namespace . '\\' . $token[1] : $token[1];
            }
        }

        return $fallbackClassName;
    }
}
