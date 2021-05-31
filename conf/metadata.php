<?php
/**
 * Options for the ytembed plugin
 *
 * @author Cameron <cameronward007@gmail.com>
 */


$meta['YT_API_KEY'] = array('string');
$meta['PLAYLIST_CACHE_TIME'] = array('numeric', '_pattern' => '/^[0-9]+$/'); //only accept numbers

