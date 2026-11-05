<?php

declare(strict_types=1);

namespace SimonSchaufi\Scanner\Php\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * @deprecated This Visitor is currently not in use
 */
final class ParentConnector extends NodeVisitorAbstract
{
    /**
     * @var Node[]
     */
    private array $stack = [];

    /**
     * @param Node[] $nodes Array of nodes
     * @return null|Node[] Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->stack = [];
        return null;
    }

    /**
     * @return null|int|Node|Node[] Replacement node (or special return value)
     */
    public function enterNode(Node $node)
    {
        if ($this->stack !== []) {
            $node->setAttribute('parent', $this->stack[count($this->stack) - 1]);
        }
        $this->stack[] = $node;
        return null;
    }

    /**
     * @return null|int|Node|Node[] Replacement node (or special return value)
     */
    public function leaveNode(Node $node)
    {
        array_pop($this->stack);
        return null;
    }
}
