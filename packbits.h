// PackBits algorithm implemented by Matthew Clark
// Updated to creating GBitmap from resource and new line-repeat compression by Ron64
// Version 1.4
// MIT license 2015 Matthew Clark and Ron64
#pragma once
	
GBitmap* gbitmap_create_from_pbt(uint32_t resource);

#ifdef PBL_COLOR
GBitmap* gbitmap_create_from_pbt_with_color(uint32_t resource, GColor color_0, GColor color_1);
#endif