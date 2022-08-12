<?php
namespace booosta\odt2pdf;

use \booosta\Framework as b;
b::init_module('odt2pdf');

class restapp extends \booosta\rest\Application
{
  protected $pdf64;
  protected $filetype = 'pdf';


  public function __construct($url)
  {
    parent::__construct();
    $this->url = $url;
    #\booosta\debug("url: $url");
  }
  
  public function set_filetype($filetype) { $this->filetype = strtolower($filetype); }

  public function load_name($filename, $data = [], $rows = [], $images = [], $subrows = [], $opttables = [])
  {
    $result = $this->post('oo2pdf/text', ['filename' => $filename, 'variables' => $data, 'rows' => $rows, 'subrows' => $subrows, 'images' => $images, 
                                          'filetype' => $this->filetype, 'opttables' => $opttables]);
    if((!is_array($result) || $result['status'] != 'OK') && is_callable([$this->topobj, 'raise_error'])) $this->topobj->raise_error($result);

    $this->pdf64 = $result[$this->filetype];
  }

  public function load_odt($odt64, $data = [], $rows = [], $images = [], $subrows = [], $opttables = [])
  {
    #\booosta\debug("images:"); \booosta\debug($images);
    $result = $this->post('oo2pdf/text', ['odt' => $odt64, 'variables' => $data, 'rows' => $rows, 'subrows' => $subrows, 'images' => $images, 
                                          'filetype' => $this->filetype, 'opttables'=> $opttables]);
    #\booosta\debug("result:"); \booosta\debug($result);
    if((!is_array($result) || $result['status'] != 'OK') && is_callable([$this->topobj, 'raise_error'])) $this->topobj->raise_error($result);

    $this->pdf64 = $result[$this->filetype];
  }

  public function pdf64() { return $this->pdf64; }
}


class odt2pdf extends \booosta\base\Module
{ 
  use moduletrait_odt2pdf;

  protected $filename; 
  protected $data;              // {variables} that are replaced in the odt
  protected $rows;              // array with data for tablerows. ODT-Table has to be named rowtable:id, $rows is indexed with this id
  protected $opttables;         // array with name of optional tables. Theses have to be named opttable:id in ODT. Only shown, if id is in $opttables
  protected $odt64;
  protected $filetype = 'pdf';
  protected $images = [];
  protected $odt2pdf_url;


  public function __construct($file = null, $data = [], $rows = [], $subrows = [], $opttables = [])
  {
    parent::__construct();
    if(is_readable($file)) $this->odt64 = base64_encode(file_get_contents($file));
    $this->data = $data;
    $this->rows = $rows;
    $this->subrows = $subrows;
    $this->opttables = $opttables;
    $this->odt2pdf_url = $this->config('odt2pdf_url');
    #\booosta\debug($this->odt64);
  }

  public function set_file($file) { if(is_readable($file)) $this->odt64 = base64_encode(file_get_contents($file)); }
  public function set_odt64($code) { $this->odt64 = $code; }
  public function set_odt($code) { $this->odt64 = base64_encode($code); }
  public function set_data($data) { $this->data = $data; }
  public function set_rows($data) { $this->rows = $data; }
  public function set_subrows($data) { $this->subrows = $data; }
  public function set_opttables($data) { $this->opttables = $data; }
  public function set_filename($filename) { $this->filename = $filename; }
  public function set_images($images) { $this->images = $images; }
  public function set_odt2pdf_url($odt2pdf_url) { $this->odt2pdf_url = $odt2pdf_url; }
  public function set_filetype($filetype) { $this->filetype = strtolower($filetype); }
  
  public function save($filename = 'output.pdf')
  {
    $pdf = $this->get_pdf();
    #\booosta\debug("saving $filename");
    file_put_contents($filename, $pdf);
  }

  public function download($filename = 'output.pdf', $disposition = 'attachment')
  {
    $pdf = $this->get_pdf();

    if($this->filetype == 'odt') $mimetype = 'application/vnd.oasis.opendocument.text';
    else $mimetype = 'application/pdf';

    header("Content-type: $mimetype");
    header('Content-Length: ' . strlen($pdf));
    header("Content-Disposition: $disposition; filename=$filename");
    print $pdf;

    $this->no_output = true;
  }

  public function show($filename = 'output.pdf')
  {
    $this->download($filename, 'inline');
  }

  protected function get_pdf() { return base64_decode($this->get_pdf64()); }

  protected function get_pdf64()
  {
    #\booosta\debug('url ' . $this->config('odt2pdf_url'));
    #\booosta\debug("this->images:"); \booosta\debug($this->images);
    $rest = $this->makeInstance('\\booosta\\odt2pdf\\restapp', $this->odt2pdf_url);
    $rest->set_filetype($this->filetype);

    $images64 = [];
    foreach($this->images as $tablename=>$tablerows):
      if(is_array($tablerows)):
        foreach($tablerows as $idx=>$imagelist)
          foreach($imagelist as $tagname=>$image)
            if(is_readable($image)) $images64[$tablename][$idx][$tagname] = base64_encode(file_get_contents($image));
            else $images64[$tablename][$idx][$tagname] = null;;
      else:
        // not tablerows -> array is $tagname => $image
        $images64['imagelist'][$tablename] = base64_encode(file_get_contents($tablerows));
        #\booosta\debug("$tablename / $tablerows");
      endif;
    endforeach;

    if($this->filename) $rest->load_name($this->filename, $this->data, $this->rows, $images64, $this->subrows, $this->opttables);
    else $rest->load_odt($this->odt64, $this->data, $this->rows, $images64, $this->subrows, $this->opttables);

    return $rest->pdf64();
  }
}
