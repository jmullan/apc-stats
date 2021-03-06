<?php
  /*
   +----------------------------------------------------------------------+
   | APC                                                                  |
   +----------------------------------------------------------------------+
   | Copyright (c) 2006 The PHP Group                                     |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | http://www.php.net/license/3_01.txt                                  |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors: Jesse Mullan <jmullan@visi.com>                             |
   |          Ralf Becker <beckerr@php.net>                               |
   |          Rasmus Lerdorf <rasmus@php.net>                             |
   |          Ilia Alshanetsky <ilia@prohost.org>                         |
   +----------------------------------------------------------------------+
   
   All other licensing and usage conditions are those of the PHP Group.
   
  */

$VERSION='$Id: apc.php,v 3.68.2.1 2008/03/25 18:04:53 gopalv Exp $';
require('apc_lib.class.php');
class APC_IMG {
    private $graph_size;
    private $graph_width;
    private $graph_height;
    private $text_height;
    private $text_width;
    private $column_width;
    private $row_height;
    private $place_limit;
    private $row_count;
    private $column_count;
    private $image;
    private $apc_lib;
    private $col_white;
    private $col_red;
    private $col_green;
    private $col_black;
    private $apc_views;
    private $centerX;
    private $centerY;
    /**
     * This is the default graphic size.
     */
    function __construct($apc_lib) {
	$this->apc_lib = $apc_lib;
	$this->text_height = 12;
	$this->text_width = 80;
	$this->row_height = $this->text_height;
	$this->column_width = $this->text_width + 20;
	$this->set_graph_size(200);
    }
    function set_apc_views($apc_views) {
	$this->apc_views = $apc_views;
    }
    function get_graph_size() {
	return $this->graph_size;
    }
    function set_graph_size($size) {
	
	$size = round($size);
	$this->graph_size = $size;
	$this->graph_width = $size * 2;
	$this->graph_height = $size + 10;
	$this->column_count = max(1, floor($this->graph_width / $this->column_width) - 1);
	$this->row_count = floor($this->graph_height / $this->text_height);
	$this->place_limit = $this->row_count * $this->column_count;
    }
    function get_graph_width() {
	return $this->graph_width;
    }
    function get_graph_height() {
	return $this->graph_height;
    }
    function graphics_avail() {
	return extension_loaded('gd');
    }
    private function init_image($number_image) {
	$this->image = imagecreate($this->graph_width, $this->graph_height);
	$this->col_white = imagecolorallocate($this->image, 0xFF, 0xFF, 0xFF);
	$this->col_red = imagecolorallocate($this->image, 0xD0, 0x60, 0x30);
	$this->col_green = imagecolorallocate($this->image, 0x60, 0xF0, 0x60);
	$this->col_mask = imagecolorallocate($this->image, 0x60, 0xF0, 0x61);
	$this->col_black = imagecolorallocate($this->image, 0, 0, 0);
	$this->centerX = $this->graph_width / 2;
	$this->centerY = $this->graph_height / 2;
	imagecolortransparent($this->image, $this->col_white);
    }
    private function polar_to_x($degrees, $radius) {
	return $this->centerX + round(cos(deg2rad($degrees)) * $radius);
    }
    private function polar_to_y($degrees, $radius) {
	return $this->centerY + round(sin(deg2rad($degrees)) * $radius);
    }
    private function fill_box($left, $top, $width, $height, $color_fill, $text = '', $place_index = null) {
	imagerectangle($this->image, $left, $top, $left + $width, $top + $height, $this->col_black);
	if ($height > 2) {
	    imagefilledrectangle($this->image,
				 $left + 1,
				 $top + 1,
				 $left + $width - 1,
				 $top + $height - 1,
				 $color_fill);
	}
	if ($text) {
	    if (!is_null($place_index)) {
		$columns = floor($this->graph_width / $this->column_width) - 1;
		$rows = floor($this->graph_height / $this->row_height);
		$column = floor($place_index / $rows);
		$row = $place_index % $rows;
		$box_size = max(1, round($this->text_height / 3));
		$box_offset = min($box_size, round($this->text_height / 3));
		$text_top = $row * $this->row_height;
		if (0 == $column) {
		    $text_left = 0;
		    imagestring($this->image, 2, $text_left, $text_top, $text, $this->col_black);	
		    imagefilledrectangle($this->image,
					 $text_left + $this->text_width,
					 $text_top + $box_offset,
					 $text_left + $this->text_width + $box_size,
					 $text_top + $box_offset + $box_size,
					 $color_fill);
		    imageline($this->image,
			      $left,
			      $top + round($height / 2),
			      $text_left + 80 + $box_size,
			      $text_top + $box_offset + round($box_size / 2),
			      $color_fill);
		} else {
		    $text_left = $this->column_width * ($column + 1);
		    imagestring($this->image, 2, $text_left, $text_top, $text, $this->col_black);
		    imagefilledrectangle($this->image,
					 $text_left - ($box_offset * 4),
					 $text_top + $box_offset,
					 $text_left - ($box_offset * 4) + $box_size,
					 $text_top + $box_offset + $box_size,
					 $color_fill);
		    imageline($this->image,
			      $left + $width,
			      $top + round($height / 2),
			      $text_left - ($box_offset * 4),
			      $text_top + round($box_size / 2),
			      $color_fill);
		}
	    }
	}
    }
    private function draw_arc($start, $end, $color_fill, $text = '', $place_index = 0) {
	$cx = $this->centerX;
	$cy = $this->centerY;
	$diam = $this->graph_size;
	$radius = $diam / 2;
	$slice_center_angle = (($start + $end + 720) / 2) % 360;
	$arc_start_x = $this->polar_to_x($start, $radius);
	$arc_start_y = $this->polar_to_y($start, $radius);
	$arc_middle_x = $this->polar_to_x($slice_center_angle, $radius);
	$arc_middle_y = $this->polar_to_y($slice_center_angle, $radius);
	$arc_end_x = $this->polar_to_x($end, $radius);
	$arc_end_y = $this->polar_to_y($end, $radius);
	if (0 == $start && 360 == $end) {
	    $arc_covers = 360;
	} else {
	    $arc_covers = ($end - $start) % 360;
	}
	$slice_center_x = $this->polar_to_x($slice_center_angle, $radius / 2);
	$slice_center_y = $this->polar_to_y($slice_center_angle, $radius / 2);
	if (360 == $arc_covers) {
	    $slice_fill_x = $cx;
	    $slice_fill_y = $cy;
	} else {
	    $slice_fill_x = $this->polar_to_x($slice_center_angle, $radius * .95);
	    $slice_fill_y = $this->polar_to_y($slice_center_angle, $radius * .95);
	}
	/* All calculations featuring $start and $end are done, so turn them
	 * into ints for the benefit of the gd functions */
	$start = round($start);
	$end = round($end);
	$arc_virtually_covers = abs($end - $start);
	$palette_index = imagecolorat($this->image, $slice_fill_x, $slice_fill_y);
	if ((($arc_start_x == $arc_end_x && $arc_start_y == $arc_end_y)
	     || 0 == $arc_virtually_covers)
	    && 360 != $arc_covers) {
	    imageline($this->image, $cx, $cy, $arc_start_x, $arc_start_y, $this->col_black);
	} elseif (4 > sqrt(pow($arc_start_x - $arc_end_x, 2) + pow($arc_start_y - $arc_end_y, 2))) {
	    imageline($this->image, $cx, $cy, $arc_middle_x, $arc_middle_y, $color_fill);
	    imageline($this->image, $cx, $cy, $arc_start_x, $arc_start_y, $this->col_black);
	    imageline($this->image, $cx, $cy, $arc_end_x, $arc_end_y, $this->col_black);
	} elseif (function_exists('imagefilledarc')) {
	    /* exists only if GD 2.0.1 is avaliable */
	    if (360 == $arc_covers) {
		/* This is the shadow */
		imagefilledarc($this->image, $cx + 1, $cy + 1, $diam, $diam, $start, $end, $this->col_black, IMG_ARC_PIE);
	    }
	    /* This is the slice of delicious pie */
	    imagefilledarc($this->image, $cx, $cy, $diam, $diam, $start, $end, $color_fill, IMG_ARC_PIE);
	    /* This is the black outline. */
	    imagefilledarc($this->image, $cx, $cy, $diam, $diam, $start, $end, $this->col_black,
			   (IMG_ARC_NOFILL | IMG_ARC_EDGED));
	} else {
	    $palette_index = imagecolorat($this->image, $slice_fill_x, $slice_fill_y);
	    imagearc( $this->image, $cx, $cy, $diam, $diam, $start, $end, $this->col_black);
	    if (360 != $arc_covers) {
		imageline($this->image, $cx, $cy, $arc_start_x, $arc_start_y, $this->col_black);
		imageline($this->image, $cx, $cy, $arc_end_x, $arc_end_y, $this->col_black);
	    }
	    if (360 == $arc_covers
		|| $palette_index == $this->col_mask) {
		imagefill($this->image, $slice_fill_x, $slice_fill_y, $color_fill);
	    }	    
	}
    } 

    private function text_arc($start, $end, $text, $call_out) {
	static $place_indexes = array(0 => 0, 1 => 0, 2 => 0, 3 => 0);
	static $side_counts = array(0 => 0, 1 => 0);
	$cx = $this->centerX;
	$cy = $this->centerY;
	$diam = $this->graph_size;
	$radius = $diam / 2;
	$arc_start_x = $this->polar_to_x($start, $radius);
	$arc_start_y = $this->polar_to_y($start, $radius);
	$arc_end_x = $this->polar_to_x($end, $radius);
	$arc_end_y = $this->polar_to_y($end, $radius);
	$slice_center_angle = (($start + $end + 720) / 2) % 360;
	$quadrant = floor(($slice_center_angle % 360) / 90);
	$side = (in_array($quadrant, array(0, 3)) ? 0 : 1);
	$top_bottom = floor($quadrant / 2);
	if (0 == $start && 360 == $end) {
	    $arc_covers = 360;
	} else {
	    $arc_covers = ($end - $start) % 360;
	}
	$slice_center_x = $this->polar_to_x($slice_center_angle, $radius / 2);
	$slice_center_y = $this->polar_to_y($slice_center_angle, $radius / 2);
	/* All calculations featuring $start and $end are done, so turn them
	 * into ints for the benefit of the gd functions */
	$start = round($start);
	$end = round($end);
	$limit = floor($this->graph_height / $this->row_height);
	if ($call_out) {
	    $place_index = $place_indexes[$quadrant]++;
	    if ($side_counts[$side]++ < $limit) {
		if (true || 1 == $top_bottom) {
		    $text_end_y = $place_index * $this->row_height;
		} else {
		    $text_end_y = $this->graph_height - ((1 + $place_index) * $this->row_height);
		}
		$box_y = $text_end_y + (0.5 * $this->row_height) - 2;
		if (1 == $side) {
		    $text_end_x = 0;
		    $box_x = $this->centerX - $radius - 4;
		} else {
		    $text_end_x = $this->centerX + $radius + 10;
		    $box_x = $text_end_x - 6;
		}
		imageline($this->image,
			  $slice_center_x,
			  $slice_center_y,
			  $box_x,
			  $box_y,
			  $this->col_black);
		$this->fill_box($box_x,
				$box_y,
				4,
				4,
				$this->col_black);
		imagestring($this->image,
			    2,
			    $text_end_x,
			    $text_end_y,
			    $text,
			    $this->col_black);
	    }
	} else {
	    if (10 < $arc_covers) {
		imagestring($this->image,
			    2,
			    $slice_center_x,
			    $slice_center_y,
			    $text,
			    $this->col_black);
	    }
	}
    }
    private function pie_text_flyout($slice_center_angle, $place_index, $text) {
	$diam = $this->graph_size;
	$radius = $diam / 2;
	$slice_center_x = $this->polar_to_x($slice_center_angle, $radius / 2);
	$slice_center_y = $this->polar_to_y($slice_center_angle, $radius / 2);
	if ($place_index > $this->row_height * $this->graph_size) {
	    $side = 1;
	} else {
	    $side = 0;
	}
	if (1 == $side) {
	    $text_end_x = 0;
	    $box_x = $this->centerX - $radius - 4;
	} else {
	    $text_end_x = $this->centerX + $radius + 10;
	    $box_x = $text_end_x - 6;
	}
	$text_end_y = ($place_index % floor($this->graph_height / $this->row_height)) * $this->row_height;
	$box_y = $text_end_y + (0.5 * $this->row_height) - 2;
	imageline($this->image,
		  $slice_center_x,
		  $slice_center_y,
		  $box_x,
		  $box_y,
		  $this->col_black);
	$this->fill_box($box_x,
			$box_y,
			4,
			4,
			$this->col_black);
	imagestring($this->image,
		    2,
		    $text_end_x,
		    $text_end_y,
		    $text,
		    $this->col_black);
    }
    function handleRequest($number_image) {	
	if (!$this->graphics_avail()) {
	    return;
	}
	$this->init_image($number_image);
	switch ($number_image) {
	case 1 : {
	    $cache =& $this->apc_lib->cache_opcode;
	    $cache_user =& $this->apc_lib->cache_user;
	
	    $total_memory = $this->apc_lib->sma_information['num_seg']
		    * $this->apc_lib->sma_information['seg_size'];
	    /*
	     * This block of code creates the pie chart.  It is a lot more complex than you
	     * would expect because we try to visualize any memory
	     * fragmentation as well.
	     */
	    $angle_from = 0;
	    $string_placement = array();
	    $this->draw_arc(0, 360, $this->col_mask);
	    $blocks = array();
	    $segment_offset = 0;
	    $segment_angle = 360 / $this->apc_lib->sma_information['num_seg'];
	    foreach ($this->apc_lib->sma_information['block_lists'] as $free) {
		$ptr = 0;
		foreach ($free as $block) {
		    if ($block['offset'] != $ptr) {
			$blocks[$ptr] = array($segment_offset + $segment_angle
					      * $ptr / $total_memory,
					      
					      $segment_offset + $segment_angle
					      * $block['offset'] / $total_memory,
					      
					      'used',
					      $block['offset'] - $ptr);
		    }
		    $blocks[$block['offset']] = array($segment_offset + $segment_angle
						      * $block['offset'] / $total_memory,
						      
						      $segment_offset + $segment_angle
						      * ($block['offset'] + $block['size']) / $total_memory,
						      
						      'free',
						      $block['size']);
		    $ptr = $block['offset'] + $block['size'];
		}
		if ($ptr < $this->apc_lib->sma_information['seg_size']) {
		    $blocks[$ptr] = array($segment_offset + $segment_angle * $ptr / $total_memory,
					  $segment_offset + $segment_angle,
					  'used',
					  $this->apc_lib->sma_information['seg_size'] - $ptr);
		}
		$segment_offset += $segment_angle;
	    }
	    ksort($blocks);
	    $block_count_limit = floor($this->graph_height / $this->row_height);
	    $blocks_by_size = array(0 => array(), 1 => array());
	    foreach ($blocks as $block) {
		$this->draw_arc($block[0],
				$block[1],
				('used' == $block[2] ? $this->col_red : $this->col_green));
	    }
	    foreach ($blocks as $block) {
		if (false) {
		    $slice_center_angle = ($block[0] + $block[1]) / 2;
		    $quadrant = floor(($slice_center_angle % 360) / 90);
		    $side = (in_array($quadrant, array(0, 3)) ? 0 : 1);
		    $top_bottom = floor($quadrant / 2);
		    if (!isset($blocks_by_size[$block[3]])) {
			$blocks_by_size[$side][$block[3]] = array();
		    }
		    $blocks_by_size[$side][$block[3]][] = $block;
		} else {
		    $block_count_limit = floor($this->graph_height / $this->row_height) * 2;
		    $blocks_by_size = array();
		    foreach ($blocks as $block) {
			if (!isset($blocks_by_size[$block[3]])) {
			    $blocks_by_size[$block[3]] = array();
			}
			$blocks_by_size[$block[3]][] = $block;
		    }
		}
	    }
	    if (false) {
		$place_index = 1;
		krsort($blocks_by_size[0]);
		krsort($blocks_by_size[1]);	    
		$blocks_to_add = array();
		foreach ($blocks_by_size as $block_sizes) {
		    $block_count = 1;
		    foreach ($block_sizes as $blocks) {
			foreach ($blocks as $block) {
			    $blocks_to_add[$block[0]] = $block;
			    if ($block_count++ > $block_count_limit) {
				break;
			    }
			}
		    }
		}
		ksort($blocks_to_add);
		foreach ($blocks_to_add as $block) {
			$this->text_arc($block[0],
					$block[1],
					$this->apc_views->bsize($block[3]),
					true);
		}
	    } else {
		ksort($blocks_by_size);
		$blocks_to_add = array();
		foreach ($blocks_by_size as $size => $some_blocks) {
		    foreach ($some_blocks as $block) {
			$blocks_to_add[$block[0]] = $block;
		    }
		}
		$angle_maps = array();
		$blocks_by_angle = array();
		foreach ($blocks_to_add as $block) {
		    $middle = (($block[0] + $block[1]) / 2);
		    $rotated = (($middle + 360) % 360) + 90;
		    $circle_percent = $rotated / 360;
		    $test_index = floor($circle_percent * $this->graph_size / $this->row_height);
		    $test_index = intval(floor($rotated * $this->graph_size / $this->row_height / 360));
		    //jesse($middle, $rotated, $test_index);
		    //jesse($middle, $test_index);
		    if (false) { 
		    if (270 == $middle) {
			/* Do something about this */
			$test_y = $this->graph_size - $this->row_height;
		    } elseif (90 == $middle) {
			$text_y = 0;
		    } else {
			$side = (((($middle + 90) % 360) > 180) ? 0 : 1);
			$top_bottom = ((($middle % 360) > 180) ? 0 : 1);
			
			$y_offset = round(tan(deg2rad($middle)) * $this->graph_size / 2);
			if (0 == $size) {
			    $test_y = $this->centerY + $y_offset;
			} else {
			    $test_y = $this->centerY - $y_offset;
			}
		    }
		    $test_index = floor($test_y / $this->row_height);
		    }
		    while (isset($blocks_by_angle[$test_index])) {
			$test_index++;
		    }
		    $block['middle'] = $middle;
		    $blocks_by_angle[$test_index] = $block;
		}
		//jesse($blocks_by_angle);
		foreach ($blocks_by_angle as $place_index => $block) {
		    $this->pie_text_flyout($block['middle'],
					   $place_index,
					   $this->apc_views->bsize($block[3])
					   );
		}
	    }
	    break;
	}
	case 2 : {
	    $cache =& $this->apc_lib->cache_opcode;
	    $cache_user =& $this->apc_lib->cache_user;
	
	    $total_requests = $cache['num_hits'] + $cache['num_misses'];
	    $hits = $cache['num_hits'];
	    
	    $this->fill_box(30,
			    $this->graph_size,
			    50,
			    -$hits * ($this->graph_size - 21) / $total_requests,
			    $this->col_green,
			    sprintf("%.1f%%", $cache['num_hits'] * 100 / $total_requests));
	    $this->fill_box(130,
			    $this->graph_size,
			    50,
			    -max(4, ($total_requests - $hits) * ($this->graph_size - 21) / $total_requests),
			    $this->col_red,
			    sprintf("%.1f%%", $cache['num_misses'] * 100 / $total_requests));
	    break;
	}
	case 3 : {
	    $cache =& $this->apc_lib->cache_opcode;
	    $cache_user =& $this->apc_lib->cache_user;
	

	    $num_seg = $this->apc_lib->sma_information['num_seg'];
	    $seg_size = $this->apc_lib->sma_information['seg_size'];
	    $total_mem = $num_seg * $seg_size;
	    $avail_mem = $this->apc_lib->sma_information['avail_mem'];
	    $left = $this->column_width;
	    $top = 1;
	    $width = 50;
	    $height = $this->graph_size - 5;
	    /* This block of code creates the bar chart.  It is a lot more
	     * complex than you would expect because we try to visualize any
	     * memory fragmentation as well.
	     */
	    $this->fill_box($left,
			    $top,
			    $width,
			    $height,
			    $this->col_red);
	    $ptr = 0;
	    $place_index = 0;
	    /* Items with lines should be drawn from the outside in so the
	     * lines don't cross previous entries.  This can be accomplished
	     * by just doing them in reverse
	     */
	    $queued_entries = array();
	    foreach ($this->apc_lib->sma_information['block_lists'] as $free_list) {
		foreach ($free_list as $block) {
		    if ($block['offset'] != $ptr) {
			$y = round($height * $ptr / $total_mem) + $top;
			$used_size = $block['offset'] - $ptr;
			$h = round($height * ($used_size - 1) / $total_mem);
			if ($place_index < $this->place_limit) {
			    $queued_entries[] = array($left,
						    $y,
						    $width,
						    $h,
						    $this->col_red,
						    $this->apc_views->bsize($used_size),
						    $place_index);
			}
			$place_index++;
		    }
		    $y = round($height * $block['offset'] / $total_mem) + $top;
		    $h = round($height * $block['size'] / $total_mem);
		    $text = $this->apc_views->bsize($block['size']);
		    if ($place_index < $this->place_limit) {
			$queued_entries[] = array($left, $y, $width, $h, $this->col_green, $text, $place_index);
		    } else {
			$this->fill_box($left, $y, $width, $h, $this->col_green);
		    }
		    $ptr = $block['offset'] + $block['size'];
		    $place_index++;
		}
	    }
	    if ($ptr < $avail_mem) {
		$y = round($height * $ptr / $total_mem) + $top;
		$used_size = $avail_mem - $ptr;
		$h = round($height * ($used_size - 1) / $total_mem);
		if ($place_index < $this->place_limit) {
		    $queued_entries[] = array($left,
					      $y,
					      $width,
					      $h,
					      $this->col_red,
					      $this->apc_views->bsize($used_size),
					      $place_index);
		}
		$place_index++;
	    }
	    foreach (array_reverse($queued_entries) as $e) {
		$this->fill_box($e[0], $e[1], $e[2], $e[3], $e[4], $e[5], $e[6]);
	    }	    
	    break;
	}
	case 4 : {
	    $cache =& $this->apc_lib->cache_opcode;
	    $cache_user =& $this->apc_lib->cache_user;
	

	    $s = $cache['num_hits'] + $cache['num_misses'];
	    $this->fill_box(30,
			    $this->graph_size,
			    50,
			    -$cache['num_hits'] * ($this->graph_size - 21) / $s,
			    $this->col_green,
			    sprintf("%.1f%%", $cache['num_hits'] * 100 / $s));
	    $this->fill_box(130,
			    $this->graph_size,
			    50,
			    -max(4, ($s - $a) * ($this->graph_size - 21) / $s),
			    $this->col_red,
			    sprintf("%.1f%%", $cache['num_misses'] * 100 / $s));
	    break;
	}
	}
	header("Content-type: image/png");
	imagepng($this->image);
	exit;
    }
}



class APC_VIEWS {
    private $apc_lib;
    private $apc_img;
    function __construct($apc_lib) {
	$this->apc_lib = $apc_lib;
    }
    function set_apc_img($apc_img) {
	$this->apc_img = $apc_img;
    }
    function get_refresh_link() {
	static $link;
	if (!isset($link)) {
	    $link = self::make_link($this->apc_lib->get_php_self(), $this->apc_lib->get_request_vars());
	}
	return $link;
    }
    function get_updated_link($new_args) {
	return self::make_link($this->get_refresh_link(), $new_args);
    }
    function get_new_link($new_args) {
	return self::make_link($this->apc_lib->get_php_self(), $new_args);
    }
    function make_link($url, $new_args) {
	$args = array();
	if (!$new_args) {
	    $new_args = array();
	}
	if (false !== strpos($url, '?')) {
	    $parts = explode('?', $url, 2);
	    $url = $parts[0];
	    $temp_args = explode('&', str_replace('&amp;', '&', $parts[1]));
	    foreach ($temp_args as $arg) {
		if (false !== strpos($arg, '=')) {
		    $parts = explode('=', $arg, 2);
		    $key = $parts[0];
		    $value = urldecode($parts[1]);
		} else {
		    $key = $arg;
		    $value = null;
		}
		$args[$key] = $value;
	    }
	}
	$processed_args = array();
	foreach (array_merge($args, $new_args) as $key => $value) {
	    if (is_null($value)) {
		$processed_args[] = $key;
	    } else {
		$processed_args[] = $key . '=' . urlencode($value);
	    }
	}
	if ($processed_args) {
	    return join('?', array($url, join('&amp;', $processed_args)));
	} else {
	    return $url;
	}
    }
    function get_rejected_login_message () {
	header("WWW-Authenticate: Basic realm=\"APC Login\"");
	header("HTTP/1.0 401 Unauthorized");
	ob_start();
	echo "\n";
	echo '<h1>Rejected!</h1>';
	echo '<p>Wrong Username or Password!</p><br/><br/>';
	echo '<p><a href="';
	echo $this->get_refresh_link();
	echo '">Continue...</a></p>';
	return ob_get_clean();
    }
    /*
     * TODO: Consider enhancing this with gettext functions.
     */
    public static function seconds_to_words($s) {
	$s = intval($s);
	if (!$s) {
	    return '0 seconds';
	}
	$intervals = array(array('second', 'seconds', 60),
			   array('minute', 'minutes', 60),
			   array('hour', 'hours', 24),
			   array('day', 'days', 7),
			   array('week', 'weeks', 52.177457),
			   array('year', 'years', 100),
			   array('century', 'centuries', 10),
			   /* You can't get here with integers in php. */
			   array('millenium', 'millenia', 100000)
			   );
	$words = array();
	foreach ($intervals as $interval) {
	    $amount = $s % $interval[2];
	    $s = floor($s / $interval[2]);
	    if (1 == $amount) {
		array_unshift($words, '1 ' . $interval[0]);
	    } elseif ($amount) {
		array_unshift($words, $amount . ' ' . $interval[1]);
	    }
	    if (!$s) {
		break;
	    }
	}
	$phrase = array(array_pop($words));
	if ($words) {
	    $phrase[] = join(', ', $words);
	}
	return join(' and ', array_reverse($phrase));
    }
    function duration($ts) {
	return self::seconds_to_words($this->apc_lib->get_time() - $ts);
    }

    public function ini_to_bsize($s) {
	$matches = array();
	if (preg_match('/^([0-9]+)([KMG]?)$/', $s, $matches)) {
	    $size = intval($matches[1]);
	    $unit = strtoupper(trim($matches[2]));
	    switch ($unit) {
	    case 'K':
		return $this->bsize($size * pow(2, 10));
	    case 'M':
		return $this->bsize($size * pow(2, 20));
	    case 'G':
		return $this->bsize($size * pow(2, 30));
	    default :
		return $this->bsize($size);
	    }
		
	} else {
	    return $s;
	}
    }
    /**
     * Pretty printer for byte values
     */
    public function bsize($s) {
	foreach (array('','K','M','G') as $i => $k) {
	    $s = round($s, 0);
	    if ($s == 1) {
		return $this->number_format($s, 0) . ' ' . $k . 'Byte';
	    } elseif ($s < 1024) {
		return $this->number_format($s, 0) . ' ' . $k . 'Bytes';
	    }
	    $s /= 1024;
	}
    }
    /**
     * At some point this should likely be localized.
     */
    public function number_format($n, $decimals = null) {
	if (is_null($decimals)) {
	    if (intval($n) == $n) {
		$decimals = 0;
	    } elseif (abs($n) < 1) {
		$decimals = 4;
	    } else {
		$decimals = 2;
	    }
	}
	return number_format($n, $decimals, '.', ',');
    }
    /**
     * Again with the localizing
     */
    public function date_format($timestamp) {
	return date($this->apc_lib->DATE_FORMAT, $timestamp);
    }
    /**
     * Ah ha!  Here there shall be no localization, since percents always work
     * the same.  I think.
     */
    public function percent_format($float, $decimals = null) {
	if (is_null($decimals)) {
	    $decimals = (0.01 > $float ? 4 : 2);
	}
	return $this->number_format($float * 100, $decimals) . '%';
    }
    /* sortable table header in "scripts for this host" view. */
    function get_sort_header($key, $name, $extra='') {
	ob_start();
	echo "\n";
	echo '<a class="sortable" href="';
	if ($this->apc_lib->get_sort() == $key) {
	    echo $this->get_updated_link(array($this->apc_lib->get_sort_context() => $key,
					       'VIEW' => $this->apc_lib->get_view(),
					       'SORT2' => ('A' == $this->apc_lib->get_sort_direction()
							   ? 'D' : 'A')));
	} else {
	    echo $this->get_updated_link(array($this->apc_lib->get_sort_context() => $key,
					       'VIEW' => $this->apc_lib->get_view()));
	}
	echo $extra;
	echo '"';
	echo '>';
	echo $name;
	echo '</a>';
	echo "\n";
	return ob_get_clean();
    }

    /* Create menu entry */
    function get_menu_entry($ob, $title) {
	ob_start();
	echo "\n";
	echo '<li>';
	echo '<a href="';
	echo $this->get_new_link(array('VIEW' => $ob));
	echo '"';
	if ($this->apc_lib->get_view() == $ob) {
	    echo ' class="child_active"';
	}
	echo '>';
	echo $title;
	echo '</a>';
	echo '</li>';
	echo "\n";
	return ob_get_clean();
    }

    function get_login_link() {
	if (!$this->apc_lib->use_internal_authentication()) {
	    return '';
	}
	ob_start();
	echo "\n";
	if ($this->apc_lib->is_authenticated()) {
	    echo  $this->apc_lib->get_user_name() . ' logged in.';
	} else{
	    echo '<a href="';
	    echo $this->get_updated_link(array('LO' => 1));
	    echo '">Login</a>';
	}
	echo "\n";
	return ob_get_clean();
    }

    function get_select($name, $label) {
	if (!empty(apc_lib::$allowed_values[$name])) {
	    $allowed_values = apc_lib::$allowed_values[$name];
	} else {
	    $allowed_values = array();
	}
	$selected = $this->apc_lib->get_selected($name);
	if (is_null($selected)) {
	    foreach ($allowed_values as $value) {
		$selected = $value;
		break;
	    }
	}
	
	ob_start();
	echo "\n";
	echo '<label for="' . $name . '">' . $label . '</label>';
	echo ' ';
	echo '<select name="' . $name . '" id="' . $name . '" onchange="form.submit();">';
	foreach ($allowed_values as $value => $value_label) {
	    echo '<option value="' . $value . '"';
	    if ($value == $selected) {
		echo ' selected="selected"';
	    }
	    echo '>';
	    echo $value_label;
	    echo '</option>';
	}
	echo '</select>';
	echo "\n";
	return ob_get_clean();
    }
    function get_table($pairs, $title = '') {
	/* Yes, we take apart the data and jam it back together.  Sue me. */
	return self::get_data_table(array('title' => $title,
					  'field_count' => 2,
					  'direction' => 'side_by_side',
					  'table_class' => 'pairs',
					  'headings' => array_keys($pairs)),
				    array_values($pairs));
    }
    function get_data_table($metadata, $data) {
	ob_start();
	if (isset($metadata['title'])) {
	    $title = $metadata['title'];
	} else {
	    $title = '';
	}
	if (isset($metadata['field_count'])) {
	    $field_count = intval($metadata['field_count']);
	}
	if (1 > $field_count) {
	    foreach ($data as $datum) {
		$field_count = count($datum);
	    }
	}
	$tableclass = 'data_table';
	if (!empty($metadata['table_class'])) {
	    $tableclass .= ' ' . $metadata['table_class'];
	}
	if (!empty($metadata['direction'])
	    && in_array($metadata['direction'], array('side_by_side',
						      'up_and_down'))) {
	    $direction = $metadata['direction'];
	} else {
	    $direction = 'up_and_down';
	}
	if (!empty($metadata['headings'])
	    && is_array($metadata['headings'])) {
	    $headings = $metadata['headings'];
	} else {
	    $headings = array();
	}
	$column_types = array_fill(0, $field_count, 'string');
	if (!empty($metadata['types'])) {
	    foreach ($metadata['types'] as $index => $type) {
		$column_types[$index] = $type;
	    }
	}
	$attributes_raw = array_fill(0, $field_count, array());
	if (!empty($metadata['attributes'])) {
	    foreach ($metadata['attributes'] as $index => $value) {
		$attributes_raw = $value;
	    }
	}
	foreach ($attributes_raw as $index => $values) {
	    if (!isset($values['class'])) {
		$attributes_raw[$index]['class'] = '';
	    }
	    $attributes_raw[$index]['class'] = $column_types[$index] . ' ' . $attributes_raw[$index]['class'];
	}
	$attributes = array_fill(0, $field_count, '');
	foreach ($attributes_raw as $index => $values) {
	    foreach ($values as $key => $value) {
		$attributes[$index] .= ' ' . $key . '="' . $value . '"';
	    }
	}
	echo "\n";
	$j = 0;
	echo '<table class="' . $tableclass  . '">';
	if ($title) {
	    echo "<caption>$title</caption>";
	}
	if ('side_by_side' == $direction) {
	    if (!empty($headings)
		&& count($headings) == count($data)) {
		$new_data = array_combine($headings, $data);
		if ($new_data) {
		    $data = $new_data;
		}
	    }
	    echo '<tbody>';
	    foreach ($data as $k => $v) {
		echo "\n";
		echo '<tr class="tr-' . $j++ . '">';
		echo '<th>';
		echo $k;
		echo '</th>';
		echo '<td>';
		if (is_array($v)) {
		    foreach ($v as $w) {
			echo $w;
			break;
		    }
		} else {
		    echo $v;
		}
		echo '</td>';
		echo '</tr>';
		$j %= 2;
	    }
	    echo '</tbody>';
	} else {
	    echo '<thead>';
	    echo '<tr class="tr-' . $j++ . '">';
	    foreach ($headings as $heading) {
		echo '<th>';
		echo $heading;
		echo '</th>';
	    }
	    echo '</tr>';
	    echo '</thead>';
	    echo '<tbody>';
	    $j %= 2;
	    foreach ($data as $datum) {
		echo '<tr class="tr-' . $j++ . '">';
		$i = 0;
		foreach ($datum as $value) {
		    if (isset($attributes[$i])) {
			echo '<td ' . $attributes[$i] . '>';
		    } else {
			echo '<td>';
		    }
		    if ('date' == $column_types[$i] && is_numeric($value)
			) {
			echo $this->date_format($value);
		    } else {
			echo $value;
		    }
		    echo '</td>';
		    $i++;
		}
		echo '</tr>';
		$j %= 2;
	    }
	    echo '</tbody>';
	}
	echo '</table>';
	echo "\n";
	return ob_get_clean();
    }
    function handleRequest($view) {
	ob_start();
	$scope_list = apc_lib::$allowed_values['SCOPE'];
	$C_D_list = apc_lib::$allowed_values['C_D'];
	$cache =& $this->apc_lib->cache_opcode;
	$cache_user =& $this->apc_lib->cache_user;
	
	echo "\n";
	
	if ($this->apc_lib->is_valid) {
	    switch ($view) {
	    case apc_lib::$VIEW_HOST_STATS : {
		$mem_size = $this->apc_lib->sma_information['num_seg'] * $this->apc_lib->sma_information['seg_size'];
		$mem_avail= $this->apc_lib->sma_information['avail_mem'];
		$mem_used = $mem_size - $mem_avail;
	    
		$seg_size = $this->apc_lib->sma_information['seg_size'];
		$uptime = $this->apc_lib->get_time() - $cache['start_time'];
		/* I think that we can assume that 0 uptime and 1 second of uptime
		 * are functionally equivalent.
		 */
		if (!$uptime) {
		    $uptime = 1;
		}
		$req_rate = ($cache['num_hits'] + $cache['num_misses']) / $uptime;
		$hit_rate = $cache['num_hits'] / $uptime;
		$miss_rate = $cache['num_misses'] / $uptime;
		$insert_rate = $cache['num_inserts'] / $uptime;
	    
		$req_rate_user = ($cache_user['num_hits'] + $cache_user['num_misses']) / $uptime;
		$hit_rate_user = $cache_user['num_hits'] / $uptime;
		$miss_rate_user = $cache_user['num_misses'] / $uptime;
		$insert_rate_user = $cache_user['num_inserts'] / $uptime;
	    
		$cache_total = $cache['num_hits'] + $cache['num_misses'];
		$cache_user_total = $cache_user['num_hits'] + $cache_user['num_misses'];
		$apcversion = phpversion('apc');
		$phpversion = phpversion();
		$number_files = $cache['num_entries']; 
		$size_files = $cache['mem_size'];
		$number_vars = $cache_user['num_entries'];
		$size_vars = $cache_user['mem_size'];


		/* Here we set up the arrays that will be converted into nice
		 * charts. */

		$general = array('APC Version' => $apcversion,
				 'PHP Version' => $phpversion);
	    
		if (!empty($_SERVER['SERVER_NAME'])) {
		    $general['APC Host'] = $_SERVER['SERVER_NAME'] . $this->apc_lib->get_host();
		}
		if (!empty($_SERVER['SERVER_SOFTWARE'])) {
		    $general['Server Software'] = $_SERVER['SERVER_SOFTWARE'];
		}
	    
		$general['Shared Memory'] =
			$this->number_format($this->apc_lib->sma_information['num_seg'])
			. (1 == $this->apc_lib->sma_information['num_seg'] ? ' Segment' : ' Segments')
			. ' with '
			. $this->bsize($seg_size)
			. '<br/>'
			. '('
			. $cache['memory_type']
			. ' memory, '
			. $cache['locking_type']
			. 'locking)';
		if (1 < $this->apc_lib->get_ini('apc.shm_segments')
		    && 'mmap' == $cache['memory_type']) {
		    $general['Shared Segments Note']
			    = 'Note: the apc.shm_segments setting is ignored in MMAP mode.';
		}
		$general['Start Time'] = $this->date_format($cache['start_time']);
		$general['Uptime'] = $this->duration($cache['start_time']);
		$general['File Upload Support'] = ($cache['file_upload_progress'] ? 'Yes' : 'No');
	    

	    
		$file_cache = array();
		$file_cache['Cached Files'] = $this->number_format($number_files) . ' (' . $this->bsize($size_files) . ')';
		$file_cache['File Count Hint'] = $this->number_format($this->apc_lib->get_ini('apc.num_files_hint'));
		if ($this->apc_lib->get_ini('apc.num_files_hint')) {
		    $file_cache['File Count Hint'] .= ' ('
			    . $this->percent_format($number_files / $this->apc_lib->get_ini('apc.num_files_hint'))
			    . ')';
		}
		$file_cache['Max File Size'] = $this->ini_to_bsize($this->apc_lib->get_ini('apc.max_file_size'));
		$file_cache['Requests'] = $this->number_format($cache_total)
			. ' (' . $this->number_format($req_rate) . ' requests/second)';

		$file_cache['Hits'] = $this->number_format($cache['num_hits'])
			. ' (' . $this->number_format($hit_rate) . ' hits/second)';

		$file_cache['Misses'] = $this->number_format($cache['num_misses']);

		if ($cache_total) {
		    $file_cache['Misses'] .= ' (' . $this->percent_format($cache['num_misses'] / $cache_total) . ')';
		}
		$file_cache['Misses'] .= ' (' . $this->number_format($miss_rate) . ' misses/second)';

		$file_cache['Inserts'] = $this->number_format($cache['num_inserts'])
			. ' ('. $this->number_format($insert_rate) . ' inserts/second)';
	    
		if (isset($cache['removes'])) {
		    $file_cache['Removed'] = $this->number_format($cache['removes']);
		    if (isset($cache['frees']) && $cache['removes'] != $cache['frees']) {
			$file_cache['Removed'] .= ' (!= ' . $this->number_format($cache['frees']) . ' freed)';
		    }
		    $parts = array();
		    if (isset($cache['num_expires'])) {
			$parts[] = $this->number_format($cache['num_expires']) . ' expirations';
		    }
		    if (isset($cache['num_replacements'])) {
			$parts[] = $this->number_format($cache['num_replacements']) . ' replacements';
		    }
		    if ($parts) {
			$file_cache['Removed'] .= ' (' . join(', ', $parts) . ')';
		    }
		}
		$file_cache['Cache full count'] = $this->number_format($cache['expunges']);
		$file_cache['File Time To Live'] = $this->number_format($this->apc_lib->get_ini('apc.ttl'))
			. ' seconds';
		if (60 < $this->apc_lib->get_ini('apc.ttl')) {
		    $file_cache['File Time To Live']
			    .= ' (' . $this->seconds_to_words($this->apc_lib->get_ini('apc.ttl')) . ')';
		}


	    
		$user_cache = array();
		$user_cache['Cached Variables'] = $this->number_format($number_vars)
			. ' (' . $this->bsize($size_vars) . ')';
		$user_cache['Cached Variables Count Hint']
			= $this->number_format($this->apc_lib->get_ini('apc.user_entries_hint'));
		if ($this->apc_lib->get_ini('apc.user_entries_hint')) {
		    $user_cache['Cached Variables Count Hint']
			    .= ' (' . $this->percent_format($number_vars / $this->apc_lib->get_ini('apc.user_entries_hint')) . ')';
		}
	    
		$user_cache['Requests'] = $this->number_format($cache_user_total)
			. ' (' . $this->number_format($req_rate_user) . ' requests/second)';

		$user_cache['Hits'] = $this->number_format($cache_user['num_hits']);
		if ($cache_user_total) {
		    $user_cache['Hits']
			    .= ' (' . $this->percent_format($cache_user['num_hits'] / $cache_user_total) . ')';
		}
		$user_cache['Hits']
			.= ' (' . $this->number_format($hit_rate_user) . ' hits/second' . ')';

		$user_cache['Misses'] = $this->number_format($cache_user['num_misses']);
		if ($cache_user_total) {
		    $user_cache['Misses']
			    .= ' (' . $this->percent_format($cache_user['num_misses'] / $cache_user_total) . ')';
		}
		$user_cache['Misses'] .= ' (' . $this->number_format($miss_rate_user) . ' misses/second)';
		$user_cache['Inserts'] = $this->number_format($cache_user['num_inserts'])
			. ' (' . $this->number_format($insert_rate_user) . ' inserts/second)';
	    
		if (isset($cache_user['removes'])) {
		    $user_cache['Removed'] = $this->number_format($cache_user['removes']);
		    if (isset($cache_user['frees']) && $cache_user['removes'] != $cache_user['frees']) {
			$user_cache['Removed'] .= ' (' . $this->number_format($cache_user['frees']) . ' freed)';
		    }
		
		    $parts = array();
		    if (isset($cache_user['num_expires'])) {
			$parts[] = $this->number_format($cache_user['num_expires']) . ' expirations';
		    }
		    if (isset($cache_user['num_replacements'])) {
			$parts[] = $this->number_format($cache_user['num_replacements']) . ' replacements';
		    }
		    if ($parts) {
			$user_cache['Removed'] .= ' (' . join(', ', $parts) . ')';
		    }
		}

		$user_cache['Cache full count'] = $this->number_format($cache_user['expunges']);
	    
		$user_cache['User Variable Time To Live']
			= $this->number_format($this->apc_lib->get_ini('apc.user_ttl')) . ' seconds';
		if (60 < $this->apc_lib->get_ini('apc.user_ttl')) {
		    $user_cache['User Variable Time To Live']
			    .= ' (' . $this->seconds_to_words($this->apc_lib->get_ini('apc.user_ttl')) . ')';
		}
    

	    
		$memory = array();
		if (false && $this->apc_img->graphics_avail()) {
		    ob_start();
		    if ($this->apc_lib->sma_information['num_seg'] > 1
			|| ($this->apc_lib->sma_information['num_seg'] == 1
			    && (count($this->apc_lib->sma_information['block_lists'][0]) > 1))) {
			echo '<p>(multiple slices indicate fragments)</p>';
		    }
		
		    echo '<img alt="" ';
		    echo ' width="' . ($this->apc_img->get_graph_width()) . '"';
		    echo ' height="' . ($this->apc_img->get_graph_height()) . '"';
		    echo ' src="'
			    . $this->get_new_link(array('IMG' => 1, 'time' => $this->apc_lib->get_time()))
			    . '" />';
		    $memory['Memory Usage'] = ob_get_clean();
		}
		$memory['Simple Chart'] = '';
		$width_multiplier = 150;
		$percent_free = round($mem_avail / $mem_size * $width_multiplier);
		$percent_used = round($mem_used / $mem_size * $width_multiplier);
		if ($percent_free) {
		    $memory['Simple Chart']
			    .= '<div class="green box" style="width: ' . $percent_free . 'px;"></div>';
		}
		if ($percent_used) {
		    $memory['Simple Chart']
			    .= '<div class="red box" style="width: ' . $percent_used . 'px;"></div>';
		}
		$memory['Free Memory'] = '<div class="green square box">&nbsp;</div>'
			. $this->bsize($mem_avail)
			. ' (' . $this->percent_format($mem_avail/$mem_size) . ')';
		$memory['Used Memory'] = '<div class="red square box">&nbsp;</div>'
			. $this->bsize($mem_used)
			. ' (' . $this->percent_format($mem_used/$mem_size) . ')';

		$cache_hits_misses = array();
		if ($cache_total) {
		    /* These percentages are only for displaying the bars */
		    $percent_hits = round($cache['num_hits'] / $cache_total * $width_multiplier);
		    $percent_misses = round($cache['num_misses'] / $cache_total * $width_multiplier);
		    $cache_hits_misses['Chart'] = '';
		    if ($percent_hits) {
			$cache_hits_misses['Chart']
				.= '<div class="green box" style="width: ' . $percent_hits . 'px;"></div>';
		    }
		    if ($percent_misses) {
			$cache_hits_misses['Chart']
				.= '<div class="red box" style="width: ' . $percent_misses . 'px;"></div>';
		    }
		}
		$cache_hits_misses['Hits'] = '<div class="green square box">&nbsp;</div>'
			. $this->number_format($cache['num_hits']);
		if ($cache_total) {
		    $cache_hits_misses['Hits'] .= ' (' . $this->percent_format($cache['num_hits'] / $cache_total) . ')';
		}
		$cache_hits_misses['Misses'] = '<div class="red square box">&nbsp;</div>'
			. $this->number_format($cache['num_misses']);
		if ($cache_total) {
		    $cache_hits_misses['Misses']
			    .= ' (' . $this->percent_format($cache['num_misses'] / $cache_total) . ')';
		}
	    
		$cache_user_hits_misses = array();
		if ($cache_user_total) {
		    /* These percentages are only for displaying the bars */
		    $percent_hits = round($cache_user['num_hits'] / $cache_user_total * $width_multiplier);
		    $percent_misses = round($cache_user['num_misses'] / $cache_user_total * $width_multiplier);
		    $cache_user_hits_misses['Chart'] = '';
		    if ($percent_hits) {
			$cache_user_hits_misses['Chart']
				.= '<div class="green box" style="width: '
				. $percent_hits . 'px;"></div>';
		    }
		    if ($percent_misses) {
			$cache_user_hits_misses['Chart']
				.= '<div class="red box" style="width: '
				. $percent_misses . 'px;"></div>';
		    }
		}
		$cache_user_hits_misses['Hits'] = '<div class="green square box">&nbsp;</div>'
			. $this->number_format($cache_user['num_hits']);
		if ($cache_user_total) {
		    $cache_user_hits_misses['Hits']
			    .= ' (' . $this->percent_format($cache_user['num_hits'] / $cache_user_total) . ')';
		}
		$cache_user_hits_misses['Misses'] = '<div class="red square box">&nbsp;</div>'
			. $this->number_format($cache_user['num_misses']);
		if ($cache_user_total) {
		    $cache_user_hits_misses['Misses']
			    .= ' (' . $this->percent_format($cache_user['num_misses'] / $cache_user_total) . ')';
		}
	    
		$total_size = 0;
		$max_size = 0;
		$block_count = 0;
		$free_block_count = 0;
		$fragsize = 0;
		$freetotal = 0;
		$smallest_size = $this->apc_lib->sma_information['seg_size'];
		$largest_size = 0;
		$sizes = array();
		$should_be_merged = array();
		$last_offset = 0;
		foreach ($this->apc_lib->sma_information['block_lists'] as $segment) {
		    $free_block_count += count($segment);
		    $ptr = 0;
		    foreach ($segment as $block) {
			$block_size = intval($block['size']);
			$offset = intval($block['offset']);
			if ($last_offset && ($last_offset == $offset)) {
			    $should_be_merged[] = $block;
			}
			if (!isset($sizes[$block_size])) {
			    $sizes[$block_size] = 0;
			}
			$sizes[$block_size]++;
			$total_size += $block_size;
			if ($block_size > $max_size) {
			    $max_size = $block_size;
			}
			if ($block_size < $smallest_size) {
			    $smallest_size = $block_size;
			}
			if ($block_size > $largest_size) {
			    $largest_size = $block_size;
			}
			$block_count++;
			$ptr = $offset + $block_size;
			$freetotal += $block_size;
		    }
		}
		/* Ignore the largest block in fragmentation calculations. */
		$fragsize = $freetotal - $largest_size;

	    
		$fragmentation = array();
		if ($this->apc_img->graphics_avail()) {
		    ob_start();
		    echo '<img alt="" ';
		    echo ' width="' . ($this->apc_img->get_graph_width()) . '"';
		    echo ' height="' . ($this->apc_img->get_graph_height()) . '"';
		    echo ' src="'
			    . $this->get_new_link(array('IMG' => 3, 'time' => $this->apc_lib->get_time()))
			    . '" />';
		    $fragmentation['Detailed Graph'] = ob_get_clean();
		}

		$fragmentation['Free Blocks'] = $free_block_count;

	    
		if (1 < $free_block_count) {
		    ob_start();
		    if ($free_block_count > 1) {
			echo $this->percent_format($fragsize / $freetotal);
			echo ' (';
			echo $this->bsize($fragsize);
			echo ' / ';
			echo $this->bsize($freetotal);
			echo ')';
		    } else {
			echo $this->percent_format(0);
		    }
		    $fragmentation['Fragmentation By Size'] = ob_get_clean();

		    ob_start();
		    $total_entries = $cache['num_entries'] + $cache_user['num_entries'];
		    echo $this->percent_format($free_block_count / $total_entries);
		    echo ' (';
		    echo $this->number_format($free_block_count);
		    echo ' free : ';
		    echo $this->number_format($total_entries);
		    echo ' used)';
		    $fragmentation['Fragmentation By Count'] = ob_get_clean();
		} else {
		    $fragmentation['Fragmentation By Size'] = 'Not fragmented';
		    $fragmentation['Fragmentation By Count'] = 'Not fragmented';
		}

	    
	    
		if (isset($this->apc_lib->sma_information['adist'])) {
		    foreach ($this->apc_lib->sma_information['adist'] as $i => $v) {
			$cur = pow(2, $i);
			$nxt = pow(2, $i + 1) - 1;
			if ($i == 0) {
			    $range = "1";
			} else {
			    $range = "$cur - $nxt";
			}
			$fragmentation[$range] = $v;
		    }
		}

		$sizes_by_count = $sizes;
		ksort($sizes);
		asort($sizes_by_count);
		if (1 < $block_count) {
		    $average_size = round(($total_size - $max_size) / ($block_count - 1));
		} else {
		    $average_size = $total_size;
		}
		$min_size_count = min($sizes);
		$max_size_count = max($sizes);
		$min_size = min(array_keys($sizes));
		$max_size = max(array_keys($sizes));
		$label_width = ceil(log($max_size, 10) * 0.75) ;
		$range = $max_size_count - $min_size_count;
		$scale = 150 / $max_size_count;


		/* This is set to true if any of the free block sizes appear more
		 * than once.
		 */
		$show_histograms = false;
		ob_start();
		echo '<table class="html_graph">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>Size</th>';
		echo '<th>Count</th>';
		echo '<th>Graph</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
		foreach ($sizes as $size => $count) {
		    if (1 < $count) {
			echo '<tr>';
			echo '<td class="numeric">';
			echo $size;
			echo '</td>';
			echo '<td class="numeric">';
			echo $count;
			echo '</td>';
			echo '<td>';
			echo '<div class="red box" style="width: ';
			echo max(2, $count * $scale);
			echo 'px; overflow: hidden;">';
			echo '</div>';
			echo '</td>';
			echo '</tr>';
			$show_histograms = true;
		    }
		}
		echo '</tbody>';
		echo '</table>';
		$histogram1 = ob_get_clean();
	    
		ob_start();
		echo '<table class="html_graph">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>Size</th>';
		echo '<th>Count</th>';
		echo '<th>Graph</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
		foreach ($sizes_by_count as $size => $count) {
		    if (1 < $count) {
			echo '<tr>';
			echo '<td class="numeric">';
			echo $size;
			echo '</td>';
			echo '<td class="numeric">';
			echo $count;
			echo '</td>';
			echo '<td>';
			echo '<div class="red box" style="width: ';
			echo max(10, $count * $scale);
			echo 'px; overflow: hidden;">';
			echo '&nbsp;';
			echo '</div>';
			echo '</td>';
			echo '</tr>';
			$show_histograms = true;
		    }
		}
		echo '</tbody>';
		echo '</table>';
		$histogram2 = ob_get_clean();
	    
		$block_stats = array('Total Size' => $this->bsize($total_size),
				     'Average Free Block Size (excluding largest)' => $this->bsize($average_size),
				     'Smallest Free Block Size' => $this->bsize($smallest_size),
				     'Largest Free Block Size' => $this->bsize($max_size),
				     'Block Size Histogram By Size (minus single entries)'
				     => ($show_histograms ? $histogram1 : '(Nothing to show)'),
				     'Block Size Histogram By Count (minus single entries)'
				     => ($show_histograms ? $histogram2 : '(Nothing to show)'),
				     'Should Be Merged'
				     => ($should_be_merged
					 ? var_export($should_be_merged, true) : '(Nothing to show)')
				     );
	    

		echo '<div class="leftColumn">';
		echo '<div class="info">' . self::get_table($general, 'General Cache Information') . '</div>';
		echo '<div class="info">' . self::get_table($file_cache, 'File Cache Information') . '</div>';
		echo '<div class="info">' . self::get_table($user_cache, 'User Cache Information') . '</div>';
		echo '<div class="info">' . self::get_table($this->apc_lib->apc_all, 'Runtime Settings') . '</div>';
		echo '</div>';
	    

		echo '<div class="rightColumn">';
		echo '<div class="info">' . self::get_table($memory, 'Memory Status') . '</div>';
	    
		echo '<div class="info">' . self::get_table($cache_hits_misses,
							    'File Cache Hits and Misses') . '</div>';
	    
		echo '<div class="info">';
		echo self::get_table($cache_user_hits_misses, 'Variable Cache Hits and Misses');
		echo '</div>';
		echo '<div class="info">';
		echo self::get_table($fragmentation, 'Detailed Memory Usage and Fragmentation');
		echo '</div>';
		echo '<div class="info">';
		echo self::get_table($block_stats, 'Block Allocation Stats');
		echo '</div>';
		echo '</div>';
		break;
	    }

	    case apc_lib::$VIEW_USER_CACHE :
	    case apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES :
	    case apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES_DIR : {
		if ($view == apc_lib::$VIEW_USER_CACHE) {
		    $use_sorts = 'USER_SORT';
		    $fieldname='info';
		    $fieldkey='info';
		    $use_list = $scope_list;
		    $meta_data = array('field_count' => 8,
				       'headings' => array($this->get_sort_header('S', 'User Entry Label'),
							   $this->get_sort_header('H', 'Hits'),
							   $this->get_sort_header('Z', 'Size'),
							   $this->get_sort_header('A', 'Last accessed'),
							   $this->get_sort_header('M', 'Last modified'),
							   $this->get_sort_header('T', 'Timeout'),
							   $this->get_sort_header('C', 'Created at'),
							   $this->get_sort_header('D', 'Deleted at')),
				       'types' => array('string',
							'integer',
							'integer',
							'date',
							'date',
							'date',
							'date',
							'string'));
		
		    $sort_keys = array('A' => 'access_time',
				       'H' => 'num_hits',
				       'Z' => 'mem_size',
				       'M' => 'mtime',
				       'C' => 'creation_time',
				       'T' => 'ttl',
				       'D' => 'deletion_time',
				       'S' => '');
		}
		if ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES) {
		    $use_sorts = 'FILE_SORT';
		    $fieldname='filename';
		    $use_list = $C_D_list;
		    if ($this->apc_lib->get_ini('apc.stat')) {
			$fieldkey = 'inode';
		    } else {
			$fieldkey = 'filename';
		    }
		    $meta_data = array('field_count' => 8,
				       'headings' => array($this->get_sort_header('S', 'Script Filename'),
							   $this->get_sort_header('H', 'Hits'),
							   $this->get_sort_header('Z', 'Size'),
							   $this->get_sort_header('A', 'Last accessed'),
							   $this->get_sort_header('M', 'Last modified'),
							   $this->get_sort_header('C', 'Created at'),
							   $this->get_sort_header('D', 'Deleted at')),
				       'types' => array('string',
							'integer',
							'integer',
							'date',
							'date',
							'date',
							'string'));
		    $sort_keys = array('A' => 'access_time',
				       'H' => 'num_hits',
				       'Z' => 'mem_size',
				       'M' => 'mtime',
				       'C' => 'creation_time',
				       'T' => 'ttl',
				       'D' => 'deletion_time',
				       'S' => '');
		}
		if ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES_DIR) {
		    if (!$this->apc_lib->is_authenticated()) {
			break;
		    }
		    $use_sorts = 'DIR_SORT';
		    $fieldname='filename';
		    $use_list = $C_D_list;
		    if ($this->apc_lib->get_ini('apc.stat')) {
			$fieldkey = 'inode';
		    } else {
			$fieldkey = 'filename';
		    }
		    $meta_data = array('field_count' => 6,
				       'headings' => array($this->get_sort_header('S', 'Directory Name'),
							   $this->get_sort_header('T', 'Number of Files'),
							   $this->get_sort_header('H', 'Hits'),
							   $this->get_sort_header('Z', 'Size'),
							   $this->get_sort_header('C', 'Avg. Hits'),
							   $this->get_sort_header('A', 'Avg. Size')));
		    $sort_keys = array('A' => 5, 'T' => 1, 'H' => 2, 'Z' => 3, 'C' => 4, 'S' => 0);
		}
	    
	    
		if ($this->apc_lib->get_request('SH')) {
		    $data = array();
		    $data2 = array();
		    $meta_data = array('field_count' => 2,
				       'headings' => array('Attribute',
							   'Value'));
		    $meta_data2 = array('field_count' => 1,
					'headings' => array('Stored Value'));
		    echo '<div class="info">';
		    $m = 0;
		    foreach ($use_list as $j => $list) {
			if (isset($cache[$list])) {
			    foreach ($cache[$list] as $i => $entry) {
				if (md5($entry[$fieldkey]) != $this->apc_lib->get_request('SH')) {
				    continue;
				}
				foreach ($entry as $k => $value) {
				    if (!$this->apc_lib->is_authenticated()) {
					/* Hide all path entries if not logged in. */
					$value = preg_replace('/^.*(\\/|\\\\)/','<i>&lt;hidden&gt;</i>/',$value);
				    }		    
				    if ($k == "num_hits") {
					$value = sprintf("%s (%.2f%%)", $value, $value * 100 / $cache['num_hits']);
				    }
				    if ($k == 'deletion_time') {
					if (!$entry['deletion_time']) {
					    $value = "None";
					}
				    }
				    $data[] = array(ucwords(preg_replace('/_/', ' ', $k)),
						    ((preg_match("/time/",$k) && $value != 'None')
						     ? $this->date_format($value) : $value));
				}
				if ($view == apc_lib::$VIEW_USER_CACHE) {
				    $data2[] = '<pre>' . htmlspecialchars(var_export(apc_fetch($entry[$fieldkey]),true)) . '</pre>';
				}
			    }
			}
		    }
		    if ($data) {
			echo $this->get_data_table($meta_data, $data);
		    }
		    if ($data2) {
			echo $this->get_data_table($meta_data2, $data2);
		    }
		
		    echo '</div>';
		    break;
		}
   
		if ($this->apc_lib->get_request('SEARCH')) {
		    /* Don't use preg_quote because we want the user to be able to
		     * specify a regular expression subpattern.
		     */
		    $search = '/' . str_replace('/', '\\/', $this->apc_lib->get_request('SEARCH')) . '/i';
		    if (preg_match($search, 'test') === false) {
			echo '<div class="error">Error: enter a valid regular expression as a search query.</div>';
			break;
		    }
		}


	    
		$data = array();
		$list = array();
		/* Builds list with alpha numeric sortable keys. */
		if ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES) {
		    $cache_reporting =& $this->apc_lib->cache_opcode;
		} elseif ($view == apc_lib::$VIEW_USER_CACHE) {
		    $cache_reporting =& $this->apc_lib->cache_user;
		}
		if ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES
		    || $view == apc_lib::$VIEW_USER_CACHE) {
		    if (isset($C_D_list[$this->apc_lib->get_scope()])
			&& isset($cache_reporting[$C_D_list[$this->apc_lib->get_scope()]])) {
			$sort_key = $sort_keys[$this->apc_lib->get_sort()];
			foreach ($cache_reporting[$C_D_list[$this->apc_lib->get_scope()]] as $i => $entry) {
			    if ('T' == $sort_key && isset($entry['ttl'])) {
				$k = sprintf('%015d-', $entry['ttl']) . $entry[$fieldname];
			    } elseif ('T' == $sort_key && !isset($entry['ttl'])) {
				$k = sprintf('%015d-', 0) . $i . $entry[$fieldname];
			    } elseif ($sort_key) {
				$k = sprintf('%015d-', $entry[$sort_key] . '-' . $i);
			    } else {
				$k = $i;
			    }
			    if (!$this->apc_lib->is_authenticated()) {
				$entry  = preg_replace('/^.*(\\/|\\\\)/', '<i>hidden</i>/', $entry);
			    }
			    if ($entry['deletion_time']) {
				$delete = $this->date_format($entry['deletion_time']);
			    } elseif ($this->apc_lib->is_authenticated()
				      && $this->apc_lib->get_view() == apc_lib::$VIEW_USER_CACHE) {
				$delete = ' [<a href="' . $this->get_updated_link(array('DU' => urlencode($entry[$fieldkey]))) . '">Delete&#160;Now</a>]';
			    } else {
				$delete = 'N/A';
			    }
			    if ($view == apc_lib::$VIEW_USER_CACHE) {
				$datum = array('<a href="' . $this->get_updated_link(array('SH' => md5($entry[$fieldkey]))) . '">'
					       . $entry[$fieldname]
					       . '</a>',
					       $entry['num_hits'],
					       $entry['mem_size'],
					       $entry['access_time'],
					       $entry['mtime'],
					       $entry['creation_time'],
					       (!empty($entry['ttl'])
						? $this->seconds_to_words($entry['ttl'])
						: 'None'),
					       $delete
					       );
			    } elseif ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES) {
				$datum = array('<a href="' . $this->get_updated_link(array('SH' => md5($entry[$fieldkey]))) . '">'
					       . $entry[$fieldname]
					       . '</a>',
					       $entry['num_hits'],
					       $entry['mem_size'],
					       $entry['access_time'],
					       $entry['mtime'],
					       $entry['creation_time'],
					       $delete
					       );
			    }
			    $entry = array();
			    $cache_reporting[$C_D_list[$this->apc_lib->get_scope()]][$i] = null;
			    $list[$k] = $datum;
			}
		    }
		} elseif ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES_DIR) {
		    $tmp = array();
		    foreach ($cache[$C_D_list[$this->apc_lib->get_scope()]] as $entry) {
			$n = dirname($entry['filename']);
			if ($this->apc_lib->get_request('AGGR') > 0) {
			    $n = preg_replace("!^(/?(?:[^/\\\\]+[/\\\\]){".($this->apc_lib->get_request('AGGR')-1)."}[^/\\\\]*).*!", "$1", $n);
			}
			if (!isset($tmp[$n])) {
			    $tmp[$n] = array(0 => $n, /* Directory Name */
					     1 => 0,  /* Number of Files */
					     2 => 0,  /* Hits */
					     3 => 0,  /* Size */
					     4 => 0,  /* Avg. Hits */
					     5 => 0   /* Avg. Size */
					     );
			}
			$tmp[$n][1]++;
			$tmp[$n][2] += $entry['num_hits'];
			$tmp[$n][3] += $entry['mem_size'];
		    }
		    foreach ($tmp as $k => $v) {
			if (!$tmp[$k][1]) {
			    jesse($tmp[$k]);
			}
			$tmp[$k][4] = round($tmp[$k][2] / $tmp[$k][1]);
			$tmp[$k][5] = round($tmp[$k][3] / $tmp[$k][1]);
		    }
		    $kn = '';
		    if ($this->apc_lib->get_sort()) {
			$sort_by = $sort_keys[$this->apc_lib->get_sort()];
		    }
		    if (0 == $sort_by) {
			$list =& $tmp;
		    } else {
			$list = array();
			foreach ($tmp as $v) {
			    $k = sprintf('%015d-', $v[$sort_by]). $v[0];
			    $list[$k] = $v;
			}
		    }
		    unset($tmp);

		}
		/* Sort and slice data */
		if ($list) {
		    if ('A' == $this->apc_lib->get_sort()) {
			ksort($list);
		    } else {
			krsort($list);
		    }
		}
		$old_list_count = count($list);
		if ($count = $this->apc_lib->get_count()) {
		    $data = array_slice($list, 0, $count);
		} else {
		    $data =& $list;
		}
		unset($list);
		$new_list_count = count($data);
    
		/* VIEW DATA */
		echo '<div class="sorting">';
		echo '<form action="" method="get">';
		echo '<p>';
		echo '<input type="hidden" name="VIEW" value="';
		echo $this->apc_lib->get_view();
		echo '" />';
		echo $this->get_select('SCOPE', 'Scope');
		echo $this->get_select($this->apc_lib->get_sort_context(), 'Sort');
		echo $this->get_select('SORT2', 'Direction');
		echo $this->get_select('COUNT', 'Count');

		if ($view == apc_lib::$VIEW_SYSTEM_CACHE_ENTRIES_DIR) {
		    echo $this->get_select('AGGR', 'Group By Dir Level');
		} else {
		    echo ' Search: <input name="SEARCH" value="';
		    echo $this->apc_lib->get_request('SEARCH');
		    echo '" type="text" size="25" />';
		}
	    
		echo '<input type="submit" value="GO!" />';
		echo '</p>';
		echo '</form>';
		echo '</div>';

		echo '<div class="info">';	    
		if ($data) {
		    echo $this->get_data_table($meta_data, $data);
		} else {
		    echo '<p>No data!</p>';
		}
		if ($old_list_count != $new_list_count) {
		    echo '<a href="';
		    echo $this->get_updated_link(array('COUNT' => 0));
		    echo '">';
		    echo '<i>';
		    echo ($old_list_count - $new_list_count);
		    echo ' more available...';
		    echo '</i>';
		    echo '</a>';
		}
		echo '</div>';
		break;
	    
	    }
		/*
		 * Version Check
		 */
	    case apc_lib::$VIEW_VERSION_CHECK : {
		echo '<div class="info">';
		echo '<h2>APC Version Information</h2>';
		$rss = @file_get_contents("http://pecl.php.net/feeds/pkg_apc.rss");
		if (!$rss) {
		    echo '<p class="error">Unable to fetch version information.</p>';
		} else {
		    $apcversion = phpversion('apc');
		    $matches = array();
		    if (!preg_match_all('!<title>APC ([0-9.]+)</title>!', $rss, $matches)) {
			echo '<p class="error">Error finding the latest version in the rss feed</p>';
		    } else {
			$highest_version = $apcversion;
			foreach ($matches[1] as $match) {
			    if (version_compare($apcversion, $match, '<')
				&& version_compare($match, $highest_version, '>')) {
				$highest_version = $match;
			    }
			}
			if ($highest_version != $apcversion) {
			    echo '<div class="failed">';
			    echo 'You are running an older version of APC ('.$apcversion.'), ';
			    echo 'The newer version ' . $highest_version . ' is available at ';
			    echo '<a href="http://pecl.php.net/package/APC/' . $highest_version . '">';
			    echo 'http://pecl.php.net/package/APC/' . $highest_version . '</a>';
			    echo '</div>';
			} else {
			    echo '<div class="ok">';
			    echo 'You are running the latest version of APC (';
			    echo $apcversion;
			    echo ')';
			    echo '</div>';

			}
		    
		    }
		    echo '<h3>Change Log:</h3><br/>';
		    $matches = array();
		    if (preg_match_all('!<(title|description)>([^<]+)</\\1>!', $rss, $matches)) {
			/* Always skip the first two matches? */
			echo '<dl>';
			foreach ($matches[0] as $i => $match) {
			    $key = $matches[1][$i];
			    $value = $matches[2][$i];
			    if ($value != 'Latest releases'
				&& $value != 'The latest releases for the package apc') {
				if ($key == 'title') {
				    $version_parts = explode(' ', $value, 2);
				    $version = array_pop($version_parts);
				    echo '<dt>';	
				    echo '<a href="http://pecl.php.net/package/APC/';
				    echo htmlspecialchars($version);
				    echo '">';
				    echo htmlspecialchars($version);
				    echo '</a>';
				    echo '</dt>';
				}
				if ($key == 'description') {
				    echo '<dd>';
				    $lines = array_filter(split("\n\s*\*", preg_replace('/^\s*\*/', '', $value)));
				    if ($lines) {
					echo '<ul>';
					foreach ($lines as $line) {
					    echo '<li>';
					    echo str_replace("\n", '<br />', trim($line));
					    echo '</li>';
					}
					echo '</ul>';
				    }
				    echo '</dd>';
				}
			    }
			}
			echo '</dl>';
		    }
		    echo '</div>';
		}
		break;
	    }
	    default : {
		echo '<p class="error">Unhandled view: ' . $view . '</p>';
	    }
	    }
	}
	return ob_get_clean();
    }
    public function display_errors($errors) {
	ob_start();
	if ($errors) {
	    echo '<ol class="errors">';
	    foreach ($errors as $error) {
		echo '<li class="error">';
		echo $error;
		echo '</li>';
	    }
	    echo '</ol>';
	}
	return ob_get_clean();
    }
    public function render($page) {
	/* HTTP/1.1 */
	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Cache-Control: post-check=0, pre-check=0", false);
	/* HTTP/1.0 */
	header("Pragma: no-cache");
	?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
	<head>
	<title><?php echo $page['title']; ?></title>
	<?php echo $page['head']; ?>
	</head>
	<body>
	<div class="head">
		  <div class="head_decoration">
	  <div class="apc">
            <div class="logo"><a href="http://pecl.php.net/package/APC">APC</a></div>
	    <div class="nameinfo">Opcode Cache</div>
	  </div>
	  <div class="login"><?php echo $this->get_login_link(); ?></div>
	  </div>
        </div>									     
	<?php
	{}
	if ($this->apc_lib->is_valid) {
	    echo '<ol class="menu">';
	    echo '<li><a href="';
	    echo $this->get_refresh_link();
	    echo '">Refresh Data</a></li>';
	    foreach (apc_lib::$allowed_values['VIEW'] as $view_index => $label) {
		echo $this->get_menu_entry($view_index, $label);
	    }
	    if ($this->apc_lib->is_authenticated()) {
		foreach (apc_lib::$allowed_values['CC'] as $key => $description) {
		    echo '<li>';
		    echo '<a href="';
		    echo $this->get_updated_link(array('CC' => $key));
		    echo '" onclick="javascipt:return confirm(\'Are you sure?\');">Clear ';
		    echo $description;
		    echo '</a>';
		    echo '</li>';
		}
	    }
	    ?>
	</ol>
		  <?php
		  }
	?>
        <div class="content">
	<?php echo $page['body']; ?>
	</div>
	<!-- <p>Based on APCGUI By R.Becker</p> -->
	</body>
	</html>
	<?php
    }
}
/*
 * You can include your optional configuration file here.
 */
if (file_exists("apc.conf.php")) {
    include("apc.conf.php");
}
define('ADMIN_PASSWORD', 'poop');
/*
 * If this file was requested directly, then it is assumed that we are using
 * this file itself to chant all of the correct incantations to generate the
 * page.
 */
if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    if (!isset($apc_lib)) {
	$apc_lib = new apc_lib();
    }
    $apc_lib->magic();
}


