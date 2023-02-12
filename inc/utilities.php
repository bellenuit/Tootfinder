<?php 
	
	
/**
 *	general purpose functions
 * 
 *  @version 1.2 2023-02-12
 */
	
if (!defined('CRAWLER')) die('invalid acces');



function getRemoteFile($url, $getheaders = false)
{
       	$c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        if ($getheaders)
        {
	        curl_setopt($c, CURLOPT_HEADER, 1);
			curl_setopt($c, CURLOPT_NOBODY, 1);
		}
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c,CURLOPT_TIMEOUT,5); // seconds
        curl_setopt($c, CURLOPT_USERAGENT, 'Tootfinder/1.1 (+https://www.tootfinder.ch/index.php)');
        $contents = curl_exec($c);
                
        $result = $contents;  
        
        // print_r($c);    

        if ($result)
        {
        	curl_close($c);
        	return $result;
        }
        else 
        {
            // echo curl_error($c);
            curl_close($c);
            return false;
         }
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
	//echo "<p>$a</p>";
	$a = preg_replace('/[^a-zA-Z&0-9 ]/',' ',$a);
	$list = array();
	foreach(explode(' ',$a) as $w)
	{
		//echo $w.' ';
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

