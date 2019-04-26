<?php error_reporting(E_ALL);

use PhpParser\Node\Expr;

require __DIR__ . '/../PHP-Parser/vendor/autoload.php';

$lexer = new PhpParser\Lexer\Emulative([
    'usedAttributes' => [
        'comments', 'startLine', 'endLine',
        'startFilePos', 'endFilePos',
    ]
]);
$parser = new PhpParser\Parser\Php7($lexer);

$visitor = new class extends PhpParser\NodeVisitorAbstract {
    public $path = null;
    public $code = null;
    public function enterNode(PhpParser\Node $node) {
        if (!$node instanceof Expr\Ternary) {
            return;
        }

        $cond = $node->cond;
        if (!$cond instanceof Expr\Ternary) {
            return;
        }

        if ($node->if === null && $cond->if === null) {
            return;
        }

        echo $this->path . ":" . $node->getStartLine() . "\n";

        // Inaccurate...
        $endPos = $cond->getEndFilePos();
        if ($this->code[$endPos+1] === ')') {
            echo "With parens\n\n";
        } else {
            echo "WITHOUT parens\n\n";
        }

        /*if ($node instanceof Expr\BinaryOp\Plus ||
            $node instanceof Expr\BinaryOp\Minus
        ) {
            if (!$node->left instanceof Expr\BinaryOp\Concat) {
                return;
            }
        } else if ($node instanceof Expr\BinaryOp\ShiftLeft ||
                   $node instanceof Expr\BinaryOp\ShiftRight
        ) {
            if (!$node->left instanceof Expr\BinaryOp\Concat &&
                !$node->right instanceof Expr\BinaryOp\Concat
            ) {
                return;
            }
        } else {
            return;
        }

        echo $this->path . ":" . $node->getStartLine() . "\n";
        echo "POSSIBLE\n\n";*/
    }
};

$traverser = new PhpParser\NodeTraverser;
$traverser->addVisitor($visitor);

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/sources'),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$i = 0;
foreach ($it as $f) {
    $path = $f->getPathName();
    if (!preg_match('/\.php$/', $path)) {
        continue;
    }

    if (++$i % 1000 == 0) {
        echo $i . "\n";
    }

    $code = file_get_contents($path);
    try {
        $stmts = $parser->parse($code);
    } catch (PhpParser\Error $e) {
        echo $path . "\n";
        echo "Parse error: " . $e->getMessage() . "\n";
        continue;
    }

    $visitor->path = $path;
    $visitor->code = $code;
    $traverser->traverse($stmts);
}
