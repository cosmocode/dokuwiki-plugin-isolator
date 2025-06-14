<?php

use dokuwiki\Extension\CLIPlugin;
use dokuwiki\plugin\isolator\Processor;
use splitbrain\phpcli\Options;

/**
 * DokuWiki Plugin isolator (CLI Component)
 *
 * Command-line interface for the isolator plugin that ensures media data
 * of a given namespace is isolated within that namespace. Provides options
 * for dry-run mode and strict namespace matching.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */
class cli_plugin_isolator extends CLIPlugin
{
    /** @inheritDoc */
    protected function setup(Options $options)
    {
        $options->setHelp('Ensure the media data of the given namespace is isolated in that given namespace.');

        // main arguments
        $options->registerArgument('namespace', 'The namespace to isolate', 'true');

        // options
        $options->registerOption(
            'dry-run',
            'Only show what would happen, do not actually do anything',
            'd'
        );
        $options->registerOption(
            'strict',
            'Media must be in exactly the same namespace as the page, not just within the given namespace',
            's'
        );
    }

    /** @inheritDoc */
    protected function main(Options $options)
    {
        $arguments = $options->getArgs();
        $namespace = $arguments[0];

        $dryRun = $options->getOpt('dry-run');
        $strict = $options->getOpt('strict');

        $processor = new Processor(
            $namespace,
            $dryRun,
            $strict,
            $this
        );

        $processor->isolate();
    }
}
