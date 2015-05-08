// PackBits algorithm implemented by Matthew Clark
// Updated to creating GBitmap from resource and new line-repeat compression by Ron64
// Version 1.4
// MIT license 2015 Matthew Clark and Ron64
#include <pebble.h>
#include "packbits.h"

//  functions

#ifdef PBL_COLOR

//  variables

bool use_palette= false; //flag to activate palette

static unsigned char lookup[16] = {
  0x0, 0x8, 0x4, 0xc, 0x2, 0xa, 0x6, 0xe,
  0x1, 0x9, 0x5, 0xd, 0x3, 0xb, 0x7, 0xf, };

uint8_t reverse(uint8_t n) {
   // Reverse the top and bottom nibble then swap them.
   return (lookup[n&0b1111] << 4) | lookup[n>>4];
}
uint8_t fix(uint8_t n){
  if (use_palette)
    return reverse(n);
  else return n;
}

#else
uint8_t fix(uint8_t n){
 return n;
}
#endif

GBitmap* gbitmap_create_from_pbt(uint32_t resource){
  ResHandle res_handle = resource_get_handle(resource);
  if (!res_handle)
    return NULL;
  int r_size = resource_size(res_handle);
  if (r_size<=0)
    return NULL;
  uint16_t d_size = r_size-2;// reduce 2 bytes header of width & height
  if (d_size>2)
     d_size= d_size-2; // reduce 2 bytes to prevent overwriting compressed buffer with data

  //  get object size
  uint8_t width, height ;
  resource_load_byte_range(res_handle, 0, &width, 1);
  resource_load_byte_range(res_handle, 1, &height, 1);

  uint8_t xbytes = (width + 7) / 8; 
  GBitmap * image;
  #ifdef PBL_COLOR
    if (!use_palette)
      image= gbitmap_create_blank(GSize(width,height),GBitmapFormat1Bit);
    else
      image= gbitmap_create_blank(GSize(width,height),GBitmapFormat1BitPalette);
  #else
    image= gbitmap_create_blank(GSize(width,height));
  #endif
  if (image==NULL)
    return NULL; //failed to allocate bitmap
  
  uint8_t raw_bytes; //number of bytes in each line.
  uint8_t *pixels;   //Image data
  uint8_t* buffer;   //Area in end of Image data used to store compressed data before extracting them.
  #ifdef PBL_COLOR
    pixels= gbitmap_get_data(image);
    raw_bytes = gbitmap_get_bytes_per_row(image);
  #else
    pixels= image->addr;
    raw_bytes = image->row_size_bytes;
  #endif
  buffer= pixels + (raw_bytes * height)- d_size;
  
  //  load compressed data
  resource_load_byte_range(res_handle, 2, buffer, d_size);
  register uint8_t* src = buffer;  // src run through compressed data while reading them. 
  //  unpack image
  int x = 0, y = 0;
  uint8_t fill;
  while ((src < buffer + d_size) && (y < height)) { 

    int count = src[0];
    if (src[0] >= 134) {
      //  repeat sequence
      count = 257 - count;    //  2's complement conversion + 1
      fill=fix(src[1]); 
      for (int i = 0;  i < count;  i++) {
        pixels[raw_bytes * y + x] = fill;
        if (++x >= xbytes) {
          x = 0;
          y++;
          if (y >= height)
            break;
        }
      }
      src += 2;
    } else if (*src <= 127){ // !(*src >= 128)
      //  literal
      count++;
      for (int i = 0;  i < count;  i++) {
        pixels[raw_bytes * y + x] = fix(src[i + 1]);
        if (++x >= xbytes) {
          x = 0;
          y++;
          if (y >= height)
            break;
        }
      }
      src += 1 + count;
    } else if ((src[0] == 0x83)||(src[0] == 0x84)){ //repeating lines
      //  Repeating Lines
      count=2;
      if (src[0] == 0x83){
        count=src[1];
        src++;
      }
      for (int i = 0;  i < count;  i++) {
        for (x=0; x<xbytes; x++)
          pixels[raw_bytes *y + x] = fix(src[x + 1]);
        y++;
        if (y >= height)
          break;
      }
      src += 1+ xbytes;
      x=0;
    } //repeating lines
  }
  if (xbytes>=2)
    resource_load_byte_range(res_handle, r_size-2, &pixels[raw_bytes *(height-1)+ xbytes-2 ], 2);
  else{
    resource_load_byte_range(res_handle, r_size-2, &pixels[raw_bytes *(height-2) ], 1);
    resource_load_byte_range(res_handle, r_size-1, &pixels[raw_bytes *(height-1) ], 1);
  };
  #ifdef PBL_COLOR
    use_palette=false;
  #endif
  return image;
};

#ifdef PBL_COLOR
GBitmap * gbitmap_create_from_pbt_with_color(uint32_t resource, GColor color_0, GColor color_1){
  use_palette= true;
  GBitmap *bmp_image = gbitmap_create_from_pbt(resource);
  if (bmp_image==NULL)
    return NULL;
  GColor * palette= gbitmap_get_palette(bmp_image);
  palette[0]= color_0;
  palette[1]= color_1;
  return bmp_image;
};
#endif