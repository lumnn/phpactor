<?php

namespace Phpactor\Extension\Magento2\Walker;

use Microsoft\PhpParser\Node;
use Phpactor\WorseReflection\Bridge\Phpactor\DocblockParser\TypeConverter;
use Phpactor\WorseReflection\Bridge\TolerantParser\Reflection\ReflectionScope;
use Phpactor\WorseReflection\Core\DocBlock\DocBlockFactory;
use Phpactor\WorseReflection\Core\DocBlock\DocBlockVar;
use Phpactor\WorseReflection\Core\Inference\Frame;
use Phpactor\WorseReflection\Core\Inference\FrameResolver;
use Phpactor\WorseReflection\Core\Inference\Variable as PhpactorVariable;
use Phpactor\WorseReflection\Core\Inference\Walker;
use Phpactor\WorseReflection\Core\TypeFactory;

class InvertedVarDocblockVariableWalker implements Walker
{
    public function __construct(
    ) {
    }

    public function nodeFqns(): array
    {
        return [];
    }

    public function enter(FrameResolver $resolver, Frame $frame, Node $node): Frame
    {
        if (!str_ends_with($node->getUri(), '.phtml')) {
            return $frame;
        }

        $this->injectVariablesFromIncorrectDocComment($resolver, $frame, $node);

        return $frame;
    }

    public function exit(FrameResolver $resolver, Frame $frame, Node $node): Frame
    {
        return $frame;
    }

    private function injectVariablesFromIncorrectDocComment(
        FrameResolver $resolver,
        Frame $frame,
        Node $node,
    ) {
        $comment = $node->getLeadingCommentAndWhitespaceText();

        if (!$comment) {
            return;
        }

        preg_match_all('/\*\s+@var\s+\$([a-z]+)\s+([a-z0-9|?\\\]+)/i', $comment, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return;
        }

        foreach ($matches as $match) {
            $name = $match[1];
            $type = TypeFactory::fromString($match[2], $resolver->reflector());

            $frame->locals()->add(
                new PhpactorVariable(
                    name: $match[1],
                    offset: $node->getStartPosition(),
                    type: $type,
                    wasAssigned: false,
                    wasDefined: true
                ),
                $node->getStartPosition()
            );
            $frame->varDocBuffer()->set('$' . $name, $type);
        }

        // $newComment = "/** \n".
        //     implode(
        //         "\n",
        //         array_map($matches, fn ($match) => " * @var {$match[2]} {$match[1]}")
        //     )
        //     .'*/';

        // $docblock = $this->docblockFactory->create($newComment, $scope);

        // if (false === $docblock->isDefined()) {
        //     return null;
        // }

        // $resolvedTypes = [];

        // /* @var DocBlockVar $var */
        // foreach ($docblock->vars() as $var) {
        //     $type = $var->type();

        //     if (empty($var->name())) {
        //         return $type;
        //     }

        //     // there's a chance this will be redefined later, but define it now
        //     // to ensure that type assertions can find a previous variable
        //     $frame->locals()->add(
        //         new PhpactorVariable(
        //             name: $var->name(),
        //             offset: $node->getStartPosition(),
        //             type: $type,
        //             wasAssigned: false,
        //             wasDefined: true
        //         ),
        //         $node->getStartPosition()
        //     );
        //     $frame->varDocBuffer()->set('$' . $var->name(), $type);
        // }

        // return null;
    }
}
