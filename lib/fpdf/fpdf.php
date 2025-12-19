<?php
/*******************************************************************************
* FPDF - Simplified version for SRMS
*******************************************************************************/

define('FPDF_VERSION','1.85');

class FPDF
{
protected $page;               // current page number
protected $n;                  // current object number
protected $buffer;             // buffer holding in-memory PDF
protected $pages;              // array containing pages
protected $state;              // current document state
protected $k;                  // scale factor (number of points in user unit)
protected $DefOrientation;     // default orientation
protected $CurOrientation;     // current orientation
protected $StdPageSizes;       // standard page sizes
protected $DefPageSize;        // default page size
protected $CurPageSize;        // current page size
protected $PageInfo;           // page-related data
protected $wPt, $hPt;          // dimensions of current page in points
protected $w, $h;              // dimensions of current page in user unit
protected $lMargin;            // left margin
protected $tMargin;            // top margin
protected $rMargin;            // right margin
protected $bMargin;            // page break margin
protected $cMargin;            // cell margin
protected $x, $y;              // current position in user unit
protected $lasth;              // height of last printed cell
protected $LineWidth;          // line width in user unit
protected $fontpath;           // path containing fonts
protected $CoreFonts;          // array of core font names
protected $fonts;              // array of used fonts
protected $FontFiles;          // array of font files
protected $encodings;          // array of encodings
protected $cmaps;              // array of ToUnicode CMaps
protected $FontFamily;         // current font family
protected $FontStyle;          // current font style
protected $underline;          // underlining flag
protected $CurrentFont;        // current font info
protected $FontSizePt;         // current font size in points
protected $FontSize;           // current font size in user unit
protected $DrawColor;          // commands for drawing color
protected $FillColor;          // commands for filling color
protected $TextColor;          // commands for text color
protected $ColorFlag;          // indicates whether fill and text colors are different
protected $ws;                 // word spacing
protected $images;             // array of used images
protected $links;              // array of internal links (hrefs)
protected $AutoPageBreak;      // automatic page breaking
protected $PageBreakTrigger;   // threshold used to trigger page breaks
protected $InHeader;           // flag set when processing header
protected $InFooter;           // flag set when processing footer
protected $AliasNbPages;       // alias for total number of pages
protected $ZoomMode;           // zoom display mode
protected $LayoutMode;         // layout display mode
protected $metadata;           // document properties
protected $PDFVersion;         // PDF version number
protected $compress;           // compression flag
protected $CurRotation;        // current page rotation
protected $offsets;            // array of object offsets

/*******************************************************************************
*                               Public methods                                 *
*******************************************************************************/

function __construct($orientation='P', $unit='mm', $size='A4')
{
    // Initialization of properties
    $this->state = 0;
    $this->page = 0;
    $this->n = 2;
    $this->buffer = '';
    $this->pages = array();
    $this->PageInfo = array();
    $this->fonts = array();
    $this->FontFiles = array();
    $this->encodings = array();
    $this->cmaps = array();
    $this->images = array();
    $this->links = array();
    $this->InHeader = false;
    $this->InFooter = false;
    $this->lasth = 0;
    $this->FontFamily = '';
    $this->FontStyle = '';
    $this->FontSizePt = 12;
    $this->underline = false;
    $this->DrawColor = '0 G';
    $this->FillColor = '0 g';
    $this->TextColor = '0 g';
    $this->ColorFlag = false;
    $this->ws = 0;
    // Font path
    if(defined('FPDF_FONTPATH'))
    {
        $this->fontpath = FPDF_FONTPATH;
        if(substr($this->fontpath,-1)!='/' && substr($this->fontpath,-1)!='\\')
            $this->fontpath .= '/';
    }
    elseif(is_dir(dirname(__FILE__).'/font'))
        $this->fontpath = dirname(__FILE__).'/font/';
    else
        $this->fontpath = '';
    // Core fonts
    $this->CoreFonts = array('courier', 'helvetica', 'times', 'symbol', 'zapfdingbats');
    // Scale factor
    if($unit=='pt')
        $this->k = 1;
    elseif($unit=='mm')
        $this->k = 72/25.4;
    elseif($unit=='cm')
        $this->k = 72/2.54;
    elseif($unit=='in')
        $this->k = 72;
    else
        $this->Error('Incorrect unit: '.$unit);
    // Page sizes
    $this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(419.53,595.28),
        'letter'=>array(612,792), 'legal'=>array(612,1008));
    $size = $this->_getpagesize($size);
    $this->DefPageSize = $size;
    $this->CurPageSize = $size;
    // Page orientation
    $orientation = strtolower($orientation);
    if($orientation=='p' || $orientation=='portrait')
        $this->DefOrientation = 'P';
    elseif($orientation=='l' || $orientation=='landscape')
        $this->DefOrientation = 'L';
    else
        $this->Error('Incorrect orientation: '.$orientation);
    $this->CurOrientation = $this->DefOrientation;
    $this->w = $size[0];
    $this->h = $size[1];
    // Page rotation
    $this->CurRotation = 0;
    // Initialize offsets array
    $this->offsets = array();
    // Page margins (1 cm)
    $margin = 28.35/$this->k;
    $this->SetMargins($margin,$margin);
    // Interior cell margin (1 mm)
    $this->cMargin = $margin/10;
    // Line width (0.2 mm)
    $this->LineWidth = .567/$this->k;
    // Automatic page break
    $this->SetAutoPageBreak(true,2*$margin);
    // Default display mode
    $this->SetDisplayMode('default');
    // Enable compression
    $this->SetCompression(true);
    // Set default PDF version number
    $this->PDFVersion = '1.3';
}

function SetMargins($left, $top, $right=null)
{
    // Set left, top and right margins
    $this->lMargin = $left;
    $this->tMargin = $top;
    if($right===null)
        $right = $left;
    $this->rMargin = $right;
}

function SetLeftMargin($margin)
{
    // Set left margin
    $this->lMargin = $margin;
    if($this->page>0 && $this->x<$margin)
        $this->x = $margin;
}

function SetTopMargin($margin)
{
    // Set top margin
    $this->tMargin = $margin;
}

function SetRightMargin($margin)
{
    // Set right margin
    $this->rMargin = $margin;
}

function SetAutoPageBreak($auto, $margin=0)
{
    // Set auto page break mode and triggering margin
    $this->AutoPageBreak = $auto;
    $this->bMargin = $margin;
    $this->PageBreakTrigger = $this->h-$margin;
}

function SetDisplayMode($zoom, $layout='default')
{
    // Set display mode in viewer
    if($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
        $this->ZoomMode = $zoom;
    else
        $this->Error('Incorrect zoom display mode: '.$zoom);
    if($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
        $this->LayoutMode = $layout;
    else
        $this->Error('Incorrect layout display mode: '.$layout);
}

function SetCompression($compress)
{
    // Set page compression
    if(function_exists('gzcompress'))
        $this->compress = $compress;
    else
        $this->compress = false;
}

function SetTitle($title, $isUTF8=false)
{
    // Title of document
    if($isUTF8)
        $title = $this->_UTF8toUTF16($title);
    $this->metadata['Title'] = $title;
}

function SetAuthor($author, $isUTF8=false)
{
    // Author of document
    if($isUTF8)
        $author = $this->_UTF8toUTF16($author);
    $this->metadata['Author'] = $author;
}

function SetSubject($subject, $isUTF8=false)
{
    // Subject of document
    if($isUTF8)
        $subject = $this->_UTF8toUTF16($subject);
    $this->metadata['Subject'] = $subject;
}

function SetKeywords($keywords, $isUTF8=false)
{
    // Keywords of document
    if($isUTF8)
        $keywords = $this->_UTF8toUTF16($keywords);
    $this->metadata['Keywords'] = $keywords;
}

function SetCreator($creator, $isUTF8=false)
{
    // Creator of document
    if($isUTF8)
        $creator = $this->_UTF8toUTF16($creator);
    $this->metadata['Creator'] = $creator;
}

function AliasNbPages($alias='{nb}')
{
    // Define an alias for total number of pages
    $this->AliasNbPages = $alias;
}

function Error($msg)
{
    // Fatal error
    throw new Exception('FPDF error: '.$msg);
}

function Close()
{
    // Terminate document
    if($this->state==3)
        return;
    if($this->page==0)
        $this->AddPage();
    // Page footer
    $this->InFooter = true;
    $this->Footer();
    $this->InFooter = false;
    // Close page
    $this->_endpage();
    // Close document
    $this->_enddoc();
}

function AddPage($orientation='', $size='', $rotation=0)
{
    // Start a new page
    if($this->state==3)
        $this->Error('The document is closed');
    $family = $this->FontFamily;
    $style = $this->FontStyle.($this->underline ? 'U' : '');
    $fontsize = $this->FontSizePt;
    $lw = $this->LineWidth;
    $dc = $this->DrawColor;
    $fc = $this->FillColor;
    $tc = $this->TextColor;
    $cf = $this->ColorFlag;
    if($this->page>0)
    {
        // Page footer
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        // Close page
        $this->_endpage();
    }
    // Start new page
    $this->_beginpage($orientation,$size,$rotation);
    // Set line cap style to square
    $this->_out('2 J');
    // Set line width
    $this->LineWidth = $lw;
    $this->_out(sprintf('%.2F w',$lw*$this->k));
    // Set font
    if($family)
        $this->SetFont($family,$style,$fontsize);
    // Set colors
    $this->DrawColor = $dc;
    if($dc!='0 G')
        $this->_out($dc);
    $this->FillColor = $fc;
    if($fc!='0 g')
        $this->_out($fc);
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
    // Page header
    $this->InHeader = true;
    $this->Header();
    $this->InHeader = false;
    // Restore line width
    if($this->LineWidth!=$lw)
    {
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w',$lw*$this->k));
    }
    // Restore font
    if($family)
        $this->SetFont($family,$style,$fontsize);
    // Restore colors
    if($this->DrawColor!=$dc)
    {
        $this->DrawColor = $dc;
        $this->_out($dc);
    }
    if($this->FillColor!=$fc)
    {
        $this->FillColor = $fc;
        $this->_out($fc);
    }
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
}

function Header()
{
    // To be implemented in your own inherited class
}

function Footer()
{
    // To be implemented in your own inherited class
}

function PageNo()
{
    // Get current page number
    return $this->page;
}

function SetDrawColor($r, $g=null, $b=null)
{
    // Set color for all stroking operations
    if(($r==0 && $g==0 && $b==0) || $g===null)
        $this->DrawColor = sprintf('%.3F G',$r/255);
    else
        $this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
    if($this->page>0)
        $this->_out($this->DrawColor);
}

function SetFillColor($r, $g=null, $b=null)
{
    // Set color for all filling operations
    if(($r==0 && $g==0 && $b==0) || $g===null)
        $this->FillColor = sprintf('%.3F g',$r/255);
    else
        $this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag = ($this->FillColor!=$this->TextColor);
    if($this->page>0)
        $this->_out($this->FillColor);
}

function SetTextColor($r, $g=null, $b=null)
{
    // Set color for text
    if(($r==0 && $g==0 && $b==0) || $g===null)
        $this->TextColor = sprintf('%.3F g',$r/255);
    else
        $this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag = ($this->FillColor!=$this->TextColor);
}

function GetStringWidth($s)
{
    // Get width of a string in the current font
    $s = (string)$s;
    $cw = &$this->CurrentFont['cw'];
    $w = 0;
    $l = strlen($s);
    for($i=0;$i<$l;$i++)
        $w += $cw[$s[$i]];
    return $w*$this->FontSize/1000;
}

function SetLineWidth($width)
{
    // Set line width
    $this->LineWidth = $width;
    if($this->page>0)
        $this->_out(sprintf('%.2F w',$width*$this->k));
}

function Line($x1, $y1, $x2, $y2)
{
    // Draw a line
    $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
}

function Rect($x, $y, $w, $h, $style='')
{
    // Draw a rectangle
    if($style=='F')
        $op = 'f';
    elseif($style=='FD' || $style=='DF')
        $op = 'B';
    else
        $op = 'S';
    $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
}

function AddFont($family, $style='', $file='')
{
    // Add a TrueType, OpenType or Type1 font
    $family = strtolower($family);
    if($file=='')
        $file = str_replace(' ','',$family).strtolower($style).'.php';
    $style = strtoupper($style);
    if($style=='IB')
        $style = 'BI';
    $fontkey = $family.$style;
    if(isset($this->fonts[$fontkey]))
        return;
    if(strpos($file,'/')!==false || strpos($file,"\\")!==false)
        $this->Error('Incorrect font definition file name: '.$file);
    
    // Check if file exists before including
    $filepath = $this->fontpath.$file;
    if(file_exists($filepath)) {
        include($filepath);
        if(!isset($name))
            $this->Error('Could not include font definition file');
        $i = count($this->fonts)+1;
        // Set default values for potentially undefined variables
        if(!isset($desc)) $desc = array();
        if(!isset($up)) $up = -100;
        if(!isset($ut)) $ut = 50;
        if(!isset($cw)) $cw = array();
        if(!isset($file)) $file = '';
        if(!isset($ctg)) $ctg = '';
        $this->fonts[$fontkey] = array('i'=>$i, 'type'=>$type, 'name'=>$name, 'desc'=>$desc, 'up'=>$up, 'ut'=>$ut, 'cw'=>$cw, 'file'=>$file, 'ctg'=>$ctg, 'n'=>0);
    } else {
        // Fall back to core fonts
        if(in_array($family, $this->CoreFonts)) {
            // Map Arial to Helvetica
            if($family == 'arial') {
                $family = 'helvetica';
                $fontkey = $family.$style;
            }
            $i = count($this->fonts)+1;
            $this->fonts[$fontkey] = array('i'=>$i, 'type'=>'Core', 'name'=>ucfirst($family).($style?'-'.$style:''), 'desc'=>array(), 'up'=>-100, 'ut'=>50, 'cw'=>array_fill(0, 255, 600), 'file'=>'', 'ctg'=>'', 'n'=>0);
        } else {
            $this->Error('Could not include font definition file');
        }
    }
}

function SetFont($family, $style='', $size=0)
{
    // Select a font; size given in points
    if($family=='')
        $family = $this->FontFamily;
    else
        $family = strtolower($family);
    $style = strtoupper($style);
    if(strpos($style,'U')!==false)
    {
        $this->underline = true;
        $style = str_replace('U','',$style);
    }
    else
        $this->underline = false;
    if($style=='IB')
        $style = 'BI';
    if($size==0)
        $size = $this->FontSizePt;
    // Test if font is already selected
    if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size)
        return;
    // Test if font is already loaded
    $fontkey = $family.$style;
    if(!isset($this->fonts[$fontkey]))
    {
        // Test if one of the core fonts
        if($family=='arial')
            $family = 'helvetica';
        if(in_array($family,$this->CoreFonts))
        {
            if($family=='symbol' || $family=='zapfdingbats')
                $style = '';
            $fontkey = $family.$style;
            if(!isset($this->fonts[$fontkey]))
                $this->AddFont($family,$style);
        }
        else
            $this->Error('Undefined font: '.$family.' '.$style);
    }
    // Select it
    $this->FontFamily = $family;
    $this->FontStyle = $style;
    $this->FontSizePt = $size;
    $this->FontSize = $size/$this->k;
    $this->CurrentFont = &$this->fonts[$fontkey];
    if($this->page>0)
        $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function SetFontSize($size)
{
    // Change font size
    if($this->FontSizePt==$size)
        return;
    $this->FontSizePt = $size;
    $this->FontSize = $size/$this->k;
    if($this->page>0)
        $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function AddLink()
{
    // Create a new internal link
    $n = count($this->links)+1;
    $this->links[$n] = array(0, 0);
    return $n;
}

function SetLink($link, $y=0, $page=-1)
{
    // Set destination of internal link
    if($y==-1)
        $y = $this->y;
    if($page==-1)
        $page = $this->page;
    $this->links[$link] = array($page, $y);
}

function Link($x, $y, $w, $h, $link)
{
    // Put a link on the page
    // Not implemented in this simplified version
}

function Text($x, $y, $txt)
{
    // Output a string
    if(!isset($this->CurrentFont))
        $this->Error('No font has been set');
    $s = sprintf('BT %.2F %.2F Td (%s) Tj ET',$x*$this->k,($this->h-$y)*$this->k,$this->_escape($txt));
    if($this->underline && $txt!='')
        $s .= ' '.$this->_dounderline($x,$y,$txt);
    if($this->ColorFlag)
        $s = 'q '.$this->TextColor.' '.$s.' Q';
    $this->_out($s);
}

function AcceptPageBreak()
{
    // Accept automatic page break or not
    return $this->AutoPageBreak;
}

function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
{
    // Output a cell
    $k = $this->k;
    if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak())
    {
        // Automatic page break
        $x = $this->x;
        $ws = $this->ws;
        if($ws>0)
        {
            $this->ws = 0;
            $this->_out('0 Tw');
        }
        $this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);
        $this->x = $x;
        if($ws>0)
        {
            $this->ws = $ws;
            $this->_out(sprintf('%.3F Tw',$ws*$k));
        }
    }
    if($w==0)
        $w = $this->w-$this->rMargin-$this->x;
    $s = '';
    if($fill || $border==1)
    {
        if($fill)
            $op = ($border==1) ? 'B' : 'f';
        else
            $op = 'S';
        $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
    }
    if(is_string($border))
    {
        $x = $this->x;
        $y = $this->y;
        if(strpos($border,'L')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'T')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
        if(strpos($border,'R')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'B')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
    }
    if($txt!=='')
    {
        if(!isset($this->CurrentFont))
            $this->Error('No font has been set');
        if($align=='R')
            $dx = $w-$this->cMargin-$this->GetStringWidth($txt);
        elseif($align=='C')
            $dx = ($w-$this->GetStringWidth($txt))/2;
        else
            $dx = $this->cMargin;
        if($this->ColorFlag)
            $s .= 'q '.$this->TextColor.' ';
        $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$this->_escape($txt));
        if($this->underline)
            $s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
        if($this->ColorFlag)
            $s .= ' Q';
        if($link)
            $this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
    }
    if($s)
        $this->_out($s);
    $this->lasth = $h;
    if($ln>0)
    {
        // Go to next line
        $this->y += $h;
        if($ln==1)
            $this->x = $this->lMargin;
    }
    else
        $this->x += $w;
}

function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false)
{
    // Output text with automatic or explicit line breaks
    if(!isset($this->CurrentFont))
        $this->Error('No font has been set');
    $cw = &$this->CurrentFont['cw'];
    if($w==0)
        $w = $this->w-$this->rMargin-$this->x;
    $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
    $s = str_replace("\r",'',$txt);
    $nb = strlen($s);
    if($nb>0 && $s[$nb-1]=="\n")
        $nb--;
    $b = 0;
    if($border)
    {
        if($border==1)
        {
            $border = 'LTRB';
            $b = 'LRT';
            $b2 = 'LR';
        }
        else
        {
            $b2 = '';
            if(strpos($border,'L')!==false)
                $b2 .= 'L';
            if(strpos($border,'R')!==false)
                $b2 .= 'R';
            $b = (strpos($border,'T')!==false) ? $b2.'T' : $b2;
        }
    }
    $sep = -1;
    $i = 0;
    $j = 0;
    $l = 0;
    $ns = 0;
    $nl = 1;
    while($i<$nb)
    {
        // Get next character
        $c = $s[$i];
        if($c=="\n")
        {
            // Explicit line break
            if($this->ws>0)
            {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
            $i++;
            $sep = -1;
            $j = $i;
            $l = 0;
            $ns = 0;
            $nl++;
            if($border && $nl==2)
                $b = $b2;
            continue;
        }
        if($c==' ')
        {
            $sep = $i;
            $ls = $l;
            $ns++;
        }
        $l += $cw[$c];
        if($l>$wmax)
        {
            // Automatic line break
            if($sep==-1)
            {
                if($i==$j)
                    $i++;
                if($this->ws>0)
                {
                    $this->ws = 0;
                    $this->_out('0 Tw');
                }
                $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
            }
            else
            {
                if($align=='J')
                {
                    $this->ws = ($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
                    $this->_out(sprintf('%.3F Tw',$this->ws*$this->k));
                }
                $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
                $i = $sep+1;
            }
            $sep = -1;
            $j = $i;
            $l = 0;
            $ns = 0;
            $nl++;
            if($border && $nl==2)
                $b = $b2;
        }
        else
            $i++;
    }
    // Last chunk
    if($this->ws>0)
    {
        $this->ws = 0;
        $this->_out('0 Tw');
    }
    if($border && strpos($border,'B')!==false)
        $b .= 'B';
    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
    $this->x = $this->lMargin;
}

function Write($h, $txt, $link='')
{
    // Output text in flowing mode
    // Simplified implementation
}

function Ln($h=null)
{
    // Line feed; default value is the last cell height
    $this->x = $this->lMargin;
    if($h===null)
        $this->y += $this->lasth;
    else
        $this->y += $h;
}

function Image($file, $x=null, $y=null, $w=0, $h=0, $type='', $link='')
{
    // Put an image on the page
    // Simplified implementation - just skip image rendering
}

function GetPageWidth()
{
    // Get current page width
    return $this->w;
}

function GetPageHeight()
{
    // Get current page height
    return $this->h;
}

function GetX()
{
    // Get x position
    return $this->x;
}

function SetX($x)
{
    // Set x position
    if($x>=0)
        $this->x = $x;
    else
        $this->x = $this->w+$x;
}

function GetY()
{
    // Get y position
    return $this->y;
}

function SetY($y, $resetX=true)
{
    // Set y position and optionally reset x
    if($y>=0)
        $this->y = $y;
    else
        $this->y = $this->h+$y;
    if($resetX)
        $this->x = $this->lMargin;
}

function SetXY($x, $y)
{
    // Set x and y positions
    $this->SetX($x);
    $this->SetY($y);
}

function Output($dest='', $name='', $isUTF8=false)
{
    // Output PDF
    if($this->state<3)
        $this->Close();
    
    if($dest=='D')
    {
        // Download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$name.'"');
        echo $this->buffer;
        for($i=1;$i<=$this->page;$i++)
            echo $this->pages[$i];
    }
    else if($dest=='I')
    {
        // Inline display
        header('Content-Type: application/pdf');
        echo $this->buffer;
        for($i=1;$i<=$this->page;$i++)
            echo $this->pages[$i];
    }
    else if($dest=='F')
    {
        // Save to local file
        $f = fopen($name, 'wb');
        if(!$f)
            $this->Error('Unable to create output file: '.$name);
        fwrite($f, $this->buffer);
        for($i=1;$i<=$this->page;$i++)
            fwrite($f, $this->pages[$i]);
        fclose($f);
    }
    else
    {
        // Default output
        echo $this->buffer;
        for($i=1;$i<=$this->page;$i++)
            echo $this->pages[$i];
    }
}

/*******************************************************************************
*                              Protected methods                               *
*******************************************************************************/

protected function _getpagesize($size)
{
    if(is_string($size))
    {
        $size = strtolower($size);
        if(!isset($this->StdPageSizes[$size]))
            $this->Error('Unknown page size: '.$size);
        $a = $this->StdPageSizes[$size];
        return array($a[0]/$this->k, $a[1]/$this->k);
    }
    else
    {
        if($size[0]>$size[1])
            return array($size[1], $size[0]);
        else
            return $size;
    }
}

protected function _beginpage($orientation, $size, $rotation)
{
    $this->page++;
    $this->pages[$this->page] = '';
    if($orientation=='')
        $orientation = $this->DefOrientation;
    else
        $orientation = strtoupper($orientation[0]);
    if($size=='')
        $size = $this->DefPageSize;
    else
        $size = $this->_getpagesize($size);
    if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1])
    {
        // New size or orientation
        if($orientation=='P')
        {
            $this->w = $size[0];
            $this->h = $size[1];
        }
        else
        {
            $this->w = $size[1];
            $this->h = $size[0];
        }
        $this->wPt = $this->w*$this->k;
        $this->hPt = $this->h*$this->k;
        $this->PageBreakTrigger = $this->h-$this->bMargin;
        $this->CurOrientation = $orientation;
        $this->CurPageSize = $size;
    }
    if($orientation!=$this->DefOrientation || $size[0]!=$this->DefPageSize[0] || $size[1]!=$this->DefPageSize[1])
        $size = array($this->wPt, $this->hPt);
    else
        $size = array($this->DefPageSize[0]*$this->k, $this->DefPageSize[1]*$this->k);
    if($rotation!=0)
    {
        if($rotation%90!=0)
            $this->Error('Incorrect rotation value: '.$rotation);
    }
    $this->CurRotation = $rotation;
    $this->PageInfo[$this->page] = array('n'=>0, 'x0'=>0, 'y0'=>0, 'x1'=>$this->wPt, 'y1'=>$this->hPt, 'rotation'=>$rotation, 'size'=>$size);
}

protected function _endpage()
{
    $this->state = 1;
}

protected function _enddoc()
{
    $this->state = 1;
    $this->_putpages();
    $this->_putresources();
    // Info
    $this->_newobj();
    $this->_put('<<');
    $this->_putinfo();
    $this->_put('>>');
    $this->_put('endobj');
    // Catalog
    $this->_newobj();
    $this->_put('<<');
    $this->_putcatalog();
    $this->_put('>>');
    $this->_put('endobj');
    // Cross-reference
    $o = strlen($this->buffer);
    $this->_put('xref');
    $this->_put('0 '.($this->n+1));
    $this->_put('0000000000 65535 f ');
    for($i=1;$i<=$this->n;$i++)
        $this->_put(sprintf('%010d 00000 n ',isset($this->offsets[$i]) ? $this->offsets[$i] : 0));
    // Trailer
    $this->_put('trailer');
    $this->_put('<<');
    $this->_puttrailer();
    $this->_put('>>');
    $this->_put('startxref');
    $this->_put($o);
    $this->_put('%%EOF');
    $this->state = 3;
}

protected function _out($s)
{
    // Add a line to the document
    if($this->state==2)
        $this->pages[$this->page] .= $s."\n";
    else
        $this->buffer .= $s."\n";
}

protected function _escape($s)
{
    // Escape special characters in strings
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace('(', '\\(', $s);
    $s = str_replace(')', '\\)', $s);
    $s = str_replace("\r", '\\r', $s);
    return $s;
}

protected function _UTF8toUTF16($s)
{
    // Convert UTF-8 to UTF-16BE
    $res = "\xFE\xFF";
    $nb = strlen($s);
    $i = 0;
    while($i<$nb)
    {
        $c1 = ord($s[$i++]);
        if($c1>=224)
        {
            // 3-byte character
            $c2 = ord($s[$i++]);
            $c3 = ord($s[$i++]);
            $res .= chr((($c1 & 0x0F)<<4) + (($c2 & 0x3C)>>2));
            $res .= chr((($c2 & 0x03)<<6) + ($c3 & 0x3F));
        }
        elseif($c1>=192)
        {
            // 2-byte character
            $c2 = ord($s[$i++]);
            $res .= chr(($c1 & 0x1C)>>2);
            $res .= chr((($c1 & 0x03)<<6) + ($c2 & 0x3F));
        }
        else
        {
            // Single-byte character
            $res .= "\0".chr($c1);
        }
    }
    return $res;
}

protected function _dounderline($x, $y, $txt)
{
    // Underline text
    $up = $this->CurrentFont['up'];
    $ut = $this->CurrentFont['ut'];
    $w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
    return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
}

protected function _putpages()
{
    $nb = $this->page;
    if(!empty($this->AliasNbPages))
    {
        // Replace number of pages
        for($n=1;$n<=$nb;$n++)
            $this->pages[$n] = str_replace($this->AliasNbPages,$nb,$this->pages[$n]);
    }
    if($this->DefOrientation=='P')
    {
        $wPt = $this->DefPageSize[0]*$this->k;
        $hPt = $this->DefPageSize[1]*$this->k;
    }
    else
    {
        $wPt = $this->DefPageSize[1]*$this->k;
        $hPt = $this->DefPageSize[0]*$this->k;
    }
    $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
    for($n=1;$n<=$nb;$n++)
    {
        // Page
        $this->_newobj();
        $this->_put('<</Type /Page');
        $this->_put('/Parent 1 0 R');
        if(isset($this->PageInfo[$n]['size']))
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$this->PageInfo[$n]['size'][0],$this->PageInfo[$n]['size'][1]));
        else
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$wPt,$hPt));
        if(isset($this->PageInfo[$n]['rotation']))
            $this->_put('/Rotate '.$this->PageInfo[$n]['rotation']);
        $this->_put('/Resources 2 0 R');
        if(isset($this->PageInfo[$n]['x0']) && isset($this->PageInfo[$n]['y0']) && isset($this->PageInfo[$n]['x1']) && isset($this->PageInfo[$n]['y1']))
        {
            $this->_put('/CropBox ['.$this->PageInfo[$n]['x0'].' '.$this->PageInfo[$n]['y0'].' '.$this->PageInfo[$n]['x1'].' '.$this->PageInfo[$n]['y1'].']');
        }
        if(isset($this->pages[$n]))
        {
            $this->_put('/Contents '.($this->n+1).' 0 R>>');
            $this->_put('endobj');
            // Page content
            $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
            $this->_newobj();
            $this->_put('<<'.$filter.'/Length '.strlen($p).'>>');
            $this->_putstream($p);
            $this->_put('endobj');
        }
        else
            $this->_put('>>');
        $this->_put('endobj');
    }
    // Pages root
    $this->offsets[1] = strlen($this->buffer);
    $this->_put('1 0 obj');
    $this->_put('<</Type /Pages');
    $kids = '/Kids [';
    for($i=0;$i<$nb;$i++)
        $kids .= (3+2*$i).' 0 R ';
    $this->_put($kids.']');
    $this->_put('/Count '.$nb);
    $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]',$wPt,$hPt));
    $this->_put('>>');
    $this->_put('endobj');
}

protected function _putresources()
{
    // Assign object numbers to fonts
    foreach($this->fonts as $fontkey => $font) {
        if($font['n'] == 0) {
            $this->_newobj();
            $this->fonts[$fontkey]['n'] = $this->n;
            $this->_put('<</Type /Font');
            $this->_put('/Subtype /Type1');
            $this->_put('/BaseFont /'.$font['name']);
            $this->_put('>>');
            $this->_put('endobj');
        }
    }
    
    $this->_put('2 0 obj');
    $this->_put('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
    $this->_put('/Font <<');
    foreach($this->fonts as $font)
        $this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
    $this->_put('>>');
    $this->_put('>>');
    $this->_put('endobj');
}

protected function _putinfo()
{
    $this->_put('/Producer '.$this->_textstring('FPDF '.FPDF_VERSION));
    if(!empty($this->metadata['Title']))
        $this->_put('/Title '.$this->_textstring($this->metadata['Title']));
    if(!empty($this->metadata['Subject']))
        $this->_put('/Subject '.$this->_textstring($this->metadata['Subject']));
    if(!empty($this->metadata['Author']))
        $this->_put('/Author '.$this->_textstring($this->metadata['Author']));
    if(!empty($this->metadata['Keywords']))
        $this->_put('/Keywords '.$this->_textstring($this->metadata['Keywords']));
    if(!empty($this->metadata['Creator']))
        $this->_put('/Creator '.$this->_textstring($this->metadata['Creator']));
    $this->_put('/CreationDate '.$this->_textstring('D:'.@date('YmdHis')));
}

protected function _putcatalog()
{
    $this->_put('/Type /Catalog');
    $this->_put('/Pages 1 0 R');
    if($this->ZoomMode=='fullpage')
        $this->_put('/OpenAction [3 0 R /Fit]');
    elseif($this->ZoomMode=='fullwidth')
        $this->_put('/OpenAction [3 0 R /FitH null]');
    elseif($this->ZoomMode=='real')
        $this->_put('/OpenAction [3 0 R /XYZ null null 1]');
    elseif(!is_string($this->ZoomMode))
        $this->_put('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
    if($this->LayoutMode=='single')
        $this->_put('/PageLayout /SinglePage');
    elseif($this->LayoutMode=='continuous')
        $this->_put('/PageLayout /OneColumn');
    elseif($this->LayoutMode=='two')
        $this->_put('/PageLayout /TwoColumnLeft');
}

protected function _puttrailer()
{
    $this->_put('/Size '.($this->n+1));
    $this->_put('/Root '.$this->n.' 0 R');
    $this->_put('/Info '.($this->n-1).' 0 R');
}

protected function _newobj($n=0)
{
    if($n>0)
        $this->n = $n;
    else
        $this->n++;
    $this->offsets[$this->n] = strlen($this->buffer);
    $this->_put($this->n.' 0 obj');
    return $this->n;
}

protected function _put($s)
{
    $this->buffer .= $s."\n";
}

protected function _putstream($s)
{
    $this->_put('stream');
    $this->_put($s);
    $this->_put('endstream');
}

protected function _textstring($s)
{
    return '('.$this->_escape($s).')';
}
}

?>