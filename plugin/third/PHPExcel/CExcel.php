<?php
define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');

class CExcel{
   
    private $phpexcel_dir = 'PHPExcel';

    /*
    *
    *@type --- str(Excel5|Excel2007|...)
    *
    */
    public function LoadReader($type='Excel5')
    {
        //require_once '../Classes/PHPExcel/IOFactory.php';
        $file_IOFactory = __DIR__ . '/' .$this->phpexcel_dir . '/IOFactory.php';
        require_once $file_IOFactory;
        $Reader = PHPExcel_IOFactory::createReader($type);
        return $Reader;
    }
    /*
    *
    *@type --- str(Excel5|Excel2007|html|pdf)
    *
    */
    public function LoadObject($type='Excel5')
    {
        require_once __DIR__ . '/PHPExcel.php';
        $object = new PHPExcel();
        return $object;
        // $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    }
    
    /*
    * desc: 获取表格数据
    *
    *@mode --- str [Excel5|Excel2007]
    *
    */
    public function getData($xlsfile, $mode="auto")
    {
        if('auto' == $mode){
            $ext = substr($xlsfile, strrpos($xlsfile,'.'));
            $xlstype = '.xls'==$ext?'Excel5':'Excel2007';
        }else{
            $xlstype = $mode;
        }
        $Reader = $this->LoadReader($xlstype);
        if(!$Reader)return false;
        
        $objPHPExcel = $Reader->load($xlsfile);
        $_dataArr = array();
        foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
            $sheet = $worksheet->getTitle();
            // echo 'Worksheet - ' , $worksheet->getTitle() , EOL;

            $row_nulls = 0; //标志整行为空的数量(连续)
            $_rowArr   = array();
            foreach ($worksheet->getRowIterator() as $row) {
                $_sn = $row->getRowIndex();
                // echo '    Row number - ' , $row->getRowIndex() , EOL;

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if it is not set
                $_r = array(); //一行
                $col_nulls = 0; //标志整行为空的数量(连续)
                foreach ($cellIterator as $cell) {
                    if (!is_null($cell)) {
                        $field = $cell->getColumn();
                        $xy    = $cell->getCoordinate(); //坐标值
                        // print_r($xy);
                        // echo "\n";
                        // print_r($cell->absoluteCoordinate());
                        // echo "\n";
                        // print_r(get_class_methods($cell));exit;
                        $value = $cell->getCalculatedValue();
                        // echo '        Cell - ' , $field , ' - ' , $value , EOL;
                        $_r[$field] = $value;
                        if(empty($value)){
                            $col_nulls++;
                            if($col_nulls >= 10){//连续10个值为空，那么认为之后的都为空
                                $_r = array_slice($_r, 0, -10);
                                break;
                            }
                        }else{
                            $col_nulls = 0;
                        }
                    }
                }
                // print_r($_r);exit;
                if($this->_is_empty_array($_r)){
                    $row_nulls++;
                    if($row_nulls >= 10)break; //连续10行都为空则不再往下读数据(可能都是空值)
                    continue;
                }else{
                    $row_nulls = 0;
                }
                $_rowArr[$_sn] = $_r;
            }
            $_dataArr[$sheet] = $_rowArr;
        }
        // print_r($_dataArr);exit;
        return $_dataArr;
    }

    /*
    * desc: 获取表格数据(考虑合并单元格)
    *
    *@mode --- str [Excel5|Excel2007]
    *
    */
    public function getMData($xlsfile, $mode="auto")
    {
        $dataArr = $this->getData($xlsfile, $mode="auto");
        if(!$dataArr)return $xlsfile;
        $dataArr = array_values($dataArr);
        $dataArr = $dataArr[0];
        $newArr  = array();
        foreach($dataArr as $sn => $row){
            $row = array_values($row);
            
            $new = array();
            for($i=0,$len=count($row); $i<$len; $i++){
                $val = $row[$i];
                if($i == $len-1){
                    if(empty($val))break;
                    $cood = "$i,$i";
                    $new[$cood] = $val;
                    break;
                }
                $cood = $i;
                for($j=$i+1; $j<$len; $j++){
                    if(trim($row[$j]) || $j==($len-1)){
                        $cood .= ",".($j-1);
                        $new[$cood] = $val;
                        $i = ($j-1);
                        break;
                    }
                }
                // break;
            }
            $newArr[$sn] = $new;
            // print_r($row);
            // print_r($new);
        }
        return $newArr;

    }

    private function _is_empty_array(&$arr)
    {
        if(empty($arr))return true;
        foreach($arr as $v){
            if(!empty($v)) return false;
        }
        return true;
    }

    /**
     * 将数字转Excel列编号序列已测试1-
     * 如 [index,1] = A
     *    [index,26] = Z
     *    [index,27] = AA
     * @param index
     * @return
    */
    public function excelColumn($index)
    {
        $value = $index;
        $A = 65;
        $Z = 90;
        $C = 26;
        $column = array();
        if($value > $C){
            //余数
            $remainder = $value % $C;
            //倍数
            $b = $value / $C;
            if($remainder!=0){
                $b ++;
            }
            $column = array();
            $column[0] = chr($b-2 + $A);
            $column[1] = chr(($remainder != 0 ? $remainder - 1 : $C-1) + $A);
        }else{
            return chr($A + $value-1)."";
        }
        return implode('', $column);
    } 

    /*
    * desc: 写表格数据
    *@dataArr --- array(
    *           'header' => array(), //头信息
    *           'body'   => array(
    *               'sheet1' => array(
    *                   'row-sn' => array(
    *                           'column-letter' => array(
    *                               'value' => value, -     -- string
    *                               'bold'  => true|false   --- bool
    *                               'italic' => true|false  --- bool
    *                               'color'  => FF008000    --- FFRRGGBB
    *                               'format' => 
    *                               'align'  => 
    *                           ),
    *                       )
    *   
    *                   )    
    *              )
    *           )
    *
    *@mode    --- str [Excel5|Excel2007]
    *
    */
    public function setData($xlsfile, $dataArr=array(), $mode="auto")
    {
        if('auto' == $mode){
            $ext = substr($xlsfile, strrpos($xlsfile,'.'));
            $xlstype = '.xls'==$ext?'Excel5':'Excel2007';
        }else{
            $xlstype = $mode;
        }
        $object = $this->LoadObject($xlstype);
        if(!$object)return false;

        $header = isset($dataArr['header'])?$dataArr['header']:null;
        $body   = isset($dataArr['body'])?$dataArr['body']:null;
        if($body){
            foreach($body as $sheet => $dataArr){
                isset($index)?$index++:($index=0);
                $object->setActiveSheetIndex($index);
                $object->getActiveSheet()->setTitle($sheet);
                foreach($dataArr as $row_sn => $row){
                    foreach($row as $col_sn => $cell){
                        $col_letter = $this->excelColumn($col_sn);
                        //cell是一个数组
                        $value = $cell['value'];
                        $value = str_replace("\r", "\n", $value);

                        $objRichText = new PHPExcel_RichText();
                        // $objRichText->createText($value);
                        $objPayable = $objRichText->createTextRun($value);
                        if(!empty($cell['bold']))$objPayable->getFont()->setBold(true);
                        if(!empty($cell['italic']))$objPayable->getFont()->setItalic(true);
                        if(isset($cell['color']) && 6==strlen($cell['color'])){
                            $objPayable->getFont()->setColor(new PHPExcel_Style_Color('FF'.$cell['color']));
                        }
                        if(!empty($cell['autosize'])){
                            $object->getActiveSheet()->getColumnDimension($col_letter)->setAutoSize(true);
                        }
                        // print_r(get_class_methods($objPayable->getFont()));exit;
                        // echo "$col_letter($col_sn).$row_sn =======";
                        // $objActSheet ->getStyle('A1')->getAlignment()->setShrinkToFit(true);//字体变小以适应宽
                        // $objActSheet ->getStyle('A1')->getAlignment()->setWrapText(true);//自动换行
                        $object->getActiveSheet()->setCellValue($col_letter.$row_sn, $objRichText);
                        // $object->getActiveSheet()->getStyle($col_letter.$row_sn)->getAlignment()->setShrinkToFit(true);//字体变小以适应宽
                        // $object->getActiveSheet()->getStyle($col_letter.$row_sn)->getAlignment()->setWrapText(true);//自动换行
                    }
                    // $object->getActiveSheet()->getRowDimension($row_sn)->setRowHeight(50);
                    // print_r(get_class_methods($object->getActiveSheet()->getRowDimension($row_sn)));exit;
                }
                // $object->getActiveSheet()->getColumnDimension('T')->setAutoSize(true);
                // $object->getActiveSheet()->getColumnDimension('U')->setAutoSize(true);
                // $object->getActiveSheet()->getColumnDimension('V')->setAutoSize(true);
            }
        }
        
        /*
        $object->getProperties()->setCreator("cty")
                             ->setLastModifiedBy("Maarten Balliauw")
                             ->setTitle("Office 2007 XLSX Test Document")
                             ->setSubject("Office 2007 XLSX Test Document")
                             ->setDescription("Test document for Office 2007 XLSX, generated using PHP classes.")
                             ->setKeywords("office 2007 openxml php")
                             ->setCategory("Test result file");

        $object->getDefaultStyle()->getFont()->setName('Arial')
                                          ->setSize(10);

        // Add some data, resembling some different data types
        $object->getActiveSheet()->setCellValue('A1', 'String')
                                      ->setCellValue('B1', 'Simple')
                                      ->setCellValue('C1', 'PHPExcel');

        $object->getActiveSheet()->setCellValue('A2', 'String')
                                      ->setCellValue('B2', 'Symbols')
                                      ->setCellValue('C2', '!+&=()~§±æþ');

        $object->getActiveSheet()->setCellValue('A3', 'String')
                                      ->setCellValue('B3', 'UTF-8')
                                      ->setCellValue('C3', 'Создать MS Excel Книги из PHP скриптов');

        $object->getActiveSheet()->setCellValue('A4', 'Number')
                                      ->setCellValue('B4', 'Integer')
                                      ->setCellValue('C4', 12);

        $object->getActiveSheet()->setCellValue('A5', 'Number')
                                      ->setCellValue('B5', 'Float')
                                      ->setCellValue('C5', 34.56);

        $object->getActiveSheet()->setCellValue('A6', 'Number')
                                      ->setCellValue('B6', 'Negative')
                                      ->setCellValue('C6', -7.89);

        $object->getActiveSheet()->setCellValue('A7', 'Boolean')
                                      ->setCellValue('B7', 'True')
                                      ->setCellValue('C7', true);

        $object->getActiveSheet()->setCellValue('A8', 'Boolean')
                                      ->setCellValue('B8', 'False')
                                      ->setCellValue('C8', false);

        $dateTimeNow = time();
        $object->getActiveSheet()->setCellValue('A9', 'Date/Time')
                                      ->setCellValue('B9', 'Date')
                                      ->setCellValue('C9', PHPExcel_Shared_Date::PHPToExcel( $dateTimeNow ));
        $object->getActiveSheet()->getStyle('C9')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_YYYYMMDD2);

        $object->getActiveSheet()->setCellValue('A10', 'Date/Time')
                                      ->setCellValue('B10', 'Time')
                                      ->setCellValue('C10', PHPExcel_Shared_Date::PHPToExcel( $dateTimeNow ));
        $object->getActiveSheet()->getStyle('C10')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_TIME4);

        $object->getActiveSheet()->setCellValue('A11', 'Date/Time')
                                      ->setCellValue('B11', 'Date and Time')
                                      ->setCellValue('C11', PHPExcel_Shared_Date::PHPToExcel( $dateTimeNow ));
        $object->getActiveSheet()->getStyle('C11')->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_DATE_DATETIME);

        $object->getActiveSheet()->setCellValue('A12', 'NULL')
                                      ->setCellValue('C12', NULL);

        $objRichText = new PHPExcel_RichText();
        // $objRichText->createText('你好 ');

        $objPayable = $objRichText->createTextRun('你 好 吗？');
        $objPayable->getFont()->setBold(true);
        $objPayable->getFont()->setItalic(true);
        $objPayable->getFont()->setColor( new PHPExcel_Style_Color(PHPExcel_Style_Color::COLOR_DARKGREEN));

        // $objRichText->createText(', unless specified otherwise on the invoice.');

        $object->getActiveSheet()->setCellValue('A13', 'Rich Text')
                                      ->setCellValue('C13', $objRichText);

                                      
        $object->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
        $object->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);

        // Rename worksheet
        $object->getActiveSheet()->setTitle('Datatypes');


        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $object->setActiveSheetIndex(0);


        // Save Excel 2007 file
        $callStartTime = microtime(true);
        */

        $objWriter = PHPExcel_IOFactory::createWriter($object, 'Excel2007');
        $objWriter->save($xlsfile);
        return $xlsfile;
    }
}
