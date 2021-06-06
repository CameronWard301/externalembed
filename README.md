# YouTube Video/Playlist Dokuwiki Embed

A Dokuwiki plugin to sync data from airtable

## Prerequisites:

* Go to https://console.cloud.google.com/ and create a google developer account
* Once registered add a YouTube Data API v3 API to your account.
* When setting up, restrict the key to only use the youtube data API. You also may want to set an application
  restriction for a production server.

## Installation

* Place the airtable folder inside your Dokuwiki plugin directory:
  DOKUWIKI_ROOT/lib/plugins
* Set your **API_KEY** using Dokuwiki's [configuration Manager](https://www.dokuwiki.org/plugin:config)
* set **PLAYLIST_CACHE_TIME** parameter - this is the time in hours before the playlist cache expires. I recommend 24 here.

## Usage:

Use the following syntax on any dokuwiki page.  
`{{YT_embed>url: "theURL"}}`

Each parameter: is followed by a space and values enclosed in "". Parameters are separated by ' | ' (note the importance
of spaces here)

Required Parameters:

* `url: ` - The YouTube URL associated with the video/playlist

### Video:

To display a video from YouTube use the following syntax:  
`{{YT_embed>url: "https://www.youtube.com/watch?v=UKvqC3t-M1g&ab_channel=Wintergatan" | autoplay: "true" | width: "720" | height: "480" | mute: "true" | controls: "false"}}`

Optional Parameters:

* `width` - The width of the iframe. Default is 1280
* `height` - The height of the iframe. Default is 720
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

`{{YT_embed>url: https://www.youtube.com/watch?v=UKvqC3t-M1g&ab_channel=Wintergatan" | autoplay: "true" | width: "720" | height: "480" | mute: "true" | controls: "false"}}`

### The Latest Video From Playlist:

The following syntax is used to get the latest video from a YouTube playlist.

Note that the rendered dokuwiki page is cached, you may want to find a way to auto purge your cache so that your videos
stay up to date: https://www.dokuwiki.org/devel:caching

The video ID's are stored in a json cache file named <playlistID>.json - these cache files last for the number of hours
set in the config file.  
This is to prevent and reduce the number of calls needed to the YouTube API (maximum is 10,000 per day)  
`{{YT_embed>url: "PLAYLIST_URL"}}`

Optional Parameters:

The same optional parameters used for the video type can also be used when using the playlist type

#### Example:

`{{YT_embed>url: "https://www.youtube.com/playlist?list=PLLLYkE3G1HED6rW-bkliHbMroHYFf4ukv" | autoplay: "true" | width: "720" | height: "480" | mute: "true"}}`
