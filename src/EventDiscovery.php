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

        print "\nEvent Class: $eventClass\n";
        print "\nEvent Namespace: $eventNamespace\n";

        foreach ($this->directories as $index => $directory) {

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($iterator as $file) {

                if ($file->isFile() && $file->getExtension() === 'php') {

                    $relativePath = str_replace([$directory, '.php'], '', $file->getRealPath());
                    $className = $this->namespaces[$index] . str_replace('/', '\\', $relativePath);

                    print "\nClass Name: $className\n";

                    if (class_exists($className, true)) {

                        $reflectionClass = new ReflectionClass($className);

                        if (is_subclass_of($className, ListenerInterface::class)) {

                            foreach ($reflectionClass->getMethods() as $method) {

                                $methodName = $method->getName();

                                if (!in_array($methodName, ['__construct', 'setUp', 'tearDown'])) {

                                    print "\nClass Name: $className. Class method: $methodName\n";

                                    $annotation = $this->annotationReader->getMethodAnnotation($method, Listener::class);

                                    if ($annotation) {

                                        $listenerEvent = strpos($annotation->for, '\\') === false
                                            ? $eventNamespace . '\\' . $annotation->for
                                            : $annotation->for;

                                        print "\nClass Name: $className. Class method: $methodName. Listener Event: $listenerEvent\n";

                                        if ($listenerEvent === $eventClass) {
                                            $listeners[] = [$className, $methodName];
                                        }

                                    } else {
                                        print "\nClass Name: $className. Class method: $method has no annotation\n";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $listeners;
    }
}

