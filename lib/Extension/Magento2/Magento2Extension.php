<?php

namespace Phpactor\Extension\Magento2;

use Phpactor\Container\Container;
use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Extension\LanguageServer\LanguageServerExtension;
use Phpactor\Extension\Magento2\Walker\InvertedVarDocblockVariableWalker;
use Phpactor\Extension\WorseReflection\WorseReflectionExtension;
use Phpactor\LanguageServer\Core\Workspace\Workspace;
use Phpactor\MapResolver\Resolver;
use Phpactor\WorseReflection\Bridge\Phpactor\DocblockParser\TypeConverter;
use Phpactor\WorseReflection\Reflector;

class Magento2Extension implements Extension
{
    public function load(ContainerBuilder $container): void
    {
        $container->register(InvertedVarDocblockVariableWalker::class,
            function (Container $container) {
                return new InvertedVarDocblockVariableWalker();
            }, [WorseReflectionExtension::TAG_FRAME_WALKER => []]
        );
    }

    public function configure(Resolver $schema): void
    {
    }

    public function name(): string
    {
        return 'magento2';
    }

    private function workspace(Container $container): Workspace
    {
        return $container->expect(LanguageServerExtension::SERVICE_SESSION_WORKSPACE, Workspace::class);
    }
}
