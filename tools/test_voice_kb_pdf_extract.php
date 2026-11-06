<?php
declare(strict_types=1);
require_once __DIR__.'/../saas/console/_core/VoiceKnowledge.php';
/**
 * A valid, self-contained text PDF, not a fake .pdf extension over a TXT file.
 * The test runs an actual Poppler extraction whenever production supports it.
 */
function kb_pdf_fixture():string {
    $stream="BT /F1 16 Tf 45 250 Td (OnTrack PDF Extract Test 2026) Tj ET\n";
    $objects=[
      '<< /Type /Catalog /Pages 2 0 R >>',
      '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] '.
        '/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
      '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
      '<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream",
    ];
    $pdf="%PDF-1.4\n";
    $offsets=[];
    foreach($objects as $i=>$obj) {
        $offsets[]=strlen($pdf);
        $pdf.=($i+1)." 0 obj\n".$obj."\nendobj\n";
    }
    $xref=strlen($pdf);
    $pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
    foreach($offsets as $offset)$pdf.=sprintf('%010d 00000 n ', $offset)."\n";
    return $pdf."trailer\n<< /Size ".(count($objects)+1).
        " /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}
$pdf=tempnam(sys_get_temp_dir(),'ontrack-pdf-ci-');
if($pdf===false)throw new RuntimeException('pdf_tempfile_failed');
try{
    file_put_contents($pdf,kb_pdf_fixture());
    if(voice_knowledge_pdf_extractor()===null){
        echo "SITE06_PDF_POPPLER_NOT_INSTALLED_OR_DISABLED\n";
    }else{
        [$kind,$content]=voice_knowledge_extract_file('genuine-sample.pdf',$pdf,(int)filesize($pdf));
        if($kind!=='pdf'||!str_contains($content,'OnTrack PDF Extract Test 2026'))
            throw new RuntimeException('site06_pdf_real_text_extraction_failed');
        echo "SITE06_PDF_REAL_POPPLER_EXTRACTION_PASS\n";
    }
}finally{
    @unlink($pdf);
}
