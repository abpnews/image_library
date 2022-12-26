<?php
/*
 * @description : Image service for Whats Hot
 */
namespace cdn_service;
error_reporting(E_ALL & ~E_NOTICE);

include_once 'config.php';
class WhImage{

    /** @var \Imagick imagick object */
    public  $image;

    /**
     * hold request query params
     * @var array
     */
    protected $params;

    protected $resizeWidth;

    protected $resizeHeight;

    protected $originalWidth;

    protected $originalHeight;


    /**
     * request image type
     * @var string JPEG | GIF | PNG
     */
    protected $imageFormat;

    function __construct($file,$params = []){
        try{
            $this->image = new \Imagick($file);
        }
        catch(\ImagickException $ex){

        }
        $this->setParams($params);
    }

    /**
     * return hash path for file
     * @param type $slug
     * @return string
     */
    public static function getPath($slug){
        $hash = sha1($slug);
        $octet = substr($hash, strlen($hash)-12);
        $octetArray = str_split($octet,2);
        $path = implode(DS,$octetArray).DS;
        return $path;
    }

    /**
     * load & parse params
     * @author Mukesh Soni <mukeshsoni151@gmail.com>
     */
    protected function setParams($params = []){
        $this->params = $params;
        $this->resizeWidth = (int) (isset($params['w']) ? $params['w'] : 0);
        $this->resizeHeight = (int) (isset($params['h']) ? $params['h'] : 0);
        $this->params['q']  = (int)!empty($params['q']) ? $params['q'] : 95;
        if($this->image){
            $this->imageFormat = $this->image->getimageformat();
            $this->originalWidth = $this->image->getimagewidth();
            $this->originalHeight = $this->image->getimageheight();
        }
        
    }

    /**
     * resize adaptive resize image
     */
    public function resize(){
        if($this->image){
            if(isset($this->params['cc'])){
                $this->cropCenter();
            }
            else if(isset($this->params['ct'])){ //crop from top
                $this->cropTop();
            }
            else if($this->resizeWidth && $this->resizeHeight){
                $this->resizeAdaptive();
            }
            if($this->params['q']){
                $this->setQuality($this->params['q']);
            }
	    if($this->params['wm']){
                $this->setWatermark();
            }
            return true;
        }
        return false;
    }

       
     public function setWatermark(){
        $main_image_w = $this->image->getImageWidth();
        $main_image_h = $this->image->getImageHeight();
        $transparent_background = new \Imagick();        
        $transparent_background->readImage(PRODUCT_LOGO_BACKGROUND);
        $transparent_background_d = $transparent_background->getImageGeometry();
        $transparent_background_w = $transparent_background_d['width'];
        $transparent_background_h = $transparent_background_d['height'];
        $y_loc = $main_image_h - $transparent_background_h; 
         if($this->imageFormat == "GIF"){
                $collsec = $this->image->coalesceimages();
                foreach($collsec as $frame){
                    $frame->compositeImage($transparent_background, \Imagick::COMPOSITE_OVER, 0, $y_loc);
                }
                $collsec = $collsec->deconstructimages();
                $this->image = $collsec;
            }
            else{
                $this->image->compositeImage($transparent_background, \Imagick::COMPOSITE_OVER, 0, $y_loc);
            }

        }    

    /**
     * center crop best fit
     */

    public function cropCenter(){
        if($this->resizeWidth > 0 && $this->resizeHeight > 0){
            if($this->imageFormat == "GIF"){
                $collsec = $this->image->coalesceimages();
                foreach($collsec as $frame){
                    $frame->cropthumbnailimage($this->resizeWidth, $this->resizeHeight);
                }
                $collsec = $collsec->deconstructimages();
                $this->image = $collsec;
            }
            else{
                $this->image->cropthumbnailimage($this->resizeWidth, $this->resizeHeight);
            }
        }        
    }


    /**
     * Crop top best fit
     */
    public function cropTop(){

        $w = $this->image->getImageWidth();
        $h = $this->image->getImageHeight();
        $new_w = $this->resizeWidth;
        $new_h = $this->resizeHeight;

        if ($w > $h) {
            $resize_w = $w * $new_h / $h;
            $resize_h = $new_h;
        }
        else {
            $resize_w = $new_w;
            $resize_h = $h * $new_w / $w;
        }
        //resize to best near scale
        $this->image->resizeImage($resize_w, $resize_h, \Imagick::FILTER_LANCZOS, 0.9);
        //crop image from x-center, y-top
        $this->image->cropImage($new_w, $new_h, ($resize_w - $new_w) / 2, 0);
        //make image best fit after crop.
        $this->resizeAdaptive($new_w,$new_h);

    }

    /**
     * resize image
     */
    public function resizeAdaptive(){
        if($this->imageFormat == "GIF"){
            $collsec = $this->image->coalesceimages();
            foreach($collsec as $frame){
                $frame->adaptiveresizeimage($this->resizeWidth, $this->resizeHeight);
            }
            $collsec = $collsec->deconstructimages();
            $this->image = $collsec;
        }
        else{
            $this->image->adaptiveresizeimage($this->resizeWidth, $this->resizeHeight);
        }
    }

    /**
     * set image quality
     * @param int $quality 1-10
     */
    public function setQuality($quality = 95){
        if ($this->imageFormat == 'JPEG' || $this->imageFormat == "WEBP") {
            //$this->image->setimagecompression(\Imagick::COMPRESSION_JPEG);
            $this->image->setimagecompressionquality($quality);
        }
    }

    /**
     * set http response header's content type
     */
    public function setHeader(){
        if($this->image){
            header('Content-Type: image/'.$this->imageFormat);
        }
    }


    /**
     * output image
     * @param boolean $setHeader
     */
    public function output($setHeader = true){
        if($setHeader){
            $this->setHeader();
        }

        if($this->imageFormat == "GIF"){
            echo $this->image->getimagesblob();
        }
        else{
            echo $this->image->getimageblob();
        }
    }

    /**
     * save file to cache
     * @param string $path
     */
    public function writeCache($path,$postOptimize = true){
        $filename = pathinfo($path,PATHINFO_BASENAME);
        $tmpfilename = pathinfo($path, PATHINFO_FILENAME).'_'.mt_rand().'.'.pathinfo($path, PATHINFO_EXTENSION);
        
        /**
         * Here, I am creating a xxx.webp file in tmp and rename same to original after process complete
         */
        try{
            if(!empty($this->params['webp'])){
                $tmpfilename = pathinfo($tmpfilename, PATHINFO_FILENAME).'.webp';
                $this->image->setimageformat("webp");
            }
        }
        catch(\Exception $e){
            //echo "<pre>"; print_r($e); echo "</pre>"; die;
        }
        
        $tmpfile = WH_IMG_TMP_PATH.DS.$tmpfilename;
        try{
            //@Fix: writing resized image on tmp for further optimization.
            if($this->imageFormat == "GIF"){
                $this->image->writeimages($tmpfile, true);            
            }
            else{
                $this->image->writeimage($tmpfile);
            }

            #optimize media after resize
            if($postOptimize){
                $mime_type = mime_content_type($tmpfile);
                $command = null;
                switch($mime_type){
                    case 'image/jpeg':
                    case 'image/jpg':
                        $command = "/usr/bin/jpegoptim --all-progressive -p -m75 --strip-all {$tmpfile}";
                        break;
                    case 'image/png':
                        $command = "/usr/bin/optipng {$tmpfile}";
                        break;
                }
                if($command){
                    $output = shell_exec($command);
                }
            }
        }
        catch(\Exception $e){
            //echo "<pre>"; print_r($e); echo "</pre>"; die;
        }
        
        $dir = dirname($path);
        if(is_dir($dir) == false){
            mkdir($dir,0755,true);
        }
        
        if(is_file($tmpfile)){
            //copy($tmpfile, $dir.DIRECTORY_SEPARATOR.$tmpfilename);
            rename($tmpfile, $path);
        }
    }
}
