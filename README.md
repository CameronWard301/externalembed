# Dokuwiki External Embed

A Dokuwiki plugin to embed external content from the web  
This plugin includes domain privacy features allow you to embed content without needing a cookie / privacy policy for
the embedded content This plugin will ask the user to accept embedded content from a domain before loading it (
preventing it from loading trackers and cookies to the user's browser without consent) - see DOMAIN_WHITELIST

## The road map:

* Currently, only YouTube has been tested with this plugin. I would like to make it more robust so that content from
  other domains can also be easily embedded (it is currently possible in this version however)

_____

## Prerequisites:

To embed YouTube content:

* Go to https://console.cloud.google.com/ and create a Google developer account
* Once registered add a YouTube Data API v3 API to your account.
* When setting up, restrict the key to only use the YouTube data API. You also may want to set an application
  restriction for a production server.

## Installation

* Place the airtable folder inside your Dokuwiki plugin directory:
  DOKUWIKI_ROOT/lib/plugins
* Set your **API_KEY** using Dokuwiki's [configuration Manager](https://www.dokuwiki.org/plugin:config)
* Set **PLAYLIST_CACHE_TIME** parameter - this is the time in hours before the playlist cache expires. I recommend 24
  here.
* Set **THUMBNAIL_CACHE_TIME** Parameter - this is the time in hours before the thumbnail cache expires. I recommend 100
  here
* Set **MINIMUM_EMBED_WIDTH** Parameter - the minimum size for an embed (200 default)
* Set **MINIMUM_EMBED_HEIGHT** Parameter - the minimum height for an embed (200 default)
* Set **DEFAULT_PRIVACY_DISCLAIMER** Parameter - The default disclaimer for an embed if you have not set a specific
  disclaimer in the whitelist section below:
* Set **DOMAIN_WHITELIST** Parameter - `<DOMAIN, DISCLAIMER (optional)>`  
  Enter the domain you want to whitelist. If you wish to set a custom disclaimer for this domain use a comma (see
  example below:)
  * `youtube.com, A YouTube video has been embedded here. You must accept the <a href="https://www.youtube.com/static?template=terms" target="_blank" rel="noopener">terms and conditions</a> in order to view the video`
  * Note how you can use standard HTML here to embed Terms Of Service links.
  * Separate each whitelist item with a new line

## Usage:

Use the following syntax on any dokuwiki page.  
`{{external_embed>url: "theURL"}}`

Each parameter: is followed by a space and values enclosed in "". Parameters are separated by ' | ' (note the importance
of spaces here)

Required Parameters:

* `url: ` - The URL associated with the embed

Optional Parameters:

* `embed-position:` - Displays the embedded content to the right, left or centre of the page. Values:
  * `left`
  * `centre`
  * `right`

### YouTube Video:

To display a video from YouTube use the following syntax:  
`{{external_embed>url: "https://www.youtube.com/watch?v=UKvqC3t-M1g&ab_channel=Wintergatan" | autoplay: "true" | width: "720" | height: "480" | mute: "true" | controls: "false"}}`

Optional Parameters:

* `height` - Sets the maximum height of the iframe. Values:
  * `360`
  * `480`
  * `720` - Default
  * `1080`
* `autoplay` - Specifies if the video will autoplay when the page is loaded. Values:
  * `true`
  * `false` - Default
* `mute` - Specifies if the video will muted if autoplay is enabled. Values:
  * `true`
  * `false` - Default
* `controls` - Specifies if the iframe will have video controls.
  * `true` - Default
  * `false`

#### Example:

`{{external_embed>url: https://www.youtube.com/watch?v=UKvqC3t-M1g&ab_channel=Wintergatan" | autoplay: "true" | width: "720" | height: "480" | mute: "true" | controls: "false"}}`

### YouTube Playlist - The Latest Video:

The following syntax is used to get the latest video from a YouTube playlist.

Note that the rendered dokuwiki page is cached, you may want to find a way to auto purge your cache so that your videos
stay up to date: https://www.dokuwiki.org/devel:caching

The video ID's are stored and cached in a json cache file - these cache files last for the number of hours set in the
config file.  
This is to prevent and reduce the number of calls needed to the YouTube API (maximum is 10,000 per day)  
`{{external_embed>url: "PLAYLIST_URL"}}`

Optional Parameters:

The same optional parameters used for the video type can also be used when using the playlist type

#### Example:

`{{external_embed>url: "https://www.youtube.com/playlist?list=PLLLYkE3G1HED6rW-bkliHbMroHYFf4ukv" | autoplay: "true" | height: "480" | mute: "true"}}`

### Fusion:

The following syntax is used to embed a fusion file using the fusion web player:
`{{external_embed>url: FUSION_URL | height: "480 | allowFullScreen: "false"`

Optional Parameters:

* `width` - the width of the iframe. Default is 1280
* `height` - the height of the iframe. Default is 720
* `allowFullScreen` - allow users to make the embed fullscreen. Values:
  * `true` - default
  * `false`

#### Example:

`{{external_embed>url: "https://inventopia.autodesk360.com/g/shares/SH56a43QTfd62c1cd968f6d420f217923c26" | width: "720" | height: "480" | allowFullScreen: "false"}}`

