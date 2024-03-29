<?php

namespace Testomatio;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\SourceLocator\SourceStubber\ReflectionSourceStubber;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\ComposerSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\FileIteratorSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use Symfony\Component\Console\Output\OutputInterface;


class CheckTests
{
    /**
     * @var TestData[]
     */
    protected $tests = [];
    protected $errors = [];
    /**
     * @var OutputInterface
     */
    private $output;


    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }


    public function analyze($directory)
    {
        $absolutePath = $this->makeAbsolutePath($directory);
        $astLocator = (new BetterReflection())->astLocator();

        $sources = [];

        try {
            $sources[] = new AutoloadSourceLocator($astLocator);
            $sources[] = new PhpInternalSourceLocator($astLocator, new ReflectionSourceStubber());
        } catch (\Throwable $e) {
            $this->errors[] = "can't use autoloader, skipping classes from autoloader" . $e->getMessage();
        }

        $directory = new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::FOLLOW_SYMLINKS);
        $filter = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use (&$foundTests) {
         // Skip hidden files and directories.
         if ($current->getFilename()[0] === '.') {
           return false;
         }
         if ($current->isDir()) {
             if ($current->getFilename() === 'vendor') {
                 return false;
             }
             // skip special dirs of codeception
             if (str_starts_with($current->getFilename(), '_')) {
                  return false;
                  }
                 return true;
             }
             if (str_ends_with($current->getFilename(), 'Cest.php')) {
                 return true;
             }
             if (str_ends_with($current->getFilename(), 'Test.php')) {
                 return true;
             }
             return false;
        });

        $iterator = new \RecursiveIteratorIterator($filter);

        $sourceLocator = new FileIteratorSourceLocator($iterator, $astLocator);
        $sources[] = $sourceLocator;

        $reflector = new DefaultReflector(new AggregateSourceLocator($sources));
        $classes = $reflector->reflectAllClasses();

        $this->output->writeln(sprintf("%d test classes found. Processing...", count($classes)));

        foreach ($classes as $class) {
            if ($class->isAbstract()) {
                continue;
            }

            if (str_ends_with($class->getShortName(), 'Cest')) {
                $this->checkCestClass($class);
            }
            if (str_ends_with($class->getShortName(), 'Test')) {
                $this->checkTestClass($class);
            }
        }

        foreach ($this->tests as $test) {
            $test->removeAbsolutePath($absolutePath);
        }
    }

    private function makeAbsolutePath($path)
    {
        if (DIRECTORY_SEPARATOR === '/') {
            $isAbsolute = (substr($path, 0, 1) === DIRECTORY_SEPARATOR);
        } else {
            $isAbsolute = preg_match('#^[A-Z]:(?![^/\\\])#i', $path) === 1;
        }
        if (!$isAbsolute) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }
        // resolve relative path parts, like ./ and ../
        if (($realpath = realpath($path)) !== false) {
            $path = $realpath;
        }
        return $path;
    }

    protected function checkCestClass(ReflectionClass $class)
    {
        $methods = $this->loadMethods($class);
        $tests = array_filter($methods, function(ReflectionMethod $method) {
            if (str_starts_with($method->getName(), '_')) {
                return false;
            }
            return true;
        });
        $this->analyzeTests($class, $tests);
    }

    private function loadMethods(ReflectionClass $class)
    {
        try {
            return $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        } catch (\Exception $exception) {
            $className = $class->getShortName();
            $this->errors[] = "Could not load class '$className' -> ' " . $exception->getMessage();
            return [];
        }
    }

    protected function analyzeTests(ReflectionClass $class, $methods)
    {
        foreach ($methods as $method) {
            /** @var $method ReflectionMethod  **/
            if ($method->isConstructor()) {
                continue;
            }
            $this->tests[] = new TestData($class, $method);
        }
    }

    protected function checkTestClass(ReflectionClass $class)
    {
        $methods = $this->loadMethods($class);
        $tests = array_filter($methods, function(ReflectionMethod $method) {
            if (str_starts_with($method->getName(), 'test')) {
                return true;
            }
            if (str_contains($method->getDocComment(), '@test ')) {
                return true;
            }
            return false;
        });
        $this->output->write('.');
        $this->analyzeTests($class, $tests);
    }

    /**
     * @return TestData[]
     */
    public function getTests()
    {
        return $this->tests;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

}
