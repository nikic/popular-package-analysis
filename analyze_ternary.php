<?php error_reporting(E_ALL);

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

require __DIR__ . '/vendor/autoload.php';

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
    public $totalArrayDimFetches = 0;
    public $alternativeArrayDimFetches = 0;
    public $totalClasses = 0;
    public $reservedClassNames = 0;

    public $fnCalls = [];
    public $traits = [];
    public $inTrait = false;
    public $i = 0;
    public $switches = [];

    public function enterNode(PhpParser\Node $node) {
        if ($node instanceof Stmt\Switch_) {
            $this->i++;
            $this->switches[] =
                "Switch $this->i at $this->path:" . $node->getStartLine() . "\n" .
                $this->getCode($node) . "\n\n";
        }

        /*if ($node instanceof Stmt\Trait_) {
            $this->inTrait = true;
            return;
        }

        if ($node instanceof Stmt\TraitUse) {
            if (count($node->traits) == 1) {
                return;
            }

            foreach ($node->adaptations as $adaptation) {
                if ($adaptation instanceof Stmt\TraitUseAdaptation\Alias
                        && $adaptation->trait === null) {
                    echo $this->path . ":" . $node->getStartLine() . "\n";
                    echo $this->getCode($node) . "\n\n";
                }
            }
        }*/

        /*if ($node instanceof Stmt\Class_ && $node->isAnonymous()) {
            echo $this->path . ":" . $node->getStartLine() . "\n";
            echo $this->getCode($node) . "\n\n";
        }*/

        /*if ($this->inTrait && $node instanceof Stmt\ClassMethod) {
            if ($node->isAbstract()) {
                echo $this->path . ":" . $node->getStartLine() . "\n";
                echo "    " . $this->getCode($node) . "\n";
            }
        }*/

        /*if (!$node instanceof Expr\ShellExec) {
            return;
        }

        echo $this->path . ":" . $node->getStartLine() . "\n";
        echo "    " . $this->getCode($node) . "\n";*/

        /*if (!$node instanceof Expr\FuncCall || !$node->name instanceof Name) {
            return;
        }

        $name = $node->name->toLowerString();
        $this->fnCalls[$name] = ($this->fnCalls[$name] ?? 0) + 1;*/

        /*if (!$node instanceof Stmt\ClassLike || !isset($node->name)) {
            return;
        }

        $this->totalClasses++;

        if (!isset($node->name)) {
            return;
        }

        $primitiveTypes = [
            'bool' => true,
            'false' => true,
            'float' => true,
            'int' => true,
            'null' => true,
            'string' => true,
            'true' => true,
            'void' => true,
            'iterable' => true,
            'object' => true,
        ];
        if (!isset($primitiveTypes[$node->name->toLowerString()])) {
            return;
        }

        $this->reservedClassNames++;
        echo $this->path . ":" . $node->getStartLine() . "\n";
        echo "    " . $node->name . "\n";*/
        /*if (!$node instanceof Expr\ArrayDimFetch) {
            return;
        }

        $this->totalArrayDimFetches++;
        if ($this->code[$node->getEndFilePos()] !== '}') {
            return;
        }

        // Special case: Don't recognize ${foo[x]}
        if (substr($this->code, $node->getStartFilePos(), 2) === '${') {
            return;
        }

        $this->alternativeArrayDimFetches++;
        echo $this->path . ":" . $node->getStartLine() . "\n";
        echo "    " . $this->getCode($node) . "\n";*/

        /*if (!$node instanceof Expr\Ternary) {
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
        }*/

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

    public function leaveNode(PhpParser\Node $node) {
        if ($node instanceof Stmt\Trait_) {
            $this->inTrait = false;
        }
    }

    private function getCode(PhpParser\Node $node) {
        $startPos = $node->getStartFilePos();
        $endPos = $node->getEndFilePos();
        return substr($this->code, $startPos, $endPos - $startPos + 1);
    }
};

$traverser = new PhpParser\NodeTraverser;
$traverser->addVisitor(new PhpParser\NodeVisitor\NameResolver);
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
        fwrite(STDERR, $i . "\n");
    }

    $code = file_get_contents($path);
    try {
        $stmts = $parser->parse($code);
    } catch (PhpParser\Error $e) {
        fwrite(STDERR, $path . "\n");
        fwrite(STDERR, "Parse error: " . $e->getMessage() . "\n");
        continue;
    }

    $visitor->path = $path;
    $visitor->code = $code;
    $traverser->traverse($stmts);
}

//echo "Total array dim fetches: ", $visitor->totalArrayDimFetches, "\n";
//echo "Alternative array dim fetches: ", $visitor->alternativeArrayDimFetches, "\n";
//echo "Total classes: ", $visitor->totalClasses, "\n";
//echo "Total reserved class names: ", $visitor->reservedClassNames, "\n";
//echo json_encode($visitor->fnCalls);
//echo json_encode($visitor->traits);

$switches = $visitor->switches;
shuffle($switches);
echo implode($switches);
