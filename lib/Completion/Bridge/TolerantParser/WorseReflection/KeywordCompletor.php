<?php

declare(strict_types=1);

namespace Phpactor\Completion\Bridge\TolerantParser\WorseReflection;

use Generator;
use Microsoft\PhpParser\Node;
use Phpactor\Completion\Bridge\TolerantParser\CompletionContext;
use Phpactor\Completion\Bridge\TolerantParser\TolerantCompletor;
use Phpactor\Completion\Core\Suggestion;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;

class KeywordCompletor implements TolerantCompletor
{
    public function complete(Node $node, TextDocument $source, ByteOffset $offset): Generator
    {
        if (CompletionContext::classClause($node, $offset)) {
            yield from $this->keywords(['implements', 'extends']);
            return true;
        }

        if (
            CompletionContext::classMembersBody($node->parent)
        ) {
            yield from $this->keywords([
                'function',
                'const',
            ]);
            return true;
        }

        if (CompletionContext::classMembersBody($node)) {
            yield from $this->keywords(['private', 'protected', 'public']);
            return true;
        }

        return true;
    }

    /**
     * @return Generator<Suggestion>
     * @param string[] $keywords
     */
    private function keywords(array $keywords): Generator
    {
        foreach ($keywords as $keyword) {
            yield Suggestion::createWithOptions($keyword, [
                'type' => Suggestion::TYPE_KEYWORD,
                'priority' => Suggestion::PRIORITY_HIGH,
            ]);
        }
    }
}
