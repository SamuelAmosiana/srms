<?php
namespace Dompdf;

/**
 * Simplified Options class for DOMPDF
 */
class Options
{
    private $options = [];

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'defaultFont' => 'serif',
            'dpi' => 96,
            'fontCache' => __DIR__ . '/../../cache/',
            'logOutputFile' => __DIR__ . '/../../logs/dompdf.log',
            'tempDir' => sys_get_temp_dir(),
            'chroot' => realpath(__DIR__ . '/../..'),
            'enableFontSubsetting' => false,
            'html5Parser' => true,
            'enable_css_float' => true,
            'enable_javascript' => false,
            'enable_remote' => true,
            'enable_html5_parser' => true,
        ], $options);
    }

    public function set($key, $value)
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function get($key)
    {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    public function setDefaultFont($font)
    {
        $this->options['defaultFont'] = $font;
        return $this;
    }

    public function getDefaultFont()
    {
        return $this->options['defaultFont'];
    }

    public function setDpi($dpi)
    {
        $this->options['dpi'] = $dpi;
        return $this;
    }

    public function getDpi()
    {
        return $this->options['dpi'];
    }

    public function getOptions()
    {
        return $this->options;
    }
}