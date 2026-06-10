<?php

use Dompdf\Dompdf;
use Mpdf\Mpdf;

function htmlToPdf($x)
{
  try {
    ob_start();
    include($x);
    $page = ob_get_contents();
    ob_end_clean();
    $document = new Dompdf();
    $document->loadHtml($page);
    $document->setPaper('A4', 'portrait');
    $document->render();
    //  $document->stream();
    $document->stream("Application.pdf", array("Attachment" => 0));
    $fileUpload = $document->output();
    return $fileUpload;
  } catch (Throwable $t) {
    echo $t->getMessage();
  }
}


$fileLoad = function ($x) {
  ob_start();
  require($x);
  $page = ob_get_contents();
  ob_end_clean();
  $document = new \Dompdf\Dompdf;
  $document->loadHtml($page);
  $document->setPaper('A4', 'portrait');
  $document->render();

  //  $document->stream("Application.pdf", array("Attachment"=>0));
  $fileupload = $document->output();
  return $fileupload;
};

function pdf_convert($file, $name)
{

  $filename = "$name contract.pdf";
  ob_start();
  require($file);
  $pdf2 = ob_get_contents();
  ob_end_clean();
  $mpdf = new \Mpdf\Mpdf();
  $mpdf->text_input_as_HTML = true;
  $mpdf->simpleTables = true;
  $html = mb_convert_encoding($pdf2, 'UTF-8', 'UTF-8');
  $mpdf->WriteHTML($html);
  $fileupload = $mpdf->Output($filename, 'S');
  return $fileupload;
}

function pdf_convert2($file)
{
  $name = $_SESSION['first_name'];
  $filename = "contract.pdf";
  ob_start();
  include($file);
  $pdf2 = ob_get_contents();
  ob_end_clean();
  $mpdf = new \Mpdf\Mpdf();
  $mpdf->text_input_as_HTML = true;
  $mpdf->simpleTables = true;
  $html = mb_convert_encoding($pdf2, 'UTF-8', 'UTF-8');
  $mpdf->WriteHTML($html);
  $fileupload = $mpdf->Output($filename, 'S');
  return $fileupload;
}


function convertHtml($x)
{
  ob_start();
  $x;
  $page = ob_get_contents();
  ob_end_clean();
  $document = new \Dompdf\Dompdf;
  $document->loadHtml($page);
  $document->setPaper('A4', 'portrait');
  $document->render();
  $document->stream("Application.pdf", array("Attachment" => 0));
  $fileupload = $document->output();
  return $fileupload;
}

function pdfConvertDom($x)
{

  $document = new Dompdf();
  ob_start();
  require($x);
  $page = ob_get_contents();
  ob_end_clean();
  $document = new Dompdf();
  $document->loadHtml($page);
  $document->setPaper('A4', 'portrait');
  $document->render();
  $document->stream("contract.pdf", array("Attachment" => 0));
  $fileupload = $document->output();
  return $fileupload;
}
