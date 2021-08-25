<?php
/** @noinspection PhpUnused */
/**
 * DokuWiki Plugin externalembed (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron Ward <cameronward007@gmail.com>
 */

if(!defined('DOKU_INC')) die();

class helper_plugin_externalembed_cacheInterface extends DokuWiki_Plugin {

    /**
     * Get the data stored in the cache file e.g. thumbnail encoded data
     *
     * @param $cache_id string the id of the cache
     * @return mixed
     */
    public function getExistingCache(string $cache_id) {
        $cache = new cache_externalembed($cache_id);
        return json_decode($cache->retrieveCache(), true);
    }

    /**
     * Updates the E tag of the cache file to be the current time
     * @param $cache_id string the id of the cache
     */
    public function updateETag(string $cache_id) {
        $cache = new cache_externalembed($cache_id);
        $cache->storeETag(md5(time()));
    }

    /**
     * Return true if the cache is still fresh, otherwise return false
     * @param      $cache_id string the cache id
     * @param      $time     // the expiry time of the cache
     * @return bool
     */
    public function checkCacheFreshness(string $cache_id, $time): bool {
        $cache = new cache_externalembed($cache_id);

        if($cache->checkETag($time)) return true;

        return false;
    }

    /**
     * Public function to get a thumbnail from a YouTube video
     * Return the thumbnail data to be cached or checked with the existing cache.
     * @param $video_id
     * @return array the url of the thumbnail with the encoded thumbnail data
     */
    public function getYouTubeThumbnail($video_id): array {
        $img_url   = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';
        $thumbnail = base64_encode(file_get_contents($img_url)); //encode the thumbnail to be sent to the browser later
        return array('url' => $img_url, 'thumbnail' => $thumbnail); //return thumbnail data to be cached or checked with existing cache
    }

    /**
     * Generate a new cache object and store the new data
     * @param $video_id   string the id of the cache
     * @param $cache_data mixed the data to store in the cache
     * @return cache_externalembed the cache object
     */
    public function cacheYouTubeThumbnail(string $video_id, $cache_data): cache_externalembed {
        $timestamp = md5(time());
        return $this->newCache($video_id, $cache_data, $timestamp); //create cache file and return object
    }

    /**
     * Public function generates new cache object
     * Stores the data within a json encoded cache file
     * @param      $cache_id  string the unique identifier for the cache
     * @param null $data      The data to be stored in the cache
     * @param null $timestamp When the cache was created
     * @return cache_externalembed the cache object
     */
    public function newCache(string $cache_id, $data = null, $timestamp = null): cache_externalembed {
        $cache = new cache_externalembed($cache_id);
        $cache->storeCache(json_encode($data));
        $cache->storeETag($timestamp);
        return $cache;
    }

    /**
     * Generate a new cache object and store the new data
     * @param $playlist_id string the id of the cache
     * @param $video_ids   mixed the data stored in the cache
     * @return cache_externalembed the new cache object
     */
    public function cachePlaylist(string $playlist_id, $video_ids): cache_externalembed {
        $timestamp = md5(time());
        return $this->newCache($playlist_id, $video_ids, $timestamp);
        //store the latest video from the playlist and return the cache object
    }

    /**
     * Gets the current video ID's associated with a YouTube Playlist
     * @param $playlist_id string the YouTube Playlist ID
     * @return array The array of video ID's associated with the playlist
     * @throws InvalidEmbed
     */
    public function getPlaylist(string $playlist_id): array {
        $video_ids = array();
        $response  = array();

        while(key_exists('nextPageToken', $response) || empty($response)) { //keep sending requests until we have seen all the videos in a playlist
            $response = $this->sendPlaylistRequest($playlist_id, '&pageToken=' . $response['nextPageToken']);
            foreach($response['items'] as $video) {
                array_push($video_ids, $video['contentDetails']['videoId']); //add the video_ids to the array
            }
        }
        return $video_ids;
    }

    /**
     * Method for getting the videos in a playlist using the YouTube Data API v3
     *
     * @param        $playlist_id     string the YouTube Playlist ID
     * @param string $next_page_token token that the API needs to get the next set of results
     * @return mixed The List of video ID's on the current page associated with the playlist ID
     * @throws InvalidEmbed
     */
    private function sendPlaylistRequest(string $playlist_id, string $next_page_token = '') {
        $url  = 'https://youtube.googleapis.com/youtube/v3/playlistItems?part=contentDetails&maxResults=50' . $next_page_token . '&playlistId=' . $playlist_id . '&key=AIzaSyCJFeNmYo-K7tzh9FfHeo8MACrPkJ8zi_Y';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            'Accept: application/json'
        );

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        //TODO: remove once in production:
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);//

        $api_response = json_decode(curl_exec($curl), true); //decode JSON to associative array

        if(curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            if(key_exists("error", $api_response)) {
                $message = $api_response['error']['message'];
            } else {
                $message = "Unknown API api_response error";
            }
            throw new InvalidEmbed($message);
        }
        curl_close($curl);
        return $api_response;
    }

    /**
     * Public function to remove the video_id cache file from the depends array and the page metadata
     * @param $video_id   string the video ID that is no longer needed on the page (new video from playlist)
     * @param $page_cache mixed the page cache used in the PARSER CACHE USE event
     */
    public function removeOldVideo(string $video_id, $page_cache) {
        $cache = new cache_externalembed($video_id);
        if(($key = array_search($cache->cache, $page_cache->depends['files'])) !== false) {
            unset($page_cache->depends['files'][$key]);//if file is in the array, remove it

        }
        $metadata = p_read_metadata($page_cache->page);//get complete metadata
        //remove from current metadata:
        if(($key = array_search($video_id, $metadata['current']['plugin']['externalembed']['video_ids'])) !== false) {
            unset($metadata['current']['plugin']['externalembed']['video_ids'][$key]);//remove from metadata
        }
        //remove from persistent metadata:
        if(($key = array_search($video_id, $metadata['persistent']['plugin']['externalembed']['video_ids'])) !== false) {
            unset($metadata['persistent']['plugin']['externalembed']['video_ids'][$key]);//remove from metadata
        }
        p_save_metadata($page_cache->page, $metadata); //save updated metadata with removed video_id

        //remove from depends array:
        if(($key = array_search($this->getCacheFile($video_id), $page_cache->depends['files'])) !== false) {
            unset($page_cache->depends['files'][$key]);
        }
    }

    /**
     * Get the file path of the cache file associated with the ID
     * @param $cache_id string The id of the cache file
     * @return string The file path for the cache file
     */
    public function getCacheFile(string $cache_id): string {
        $cache = new cache_externalembed($cache_id);
        return $cache->cache;
    }
}

/**
 * Class that handles cache files, file locking and cache expiry
 */
class cache_externalembed extends \dokuwiki\Cache\Cache {
    public $e_tag = '';
    var $_etag_time;

    public function __construct($embed_id) {
        parent::__construct($embed_id, '.externalembed');
        $this->e_tag = substr($this->cache, 0, -15) . '.etag';
    }

    public function getETag($clean = true) {
        return io_readFile($this->e_tag, $clean);
    }

    public function storeETag($e_tag_value): bool {
        if($this->_nocache) return false;

        return io_saveFile($this->e_tag, $e_tag_value);
    }

    public function getCacheData() {
        return json_decode($this->retrieveCache(), true);

    }

    /**
     * Public function that returns true if the cache (Etag) is still fresh
     * Otherwise false
     * @param $expireTime
     * @return bool
     */
    public function checkETag($expireTime): bool {
        if($expireTime < 0) return true;
        if($expireTime == 0) return false;
        if(!($this->_etag_time = @filemtime($this->e_tag))) return false; //check if cache is still there
        if((time() - $this->_etag_time) > $expireTime) return false; //Cache has expired
        return true;
    }
}
