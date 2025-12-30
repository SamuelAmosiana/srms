<?php
namespace Dompdf\Css;

use Dompdf\Dompdf;

/**
 * Simplified Stylesheet class for DOMPDF
 */
class Stylesheet
{
    private $dompdf;
    private $css = [];

    public function __construct(Dompdf $dompdf)
    {
        $this->dompdf = $dompdf;
    }

    public function load_css($css)
    {
        // In a real implementation, this would parse CSS
        $this->css = $css;
    }

    public function apply_styles($element)
    {
        // In a real implementation, this would apply styles to elements
        return $element;
    }

    public function get_dompdf()
    {
        return $this->dompdf;
    }
}