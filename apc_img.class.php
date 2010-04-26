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

class apc_img {
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
      imagearc($this->image, $cx, $cy, $diam, $diam, $start, $end, $this->col_black);
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
