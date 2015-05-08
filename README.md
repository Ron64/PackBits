# PackBits
Image compression library for Pebble smart watch
By Matt Clark & Ron64

This Image compression library is an adaptation of [PackBits](http://en.wikipedia.org/wiki/PackBits) algorithm on for 2 colour images for Pebble smart-watch.

PackBits was implemented by Matthew Clark. Several bug-fix, modifications and additions by Ron64

## Background & information
The Pebble pbi format was designed to easily load B/W images to GBitmap. It uses 32 bit padding like GBitmap. PackBits uses 8Bit padding, so even if there is no significant reduction of size by compression, for most images we gain from leaving out 1-3 bytes per line.

I (Ron64) adopted PackBits for my new watchface CJ12 that like 'Finally' includes several popular watchfaces. Some of them had many resources, so I needed to compress more. Matthew was kind enough to share his implementation of packbits (old lossless RLE compression [by apple](http://web.archive.org/web/20080705155158/http://developer.apple.com/technotes/tn/tn1023.html)). 

This repository will include several examples:
Taller
91Dub2


## Using PackBits

### Compression
The script packbits.php should run in the folder with the png images to be processed.

For users who like full instructions on how to use it:

* Install PHP on your machine (including php-gd2)
* Make sure you have resources/data folder
* Create a folder in project root for the images needed to be converted. (They can be removed from resources/images)
* Run the script from the above folder. The *.png images will be converted and placed in resources/data with pbt extension.

### Updating resources
Add the images as raw, located at resources/data

(I saved manual work by taking original appinfo.json, separating the converted images and doing search+replace of "png" => "raw" , .png" => .pbt" , images/ => data/ )

### Load PackBits images
To load PackBits compressed images in your watchface preform the flowing steps:

* Add packbits.c & packbits.h in your project.
* Add to your relevant source: #include "packbits.h"
* Load images using gbitmap_create_from_pbt() instead of [gbitmap_create_with_resource()](http://developer.getpebble.com/docs/c/Graphics/Graphics_Types/#gbitmap_create_with_resource)
* For Basalt the optional gbitmap_create_from_pbt_with_color(resource, color_0, color_1) will create image with selected colours.

## Additional information

### Included examples and resources
(Will be added soon)
* 91Dub-2
* Taller2
* Visual studio debug project.

### Code and compression 
* The PackBits library adds under 1/2 KB to the project compiled size. 
* A common screen background image will be compressed from 3.3k to 1k-1.5K.
* Small images like fonts are likely to reduce to 20-50% compared to PBI size
* Small images are likely to be compressed to smaller size than with the PNG used in SDK3 due to lower overhead (RAM usage for all images is also insignificant comparing to PNG extraction)


### version Info

Ver 1.4
* Added repeating line detection. (Do not mix files compressed with previous version with new decompression)
* Added option to create colour images for Basalt
* Extracting images using the original bitmap. (save allocating extra buffer for decompression and reduce memory fragmentation.)
* Optimised compression to use literal instead of short repeating sequence that caused some sections of results to be longer than source.

Ver 1.0
* Initial release

### Future plans
Currently I consider flowing upgrades:
* Add support to compressing colour images.
* Add detection for lines that occurred before.
* Add other drawing method like [graphics_draw_bitmap_in_rect()](http://developer.getpebble.com/docs/c/Graphics/Drawing_Primitives/#graphics_draw_bitmap_in_rect) as Matt used originally. (Is anyone interested?)
* Establish web server to give service of image compression. (Looking for developer that can build a frame for it (load zip, extract, run PHP script, compress result and give download link)

We would like to hear from developers who use it, and any comments about it.
