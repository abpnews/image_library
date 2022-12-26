<?php
error_reporting(E_ALL & ~E_NOTICE);
//error_reporting(E_ALL);
require_once 'config.php';
require_once 'WhImage.php';
require_once './UserAgentParser.php';

use cdn_service\WhImage;

$params = $_REQUEST;
$reqPath = urldecode($_SERVER['REQUEST_URI']);
$uriInfo = parse_url($reqPath);
$pathInfo = pathinfo($uriInfo['path']);

$ua = parse_user_agent();

/** If WEBP_SUPPORT_ENABLE true and browser is chrome then webp param will be automatically set */
if( (WEBP_SUPPORT_ENABLE || isset($params['webp'])) && (!empty($ua['browser']) && strtolower($ua['browser']) == 'chrome')){
    $params['webp'] = 1;
    $params['q'] = 75; //setting quality to 75 only for webp
}

/*
$params = $_REQUEST;
$uriInfo = parse_url($_SERVER['REQUEST_URI']);
$pathInfo = pathinfo($uriInfo['path']);
*/
if (!empty($pathInfo['basename'])) {
    $reqFile = $uriInfo['path'];
    $mediaHashPath = rtrim(rtrim($reqFile,$pathInfo['basename']),"/");
    $srcMedia = WH_IMG_BASE_PATH . $reqFile;
    $extension = $pathInfo['extension'];
    if (is_file($srcMedia) && is_readable($srcMedia)) {
        try {
            $cacheFile = genrateCacheFileName($reqFile, $params);
            if(isset($params['rmcache'])){
                removeCache($pathInfo['basename'],$mediaHashPath);
            }
            if (isset($params['nc']) || !isCached($cacheFile, $mediaHashPath)) {
                $resizer = processMedia($srcMedia, $params);
                storeCache($resizer, $cacheFile, $mediaHashPath);
                serveCache($cacheFile, $mediaHashPath);
            } else if (isCached($cacheFile, $mediaHashPath)) {
                serveCache($cacheFile, $mediaHashPath);
            } else {
                show404();
            }
        } catch (\Exception $ex) {
            showException($ex);
        }
    } 
    else{
     show404();
    }	
} else if (isHome()) {
    showHome();
} else {
    show404();
}


/**
 * process media for resize 
 * @param type $srcMedia
 * @param type $params
 * @return \Resizer
 */
function processMedia($srcMedia, $params) {
    $resizer = new WhImage($srcMedia, $params);
    if($resizer->resize()){
        return $resizer;
    }
    else{
        show404();
        exit;
    }
    //responseHeaderSet('success');
    //$resizer->output();
}

/**
 * serve file from cache
 * @param type $cacheFile
 */
function serveCache($cacheFile, $mediaHashPath) {
    //clearstatcache();
    $file = WH_IMG_CACHE_PATH . $mediaHashPath .DS . $cacheFile;
    if (is_file($file)) {        
        $fileType = mime_content_type($file);
        responseHeaderSet('success');
        header('Content-Type: ' . $fileType);
        header('Content-Length: ' . filesize($file));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($file)));
        readfile($file);
        exit;
    } else {
        show404();
        exit;
    }
}

/**
 * save file as cached object
 * @param \Resizer $resizer
 * @param string $cacheFile new cache file name
 */
function storeCache($resizer, $cacheFile, $mediaHashPath) {
    if (WH_IMG_CACHE_STORE) {
        try {
            $storePath = WH_IMG_CACHE_PATH . $mediaHashPath . DS. $cacheFile;
            $resizer->writeCache($storePath);
        } catch (\Exception $ex) {
            
        }
    }
}

/**
 * Remove cache file
 */
function removeCache($filename, $mediaHashPath){
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $srcMedia = WH_IMG_CACHE_PATH.$mediaHashPath.DS.$basename;
    try{
        $pattern = $srcMedia.'*';
        $cachedFiles = glob($pattern);
        if(!empty($cachedFiles)){
            foreach($cachedFiles as $file){
                if(is_file($file)){
                    @unlink($file);
                }
            }
            responseHeaderSet('success',false);
            exit();
        }
        responseHeaderSet('404',false);
        exit();
    }
    catch (\Exception $e){
        
    }
}

/**
 * check wheather file is cached or not
 * @param string $cacheFile cached file name
 * @return boolean
 */
function isCached($cacheFile, $mediaHashPath) {
    if (is_file(WH_IMG_CACHE_PATH . DS . $mediaHashPath . $cacheFile)) {
        return true;
    }
    return false;
}

/**
 * return cache image name
 * @param path to source file $srcMedia
 * @param array $params
 * @return string
 */
function genrateCacheFileName($srcMedia, $params) {
    $filename = pathinfo($srcMedia, PATHINFO_FILENAME);
    $ext = pathinfo($srcMedia, PATHINFO_EXTENSION);
    $newfile = $filename;
    if (isset($params['w'])) {
        $newfile .= "_w" . cleanParam($params['w']);
    }
    if (isset($params['h'])) {
        $newfile .= "_h" . cleanParam($params['h']);
    }
    if (isset($params['q'])) {
        $newfile .= "_q" . cleanParam($params['q']);
    }
    if (isset($params['cc'])) {
        $newfile .= "_cc";
    }
    if (isset($params['ct'])) {
        $newfile .= "_ct";
    }
    if (isset($params['wm'])) {
        $newfile .= "_wm";
    }
    if(isset($params['webp'])){
        $newfile .= "_webp";
    }
    $newfile .= "." . $ext;
    return $newfile;
}

/**
 * return home page respose
 */
function showHome() {
    responseHeaderSet('sucess', false);
}

/**
 * check wheather it is home page or not 
 * @return boolean
 */
function isHome() {
    if (ltrim($_SERVER['REQUEST_URI'], '\/') == "") {
        return true;
    }
    return false;
}

/**
 * show 404 header
 */
function show404() {
    responseHeaderSet('404', false);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
}

/**
 * show exception
 * @param \Exception $ex
 */
function showException($ex) {
    responseHeaderSet('error');
    $text = "{$ex->getMessage()} -FILE- {$ex->getFile()} -LINE- {$ex->getLine()}";
    header('EXCEPTION-TEXT:' . $text);
}

/**
 * return alpha numeric value only
 * @param sting $value
 * @return string
 */
function cleanParam($value) {
    return preg_replace("/[^a-zA-Z0-9]/", '', $value);
}

/**
 * return media hash path from basename
 * @param string $filename
 * @return string
 */
function getMediaHashPath($filename) {
    return WhImage::getPath($filename) . $reqFile;
}

/**
 * set http response header
 * @param string $type success|404
 * @param boolean $cache default:true
 * @param int $expire default 1 hour
 */
function responseHeaderSet($type, $cache = true, $expire = 'default') {
    if ($type == 'success') {
        header("HTTP/1.0 200 OK");
    } else if ($type == '404') {
        header("HTTP/1.0 404 Not Found");
    } else if ($type == 'error') {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    } else {
        $cache = false;
    }

    if ($cache) {
        # default : 1 hour

        define('CACHE_EXPIRE_DEFAULT', 31536000);
        $expire_time = $expire == "default" ? gmdate('D, d M Y H:i:s \G\M\T', time() + CACHE_EXPIRE_DEFAULT) : gmdate('D, d M Y H:i:s \G\M\T', time() + $expire);
        header("Cache-Control: public, max-age=" . $expire_time);
        header("Expires: " . $expire_time);
    }
}
