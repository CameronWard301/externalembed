<?php /** @noinspection DuplicatedCode */
/**
 * DokuWiki Plugin ytembed (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Cameron <cameronward007@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

/**
 * Exception Class
 *
 * Class InvalidYouTubeEmbed
 */
class InvalidYouTubeEmbed extends Exception {
    public function errorMessage(): string {
        return $this->getMessage();
    }
}


class syntax_plugin_ytembed extends DokuWiki_Syntax_Plugin
{
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
    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern('{{YT_embed>', $mode, 'plugin_ytembed');
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern('}}', 'plugin_ytembed');
    }

    /**
     * Handle matches of the ytembed syntax
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
                        define('YT_API_KEY', $this->getConf('YT_API_KEY'));
                        define('PLAYLIST_CACHE_TIME', $this->getConf('PLAYLIST_CACHE_TIME'));
                        if(empty(YT_API_KEY)) throw new InvalidYouTubeEmbed('Empty API Key');
                        if(empty(PLAYLIST_CACHE_TIME)) throw new InvalidYouTubeEmbed('Empty cache time');
                        $embed_type = $this->getEmbedType($match);

                        switch(true) {
                            case ($embed_type === "video"):
                                $parameter_array = $this->parseVideoString($match);
                                $yt_request      = $this->getVideoRequest($parameter_array);
                                $html            = $this->renderYouTubeVideo($yt_request, $parameter_array);
                                return array('YT_embed_html' => $html);
                            case ($embed_type === "playlist"):
                                $parameter_array             = $this->parsePlaylistString($match);
                                $playlist_cache              = $this->checkCache($parameter_array);
                                $cached_video_id             = $this->getLatestVideo($playlist_cache);
                                $parameter_array['video_id'] = $cached_video_id;
                                $yt_request                  = $this->getVideoRequest($parameter_array);
                                $html                        = $this->renderYouTubeVideo($yt_request, $parameter_array);
                                return array('YT_embed_html' => $html);
                        }
                    } catch(InvalidYouTubeEmbed $e) {
                        $html = "<p style='color: red; font-weight: bold;'>YouTube Embed Error: " . $e->getMessage() . "</p>";
                        return array('YT_embed_html' => $html);
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
        if(!empty($data['YT_embed_html'])) {
            $renderer->doc .= $data['YT_embed_html'];
            return true;
        } else {
            return false;
        }
    }

    /**
     * Method that generates a HTML iframe for youtube embeds
     *
     * @param $request
     * @param $parameters
     * @return string
     */
    private function renderYouTubeVideo($request, $parameters): string {
        return '<iframe style="border: none;" width="'.$parameters["width"].'" height="'.$parameters["height"].'" src="'.$request.'"></iframe>';
    }

    /**
     * Gets the type of embed.
     * If type is playlist, the embed will show the latest video in the playlist
     * If the type is video, the embed will only show the video
     *
     * @param $user_string
     * @return string //either: 'playlist' or 'video'
     * @throws InvalidYouTubeEmbed
     */
    private function getEmbedType($user_string): string {
        $type = substr($user_string, 0, strpos($user_string, " | "));

        if($type == "") throw new InvalidYouTubeEmbed("Missing Type Parameter / Not Enough Parameters");

        $decoded_string = explode("type: ", strtolower($type))[1];
        $embed_type   = str_replace('"', '', $decoded_string);

        if($embed_type == null) throw new InvalidYouTubeEmbed("Missing Type Parameter");

        $embed_type   = strtolower($embed_type);

        $accepted_types = array("playlist", "video");

        if(array_search($embed_type, $accepted_types) === false) {
            throw new InvalidYouTubeEmbed(
                "Invalid Type Parameter: " . htmlspecialchars($embed_type) . "
            <br>Accepted Types: " . implode(" | ", $accepted_types)
            );
        }
        return $embed_type;
    }

    /**
     * Method that parses the users string query for a video from the wiki editor
     *
     * @param $user_string
     * @return array //an array of parameter: value, associations
     * @throws InvalidYouTubeEmbed
     */
    private function parseVideoString($user_string): array {
        $video_parameter_types  =  array("type" => true, 'url' => true, 'video_id' => true, 'width'=> '1280', 'height' => '720', 'autoplay' => 'false', 'mute'=> 'false', 'loop' => 'false', 'controls' => 'true');
        $video_parameter_values =  array('autoplay' => ['', 'true', 'false'], 'mute' => ['', 'true', 'false'], 'loop' => ['', 'true', 'false'], 'controls' => ['', 'true', 'false']);
        $regex                  = '/^((?:https?:)?\/\/)?((?:www|m)\.)?((?:youtube\.com|youtu.be))(\/(?:[\w\-]+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$/';

        $parameters             = $this->getParameters($user_string);
        if(!key_exists('url', $parameters)) throw new InvalidYouTubeEmbed('Missing url parameter');

        if(preg_match($regex, $parameters['url'], $match)){
            $parameters['video_id'] = $match[5];
        }
        else{
            throw new InvalidYouTubeEmbed('Missing video/playlist id from URL');
        }

        return $this->checkParameters($parameters, $video_parameter_types, $video_parameter_values);
    }

    /**
     * Method that parses the users string query for a playlist from the wiki editor
     *
     * @param $user_string
     * @return array //an array of parameter: value, associations
     * @throws InvalidYouTubeEmbed
     */
    private function parsePlaylistString($user_string): array {
        $playlist_parameter_types = array("type" => true, 'url' => true, 'playlist_id' => true, 'width'=> '1280', 'height' => '720', 'autoplay' => 'false', 'mute'=> 'false', 'loop' => 'false', 'controls' => 'true');
        $playlist_parameter_values =  array('autoplay' => ['', 'true', 'false'], 'mute' => ['', 'true', 'false'], 'loop' => ['', 'true', 'false'], 'controls' => ['', 'true', 'false']);
        $regex                     = '/^.*(youtu.be\/|list=)([^#\&\?]*).*/';

        $parameters             = $this->getParameters($user_string);
        if(!key_exists('url', $parameters)) throw new InvalidYouTubeEmbed('Missing url parameter');

        if(preg_match($regex, $parameters['url'], $matches)) {
            $parameters['playlist_id'] = $matches[2];
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
     * @throws InvalidYouTubeEmbed
     */
    private function checkParameters(array &$query_array, array $required_parameters, array $parameter_values): array {
        foreach($required_parameters as $key => $value) {
            if(!array_key_exists($key, $query_array)) { // if parameter is missing:
                if($value === true) { // check if parameter is required
                    throw new InvalidYouTubeEmbed("Missing Parameter: " . $key);
                }
                $query_array[$key] = $value; // substitute default
            }
            if(($query_array[$key] == null || $query_array[$key] === "") && $value === true) { //if parameter is required but value is not present
                throw new InvalidYouTubeEmbed("Missing Parameter Value for: '" . $key . "'.");
            }
            if(array_key_exists($key, $parameter_values)) { //check accepted parameter_values array
                if(!in_array($query_array[$key], $parameter_values[$key])) { //if parameter value is not accepted:
                    $message = "Invalid Parameter Value: '" . htmlspecialchars($query_array[$key]) . "' for Key: '" . $key . "'.
                    <br>Possible values: " . implode(" | ", $parameter_values[$key]);
                    if(in_array("", $parameter_values[$key])) {
                        $message .= " or ''";
                    }
                    throw new InvalidYouTubeEmbed($message);
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

        if($parameters['mute'] === 'true'){
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
        return 'https://www.youtube.com/embed/'.$parameters['video_id'].'?'.'autoplay='.$autoplay.'&mute='.$mute.'&loop='.$loop.'&controls='.$controls;
    }

    /**
     * @param $video_cache
     * @return string //returns the last video in the cache (the latest one)
     */
    private function getLatestVideo($video_cache): string {
        return end($video_cache);
    }

    /**
     * Method for checking the cache for a given playlist
     * If the cache exists and is expired or the cache doesnt exist: fetch data from YouTube and store in a JSON file
     * Otherwise open and return the valid cache array
     *
     * @param $parameters
     * @return mixed
     * @throws InvalidYouTubeEmbed
     */
    private function checkCache($parameters){
        if(file_exists($file_cache = DOKU_INC.'/lib/plugins/ytembed/cache/'.$parameters["playlist_id"].'.json')){
            if(!$cached_playlist = json_decode(file_get_contents($file_cache), true)){
                throw new InvalidYouTubeEmbed('Could not open and/or decode existing cache file for playlist: '.$parameters["playlist_id"]);
            }
            if($cached_playlist['expires'] < time()){ //if the cache has expired:
                $this->cachePlaylist($parameters); //generate new cache
            }
        } else { //if file does not exist:
            $this->cachePlaylist($parameters); //cache the new playlist
        }
        if(!$cached_playlist = json_decode(file_get_contents($file_cache))){
            throw new InvalidYouTubeEmbed('Could not open and/or decode existing cache file for playlist: '.$parameters["playlist_id"]);
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
     * @throws InvalidYouTubeEmbed
     */
    private function cachePlaylist($parameters) {
        $video_ids = array();
        $response = array();
        $video_ids['expires'] = time() + (PLAYLIST_CACHE_TIME*60*60); //set cache to expire in seconds

        while(key_exists('nextPageToken', $response) || empty($response)){
            $response = $this->sendPlaylistRequest($parameters, '&pageToken='.$response['nextPageToken']);
            foreach($response['items'] as $video){
                array_push($video_ids, $video['contentDetails']['videoId']);
            }
        }

        if(file_exists($file_cache = DOKU_INC.'/lib/plugins/ytembed/cache/'.$parameters["playlist_id"].'.json')){
            if(!unlink($file_cache)){
                throw new InvalidYouTubeEmbed('Could not delete old cache file for playlist: '. $parameters["playlist_id"]);
            }
        }
        if(!$newCache = fopen(DOKU_INC.'/lib/plugins/ytembed/cache/'.$parameters["playlist_id"].'.json', "w")){
            throw new InvalidYouTubeEmbed('Cannot create cache file: cache/'.$parameters['playlist_id'].'.json');
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
     * @throws InvalidYouTubeEmbed
     */
    private function sendPlaylistRequest($parameters, $next_page_token = ''){
        $url  = 'https://youtube.googleapis.com/youtube/v3/playlistItems?part=contentDetails&maxResults=50'.$next_page_token.'&playlistId='.$parameters["playlist_id"].'&key=AIzaSyCJFeNmYo-K7tzh9FfHeo8MACrPkJ8zi_Y';
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
            throw new InvalidYouTubeEmbed($message);
        }
        curl_close($curl);
        return $api_response;
    }
}
