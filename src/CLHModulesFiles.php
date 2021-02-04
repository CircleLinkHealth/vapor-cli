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
        $finder = (new Finder())
            ->in($path);

        foreach (explode("\n", file_get_contents('monorepo-modules.txt')) as $dir) {
            $finder->path('/'.preg_quote($dir, '/').'/');
        }

        return  $finder
                ->notPath('/^'.preg_quote('Tests', '/').'/')
                ->exclude('node_modules')
                ->exclude('vendor')
                ->ignoreVcs(true)
                ->ignoreDotFiles(true);
    }
}
