<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */
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
     * @noinspection PhpMissingParamTypeInspection
     */
    function handle($match, $state, $pos, $handler): array {
        switch($state) {
            case DOKU_LEXER_EXIT:
            case DOKU_LEXER_ENTER :
                /** @var array $data */
                return array();

            case DOKU_LEXER_SPECIAL:
            case DOKU_LEXER_MATCHED :
                break;

            case DOKU_LEXER_UNMATCHED :
                if(!empty($match)) {
                    try {
                        //get and define config variables
                        define('YT_API_KEY', $this->getConf('YT_API_KEY'));
                        define('THUMBNAIL_CACHE_TIME', $this->getConf('THUMBNAIL_CACHE_TIME') * 60 * 60);
                        define('PLAYLIST_CACHE_TIME', $this->getConf('PLAYLIST_CACHE_TIME'));
                        define('DEFAULT_PRIVACY_DISCLAIMER', $this->getConf('DEFAULT_PRIVACY_DISCLAIMER')); // cam be empty
                        $disclaimers = array();
                        define('DOMAIN_WHITELIST', $this->getDomains($this->getConf('DOMAIN_WHITELIST'), $disclaimers));
                        define('DISCLAIMERS', $disclaimers); //can be empty
                        define('MINIMUM_EMBED_WIDTH', $this->getConf('MINIMUM_EMBED_WIDTH'));
                        define('MINIMUM_EMBED_HEIGHT', $this->getConf('MINIMUM_EMBED_WIDTH'));

                        if(!($cacheHelper = $this->loadHelper('externalembed_cacheInterface'))) {
                            throw new InvalidEmbed('Could not load cache interface helper');
                        }

                        //validate config variables
                        if(empty(YT_API_KEY)) {
                            throw new InvalidEmbed('Empty API Key, set this in the configuration manager in the admin panel');
                        }
                        if(empty(THUMBNAIL_CACHE_TIME)) {
                            throw new InvalidEmbed('Empty cache time for thumbnails, set this in the configuration manager in the admin panel');
                        }
                        if(empty(PLAYLIST_CACHE_TIME)) {
                            throw new InvalidEmbed('Empty cache time for playlists, set this in the configuration manager in the admin panel');
                        }
                        if(empty(DOMAIN_WHITELIST)) {
                            throw new InvalidEmbed('Empty domain whitelist, set this in the configuration manager in the admin panel');
                        }

                        $parameters         = $this->getParameters($match);
                        $embed_type         = $this->getEmbedType($parameters);
                        $parameters['type'] = $embed_type;
                        //gets the embed type and checks if the domain is in the whitelist

                        //MAIN PROGRAM:
                        switch(true) {
                            case ($embed_type === "youtube_video"):
                                $validated_parameters              = $this->parseYouTubeVideoString($parameters);
                                $yt_request                        = $this->getVideoRequest($validated_parameters);
                                $validated_parameters['thumbnail'] = $this->cacheYouTubeThumbnail($cacheHelper, $validated_parameters['video_id']);
                                $html                              = $this->renderJSON($yt_request, $validated_parameters);
                                return array('embed_html' => $html, 'video_ID' => $validated_parameters['video_id']); //return html and metadata
                            case ($embed_type === "youtube_playlist"):
                                $validated_parameters              = $this->parseYouTubePlaylistString($parameters);
                                $playlist_cache                    = $this->cachePlaylist($cacheHelper, $validated_parameters);
                                $cached_video_id                   = $this->getLatestVideo($playlist_cache);
                                $validated_parameters['video_id']  = $cached_video_id; //adds the video ID to the metadata later
                                $validated_parameters['thumbnail'] = $this->cacheYouTubeThumbnail($cacheHelper, $validated_parameters['video_id']);
                                $yt_request                        = $this->getVideoRequest($validated_parameters);
                                $html                              = $this->renderJSON($yt_request, $validated_parameters);
                                return array('embed_html' => $html, 'video_ID' => $validated_parameters['video_id'], 'playlist_ID' => $validated_parameters['playlist_id']);
                            case ($embed_type === 'fusion'):
                                $validated_parameters = $this->parseFusionString($parameters);
                                $fusion_request       = $this->getFusionRequest($validated_parameters);
                                $html                 = $this->renderJSON($fusion_request, $validated_parameters);
                                return array('embed_html' => $html);
                            case ($embed_type === 'other'):
                                $validated_parameters = $this->parseOtherEmbedString($parameters);
                                $html                 = $this->renderJSON($validated_parameters['url'], $validated_parameters);
                                return array('embed_html' => $html);
                            default:
                                throw new InvalidEmbed("Unknown Embed Type");

                            //todo: allow fusion embed links
                        }
                    } catch(InvalidEmbed $e) {
                        $html = "<p style='color: red; font-weight: bold;'>External Embed Error: " . $e->getMessage() . "</p>";
                        return array('embed_html' => $html);
                    }
                }
        }
        return array();
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
        if($data === false) return false;

        if($mode == 'xhtml') {
            if(!empty($data['embed_html'])) {
                $renderer->doc .= $data['embed_html'];
                return true;
            } else {
                return false;
            }
        } elseif($mode == 'metadata') {
            if(!empty($data['video_ID'])) {
                /** @var Doku_Renderer_metadata $renderer */
                // erase tags on persistent metadata no more used
                if(isset($renderer->persistent['plugin']['externalembed']['video_ids'])) {
                    //unset($renderer->meta['plugin']['externalembed']['video_ids']);
                    unset($renderer->persistent['plugin']['externalembed']['video_ids']);
                    $renderer->meta['plugin']['externalembed']['video_ids'] = array();
                    //$renderer->persistent['plugin']['externalembed']['video_ids'] = array();
                }

                // merge with previous tags and make the values unique
                if(!isset($renderer->meta['plugin']['externalembed']['video_ids'])) {
                    $renderer->meta['plugin']['externalembed']['video_ids'] = array();
                }
                //$renderer->persistent['plugin']['externalembed']['video_ids'] = array();

                $renderer->meta['plugin']['externalembed']['video_ids'] = array_unique(array_merge($renderer->meta['plugin']['externalembed']['video_ids'], array($data['video_ID'])));
                //$renderer->persistent['plugin']['externalembed']['video_ids'] = array_unique(array_merge($renderer->persistent['plugin']['externalembed']['video_ids'], array($data['video_ID'])));

                if(!empty($data['playlist_ID'])) {
                    if(isset($renderer->persistent['plugin']['externalembed']['playlist_ids'])) {
                        unset($renderer->persistent['plugin']['externalembed']['playlist_ids']);
                        $renderer->meta['plugin']['externalembed']['playlist_ids']       = array();
                        $renderer->persistent['plugin']['externalembed']['playlist_ids'] = array();
                    }

                    if(!isset($renderer->meta['plugin']['externalembed']['playlist_ids'])) {
                        $renderer->meta['plugin']['externalembed']['playlist_ids']       = array();
                        $renderer->persistent['plugin']['externalembed']['playlist_ids'] = array();
                    }
                    $renderer->meta['plugin']['externalembed']['playlist_ids']       = array_unique(array_merge($renderer->meta['plugin']['externalembed']['playlist_ids'], array($data['playlist_ID'])));
                    $renderer->persistent['plugin']['externalembed']['playlist_ids'] = array_unique(array_merge($renderer->persistent['plugin']['externalembed']['playlist_ids'], array($data['playlist_ID'])));

                }
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Method that generates an HTML iframe for embedded content
     * Substitutes default privacy disclaimer if none is found the disclaimers array
     *
     * @param $request    string the source url
     * @param $parameters array iframe attributes and url data
     * @return string the html to embed
     * @throws InvalidEmbed
     */
    private function renderJSON(string $request, array $parameters): string {
        $parameters['disclaimer'] = DEFAULT_PRIVACY_DISCLAIMER;
        $parameters['request']    = $request;
        $type                     = $parameters['type'];
        $parameters['size']       = $this->getEmbedSize($parameters);
        //remove unnecessary parameters that don't need to be sent
        unset(
            $parameters['url'],
            $parameters['type'],
            $parameters['autoplay'],
            $parameters['loop'],
            $parameters['mute'],
            $parameters['controls'],
            //$parameters['width']
        );

        if(key_exists($parameters['domain'], DISCLAIMERS)) { //if there is a unique disclaimer for the domain, replace the default value with custom value
            if(!empty(DISCLAIMERS[$parameters['domain']])) {
                $parameters['disclaimer'] = DISCLAIMERS[$parameters['domain']];
            }
        }
        $dataJSON = json_encode(array_map("utf8_encode", $parameters));
        return '<div class="externalembed_embed externalembed_TOS ' . $parameters['size'] . ' externalembed_embedType-' . htmlspecialchars($type) . '" data-json=\'' . $dataJSON . '\'></div>';
    }

    /**
     * Selects the class to add to the embed so that its size is correct
     * @param $parameters
     * @return string
     * @throws InvalidEmbed
     */
    private function getEmbedSize(&$parameters): string {
        switch($parameters['height']) {
            case '360':
                $parameters['width'] = '640';
                return 'externalembed_height_360';
            case '480':
                $parameters['width'] = '854';
                return 'externalembed_height_480';
            case '720':
                $parameters['width'] = '1280';
                return 'externalembed_height_720';
            default:
                throw new InvalidEmbed('Unknown width value for size class');
        }
    }

    /**
     * Check to see if domain in the url is in the domain whitelist.
     *
     * Check url to determine the type of embed
     * If the url is a YouTube playlist, the embed will show the latest video in the playlist
     * If the url is a YouTube video, the embed will only show the video
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
            //determine if the url is a video or a playlist https://youtu.be/clD_8BItvh4
            if(strpos($parameters['url'], 'playlist?list=') !== false) {
                return 'youtube_playlist';
            } else if((strpos($parameters['url'], '/watch') || strpos($parameters['url'], 'youtu.be/')) !== false) {
                return 'youtube_video';
            } else {
                throw new InvalidEmbed("Unknown youtube url");
            }
        }
        if($parameters['domain'] === 'inventopia.autodesk360.com') {
            return 'fusion';
        }

        return $embed_type;
    }

    /**
     * Method that checks the domain entered by the user against the accepted whitelist of domains sets in the configuration manager
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
    private function getDomains(string $whitelist_string, array &$disclaimers): array {
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
        $video_parameter_types  = array("type" => true, 'url' => true, 'domain' => true, 'video_id' => true, 'height' => '720', 'autoplay' => 'false', 'mute' => 'false', 'loop' => 'false', 'controls' => 'true');
        $video_parameter_values = array('autoplay' => ['', 'true', 'false'], 'mute' => ['', 'true', 'false'], 'loop' => ['', 'true', 'false'], 'controls' => ['', 'true', 'false'], 'height' => ['360', '480', '720']);
        $regex                  = '/^((?:https?:)?\/\/)?((?:www|m)\.)?(youtube\.com|youtu.be)(\/(?:[\w\-]+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$/';

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
        $playlist_parameter_types  = array("type" => true, 'url' => true, 'domain' => true, 'playlist_id' => true, 'height' => '720', 'autoplay' => 'false', 'mute' => 'false', 'loop' => 'false', 'controls' => 'true');
        $playlist_parameter_values = array('autoplay' => ['', 'true', 'false'], 'mute' => ['', 'true', 'false'], 'loop' => ['', 'true', 'false'], 'controls' => ['', 'true', 'false'], 'height' => ['360', '480', '720']);
        $regex                     = '/^.*(youtu.be\/|list=)([^#&?]*).*/';

        if(preg_match($regex, $parameters['url'], $matches)) {
            $parameters['playlist_id'] = $matches[2]; //set the playlist id
        }
        return $this->checkParameters($parameters, $playlist_parameter_types, $playlist_parameter_values);
    }

    /**
     * Method that parses the users string query for a fusion embed
     *
     * @param $parameters
     * @return array an array of validated parameters
     * @throws InvalidEmbed
     */
    private function parseFusionString($parameters): array {
        $fusion_parameter_types  = array('type' => true, 'url' => true, 'domain' => true, 'width' => '1280', 'height' => '720', 'allowFullScreen' => 'true');
        $fusion_parameter_values = array('allowFullScreen' => ['true', 'false']);

        return $this->checkParameters($parameters, $fusion_parameter_types, $fusion_parameter_values);
    }

    /**
     * Method that parses the users string query for an embed type classed as "other"
     *
     * @param $parameters
     * @return array an array of validated parameters
     * @throws InvalidEmbed
     */
    private function parseOtherEmbedString($parameters): array {
        $other_parameter_types  = array("type" => true, 'url' => true, 'domain' => true, 'width' => '1280', 'height' => '720');
        $other_parameter_values = array();

        return $this->checkParameters($parameters, $other_parameter_types, $other_parameter_values);
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
        //if(intval($query_array['width']) < MINIMUM_EMBED_WIDTH) $query_array['width'] = MINIMUM_EMBED_WIDTH;
        if(intval($query_array['height']) < MINIMUM_EMBED_WIDTH) $query_array['height'] = MINIMUM_EMBED_HEIGHT;

        foreach($query_array as $key => $value) {
            if(!array_key_exists($key, $required_parameters)) {
                throw new InvalidEmbed("Invalid parameter: " . htmlspecialchars($key) . '. For url: ' . htmlspecialchars($query_array['url']));
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
     * Method that turns a normal fusion url into an embed url
     * Also sets the required iframe parameters for enabling fullscreen
     * @param $parameters
     * @return string
     */
    private function getFusionRequest(&$parameters): string {
        if($parameters['allowFullScreen'] === 'true') {
            $parameters['allowfullscreen']       = 'true';
            $parameters['webkitallowfullscreen'] = 'true';
            $parameters['mozallowfullscreen']    = 'true';
        }
        unset($parameters['allowFullScreen']);
        return $parameters['url'] . '?mode=embed';
    }

    /**
     * @param $video_cache
     * @return string //returns the last video in the cache (the latest one)
     */
    private function getLatestVideo($video_cache): string {
        $cache_data = json_decode($video_cache->retrieveCache());
        return end($cache_data);
    }

    /**
     * Method for getting YouTube thumbnail and storing it as a base64 string in a cache file
     *
     * @param $cache_helper object The ExternalEmbedInterface
     * @param $video_id     string the YouTube video ID
     * @return string YouTube Thumbnail as a base 64 string
     */
    private function cacheYouTubeThumbnail(object $cache_helper, string $video_id): string {
        $thumbnail = $cache_helper->getYouTubeThumbnail($video_id);
        $cache_helper->cacheYouTubeThumbnail($video_id, $thumbnail); //use the helper interface to create the cache
        return $thumbnail['thumbnail']; //return the thumbnail encoded data
    }

    /**
     * Generates a cache json file using the playlist ID.
     * The cache file stores all the video ids from the playlist
     *
     * @param $cacheHelper
     * @param $parameters
     * @return mixed
     */
    private function cachePlaylist($cacheHelper, $parameters) {
        $playlist_data = $cacheHelper->getPlaylist($parameters['playlist_id']);
        return $cacheHelper->cachePlaylist($parameters['playlist_id'], $playlist_data);
    }
}
