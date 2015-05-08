<?php 
// PackBits algorithm implemented by Matthew Clark
// One bugfix by Ron64
// Targeted to be released under MIT license

//  constants

define('SOURCE_PATTERN', '*.png');
define('DEST_DIRECTORY', '../resources/data/');

//  code
//print 'point1';
foreach (glob(SOURCE_PATTERN) as $filename) {

  
  $img_src = @imagecreatefrompng($filename);
echo $img_src;
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
        //  loop through image
        for ($y = 0;  $y < $height;  $y++) {
          for ($x = 0;  $x < $width;  $x += 8) {
            //  calculate byte
            $byte = 0;
            for ($bit = 0;  $bit < 8;  $bit++)
              if (($x + $bit < $width) && ((imagecolorat($img_dest, $x + $bit, $y) & 0xff) > 0x80))
                $byte |= (1 << $bit);
            $byte = pack('C', $byte);
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
                  if (strlen($snippet) >= 0x78) { //Was 0x80. Reduced to reserve opCode for future update.
                    //  add key for 120 repeated items
                    $data .= pack('c', 1 - strlen($snippet)) . substr($snippet, -1);
                    //  reset
                    $snippet = null;
                    $mode = 'unknown';
                  }
                } else {
                  //  add repeated data so far
                  $data .= pack('c', 1 - strlen($snippet)) . substr($snippet, -1);
                  //  start new sequence
                  $snippet = null;
                  $mode = 'unknown';
                }
                //  add this value
                $snippet .= $byte;
                break;
              case 'literal':
                if (($byte == substr($snippet, -1)) && (strlen($snippet) > 2)) {
                  //  save literals (except last character)
                  $data .= pack('C', strlen($snippet) - 2) . substr($snippet, 0, strlen($snippet) - 1);
                  //  restart repeat sequence
                  $snippet = $byte;
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
        //  cleanup
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
        //  save packbits image
        file_put_contents('../resources/data/' . substr($filename, 0, strlen($filename) - 3) . 'pbt', $data);
        //  done with copy
        imagedestroy($img_dest);
      }
    }
    //  done with source
    imagedestroy($img_src);
  }
}
?>
