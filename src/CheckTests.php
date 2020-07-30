<?php

namespace Testomatio;

use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\DirectoriesSourceLocator;
use Roave\BetterReflection\Reflection\ReflectionClass;

class CheckTests
{
    protected $tests = [];

    public function analyze($directory)
    {
        $astLocator = (new BetterReflection())->astLocator();
        $directoriesSourceLocator = new DirectoriesSourceLocator([$directory], $astLocator);
        $reflector = new ClassReflector($directoriesSourceLocator);
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
            $test->update(function() use ($directory) {
                $this->file = str_replace($directory, '', $this->file);
            });
        }
    }

    public function getTests()
    {
        return $this->tests;
    }

    protected function checkTestClass(ReflectionClass $class)
    {
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
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
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        $tests = array_filter($methods, function(ReflectionMethod $method) {
            if (str_starts_with($method->getName(), '_')) {
                return false;
            }
            return true;
        });
        $this->analyzeTests($tests);
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

}