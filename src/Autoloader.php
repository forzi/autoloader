<?php

namespace stradivari\autoloader;

use function stradivari\functions\left_cut;

class Autoloader {
    public $onLoad;
    public $vendor;
    public $extensions = ['php', 'inc'];
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
            if (file_exists($filename)) {
                $this->environments[$key] += include($filename);
            }
        }
    }
    public function register() {
        $this->loadFiles();
        spl_autoload_register([$this, 'autoloader'], true, true);
    }
    public function unregister() {
        spl_autoload_unregister([$this, 'autoloader']);
    }
    private function autoloader($class) {
        $result = $this->loadClassmap($class);
        $result = $result ?: $this->loadNamespace($class);
        if (!$result) {
            return false;
        }
        $onLoad = $this->onLoad;
        if (is_callable($onLoad)) {
            $onLoad($class);
        }
        return true;
    }
    private function loadFiles() {
        foreach ( $this->environments['files'] as $file ) {
            $file = $this->addVendor($file);
            if (is_file($file)) {
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
        if (!array_key_exists($class, $this->environments['classmap'])) {
            return false;
        }
        if (!file_exists($this->environments['classmap'][$class])) {
            return false;
        }
        $file = $this->environments['classmap'][$class];
        $this->addVendor($file);
        return $this->isClassInFile($class, $file);
    }
    private function isClassInFile($class, $file) {
        if (!is_readable($file)) {
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
        foreach (['psr0', 'psr4'] as $curMap) {
            $result = $this->psrSearch($path, $curMap);
            if ($result) {
                return $result;
            }
        }
        return false;
    }
    private function psrSearch($path, $map) {
        foreach ($this->environments[$map] as $prefix => $environments ) {
            $prefix = str_replace('\\', '/', $prefix);
            foreach ($environments as $environment) {
                $prefixPos = $prefix ? strpos($path, $prefix) : 0;
                if ($prefixPos === 0) {
                    $method = "{$map}Search";
                    $file = $this->{$method}($prefix, $environment.'/', $path);
                    if ($file) {
                        return $file;
                    }
                }
            }
        }
        return false;
    }
    private function psr0Search($prefix, $environment, $path) {
        $realPath = "{$environment}/{$path}";
        $realPath = $this->addVendor($realPath);
        return realpath($realPath);
    }
    private function psr4Search($prefix, $environment, $path) {
        $realPath = str_replace($prefix, $environment, $path);
        $realPath = $this->addVendor($realPath);
        return realpath($realPath);
    }
    private function addVendor($path) {
        $vendor = $this->vendor;
        if (!$vendor) {
            return $path;
        }
        $vendor = rtrim($vendor, '/');
        $vendor = rtrim($vendor, '\\');
        $vendor .= '/';
        $path = str_replace('\\', '/', $path);
        $path = left_cut($path, $vendor);
        $path = $vendor . $path;
        return $path;
    }
}
