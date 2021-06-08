<?php /** @noinspection DuplicatedCode */
/**
 * DokuWiki Plugin externalembed (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) {
    die();
}

/**
 * Exception Class
 *
 * Class InvalidYouTubeEmbed
 */
class InvalidEmbed extends Exception {
    public function errorMessage(): string {
        return $this->getMessage();
    }
}

class syntax_plugin_externalembed extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax mode type
     */
    public function getType(): string {
        return 'substition';
    }

    /**
     * @return string Paragraph type
     */
    public function getPType(): string {
        return 'block';
    }

    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort(): int {
        return 2;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('{{external_embed>', $mode, 'plugin_externalembed');
    }

    public function postConnect() {
        $this->Lexer->addExitPattern('}}', 'plugin_externalembed');
    }

    /**
     * Handle matches of the externalembed syntax
     *
     * @param string       $match   The match of the syntax
     * @param int          $state   The state of the handler
     * @param int          $pos     The position in the document
     * @param Doku_Handler $handler The handler
     *
     * @return array Data for the renderer
     */
    function handle($match, $state, $pos, $handler): array {
        switch($state) {
            case DOKU_LEXER_EXIT:
            case DOKU_LEXER_ENTER :
                /** @var array $data */
                $data = array();
                return $data;

            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_MATCHED :
                break;

            case DOKU_LEXER_UNMATCHED :
                if(!empty($match)) {
                    try {
                        //get and define config variables
                        define('YT_API_KEY', $this->getConf('YT_API_KEY'));
                        define('THUMBNAIL_CACHE_TIME', $this->getConf('THUMBNAIL_CACHE_TIME'));
                        define('PLAYLIST_CACHE_TIME', $this->getConf('PLAYLIST_CACHE_TIME'));
                        define('DEFAULT_PRIVACY_DISCLAIMER', $this->getConf('DEFAULT_PRIVACY_DISCLAIMER')); // cam be empty
                        $disclaimers = array();
                        define('DOMAIN_WHITELIST', $this->getDomains($this->getConf('DOMAIN_WHITELIST'), $disclaimers));
                        define('DISCLAIMERS', $disclaimers); //can be empty
                        define('CACHE_DIR', $GLOBALS["conf"]["cachedir"] . '/plugin_externalembed');
                        if(!file_exists(CACHE_DIR)) {
                            mkdir(CACHE_DIR, 0777, true);
                        }

                        //validate config variables
                        if(empty(YT_API_KEY)) throw new InvalidEmbed('Empty API Key, set this in the configuration manager in the admin panel');
                        if(empty(THUMBNAIL_CACHE_TIME)) throw new InvalidEmbed('Empty cache time for thumbnails, set this in the configuration manager in the admin panel');
                        if(empty(PLAYLIST_CACHE_TIME)) throw new InvalidEmbed('Empty cache time for playlists, set this in the configuration manager in the admin panel');
                        if(empty(DOMAIN_WHITELIST)) throw new InvalidEmbed('Empty domain whitelist, set this in the configuration manager in the admin panel');

                        $parameters         = $this->getParameters($match);
                        $embed_type         = $this->getEmbedType($parameters);
                        $parameters['type'] = $embed_type;
                        //gets the embed type and checks if the domain is in the whitelist

                        switch(true) {
                            case ($embed_type === "youtube_video"):
                                $validated_parameters                      = $this->parseYouTubeVideoString($parameters);
                                $yt_request                                = $this->getVideoRequest($validated_parameters);
                                $validated_parameters['thumbnail'] = $this->checkThumbnailCache($validated_parameters);
                                $html                                      = $this->renderJSON($yt_request, $validated_parameters);
                                return array('embed_html' => $html);
                            case ($embed_type === "youtube_playlist"):
                                $validated_parameters                      = $this->parseYouTubePlaylistString($parameters);
                                $playlist_cache                            = $this->checkPlaylistCache($validated_parameters);
                                $cached_video_id                           = $this->getLatestVideo($playlist_cache);
                                $validated_parameters['video_id']          = $cached_video_id;
                                $validated_parameters['thumbnail'] = $this->checkThumbnailCache($validated_parameters);
                                $yt_request                                = $this->getVideoRequest($validated_parameters);
                                $html                                      = $this->renderJSON($yt_request, $validated_parameters);
                                return array('embed_html' => $html);
                            //todo: allow fusion embed links
                            //todo: allow other embeds
                        }
                    } catch(InvalidEmbed $e) {
                        $html = "<p style='color: red; font-weight: bold;'>External Embed Error: " . $e->getMessage() . "</p>";
                        return array('embed_html' => $html);
                    }
                }
        }
        $data = array();
        return $data;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     * @noinspection PhpParameterNameChangedDuringInheritanceInspection
     */
    public function render($mode, Doku_Renderer $renderer, $data): bool {
        if($mode !== 'xhtml') {
            return false;
        }
        if(!empty($data['embed_html'])) {
            $renderer->doc .= $data['embed_html'];
            return true;
        } else {
            return false;
        }
    }

    /**
     * Method that generates a HTML iframe for embedded content
     * Substitutes default privacy disclaimer if none is found the the disclaimers array
     *
     * @param $request    string the source url
     * @param $parameters array iframe attributes and url data
     * @return string the html to embed
     */
    private function renderJSON($request, $parameters): string {
        $parameters['disclaimer'] = DEFAULT_PRIVACY_DISCLAIMER;
        $parameters['request']    = $request;
        $type                     = $parameters['type'];

        //remove unnecessary parameters that don't need to be sent
        unset(
            $parameters['url'],
            $parameters['type'],
            $parameters['autoplay'],
            $parameters['loop'],
            $parameters['mute'],
            $parameters['controls']
        );

        if(key_exists($parameters['domain'], DISCLAIMERS)) { //if there is a unique disclaimer for the domain, replace the default value with custom value
            if(!empty(DISCLAIMERS[$parameters['domain']])) {
                $parameters['disclaimer'] = DISCLAIMERS[$parameters['domain']];
            }
        }
        $dataJSON = json_encode(array_map("utf8_encode", $parameters));
        return '<div class="externalembed_embed externalembed_embedType-' . htmlspecialchars($type) . '" data-json=\'' . $dataJSON . '\'></div>';
    }

    /**
     * Check to see if domain in the url is in the domain whitelist.
     *
     * Check url to determine the type of embed
     * If the url is a youtube playlist, the embed will show the latest video in the playlist
     * If the url is a youtube video, the embed will only show the video
     * Else the type is 'other' as long as the domain is on the whitelist
     *
     * @param $parameters
     * @return string either: 'playlist' 'YT_video' or 'other'
     * @throws InvalidEmbed
     */
    private function getEmbedType(&$parameters): string {
        if(key_exists('url', $parameters) === false) {
            throw new InvalidEmbed('Missing url parameter');
        }
        $parameters['domain'] = $this->validateDomain($parameters['url']); //validate and return the domain of the url

        $embed_type = 'other';

        if($parameters['domain'] === 'youtube.com' || $parameters['domain'] === 'youtu.be') {
            //determine if the url is a video or a playlist
            if(strpos($parameters['url'], 'playlist?list=') !== false) {
                return 'youtube_playlist';
            } else if(strpos($parameters['url'], '/watch') !== false) {
                return 'youtube_video';
            } else {
                throw new InvalidEmbed("Unknown youtube url");
            }
        }
        return $embed_type;
    }

    /**
     * Method that checks the domain entered by the user against the accepted whitelist of domains set in the configuration manager
     *
     * @param $url
     * @return string The valid domain
     * @throws InvalidEmbed If the domain is not in the whitelist
     */
    private function validateDomain($url): string {
        $domain = ltrim(parse_url('http://' . str_replace(array('https://', 'http://'), '', $url), PHP_URL_HOST), 'www.');

        if(array_search($domain, DOMAIN_WHITELIST) === false) {
            throw new InvalidEmbed(
                "Could not embed content from domain: " . htmlspecialchars($domain) . "
            <br>Contact your administrator to add it to their whitelist.
            <br>Accepted Domains: " . implode(" | ", DOMAIN_WHITELIST)
            );
        }
        return $domain;
    }

    /**
     * Method for extracting the accepted domains from the config string
     * Split each data entry by line
     * Then split each line by commas to extract the disclaimer for each accepted domain.
     * If there is no disclaimer for a domain, store this as an empty string "" in the disclaimers array
     *
     * @param $whitelist_string string string entered from config file
     * @param $disclaimers      array array that stores the disclaimers for each accepted domain
     * @return array
     */
    private function getDomains($whitelist_string, &$disclaimers): array {
        $domains = array();
        $items   = explode("\n", $whitelist_string);
        foreach($items as $domain_disclaimer) {
            $data = explode(',', $domain_disclaimer);
            array_push($domains, trim($data[0]));
            $disclaimers[trim($data[0])] = trim($data[1]);
        }
        return $domains;
    }

    /**
     * Method that parses the users string query for a video from the wiki editor
     *
     * @param $parameters
     * @return array //an array of parameter: value, associations
     * @throws InvalidEmbed
     */
    private function parseYouTubeVideoString($parameters): array {
        $video_parameter_types  = array("type" => true, 'url' => true, 'video_id' => true, 'width' => '1280', 'height' => '720', 'autoplay' => 'false', 'mute' => 'false', 'loop' => 'false', 'controls' => 'true');
        $video_parameter_values = array('autoplay' => ['', 'true', 'false'], 'mute' => ['', 'true', 'false'], 'loop' => ['', 'true', 'false'], 'controls' => ['', 'true', 'false']);
        $regex                  = '/^((?:https?:)?\/\/)?((?:www|m)\.)?((?:youtube\.com|youtu.be))(\/(?:[\w\-]+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$/';

        if(preg_match($regex, $parameters['url'], $match)) {
            $parameters['video_id'] = $match[5];
        } else {
            throw new InvalidEmbed('Invalid YouTube URL');
        }

        return $this->checkParameters($parameters, $video_parameter_types, $video_parameter_values);
    }

    /**
     * Method that parses the users string query for a playlist from the wiki editor
     *
     * @param $parameters
     * @return array //an array of parameter: value, associations
     * @throws InvalidEmbed
     */
    private function parseYouTubePlaylistString($parameters): array {
        $playlist_parameter_types  = array("type" => true, 'url' => true, 'playlist_id' => true, 'width' => '1280', 'height' => '720', 'autoplay' => 'false', 'mute' => 'false', 'loop' => 'false', 'controls' => 'true');
        $playlist_parameter_values = array('autoplay' => ['', 'true', 'false'], 'mute' => ['', 'true', 'false'], 'loop' => ['', 'true', 'false'], 'controls' => ['', 'true', 'false']);
        $regex                     = '/^.*(youtu.be\/|list=)([^#\&\?]*).*/';

        if(preg_match($regex, $parameters['url'], $matches)) {
            $parameters['playlist_id'] = $matches[2]; //set the playlist id
        }
        return $this->checkParameters($parameters, $playlist_parameter_types, $playlist_parameter_values);
    }

    /**
     * Splits the query string into an associative array of Type => Value pairs
     *
     * @param $user_string string The user's embed query
     * @return array
     */
    private function getParameters(string $user_string): array {
        $query        = array();
        $string_array = explode(' | ', $user_string);
        foreach($string_array as $item) {
            $parameter                        = explode(": ", $item); //creates key value pairs for parameters e.g. [type] = "image"
            $query[strtolower($parameter[0])] = str_replace('"', '', $parameter[1]); //removes quotes
        }
        if(array_key_exists("fields", $query)) { // separate field names into an array if it exists
            $fields          = array_map("trim", explode(",", $query['fields']));
            $query['fields'] = $fields;
        }
        return $query;
    }

    /**
     * Checks query parameters to make sure:
     *      Required parameters are present
     *      Missing parameters are substituted with default params
     *      Parameter values match expected values
     *
     * @param $query_array         array
     * @param $required_parameters array
     * @param $parameter_values    array
     * @return array // query array with added default parameters
     * @throws InvalidEmbed
     */
    private function checkParameters(array &$query_array, array $required_parameters, array $parameter_values): array {
        foreach($required_parameters as $key => $value) {
            if(!array_key_exists($key, $query_array)) { // if parameter is missing:
                if($value === true) { // check if parameter is required
                    throw new InvalidEmbed("Missing Parameter: " . $key);
                }
                $query_array[$key] = $value; // substitute default
            }
            if(($query_array[$key] == null || $query_array[$key] === "") && $value === true) { //if parameter is required but value is not present
                throw new InvalidEmbed("Missing Parameter Value for: '" . $key . "'.");
            }
            if(array_key_exists($key, $parameter_values)) { //check accepted parameter_values array
                if(!in_array($query_array[$key], $parameter_values[$key])) { //if parameter value is not accepted:
                    $message = "Invalid Parameter Value: '" . htmlspecialchars($query_array[$key]) . "' for Key: '" . $key . "'.
                    <br>Possible values: " . implode(" | ", $parameter_values[$key]);
                    if(in_array("", $parameter_values[$key])) {
                        $message .= " or ''";
                    }
                    throw new InvalidEmbed($message);
                }
            }
        }
        return $query_array;
    }

    /**
     * Method that generates the src attribute for the iframe element
     *
     * @param $parameters
     * @return string
     */
    private function getVideoRequest($parameters): string {
        if($parameters['autoplay'] === 'true') {
            $autoplay = '1';
        } else {
            $autoplay = '0';
        }

        if($parameters['mute'] === 'true') {
            $mute = '1';
        } else {
            $mute = '0';
        }

        if($parameters['loop'] === 'true') {
            $loop = '1';
        } else {
            $loop = '0';
        }

        if($parameters['controls'] === 'true') {
            $controls = '1';
        } else {
            $controls = '0';
        }
        return 'https://www.youtube.com/embed/' . $parameters['video_id'] . '?' . 'autoplay=' . $autoplay . '&mute=' . $mute . '&loop=' . $loop . '&controls=' . $controls;
    }

    /**
     * @param $video_cache
     * @return string //returns the last video in the cache (the latest one)
     */
    private function getLatestVideo($video_cache): string {
        return end($video_cache);
    }

    /**
     * Method for getting youtube thumbnail and storing it as a base64 string in a cache file
     *
     * @param $parameters
     * @throws InvalidEmbed
     */
    private function cacheYouTubeThumbnail($parameters) {
        $img_url              = 'https://img.youtube.com/vi/' . $parameters['video_id'] . '/maxresdefault.jpg';
        $thumbnail['expires'] = time() + (THUMBNAIL_CACHE_TIME * 60 * 60); //set cache to expire in seconds
        $thumbnail['data']    = base64_encode(file_get_contents($img_url));

        if(file_exists($file_cache = CACHE_DIR . '/' . $parameters["video_id"] . '.json')) {
            if(!unlink($file_cache)) {
                throw new InvalidEmbed('Could not delete old thumbnail cache file for video: ' . $parameters["video_id"]);
            }
        }
        if(!$newCache = fopen(CACHE_DIR . '/' . $parameters["video_id"] . '.json', "w")) {
            throw new InvalidEmbed('Cannot create cache file: cache/' . $parameters['video_id'] . '.json');
        }
        fwrite($newCache, json_encode($thumbnail));
        fclose($newCache);
    }

    /**
     * Method for checking the cache for an individual video
     *
     * If the cache exists and is expired or the cache doesnt exist: fetch data from YouTube and store in a JSON file
     * Otherwise open and return the valid cache array
     *
     * @param $parameters
     * @return mixed
     * @throws InvalidEmbed
     */
    private function checkThumbnailCache($parameters) {
        $file_cache = CACHE_DIR . '/' . $parameters['video_id'] . '.json';
        if(file_exists($file_cache)) {
            if(!$cached_thumbnail = json_decode(file_get_contents($file_cache), true)) { //open and decode existing cache file
                throw new InvalidEmbed('Could not open and/or decode existing thumbnail cache file for video: ' . $parameters["video_id"]);
            }
            if($cached_thumbnail['expires'] < time()) {
                $this->cacheYouTubeThumbnail($parameters);
            }
        } else {
            $this->cacheYouTubeThumbnail($parameters);
        }
        if(!$cached_thumbnail_file = json_decode(file_get_contents($file_cache), true)) { //open and decode existing cache file
            throw new InvalidEmbed('Could not open and/or decode existing thumbnail cache file for video: ' . $parameters["video_id"]);
        }
        return $cached_thumbnail_file['data'];
    }

    /**
     * Method for checking the cache for a given playlist
     * If the cache exists and is expired or the cache doesnt exist: fetch data from YouTube and store in a JSON file
     * Otherwise open and return the valid cache array
     *
     * @param $parameters
     * @return mixed
     * @throws InvalidEmbed
     */
    private function checkPlaylistCache($parameters) {
        $file_cache = CACHE_DIR . '/' . $parameters["playlist_id"] . '.json';
        if(file_exists($file_cache)) {
            if(!$cached_playlist = json_decode(file_get_contents($file_cache), true)) {
                throw new InvalidEmbed('Could not open and/or decode existing cache file for playlist: ' . $parameters["playlist_id"]);
            }
            if($cached_playlist['expires'] < time()) { //if the cache has expired:
                $this->cachePlaylist($parameters); //generate new cache
            }
        } else { //if file does not exist:
            $this->cachePlaylist($parameters); //cache the new playlist
        }
        if(!$cached_playlist = json_decode(file_get_contents($file_cache))) {
            throw new InvalidEmbed('Could not open and/or decode existing cache file for playlist: ' . $parameters["playlist_id"]);
        }
        return $cached_playlist;
    }

    /**
     * Generates a cache json file using the playlist ID.
     * The cache file stores all the video ids from the playlist
     *
     * Pre-conditions: Cache is expired or does not exist
     *
     * @param $parameters
     * @throws InvalidEmbed
     */
    private function cachePlaylist($parameters) {
        $video_ids            = array();
        $response             = array();
        $video_ids['expires'] = time() + (PLAYLIST_CACHE_TIME * 60 * 60); //set cache to expire in seconds

        while(key_exists('nextPageToken', $response) || empty($response)) {
            $response = $this->sendPlaylistRequest($parameters, '&pageToken=' . $response['nextPageToken']);
            foreach($response['items'] as $video) {
                array_push($video_ids, $video['contentDetails']['videoId']);
            }
        }

        if(file_exists($file_cache = CACHE_DIR . '/' . $parameters["playlist_id"] . '.json')) {
            if(!unlink($file_cache)) {
                throw new InvalidEmbed('Could not delete old cache file for playlist: ' . $parameters["playlist_id"]);
            }
        }
        if(!$newCache = fopen(CACHE_DIR . '/' . $parameters["playlist_id"] . '.json', "w")) {
            throw new InvalidEmbed('Cannot create cache file: cache/' . $parameters['playlist_id'] . '.json');
        }
        fwrite($newCache, json_encode($video_ids));
        fclose($newCache);
    }

    /**
     * Method for getting the videos in a playlist using the Youtube Data API v3
     *
     * @param        $parameters
     * @param string $next_page_token
     * @return mixed
     * @throws InvalidEmbed
     */
    private function sendPlaylistRequest($parameters, $next_page_token = '') {
        $url  = 'https://youtube.googleapis.com/youtube/v3/playlistItems?part=contentDetails&maxResults=50' . $next_page_token . '&playlistId=' . $parameters["playlist_id"] . '&key=AIzaSyCJFeNmYo-K7tzh9FfHeo8MACrPkJ8zi_Y';
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
}
