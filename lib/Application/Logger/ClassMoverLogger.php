<?php

namespace Phpactor\Application\Logger;

use Phpactor\Filesystem\Domain\FilePath;
use Phpactor\ClassMover\Domain\FoundReferences;
use Phpactor\ClassMover\Domain\Name\FullyQualifiedName;

interface ClassMoverLogger
{
    public function moving(FilePath $srcPath, FilePath $destPath);

    public function replacing(FilePath $path, FoundReferences $references, FullyQualifiedName $replacementName);
}
