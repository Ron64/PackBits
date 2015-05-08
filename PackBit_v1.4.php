<?php 
// PackBits algorithm implemented by Matthew Clark
// Updated to creating GBitmap from resource and new line-repeat compression by Ron64
// Version 1.4
// MIT license 2015 Matthew Clark and Ron64

//  constants

define('SOURCE_PATTERN', '*.png');
define('DEST_DIRECTORY', '../resources/data/');

 // define early      
	$snippet = null;
	$mode = 'init';
	$data = null;
	$line = null;
	$img_dest = null;
	$width = 1;
	$bytes = 1;

//  code
function get_line($num){
  	global $img_dest, $width;
	$rslt = null;
	$byte = 0;
	for ($x = 0;  $x < $width;  $x += 8) {
		for ($bit = 0;  $bit < 8;  $bit++)
			if (($x + $bit < $width) && ((imagecolorat($img_dest, $x + $bit, $num) & 0xff) > 0x80))
				$byte |= (1 << $bit);

		$rslt .= pack('C', $byte);
		$byte=0;
	}

	return $rslt;
}	
function cleanup(){
	global $snippet, $mode, $data;
    switch ($mode) {
      case 'unknown':
        //  just send one character
		$data .= pack('C', 0) . $snippet;
		break;
	  case 'repeat':
		//  add remaining repeated bytes
		$data .= pack('c', 1 - strlen($snippet)) . substr($snippet, -1);
		break;
	  case 'literal':
		//  add literal remainder
		$data .= pack('C', strlen($snippet) - 1) . $snippet;
		break;
	}
	$snippet = null;				  
	$mode = 'init';
}
function use_line() //test if the line compression can help
{
	global $bytes, $line;
	if ($bytes<2) return false;
	if (substr_count($line,substr($line,0,1) )==$bytes)
		return false;

	return true;
}

foreach (glob(SOURCE_PATTERN) as $filename) {

  
  $img_src = @imagecreatefrompng($filename);
  if ($img_src) {
    //  get source image size
    $width = imagesx($img_src);
    $height = imagesy($img_src);
    echo $filename . ' width=' . $width . ' height=' . $height . "\n";
    //  create destination image
    $img_dest = imagecreatetruecolor($width, $height);
    if ($img_dest) {
      //  copy
      if (imagecopyresized($img_dest, $img_src, 0, 0, 0, 0, $width, $height, $width, $height)) {
        //  initialize variables
        $snippet = null;
        $mode = 'init';
        //  start with width and height
        $data = pack('CC', $width, $height);
		$line = get_line(0);
		$bytes= strlen($line);
        $lastY= $height-1; $lastX= $bytes-1;
        if ($bytes<3){ //Last 2 bytes will be literal to allow using same GBitmap memory for decompression
          if ($bytes==2)
            {$lastY= $height-2; $lastX= 1;}
          if ($bytes==1)
            {$lastY= $height-3; $lastX= 0;}
        } else
          $lastX= $bytes-3;
        //  loop through image
        for ($y = 0;  $y <= $lastY;  $y++) {
		  $line = get_line($y);
			
		  if (($y <= $lastY-1)){// & test_line() ){//process lines
			$next_next= get_line($y+1);
			$repeats=1;
			while (($next_next==$line) & ($y+$repeats <= $lastY-1) & use_line() ){
		      $repeats=1;

			  while (($next_next==$line) & ($y+$repeats <= $lastY-1)){ // count all repeats
			    $repeats++;
			    $next_next=get_line($y+$repeats);
			  }
		      $next_next=null;
			  if ( ($repeats>2) | ($bytes>2) ){ //if it's too short literal will be same or better
				// purge unsaved data
				if (strlen($snippet)>0)
				 cleanup();    		  
				if ($repeats == 2)
				  $data .= pack('C', 1- 0x7D) . $line;
				else
				  $data .= pack('CC', 1- 0x7E , $repeats) . $line;

				$y+=$repeats;
				$line = get_line($y);
				if ($y <= $lastY-1)
				  $next_next= get_line($y+1);
              }
			}
		  }
          $length=$bytes;
          if ($y==$lastY){
            $length= $lastX+1;
          }
          for ($x = 0;  $x < $length;  $x++) {
			$byte= substr($line,$x,1);

            //  check mode
            switch ($mode) {
              case 'init':
                $snippet = $byte;
                $mode = 'unknown';
                break;
              case 'unknown':
                //  set to repeat or literal mode
                $mode = ($byte == substr($snippet, -1)) ? 'repeat' : 'literal';
                //  add to new repeat sequence
                $snippet .= $byte;
                break;
              case 'repeat':
                if ($byte == substr($snippet, -1)) {
                  //  check for too much data
                  if (strlen($snippet) >= 0x7A) {
                    //  add key for 122 repeated items
                    $data .= pack('c', 1 - strlen($snippet)) . substr($snippet, -1);
                    //  reset
                    $snippet = null;
                    $mode = 'unknown';
                  }
                } else {
                  if (strlen($snippet) >= 3){
                    //  add repeated data so far
                    $data .= pack('c', 1 - strlen($snippet)) . substr($snippet, -1);
                    //  start new sequence
                    $snippet = null;
                    $mode = 'unknown';
                  } else{
                    $mode = 'literal'; //only two repeating bytes are better handled as literal
                  }
                }
                //  add this value
                $snippet .= $byte;
                break;
              case 'literal':
                if ( ($byte == substr($snippet, -1  )) && (strlen($snippet) > 3) &&
                     ($byte == substr($snippet, -2,1)) ) {// change to repeat only if more than 2 exist
                  //  save literals (except last character)
                  $data .= pack('C', strlen($snippet) - 3) . substr($snippet, 0, strlen($snippet) - 2);
                  //  restart repeat sequence
                  $snippet = $byte.$byte;
                  $mode = 'repeat';
                  //  check for too much data
                } elseif (strlen($snippet) >= 0x80) {
                  //  add key for 128 items
                  $data .= pack('C', 0x7f) . $snippet;
                  //  reset
                  $snippet = null;
                  $mode = 'unknown';
                }
                //  add this character
                $snippet .= $byte;
                break;
            }
          }
        }
        //  clean-up
		cleanup();
        // Adding last 2 bytes to end.
        if ($bytes==2)
		  $data .= get_line($height-1);
		elseif ($bytes==1)
		  $data .= get_line($height-2) . get_line($height-1);
        else
          $data .= substr($line, -2);

        //  save packbits image
        file_put_contents(DEST_DIRECTORY . substr($filename, 0, strlen($filename) - 3) . 'pbt', $data);
        //  done with copy
        imagedestroy($img_dest);
      }
    }
    //  done with source
    imagedestroy($img_src);
  }
}
?>
