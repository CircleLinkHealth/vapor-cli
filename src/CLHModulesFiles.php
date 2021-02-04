<?php

namespace Laravel\VaporCli;

use Symfony\Component\Finder\Finder;

class CLHModulesFiles
{
    /**
     * Get an application Finder instance.
     *
     * @param string $path
     *
     * @return \Symfony\Component\Finder\Finder
     */
    public static function get($path)
    {
        return (new Finder())
                ->in($path)
                ->directories()
                ->name(explode("\n", file_get_contents('monorepo-modules.txt')))
                ->notPath('/^'.preg_quote('tests', '/').'/')
                ->exclude('node_modules')
                ->exclude('vendor')
                ->ignoreVcs(true)
                ->ignoreDotFiles(true);
    }
}
