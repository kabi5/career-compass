<?php
// Dependency-free, paginated text PDF export. No external requests or temporary personal files.
function pdf_wrapped_lines(string $text): array {
 // Conservative Helvetica advance widths, in ems; long words are split safely.
 $result=[];foreach(explode("\n",$text) as $paragraph){$line='';$width=0.0;foreach(str_split($paragraph) as $char){$em=0.60;if(str_contains(' il.,:;!\'|',$char))$em=0.28;elseif(str_contains('frtIj()[]',$char))$em=0.40;elseif(str_contains('mwMW%&@',$char))$em=1.05;elseif(ctype_upper($char))$em=0.80;elseif(ord($char)>126)$em=1.0;$advance=$em*11;
 if($width+$advance>500&&$line!==''){$space=strrpos($line,' ');if($space!==false&&$space>20){$result[]=substr($line,0,$space);$line=substr($line,$space+1);$width=0;foreach(str_split($line) as $existing)$width+=11*(str_contains('mwMW%&@',$existing)?1.05:(ctype_upper($existing)?0.80:0.60));}else{$result[]=$line;$line='';$width=0;}}
 $line.=$char;$width+=$advance;}$result[]=$line;}return $result;
}
function guidance_pdf(array $paragraphs): string {
 $lines=[];foreach($paragraphs as $line){$line=str_replace(["\r","\t"],['',' '],(string)$line);$converted=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line);foreach(pdf_wrapped_lines($converted===false?'':$converted) as $wrapped)$lines[]=$wrapped;$lines[]='';}
 $pages=array_chunk($lines,48);if(!$pages)$pages=[['Career Compass']];$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];
 foreach($pages as $i=>$page){$pageId=4+$i*2;$streamId=$pageId+1;$kids[]=$pageId.' 0 R';$stream="BT /F1 11 Tf 14 TL 44 795 Td\n";foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$safe=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$safe);$stream.='('.$safe.") Tj T*\n";}$stream.="ET\nBT /F1 9 Tf 44 28 Td (Career Compass - private guidance report - page ".($i+1).' of '.count($pages).") Tj ET\n";$objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R >>';$objects[$streamId]='<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream';}
 $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);$pdf.='trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
}
