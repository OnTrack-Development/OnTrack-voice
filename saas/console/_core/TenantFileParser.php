<?php
declare(strict_types=1);

/** Decode tenant-owned CSV, TXT or XLSX without executing workbook formulas/macros. */
function tenant_file_rows(string $tmp,string $filename): array {
    $size=filesize($tmp);
    if($size===false||$size>1024*1024||$size<=0)throw new InvalidArgumentException('invalid_file_size');
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    if(!in_array($ext,['csv','txt','xlsx'],true))throw new InvalidArgumentException('file_type');
    if($ext!=='xlsx'){
        $lines=file($tmp,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
        if($lines===false)throw new InvalidArgumentException('cannot_read_file');
        $rows=[];foreach($lines as $line){
            $line=trim(preg_replace('/^\xEF\xBB\xBF/','',$line));
            if($line==='')continue;
            if(str_contains($line,',')||str_contains($line,';')){
                $parts=str_getcsv($line,str_contains($line,';')&&!str_contains($line,',')?';':',');
                $rows[]=['name'=>(string)($parts[0]??''),'phone'=>(string)($parts[1]??'')];
            }else{$rows[]=['name'=>'','phone'=>$line];}
            if(count($rows)>2000)throw new InvalidArgumentException('too_many_contacts');
        }
        return $rows;
    }
    if(!class_exists('ZipArchive')||!class_exists('SimpleXMLElement'))throw new RuntimeException('xlsx_parser_extension_missing');
    $zip=new ZipArchive();
    if($zip->open($tmp)!==true)throw new InvalidArgumentException('xlsx_invalid_archive');
    try {
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
        if($sheet===false||strlen($sheet)>1500000)throw new InvalidArgumentException('xlsx_sheet_unavailable');
        $strings=[];
        $shared=$zip->getFromName('xl/sharedStrings.xml');
        if($shared!==false) {
            if(strlen($shared)>1000000)throw new InvalidArgumentException('xlsx_strings_too_large');
            $xml=simplexml_load_string($shared,'SimpleXMLElement',LIBXML_NONET);
            if($xml===false)throw new InvalidArgumentException('xlsx_strings_invalid');
            foreach($xml->si as $si){
                $s=(string)$si->t;
                foreach($si->r as $r)$s.=(string)$r->t;
                $strings[]=$s;
            }
        }
        $xml=simplexml_load_string($sheet,'SimpleXMLElement',LIBXML_NONET);
        if($xml===false)throw new InvalidArgumentException('xlsx_sheet_invalid');
        $rows=[];
        foreach($xml->sheetData->row as $row) {
            $cells=[];
            foreach($row->c as $cell){
                $ref=(string)$cell['r'];
                if(!preg_match('/^([A-Z]{1,3})[0-9]{1,7}$/D',$ref,$match))continue;
                $value=(string)$cell->v;
                if((string)$cell['t']==='s')$value=(string)($strings[(int)$value]??'');
                if((string)$cell['t']==='inlineStr')$value=(string)$cell->is->t;
                if((string)$cell['t']==='e' || $cell->f->count()>0)continue; // never evaluate formulas
                $cells[$match[1]]=$value;
            }
            $rows[]=['name'=>(string)($cells['A']??''),'phone'=>(string)($cells['B']??'')];
            if(count($rows)>2000)throw new InvalidArgumentException('too_many_contacts');
        }
        return $rows;
    }finally{$zip->close();}
}
