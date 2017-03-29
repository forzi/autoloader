<?php

namespace stradivari\autoloader;

class Autoloader {
    public $base;
    public $vendor;
    public $extensions = ['php'];
    public $composerFileMap = ['files', 'classmap', 'psr0' => 'namespaces', 'psr4'];
    public $environments = [
        'files' => [],
        'classmap' => [],
        'psr0' => [],
        'psr4' => []
    ];
	
    public function inheritComposer() {
        foreach ($this->composerFileMap as $key => $file) {
            if (!is_string($key)) {
                $key = $file;
            }
            $filename = $this->vendor . '/composer/autoload_' . $file . '.php';
            if (is_readable($filename)) {
                $this->environments[$key] += include($filename);
            }
        }
        return $this;
    }
	
    public function register() {
        $this->loadFiles();
        spl_autoload_register([$this, 'autoloader'], true, true);
    }
	
    public function unregister() {
        spl_autoload_unregister([$this, 'autoloader']);
    }
	
    private function autoloader($class) {
        if ($this->loadClassmap($class)) {
            return true;
        }
        if ($this->loadNamespace($class)) {
            return true;
        }
        return false;
    }
	
    private function loadFiles() {
        foreach ($this->environments['files'] as $file) {
            $file = $this->addVendor($file);
            if ($file) {
                require_once($file);
            }
        }
    }
	
    private function loadNamespace($class) {
        $path = explode('\\', $class);
        $last = array_pop($path);
        $last = explode('_', $last);
        $path = array_merge($path, $last);
        $path = implode('/', $path);
        return $this->isClassInFile($class, $this->searchPhpFile($path));
    }
	
    private function loadClassmap($class) {
        if (array_key_exists($class,
                $this->environments['classmap']) && file_exists($this->environments['classmap'][$class])
        ) {
            $file = $this->environments['classmap'][$class];
            $this->addVendor($file);
            return $this->isClassInFile($class, $file);
        }
        return false;
    }
	
    private function isClassInFile($class, $file) {
        if (!$file) {
            return false;
        }
        require_once $file;
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
    }
	
    public function searchPhpFile($path) {
        foreach ($this->extensions as $extension) {
            $file = $this->searchFile("{$path}.{$extension}");
            if ($file) {
                return $file;
            }
        }
        return false;
    }
	
    public function searchFile($path) {
        $path = str_replace('\\', '/', $path);
        foreach (array('psr0', 'psr4') as $curMap) {
            $result = $this->psrSearch($path, $curMap);
            if ($result) {
                return $result;
            }
        }
        return false;
    }
	
    private function psrSearch($path, $map) {
        foreach ($this->environments[$map] as $prefix => $environments) {
            $prefix = str_replace('\\', '/', $prefix);
            $prefixPos = $prefix ? strpos($path, $prefix) : 0;
            if ($prefixPos !== 0) {
                continue;
            }
            foreach ($environments as $environment) {
                $realPath = $this->getRealPath($path, $map, $prefix, $environment);
                $file = $this->addVendor($realPath);
                if ($file) {
                    return $file;
                }
            }
        }
        return false;
    }
	
    private function getRealPath($path, $map, $prefix, $environment) {
        if ($map == 'psr0') {
            return "{$environment}/{$path}";
        }
        return str_replace($prefix, $environment . '/', $path);
    }
	
    private function addVendor($path) {
        $vendors = [
            '',
            $this->base,
            $this->vendor
        ];
        $path = str_replace('\\', '/', $path);
        foreach (array_unique($vendors) as $vendor) {
            $currentPath = realpath($vendor . $path);
            if ($currentPath && is_file($currentPath)) {
                return $currentPath;
            }
        }
        return false;
    }
}
