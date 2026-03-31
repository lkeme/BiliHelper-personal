<?php declare(strict_types=1);

namespace Bhp\Remote;

use Bhp\Config\Config;
use Bhp\Env\Env;
use Bhp\Util\GhProxy\GhProxy;

final class RemoteResourceResolver
{
    private const DEFAULT_OWNER = 'lkeme';
    private const DEFAULT_REPOSITORY = 'BiliHelper-personal';

    public function branch(): string
    {
        $overrideBranch = $this->overrideBranch();
        if ($overrideBranch !== null) {
            return $overrideBranch;
        }

        $branch = $this->configuredBranch();
        if ($branch !== null) {
            return $branch;
        }

        return 'master';
    }

    public function overrideBranch(): ?string
    {
        $branch = trim((string)getenv('BRANCH'));
        if ($branch === '') {
            return null;
        }

        return $branch;
    }

    public function configuredBranch(): ?string
    {
        $branch = trim((string)Config::getInstance()->get('app.branch', 'master'));
        if ($branch === '') {
            return null;
        }

        return $branch;
    }

    public function resourceRawUrl(string $resourcePath): string
    {
        return $this->rawUrl('resources/' . ltrim(str_replace('\\', '/', $resourcePath), '/'));
    }

    public function rawUrl(string $path): string
    {
        [$owner, $repository] = $this->repository();
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        return GhProxy::mirror(sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            $owner,
            $repository,
            $this->branch(),
            $normalizedPath,
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function repository(): array
    {
        $source = trim((string)Env::getInstance()->app_source);
        if ($source !== '' && preg_match('~github\.com/([^/]+)/([^/]+?)(?:\.git)?/?$~i', $source, $matches) === 1) {
            return [$matches[1], $matches[2]];
        }

        return [self::DEFAULT_OWNER, self::DEFAULT_REPOSITORY];
    }
}
