<?php
//new constants
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
define('MEDIA_BASE', __DIR__.'/media');
define('PRODUCT_LOGO',MEDIA_BASE.'/wlogo/il.png');
define('PRODUCT_LOGO_BACKGROUND',MEDIA_BASE.'/wlogo/itbackwithlogo.png');
define('WEBP_SUPPORT_ENABLE',TRUE);


//Old constants
define('WH_IMG_BASE_PATH',MEDIA_BASE);
define('WH_IMG_CACHE_PATH', WH_IMG_BASE_PATH.DS.'_cache');
define('WH_IMG_TMP_PATH', WH_IMG_BASE_PATH.DS.'tmp');
define('WH_IMG_CACHE_STORE',true);                                                               