<?php
namespace Dompdf;

/**
 * Simplified Canvas interface for DOMPDF
 */
interface Canvas
{
    public function line($x1, $y1, $x2, $y2, $color, $width, $style = []);
    
    public function rectangle($x, $y, $w, $h, $color, $width, $style = []);
    
    public function filled_rectangle($x, $y, $w, $h, $color);
    
    public function text($x, $y, $text, $font, $size, $color = [0, 0, 0], $word_space = 0.0, $char_space = 0.0, $angle = 0.0);
    
    public function page_text($x, $y, $text, $font, $size, $color = [0, 0, 0]);
    
    public function page_script($script, $type = "text/php");
    
    public function add_info($label, $value);
    
    public function get_width();
    
    public function get_height();
    
    public function get_page_number();
    
    public function get_page_count();
    
    public function new_page();
    
    public function stream($filename, $options = []);
    
    public function output($options = []);
}