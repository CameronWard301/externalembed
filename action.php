<?php
/** @noinspection PhpUnused */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/**  TEST AT 9:00 and then 9:35*/
/**
 * DokuWiki Plugin externalembed (action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
}

class InvalidCacheCreation extends Exception {
    public function errorMessage(): string {
        return $this->getMessage();
    }
}

class action_plugin_externalembed extends DokuWiki_Action_Plugin {

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, 'handle_indexer_tasks_run');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');

    }

    /**
     * Checks the currently used cache files and then invalidates the cache if it is out of date
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered
     *
     * @return void
     * @noinspection PhpUnusedParameterInspection
     */
    public function handle_indexer_tasks_run(Doku_Event $event, $param) {
        $metadata = p_get_metadata($_GET['id']);
        if(empty($metadata)) return;
        if(!($cacheHelper = $this->loadHelper('externalembed_cacheInterface'))) { //load the helper functions
            echo 'Could not load cache interface helper';
        }
        $video_ids    = $metadata["plugin"]["externalembed"]["video_ids"];
        $playlist_ids = $metadata['plugin']['externalembed']['playlist_ids'];
        if(!empty($video_ids)) {
            $expire_time = $this->getConf('THUMBNAIL_CACHE_TIME') * 60 * 60;
            foreach($video_ids as $video_id) { //check each cache file to see if it needs to be updated
                if(!$cacheHelper->checkCacheFreshness($video_id, $expire_time)) {
                    //cache is not fresh time to update
                    $event->preventDefault();
                    $event->stopPropagation();
                    touch($cacheHelper->getCacheFile($video_id));
                }
            }
        }
        if(!empty($playlist_ids)) {
            $expire_time = $this->getConf('PLAYLIST_CACHE_TIME') * 60 * 60;
            foreach($playlist_ids as $playlist_id) {
                if(!$cacheHelper->checkCacheFreshness($playlist_id, $expire_time)) {
                    //cache is not fresh time to update
                    $event->preventDefault();
                    $event->stopPropagation();
                    touch($cacheHelper->getCacheFile($playlist_id));
                }
            }
        }

    }

    /**
     * Handles the parser cache use event.
     * Checks the cache files created using the metadata
     * Adds the cache files to the depends array to be used by the indexer event later
     * @param Doku_Event $event
     * @param            $param
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    public function handle_parser_cache_use(Doku_Event &$event, $param) {
        $page_cache =& $event->data;

        if(!isset($page_cache->page)) return;
        if(!isset($page_cache->mode) || $page_cache->mode != 'xhtml') return;

        if(!($cacheHelper = $this->loadHelper('externalembed_cacheInterface'))) { //load the helper functions
            echo 'Could not load cache interface helper';
        }

        $metadata = p_get_metadata($page_cache->page, 'plugin');
        if(empty($metadata['externalembed'])) return;
        $video_ids    = $metadata['externalembed']['video_ids'];
        $playlist_ids = $metadata['externalembed']['playlist_ids'];
        try {
            if(!empty($video_ids)) {
                $expire_time = $this->getConf('THUMBNAIL_CACHE_TIME') * 60 * 60;
                foreach($video_ids as $video_id) {
                    if(!$cacheHelper->checkCacheFreshness($video_id, $expire_time)) { //update cache if not fresh (expired)
                        $latest_thumbnail = $cacheHelper->getYouTubeThumbnail($video_id); //get latest thumbnail
                        if($latest_thumbnail === $cacheHelper->getExistingCache($video_id)) { //check if the current cache is the same as the "new" cache
                            $cacheHelper->updateETag($video_id); //update the last time we checked
                        } else {
                            $new_cache                      = $cacheHelper->cacheYouTubeThumbnail($video_id, $latest_thumbnail); //create new cache file with updated data
                            $page_cache->depends['files'][] = $new_cache->cache; //add cache file to the 'depends' array
                        }
                    } else {
                        $page_cache->depends['files'][] = $cacheHelper->getCacheFile($video_id); // adds the file path to the depends array
                    }
                }
            }
            if(!empty($playlist_ids)) {
                $expire_time = $this->getConf('PLAYLIST_CACHE_TIME') * 60 * 60;
                foreach($playlist_ids as $playlist_id) {
                    if(!$cacheHelper->checkCacheFreshness($playlist_id, $expire_time)) { //update cache if not fresh (expired)
                        $latest_playlist = $cacheHelper->getPlaylist($playlist_id); //get latest playlist
                        $existing_cache  = $cacheHelper->getExistingCache($playlist_id);
                        if($latest_playlist === $existing_cache) { //check if current cache is the same as the "new" cache
                            $cacheHelper->updateETag(md5(time()));
                        } else {
                            $cacheHelper->removeOldVideo(end($existing_cache), $page_cache);
                            $new_cache = $cacheHelper->cachePlaylist($playlist_id, $latest_playlist); //create new cache file

                            //Get the latest video from the playlist and add it to the page's metadata
                            $new_playlist_data = $new_cache->getCacheData();
                            $new_metadata      = p_read_metadata($page_cache->page); //get existing metadata
                            array_push($new_metadata['current']["plugin"]["externalembed"]["video_ids"], end($new_playlist_data));
                            array_push($new_metadata['persistent']["plugin"]["externalembed"]["video_ids"], end($new_playlist_data));

                            p_save_metadata($page_cache->page, $new_metadata); //add the video ids to the page's metadata

                            $page_cache->depends['files'][] = $new_cache->cache; //add new playlist cache file to the depends array

                            return;
                        }
                    }
                    $page_cache->depends['files'][] = $cacheHelper->getExistingCache($playlist_id)->cache;

                }
            }
            return;
        } catch(InvalidCacheCreation $e) {
            echo "<p style='color: red; font-weight: bold;'>External Embed Error: " . $e->getMessage() . "</p>";
            return;
        }
    }
}

