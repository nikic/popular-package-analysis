<?php error_reporting(E_ALL);

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

require __DIR__ . '/vendor/autoload.php';

class ClassInfo {
    public $parents = [];
    public $paramNames = [];
}

class ClassNamer {
    private $anonCounter = 0;

    public function getName(Stmt\ClassLike $class) {
        if ($class->name) {
            return $class->namespacedName->toString();
        }
        return 'class@anonymous$' . $this->anonCounter++;
    }
}

$collector = new class extends PhpParser\NodeVisitorAbstract {
    private $classNamer;
    private $classStack = [];
    public $paramNames = [];

    public function __construct() {
        $this->classNamer = new ClassNamer;
    }

    public function enterNode(PhpParser\Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            $name = $this->classNamer->getName($node);
            if (isset($this->classes[$name])) {
                $classInfo = $this->classes[$name];
            } else {
                $classInfo = new ClassInfo;
                $this->classes[$name] = $classInfo;

                if ($node instanceof Stmt\Class_) {
                    if ($node->extends !== null) {
                        $classInfo->parents[] = $node->extends->toString();
                    }
                    foreach ($node->implements as $interface) {
                        $classInfo->parents[] = $interface->toString();
                    }
                } else if ($node instanceof Stmt\Interface_) {
                    foreach ($node->extends as $interface) {
                        $classInfo->parents[] = $interface->toString();
                    }
                }
            }
            $this->classStack[] = $classInfo;
        }

        if ($node instanceof Stmt\ClassMethod) {
            $classInfo = $this->getCurrentClassInfo();
            $classInfo->paramNames[$node->name->toString()] = array_map(
                fn($param) => $param->var->name, $node->params);
        }
    }

    public function leaveNode(PhpParser\Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classStack);
        }
    }

    private function getCurrentClassInfo() {
        return $this->classStack[count($this->classStack) - 1];
    }
};

$visitor = new class extends PhpParser\NodeVisitorAbstract {
    private $classNamer;
    public $path = null;
    public $code = null;
    public $classNames = [];
    public $classes;

    public function __construct() {
        $this->classNamer = new ClassNamer;
    }

    public function enterNode(PhpParser\Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            $name = $this->classNamer->getName($node);
            $this->classNames[] = $name;
        }
        if ($node instanceof Stmt\ClassMethod) {
            $className = $this->classNames[count($this->classNames) - 1];
            $classInfo = $this->classes[$className];
        }
    }

    public function leaveNode(PhpParser\Node $node) {
        if ($node instanceof Stmt\ClassLike) {
            array_pop($this->classNames);
        }
    }

    private function getCode(PhpParser\Node $node) {
        $startPos = $node->getStartFilePos();
        $endPos = $node->getEndFilePos();
        return substr($this->code, $startPos, $endPos - $startPos + 1);
    }
};

function visit(PhpParser\NodeVisitor $visitor) {
    $lexer = new PhpParser\Lexer\Emulative([
        'usedAttributes' => [
            'comments', 'startLine', 'endLine',
            'startFilePos', 'endFilePos',
        ]
    ]);
    $parser = new PhpParser\Parser\Php7($lexer);

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
}

function formatSignature(array $paramNames) {
    return implode(", ", array_map(fn($name) => "$" . $name, $paramNames));
}

visit($collector);

$classes = $collector->classes;
foreach ($classes as $className => $class) {
    foreach ($class->parents as $parentName) {
        if (!isset($classes[$parentName])) {
            //echo "Parent $parentName of $className not found\n";
            continue;
        }

        $parent = $classes[$parentName];
        foreach ($class->paramNames as $methodName => $paramNames) {
            if ($methodName == '__construct') {
                continue;
            }
            if (!isset($parent->paramNames[$methodName])) {
                continue;
            }

            $parentParamNames = $parent->paramNames[$methodName];
            $nameToPos = array_flip($paramNames);
            $parentNameToPos = array_flip($parentParamNames);

            foreach ($nameToPos as $name => $pos) {
                if (isset($parentNameToPos[$name])) {
                    $parentPos = $parentNameToPos[$name];
                    if ($pos != $parentPos) {
                        echo "Signature mismatch:\n";
                        echo "    $className::$methodName(" . formatSignature($paramNames) . ")\n";
                        echo "    $parentName::$methodName(" . formatSignature($parentParamNames) . ")\n";
                        echo "\n";
                    }
                }
            }
        }
    }
}
