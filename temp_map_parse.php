<?php
$map_url='https://maps.app.goo.gl/mk9aNJbRpvwpoLft8';
if(!preg_match('/^https?:\/\//i',$map_url)){
    $map_url='https://'.ltrim($map_url,'/');
}
$map_host=strtolower((string)(parse_url($map_url, PHP_URL_HOST) ?? ''));
$parse_url=$map_url;
if(($map_host==='maps.app.goo.gl'||$map_host==='goo.gl') && function_exists('curl_init')){
    $ch=curl_init($map_url);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_NOBODY,true);
    curl_setopt($ch,CURLOPT_TIMEOUT,5);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,3);
    curl_setopt($ch,CURLOPT_MAXREDIRS,5);
    curl_exec($ch);
    $effective=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if(!empty($effective)&&$effective!==$map_url){
        $parse_url=$effective;
    }
}
$lat=null;
$lng=null;
if(preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/',$parse_url,$m)){
    $lat=$m[1];
    $lng=$m[2];
} elseif(preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/',$parse_url,$m)){
    $lat=$m[1];
    $lng=$m[2];
}
echo 'PARSE_URL=' . $parse_url . PHP_EOL;
echo 'LAT=' . ($lat ?? '') . PHP_EOL;
echo 'LNG=' . ($lng ?? '') . PHP_EOL;
