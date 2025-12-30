<?php
namespace Dompdf;

use Dompdf\Adapter\CPDF;
use Dompdf\Css\Stylesheet;
use Dompdf\Renderer\ListBullet;

/**
 * Simplified DOMPDF class for LSC SRMS
 */
class Dompdf
{
    /**
     * @var Canvas
     */
    private $canvas;

    /**
     * @var Stylesheet
     */
    private $css;

    /**
     * @var string
     */
    private $html;

    /**
     * @var Options
     */
    private $options;

    public function __construct($options = null)
    {
        $this->options = $options ?: new Options();
        $this->canvas = new CPDF($this->options);
        $this->css = new Stylesheet($this);
    }

    /**
     * Load HTML content
     */
    public function loadHtml($html)
    {
        $this->html = $html;
    }

    /**
     * Render the PDF
     */
    public function render()
    {
        // Simplified rendering - just return the HTML for now
        // In a real implementation, this would convert HTML to PDF
        return $this->html;
    }

    /**
     * Output the PDF
     */
    public function stream($filename = "document.pdf", $options = [])
    {
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $this->render();
    }

    /**
     * Get the canvas
     */
    public function getCanvas()
    {
        return $this->canvas;
    }

    /**
     * Get the CSS
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Get the options
     */
    public function getOptions()
    {
        return $this->options;
    }
}