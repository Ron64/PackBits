#include <pebble.h>
#include "packbits.h"
// PackBits algorithm implemented by Matthew Clark
// Updated to creating GBitmap from resource by Ron64
// Targeted to be released under MIT license
	
//  variables

//  functions

GBitmap* gbitmap_create_from_pbt(uint32_t resource){
  ResHandle res_handle = resource_get_handle(resource);
  if (res_handle) {
    int n_size = resource_size(res_handle);
    if (n_size) {
      //  allocate buffer
      uint8_t* buffer = malloc(n_size); // ToDo: consider using bitmap buffer (after loading width,height)
      if (buffer) {
        //  load resource
        resource_load(res_handle, buffer, n_size);
        //  get object size
        register uint8_t* src = buffer;
        uint8_t width = *(src++);
        uint8_t height = *(src++);
        uint8_t xbytes = (width + 7) / 8; //w:48 h:48 xb:6 raw:8
        uint8_t raw_bytes = ((xbytes + 3) & 0xFC); //can also be read from GBitmap
        //APP_LOG(APP_LOG_LEVEL_DEBUG, "w:%d h:%d xb:%d raw:%d",width,height,xbytes,raw_bytes);
        GBitmap * image= gbitmap_create_blank(GSize(width,height));
        if (image==NULL){
          free(buffer);			
          return NULL; //failed to allocate bitmap
		}
        uint8_t *pixels = image->addr;
		  
        //  unpack image
        int x = 0, y = 0;
        while ((src < buffer + n_size) && (y < height)) {
          int count = *src;
          if (*src >= 128) {
            //  repeat sequence
            count = 257 - count;    //  2's complement conversion + 1
            for (int i = 0;  i < count;  i++) {
              pixels[raw_bytes * y + x] = src[1];
              if (++x >= xbytes) {
                x = 0;
                y++;
                if (y >= height)
                  break;
              }
            }
            src += 2;
          } else { // !(*src >= 128)
            //  literal
            count++;
            for (int i = 0;  i < count;  i++) {
              pixels[raw_bytes * y + x] = src[i + 1];
              if (++x >= xbytes) {
                x = 0;
                y++;
                if (y >= height)
                  break;
              }
            }
            src += 1 + count;
          }
        }
        free(buffer);
        return image;
      }
	  else 
        return NULL; //can't allocate buffer
    }
    else //resource zero
      return NULL;
  }
  else //fail to load resource
    return NULL;
}

