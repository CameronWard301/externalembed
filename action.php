<?php
/**
 * DokuWiki Plugin ytembed (action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
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
     * [Custom event handler which performs action]
     *
     * Called for event:
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_indexer_tasks_run(Doku_Event $event, $param) {
        echo 'hello';
    }

    public function handle_parser_cache_use(Doku_Event &$event, $param) {
        $page_cache =& $event->data;

        if(!isset($page_cache->page)) return;
        if(!isset($page_cache->mode) || $page_cache->mode != 'xhtml') return;

        $data = p_get_metadata($page_cache->page, 'data');
        if(empty($data)) return;
        $video_ids    = $data['YT-video-ids'];
        $playlist_ids = $data['YT-playlist-ids'];
        return;

        /*foreach($video_ids as $video_id) {
            $page_cache->depends['files'][] = $this->checkCacheIssues($video_id);
        }*/
    }

    public function checkCacheIssues($cache_id): cache_externalembed {
        $cache = new cache_externalembed($cache_id);
        $this->checkCacheFreshness($cache_id, $cache);
        return $cache;
    }

    public function checkCacheFreshness($cache_id, &$cache = null): bool {
        if(!isset($cache)) {
            $cache = new cache_externalembed($cache_id);
        }

        if($cache->checkETag($this->getConf('THUMBNAIL_CACHE_TIME') * 60 * 60)) return true;

        return false;
    }

}

