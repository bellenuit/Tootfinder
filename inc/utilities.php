<?php 
	
	
/**
 *	general purpose functions
 * 
 *  @version 2.2 2023-09-10
 */
	
if (!defined('CRAWLER')) die('invalid acces');


function getRemoteFiles($jobs)
{
	$mh = curl_multi_init();
	foreach($jobs as $k => $v)
	{
		$f = fopen($v,"wb");
		$c = curl_init();
		
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($c, CURLOPT_URL, $k);
		curl_setopt($c, CURLOPT_TIMEOUT,5);
		curl_setopt($c, CURLOPT_USERAGENT, 'Tootfinder/1.1 (+https://www.tootfinder.ch/index.php)');
		if ($fv = @filemtime($v)) 
		{
			curl_setopt($c, CURLOPT_TIMEVALUE, $fv);
			curl_setopt($c, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
		}
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($c, CURLOPT_FILE, $f); 
		
	    if (substr($v,-5)=='.json') curl_setopt($c, CURLOPT_HTTPHEADER, array('Accept: application/activity+json'));

		curl_multi_add_handle($mh,$c);
	}
	do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
	} while ($running > 0);

}


function getRemoteString($url,$v = '')
{
       	$c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_TIMEOUT,5);
        curl_setopt($c, CURLOPT_USERAGENT, 'Tootfinder/1.1 (+https://www.tootfinder.ch/index.php)');
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        if (substr($v,-5)=='.json') curl_setopt($c, CURLOPT_HTTPHEADER, array('Accept: application/activity+json'));
        $contents = curl_exec($c);
        curl_close($c);
		return $contents;
 }

function header2dict($s)
{
 	$result = array();
 	$lines = explode(PHP_EOL,$s);
 	foreach($lines as $line)
 	{
	 	$fields = explode(':',$line);
	 	if (count($fields) < 2) continue;
	 	$key = array_shift($fields);
	 	$value = join(':',$fields);
	 	$result[$key][] = $value;
	}
	return $result;
}

function soundexLong($a)
{
	$a = preg_replace('/[^a-zA-Z&0-9 ]/',' ',$a);
	$list = array();
	foreach(explode(' ',$a) as $w)
	{
		$list[] = soundex($w);
	}
	return join(' ',$list);	
}


function xml2array( $xmlObject, $out = array () )
{
        foreach ( (array) $xmlObject as $index => $node )
            $out[$index] = ( is_object ( $node ) ||  is_array ( $node ) ) ? xml2array ( $node ) : $node;

        return $out;
}

function debugLog($s)
{
	global $tfDebug; 
	if (strlen($tfDebug) < 10000000) $tfDebug .= $s;  // prevent overflow
}

function wordList($s)
{
	$p = str_replace('<p'," <p",$s);
	$p = str_replace('<br'," <br",$p);	  	 
	$p = preg_replace('/<.*?>/','',$p); 
	$p = preg_replace('#https?://\S*#','',$p);	  	 
	$p = preg_replace('/[!-)\+-,\.\/:-@[-`\{-~]|\*|#|’|-/',' ',$p);
	
	// [!-) ascii 33-41
	// \. ascii 46
	// \/ ascii 47
	// :-@ ascii 58-64
	// [-` ascii 91-96
	// \{-~ ascii 123-126
	
	return array_unique(explode(' ',$p));
}

