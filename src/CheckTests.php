<?php

namespace Testomatio;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\SourceLocator\Type\FileIteratorSourceLocator;


class CheckTests
{
    protected $tests = [];
    protected $errors = [];

    public function analyze($directory)
    {
        $absolutePath = $this->makeAbsolutePath($directory);
        $astLocator = (new BetterReflection())->astLocator();

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

        $reflector = new ClassReflector($sourceLocator);
        $classes = $reflector->getAllClasses();

        foreach ($classes as $class) {
            if (str_ends_with($class->getShortName(), 'Cest')) {
                $this->checkCestClass($class);
            }
            if (str_ends_with($class->getShortName(), 'Test')) {
                $this->checkTestClass($class);
            }
        }

        foreach ($this->tests as $test) {
            $test->update(function() use ($absolutePath) {
                $this->file = trim(str_replace($absolutePath, '', $this->file), DIRECTORY_SEPARATOR);
            });
        }
    }

    public function getTests()
    {
        return $this->tests;
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
        $this->analyzeTests($tests);
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
        $this->analyzeTests($tests);
    }

    private function loadMethods(ReflectionClass $class)
    {
        try {
            return $class->getImmediateMethods(\ReflectionMethod::IS_PUBLIC);
        } catch (\Exception $exception) {
            $className = $class->getShortName();
            $this->errors[] = "Could not load class '$className' -> ' " . $exception->getMessage();
            return [];
        }
    }

    protected function analyzeTests($methods)
    {
        foreach ($methods as $method) {
            /** @var $method ReflectionMethod  **/
            if ($method->isConstructor()) {
                continue;
            }
            $this->tests[] = new TestData($method);
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
        return $path;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

}