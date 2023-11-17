<?php

namespace Phelixjuma\Enqueue;

use Doctrine\Common\Annotations\AnnotationReader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class EventDiscovery {

    private $annotationReader;
    private $directories = [];
    private $namespaces = [];

    public function __construct() {
        $this->annotationReader = new AnnotationReader();
    }

    /**
     * @param string $directory
     * @param string $namespace
     * @return $this
     */
    public function registerDirectory(string $directory, string $namespace): EventDiscovery
    {
        if (is_dir($directory)) {
            $this->directories[] = realpath($directory);
            $this->namespaces[] = trim($namespace, '\\');
        }
        return $this;
    }

    /**
     * @throws \ReflectionException
     */
    public function getListenersForEvent(string $eventClass): array {

        $listeners = [];

        // Extract namespace from the event class
        $eventNamespace = substr($eventClass, 0, strrpos($eventClass, '\\'));

        //print "\nEvent Class: $eventClass\n";
        //print "\nEvent Namespace: $eventNamespace\n";

        foreach ($this->directories as $index => $directory) {

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            //print "\nChecking all classes:\n";
            foreach ($iterator as $file) {

                if ($file->isFile() && $file->getExtension() === 'php') {

                    $relativePath = str_replace([$directory, '.php'], '', $file->getRealPath());
                    $className = $this->namespaces[$index] . str_replace('/', '\\', $relativePath);

                    //print "\nChecking class: $className\n";

                    if (class_exists($className, true)) {

                        $reflectionClass = new ReflectionClass($className);

                        //print "\nChecking class methods:\n";

                        if (is_subclass_of($className, ListenerInterface::class)) {

                            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

                                // This checks if the declaring class of the method is the same as the reflection class
                                if ($method->getDeclaringClass()->getName() === $reflectionClass->getName()) {

                                    $methodName = $method->getName();

                                    if (!in_array($methodName, ['__construct', 'setUp', 'tearDown'])) {

                                        //print "\nMethod: $methodName\n";

                                        $annotation = $this->annotationReader->getMethodAnnotation($method, Listener::class);

                                        if ($annotation) {

                                            $listenerEvent = strpos($annotation->for, '\\') === false
                                                ? $eventNamespace . '\\' . $annotation->for
                                                : $annotation->for;

                                            //print "\nMethod $methodName is listening to Event $listenerEvent\n";

                                            if ($listenerEvent === $eventClass) {
                                                //print "\nMethod $methodName is listening to $eventClass. We include it\n";
                                                $listeners[] = [$className, $methodName];
                                            } else {
                                                //print "\nMethod $methodName is not listening to $eventClass. We skip it\n";
                                            }

                                        } else {
                                            //print "\nMethod $methodName has no annotation\n";
                                        }
                                    }
                                }

                            }
                        }
                    }
                }
            }
        }
        //print_r($listeners);

        return $listeners;
    }
}

