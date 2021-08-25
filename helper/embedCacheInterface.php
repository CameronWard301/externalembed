<?php
///**
// * DokuWiki Plugin externalembed (Helper Component)
// *
// * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
// * @author  Cameron Ward <cameronward007@gmail.com>
//*/
//
//if(!defined('DOKU_INC')) die();
//
//class helper_plugin_externalembed_embedCacheInterface extends DokuWiki_Plugin {
//    /**
//     * Check if cache is expired
//     * Then check if the page is older than the (possibly updated cache)
//     *
//     * @param $cache_id
//     * @return mixed
//     */
//    public function checkCacheIssues($cache_id): cache_externalembed {
//        $cache = new cache_externalembed($cache_id);
//        $this->checkCacheFreshness($cache_id, $cache);
//        return $cache;
//    }
//
//    public function checkCacheFreshness($cache_id, &$cache = null): bool {
//        if(!isset($cache)) {
//            $cache = new cache_externalembed($cache_id);
//        }
//
//        if($cache->checkETag($this->getConf('THUMBNAIL_CACHE_TIME')*60*60)) return true;
//
//        return false;
//    }
//
//}
//
//class cache_externalembed extends \dokuwiki\Cache\Cache {
//    public $e_tag = '';
//    var    $_etag_time;
//
//    public function __construct($embed_id){
//        parent::__construct($embed_id, '.externalembed');
//        $this->e_tag = substr($this->cache, 0, -15).'.etag';
//    }
//
//    public function getETag($clean = true){
//        return io_readFile($this->e_tag, $clean);
//    }
//
//    public function storeETag($e_tag_value): bool {
//        if($this->_nocache) return false;
//
//        return io_saveFile($this->e_tag, $e_tag_value);
//    }
//
//    public function checkETag($expireTime): bool {
//        if($expireTime < 0 ) return true;
//        if($expireTime == 0) return false;
//        if(!($this->_etag_time = @filemtime($this->e_tag))) return false; //check if cache is still there
//        if((time() - $this->_etag_time) > $expireTime) return false; //Cache has expired
//        return true;
//    }
//}
