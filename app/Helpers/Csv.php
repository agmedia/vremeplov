<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 *
 */
class Csv
{
    
    /**
     * @var string|null
     */
    protected $path = null;

    /**
     * @var Worksheet
     */
    public $csv;
    
    
    /**
     * @param string|null $path
     *
     * @throws Exception
     */
    public function __construct(string $path = null, string $type = 'Csv')
    {
        $this->path = $path;
        $this->setFile($type);
    }
    
    
    /**
     * @return Worksheet
     */
    public function getCsv(): Worksheet
    {
        return $this->csv;
    }
    
    
    /**
     * @return Collection
     */
    public function collect(): Collection
    {
        return collect($this->csv->toArray());
    }
    
    
    /**
     * @param string $type
     *
     * @return $this
     * @throws Exception
     */
    public function setFile(string $type = 'Csv'): Csv
    {
        if ($this->path) {
            $reader    = IOFactory::createReader($type);
            $this->csv = $reader->load($this->path)->getActiveSheet();
        }
        
        return $this;
    }



    public function createExcelFile($filename, $rows, $keys = [], $formats = [])
    {
        // instantiate the class
        $doc = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $doc->getActiveSheet();

        // $keys are for the header row.  If they are supplied we start writing at row 2
        if ($keys) {
            $offset = 2;
        } else {
            $offset = 1;
        }

        // write the rows
        $i = 0;
        foreach($rows as $row) {
            $doc->getActiveSheet()->fromArray($row, null, 'A' . ($i++ + $offset));
        }

        // write the header row from the $keys
        if ($keys) {
            $doc->setActiveSheetIndex(0);
            $doc->getActiveSheet()->fromArray($keys, null, 'A1');
        }

        // get last row and column for formatting
        $last_column = $doc->getActiveSheet()->getHighestColumn();
        $last_row = $doc->getActiveSheet()->getHighestRow();

        // autosize all columns to content width
        for ($i = 'A'; $i <= $last_column; $i++) {
            $doc->getActiveSheet()->getColumnDimension($i)->setAutoSize(TRUE);
        }

        // if $keys, freeze the header row and make it bold
        if ($keys) {
            $doc->getActiveSheet()->freezePane('A2');
            $doc->getActiveSheet()->getStyle('A1:' . $last_column . '1')->getFont()->setBold(true);
        }

        // format all columns as text
        $doc->getActiveSheet()->getStyle('A2:' . $last_column . $last_row)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        if ($formats) {
            // if there are user supplied formats, set each column format accordingly
            // $formats should be an array with column letter as key and one of the PhpOffice constants as value
            // https://phpoffice.github.io/PhpSpreadsheet/1.2.1/PhpOffice/PhpSpreadsheet/Style/NumberFormat.html
            // EXAMPLE:
            // ['C' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00, 'D' => \PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_00]
            foreach ($formats as $col => $format) {
                $doc->getActiveSheet()->getStyle($col . $offset . ':' . $col . $last_row)->getNumberFormat()->setFormatCode($format);
            }
        }

        // write and save the file
        $writer = new Xlsx($doc);

        return $writer->save(storage_path($filename));
    }
}