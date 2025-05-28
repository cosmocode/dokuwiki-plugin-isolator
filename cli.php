<?php

use dokuwiki\Parsing\Parser;
use dokuwiki\plugin\isolator\RewriteHandler;
use splitbrain\phpcli\Options;

/**
 * DokuWiki Plugin isolator (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */
class cli_plugin_isolator extends \dokuwiki\Extension\CLIPlugin
{
    /** @inheritDoc */
    protected function setup(Options $options)
    {
        $options->setHelp('FIXME: What does this CLI do?');

        // main arguments
        $options->registerArgument('namespace', 'The namespace to isola', 'true');

        // options
        // $options->registerOption('FIXME:longOptionName', 'FIXME: helptext for option', 'FIXME: optional shortkey', 'FIXME:needs argument? true|false', 'FIXME:if applies only to subcommand: subcommandName');

        // sub-commands and their arguments
        // $options->registerCommand('FIXME:subcommandName', 'FIXME:subcommand description');
        // $options->registerArgument('FIXME:subcommandArgumentName', 'FIXME:subcommand-argument description', 'FIXME:required? true|false', 'FIXME:subcommandName');
    }

    /** @inheritDoc */
    protected function main(Options $options)
    {
        // $command = $options->getCmd()
        $arguments = $options->getArgs();

            $this->isolate($arguments[0]);
    }


    protected function isolate($namespace)
    {
        global $conf;

        $pagedir = $conf['datadir'];
        $namespacedir = utf8_encodeFN($namespace);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator("$pagedir/$namespacedir", \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $fullPath = $file->getPathname();
            if (!preg_match('/\.txt$/', $fullPath)) continue; // only process text files
            $relativePath = preg_replace('/^' . preg_quote("$pagedir/", '/') . '/', '', $fullPath);
            $pageID = pathID($relativePath);

            echo $pageID;
            $this->processPage($pageID, $namespace);
            echo "\n";
        }

    }


    protected function processPage($id, $ns)
    {
        $text = rawWiki($id);

        // Create the parser
        $Parser = new Parser(new Doku_Handler());
        $Handler = new RewriteHandler($id, $ns); // FIXME pass info via constructor


        // Use reflectiion to actually use our own handler
        $reflectParser = new ReflectionClass(Parser::class);
        $handlerProperty = $reflectParser->getProperty('handler');
        $handlerProperty->setAccessible(true);
        $handlerProperty->setValue($Parser, $Handler);


        //add modes to parser
        $modes = p_get_parsermodes();
        foreach($modes as $mode) {
            $Parser->addMode($mode['mode'], $mode['obj']);
        }

        $Parser->parse($text);
        $new = $Handler->getWikiText();
        return $new;
    }
}
