<?php

    include_once("config.php");

    include_once("session_check.php");

    $myProduct = new Product($myMySQL);
    $myUserCollect = new UserCollect($myMySQL);


    $myTemplate = new Template(TEMPLATE_DIR ."/user_collect_product.html");

    include_once("common.inc.php");

    //title
    $dataArray = array();
    $dataArray["{site_title}"] = $systemConfigRow['site_title'];
    $myTemplate->setReplace("other_data", $dataArray);

    $page_size = 5;

    $condition = "user_no = ".$_SESSION['user_no']." AND product_no != 0 ";
  
    $total_page = $myUserCollect->getPageCount($page_size, $condition);

    $rows = $myUserCollect->getPage("*", 1, $page_size, $condition ." ORDER BY add_time DESC");

    if( empty($rows) )
    {
        $myTemplate->setReplace("empty", array());
    }

    for($i = 0; isset($rows[$i]); $i++)
    {
        $product_no = $rows[$i]['product_no'];

        $productRow = $myProduct->getRow("*", "no = $product_no");
        
        $dataArray = $myProduct->getData($productRow);

        $myTemplate->setReplace("product", $dataArray);

        $labelList = explode(",", $rows[$i]['label']);

        for($j = 0; isset($labelList[$j]); $j++)
        {
            if( empty($labelList[$j]) )
            {
                continue;
            }

            $dataArray = array(); 
            $dataArray['{label}'] = $labelList[$j];
            $dataArray['{color}'] = 'color'.($j+1);

            $myTemplate->setReplace("label_list", $dataArray, 2);
        }
    }

    $dataArray = array();
    $dataArray["{total_page}"] = $total_page;

    $myTemplate->setReplace("page", $dataArray);

    $myTemplate->process();
    $myTemplate->output();


?>