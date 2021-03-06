<?php

    include_once("config.php");

    ob_start();
    include_once(INCLUDE_DIR. "/PHPExcel/PHPExcel/IOFactory.php");
    include_once(INCLUDE_DIR. "/Product.php");    
    include_once(INCLUDE_DIR. "/TaobaoApi.php");  
    include_once(INCLUDE_DIR. "/Category.php");
    include_once(INCLUDE_DIR. "/Curl.php");    
    ob_clean();

    $category_no = !empty($_REQUEST["category_no"]) ? $_REQUEST["category_no"] : 0;

    if( empty($category_no)  )
    {
        Output::error('分类不能为空！',array(), 1);
    }

    if( $_FILES["file"]["size"] <= 0 )
    {
        Output::error('请上传正确的文件',array(), 1);
    }

    $myMySQL = new MySQL();
    $myMySQL->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $myProduct = new Product($myMySQL);
    $myCategory = new Category($myMySQL);
    $myTaobaoApi = new TaobaoApi();
    $myCurl = new Curl();


    //判断这个分类是否是二级分类
    $categoryRow = $myCategory->getRow("*", "no = ".$category_no);

    if ( $_FILES["file"]["size"] > 0 )
    {
        $pathParts = pathinfo($_FILES["file"]["name"]);
        $filename = rand().uniqid().".".$pathParts["extension"];

        move_uploaded_file($_FILES["file"]["tmp_name"], LOGS_DIR ."/". $filename);
    }

    $myPHPExcel = PHPExcel_IOFactory::load(LOGS_DIR ."/". $filename);

    $sheet = $myPHPExcel->getSheet(0); 
 
    $highestRow = $sheet->getHighestRow(); 

    $highestColumm = $sheet->getHighestColumn(); 

    $mapArray = array('A' => 'product_id', 
                      'B' => 'title', 
                      'C' => 'pic_url', 
                      'D' => 'detail_url', 
                      'E' => 'shop_name', 
                      'F' => 'price', 
                      'G' => 'sale_num',
                      'H' => 'price_ratio', 
                      'I' => 'fee', 
                      'J' => 'sekerclean', 
                      'K' => 'taobao_short_link', 
                      'L' => 'taobao_link',
                      'M' => 'taobao_command',
                      'N' => 'coupon_num',
                      'O' => 'coupon_residue', 
                      'P' => 'coupon_title',
                      'Q' => 'coupon_start_date',
                      'R' => 'coupon_end_date',
                      'S' => 'coupon_link',
                      'T' => 'coupon_command',
                      'U' => 'coupon_short_link',
                      'V' => 'is_marketing');


    $count = 0;
    $errorList = array();
    for ($row = 2; $row <= $highestRow; $row++)
    {
        $dataArray = array();

        $flag_add = true;

        for ($column = 'A'; $column <= $highestColumm; $column++) 
        {
            $value = $sheet->getCell($column.$row)->getValue();

            if( empty($mapArray[$column]) || $mapArray[$column] =='no' )
            {   
                continue;
            }

            if( $mapArray[$column] == 'price' ||  $mapArray[$column] == 'fee' )
            {
               $value = $value * 100;
            }

            //判断商品是否存在
            if( $mapArray[$column] == 'product_id' )
            {
                $product = $myProduct->getRow("*", "product_id = '".$value."'");

                if( !empty($product) )
                {
                    $data = array();
                    $data['product_id'] = $value;
                    $data['index'] = $row;

                    //$errorList[] = $data;

                    $flag_add = false;
                }
            }

            //修改价格
            //满75元减10元，5元无条件券

            if( $mapArray[$column] == 'coupon_title' )
            {
                $list = explode("减", $value);
                if( !empty($list[1]) )
                {
                   $coupon_save_price = str_replace("元", "", $list[1]);

                   $coupon_save_price = trim($coupon_save_price);

                   $dataArray['coupon_save_price'] = $coupon_save_price;
                }
                else
                {
                    $coupon_save_price = str_replace("元无条件券", "", $value);

                    $coupon_save_price = trim($coupon_save_price);

                    $dataArray['coupon_save_price'] = $coupon_save_price;
                }
            }

            $dataArray[ $mapArray[$column] ] = $value;
        }


        if( !empty($categoryRow) )
        {
            if( $categoryRow['parent_no'] != 0 )
            {
                $dataArray['category_no1'] = $categoryRow['parent_no'];
                $dataArray['category_no2'] = $category_no;
            }
            else
            {
                $dataArray['category_no1'] = $category_no;
            }
        }

        $dataArray['add_time'] = 'now()';
        $dataArray['update_time'] = 'now()';
        $dataArray['category_no'] = $category_no;
        $dataArray['is_online'] = 'Y';

        //获取商品详情
        $productInfo = $myTaobaoApi->getProductInfo($dataArray['product_id']);
        $productInfo['shop_info'] = $myTaobaoApi->getFavoritesInfo($dataArray['shop_name']);
        $product_info_json = json_encode($productInfo, JSON_UNESCAPED_UNICODE);

        $dataArray['product_info'] = $product_info_json;

        if( $flag_add )
        {
            $myProduct->addRow($dataArray);
            ++$count;
        }
        else
        {
            //更新
            $dataArray['update_time'] = 'now()';
            $myProduct->update($dataArray, "product_id = '".$dataArray['product_id']."'");

        }
    }

    $text = '';
    if( !empty($errorList) )
    {
        foreach ($errorList as $index => $item) 
        {
            $text .= "商品id：".$item['product_id'];
        }
    }

    if( empty($errorList) )
    {
       Output::succ('导入成功',array());
    }
    else
    {
        Output::error($text."导入失败，有重复的商品id",array(), 1);
    }

?>