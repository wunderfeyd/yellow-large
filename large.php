<?php
// Improve file handling for large websites, https://github.com/wunderfeyd
    
class YellowLarge {
    const VERSION = "0.9.2";
    public $yellow;         // access to API
    
    // Handle initialisation
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        $this->yellow->content = new YellowContentSerialize($yellow);
        $this->yellow->media = new YellowMediaSerialize($yellow);
    }
    
    // Handle update
    public function onUpdate($action) {
        if ($action=="clean" || $action=="daily" || $action=="uninstall") {
            $statusCode = 300;
            $path = $this->yellow->system->get("coreWorkerDirectory");
            foreach ($this->yellow->toolbox->getDirectoryEntries($path, "/^large-(.*)\.raw$/", false, false) as $entry) {
                if ($action==="daily" && $this->isFileRequired($entry)) continue;
                if (!$this->yellow->toolbox->deleteFile($entry)) $statusCode = 500;
            }
            if ($statusCode==500) $this->yellow->toolbox->log("error", "Can't delete files in directory '$path'!");
        }
    }
    
    // Serialize large data structure into raw data
    public function serializeLargeData($pages, $text) {
        foreach ($pages as $page) {
            $page->yellow = null;
        }
        $rawData = "/* $text */\n".serialize($pages);
        foreach ($pages as $page) {
            $page->yellow = $this->yellow;
        }
        return $rawData;
    }
    
    // Unserialize large data structure from raw data
    public function unserializeLargeData($rawData) {
        $pages = unserialize(substrb($rawData, strposb($rawData, "\n")+1));
        foreach ($pages as $page) {
            $page->yellow = $this->yellow;
        }
        return $pages;
    }
    
    // Check if file is required
    public function isFileRequired($fileName) {
        $path = $this->yellow->system->get("coreWorkerDirectory");
        $fileData = $this->yellow->toolbox->readFile($fileName, 4096);
        if (preg_match("/^\/\* (\S*\/) \*\/\n/", $fileData, $matches)) {
            $path = $matches[1];
        }
        return is_dir($path);
    }
}

class YellowContentSerialize extends YellowContent {

    // Scan file system on demand
    public function scanLocation($location) {
        if (!isset($this->pages[$location]) && !is_string_empty($location)) {
            $this->yellow->toolbox->timerStart($time);
            $path = $this->yellow->lookup->findFileFromContentLocation($location, true);
            $id = substru(md5($location.$path), 0, 15);
            $fileName = $this->yellow->system->get("coreWorkerDirectory")."large-$id.raw";
            list($modified, $fileCount) = $this->yellow->toolbox->getDirectoryInformation($path);
            if ($fileCount>100) {
                if ($this->yellow->toolbox->getFileModified($fileName)==$modified) {
                    $fileData = $this->yellow->toolbox->readFile($fileName);
                    $this->pages[$location] = $this->yellow->extension->get("large")->unserializeLargeData($fileData);
                } else {
                    $fileData = $this->yellow->extension->get("large")->serializeLargeData(parent::scanLocation($location), $path);
                    if (is_file($fileName)) $this->yellow->toolbox->deleteFile($fileName);
                    if (!$this->yellow->toolbox->writeFile($fileName, $fileData) ||
                        !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                        $this->yellow->page->error(500, "Can't write file '$fileName'!");
                    }
                }
            }
            $this->yellow->toolbox->timerStop($time);
            if ($this->yellow->system->get("coreDebugMode")>=2) {
                echo "YellowContentSerialize::scanLocation location:$location, $fileCount files, $time ms<br>\n";
            }
        }
        return parent::scanLocation($location);
    }
    
    // Return page collection with all pages
    public function index($showInvisible = false, $multiLanguage = false) {
        $this->yellow->toolbox->timerStart($time);
        $rootLocation = $multiLanguage ? "" : $this->getRootLocation($this->yellow->page->location);
        $path = $this->yellow->system->get("coreContentDirectory");
        $arguments = "index content showInvisible:".($showInvisible?"1":"0")." multiLanguage:".($multiLanguage?"1":"0");
        $id = substru(md5($rootLocation.$arguments), 0, 15);
        $fileName = $this->yellow->system->get("coreWorkerDirectory")."large-$id.raw";
        list($modified, $fileCount) = $this->yellow->toolbox->getDirectoryInformationRecursive($path);
        if ($this->yellow->toolbox->getFileModified($fileName)==$modified) {
            $pages = new YellowPageCollection($this->yellow);
            $fileData = $this->yellow->toolbox->readFile($fileName);
            $pages->exchangeArray($this->yellow->extension->get("large")->unserializeLargeData($fileData));
        } else {
            $pages = $this->getChildrenRecursive($rootLocation, $showInvisible);
            $fileData = $this->yellow->extension->get("large")->serializeLargeData($pages->getArrayCopy(), $arguments);
            if (is_file($fileName)) $this->yellow->toolbox->deleteFile($fileName);
            if (!$this->yellow->toolbox->writeFile($fileName, $fileData) ||
                !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                $this->yellow->page->error(500, "Can't write file '$fileName'!");
            }
        }
        $this->yellow->toolbox->timerStop($time);
        if ($this->yellow->system->get("coreDebugMode")>=2) {
            echo "YellowContentSerialize::index $fileCount files, $time ms<br>\n";
        }
        return $pages;
    }
}
    
class YellowMediaSerialize extends YellowMedia {

    // Return page collection with all media files
    public function index($showInvisible = false, $multiPass = false) {
        $this->yellow->toolbox->timerStart($time);
        $path = $this->yellow->system->get("coreMediaDirectory");
        $arguments = "index media showInvisible:".($showInvisible?"1":"0");
        $id = substru(md5($arguments), 0, 15);
        $fileName = $this->yellow->system->get("coreWorkerDirectory")."large-$id.raw";
        list($modified, $fileCount) = $this->yellow->toolbox->getDirectoryInformationRecursive($path);
        if ($this->yellow->toolbox->getFileModified($fileName)==$modified) {
            $pages = new YellowPageCollection($this->yellow);
            $fileData = $this->yellow->toolbox->readFile($fileName);
            $pages->exchangeArray($this->yellow->extension->get("large")->unserializeLargeData($fileData));
        } else {
            $pages = $this->getChildrenRecursive("", $showInvisible);
            $fileData = $this->yellow->extension->get("large")->serializeLargeData($pages->getArrayCopy(), $arguments);
            if (is_file($fileName)) $this->yellow->toolbox->deleteFile($fileName);
            if (!$this->yellow->toolbox->writeFile($fileName, $fileData) ||
                !$this->yellow->toolbox->modifyFile($fileName, $modified)) {
                $this->yellow->page->error(500, "Can't write file '$fileName'!");
            }
        }
        $this->yellow->toolbox->timerStop($time);
        if ($this->yellow->system->get("coreDebugMode")>=2) {
            echo "YellowMediaSerialize::index $fileCount files, $time ms<br>\n";
        }
        return $pages;
    }
}
