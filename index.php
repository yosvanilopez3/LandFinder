<?php
/*************************************************************
        Utility functions to obtain data from page
*************************************************************/
  require('simple_html_dom.php');
  // obtain HTML from a given URL 
  function getPage ($url) {
      $useragent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36';
      $timeout= 300;
      $dir            = dirname(__FILE__);
      $cookie_file    = $dir . '/cookies/' . md5($_SERVER['REMOTE_ADDR']) . '.txt';
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_FAILONERROR, true);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
      curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
      curl_setopt($ch, CURLOPT_ENCODING, "" );
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
      curl_setopt($ch, CURLOPT_AUTOREFERER, true );
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout );
      curl_setopt($ch, CURLOPT_TIMEOUT, $timeout );
      curl_setopt($ch, CURLOPT_MAXREDIRS, 10 );
      curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
      curl_setopt($ch, CURLOPT_REFERER, 'http://www.google.com/');
      $content = curl_exec($ch);
      if(curl_errno($ch)) {
          echo 'error:' . curl_error($ch);
      }
      else {
          return $content;        
      }
      curl_close($ch);
  }

  // get a given class from HTML 
  function getClass($html, $class) {
      $dom = new DomDocument();
      @$dom->loadHTML($html);
      $finder = new DomXPath($dom);
      $nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]");
      return $nodes;
  }

  /*************************************************************
              Unit Conversion/Formatting Functions 
  *************************************************************/
  // calculate/find exchange rate GTQ to USD 
  $moneyConverterHTML = getPage("http://www.exchange-rates.org/Rate/GTQ/USD");
  preg_match("/1 Guatemalan Quetzal = ([[0-9.]*) US Dollars/", $moneyConverterHTML, $rate);
  $conversionRate = (float) $rate[1]; 

   // returns array with price in USD and GTQ. VALID tells whether the price seems valid or if there even exist a price for that given listing 
  function formatPrice($data, $GTQtoUSD) {
    $prices = [];
    $minimumReasonablePrice = 5000;
    // price is in Quetzal
    if (strpos($data, 'Q') !== false) {
      $price = (float) preg_replace("/[^\d.]/","", $data);
      $prices["USD"] = number_format($price*$GTQtoUSD, 2,".",",");
    }
    // price is in Dollars 
    else if (strpos($data, '$') !== false) {
      $price =  (float) preg_replace("/[^\d.]/","", $data);
      $prices["USD"] = number_format($price, 2,".",",");
    }
    $prices["valid"] = true;
    if (preg_replace("/[^0-9.]/","", $prices["USD"]) < $minimumReasonablePrice) {
      $prices["valid"] = false;
    }
    return $prices;
  }

  function formatDate($date) {
    $number = (int) preg_replace("/[^0-9.]/","", $date);
    if (strpos($date, ' d') !== false) {
      	if ($number !== 1) {
      		return $number." days ago"; 
      	}
		return $number." day ago";
    }
    else if (strpos($date, ' m') !== false) {
    	if ($number !== 1) {
    		return $number." months ago"; 
    	}
    	return $number." month ago"; 
    }
    else if (strpos($date, ' h') !== false) {
    	if ($number !== 1) {
    		return $number." hours ago"; 
    	}
    	return $number." hour ago";
    }
    return ""; 
  }
	function translateNumber($number) {
      $number = preg_replace("/[.]+/",",", $number);
      $partition = explode(",", $number);
      $finNumber = $partition[0]; 
      for ($i = 1; $i < count($partition); $i++) {
          if (strlen($partition[$i]) != 3) {
              $partition[$i] = ".".$partition[$i]; 
          }
          $finNumber = $finNumber.$partition[$i];
      }
      return  (int) $finNumber;
	}

  // returns array with size in meters and varas. VALID tells whether the size seems valid or if there even exist a size for that given listing 
  function formatSize($data, $totalPrice, $conversionRate) {
    $metersToVaras = 1.431153639;
    $minMeters = 80; 
    $maxMeters = 50000;
    $minVaras = $minMeters * $metersToVaras;
    $maxVaras = $maxMeters * $metersToVaras;
    $priority = 6; 
    foreach ($data as $size) {
      $number = preg_replace("/[a-z]+2/i","", $size);
      $number = preg_replace("/[^0-9.,]/","", $number);
      while (!is_numeric($number[0])) {
        $number = substr($number, 1); 
      }
      $number = translateNumber($number);
      if (preg_match("/\d[\d. ]*[metros2 .]*(x|\*|por|by)\s*\d[\d. ]*[metros2 .]*|(ancho|frente)/i", $size) AND $priority > 3) {
        $size = preg_replace("/[metros .]{2,}|[metros .]{2,}2/i","", $size);
        preg_match_all("/[0-9.]+/i", $size, $nums);
        $nums = $nums[0]; 
        $number = (float)$nums[0] * (float)$nums[1];
        if ($number > $minMeters AND $number < $maxMeters) {
          $sizes["meters"] = number_format($number, 2,".",",");
          $priority = 3; 
        }
      }
      else if ((strpos($size, 'Metros Cuadrados') !== false) AND $number > $minMeters AND $number < $maxMeters AND $priority > 0) {
          $sizes["meters"] =  number_format($number, 2,".",",");
          $priority = 0; 
      } 
      else if (preg_match("/[0-9., ]+m[etros2.]+/i", $size) || preg_match("/m[etros2 .]+[ :-=[0-9., ]+/i", $size)  AND $number > $minMeters AND $number < $maxMeters AND $priority > 2) {
          $sizes["meters"] =  number_format($number, 2,".",",");
          $priority = 2; 
      } 
      else if (preg_match("/[\d., ]{2,}v[aras2]+|v[aras2]+[\d., ]{2,}/i", $size) AND $number > $minVaras AND $number < $maxVaras AND $priority > 1) {
          $sizes["meters"] =  number_format($number/$metersToVaras, 2,".",",");
          //$sizes["varas"] = number_format($number, 2,".",",");
          $priority = 1; 
      } 
      else if ((strpos($size, 'por') !== false) AND $priority > 5) {
          if (preg_match("/\$/i", $size)) {
            $sizes["meters"] = number_format(translateNumber($totalPrice)/$number, 2,".",",");
            $priority = 5;
          } else if (preg_match("/Q/i", $size)) {
            $sizes["meters"] = number_format(translateNumber($totalPrice)/($number*$conversionRate), 2,".",",");
            $priority = 5;
          }
      }  
    }
    $sizes["valid"] = true;
    if ($priority == 6) {
      $sizes["valid"] = false; 
    }
    return $sizes; 
  }
  /*************************************************************
                   Build dictionary of listings 
  *************************************************************/
  function getPageCount() {
      $maxPageNum = 100000;
      $search = preg_replace('/\s+/', '%20', $_GET["search"]);
      $html = getPage('https://www.olx.com.gt/nf/search/'.$search.'/-p-'.$maxPageNum); 
      preg_match("/P-([0-9]*) - Guatemala/sim", $html, $lastPage);
      $numberOfPages = $lastPage[1];
	  if ($numberOfPages == $maxPageNum) {
        return 0;
      }
      return $numberOfPages;
  }

	function getMatchesOnPage($page) {
      $matches = [];
      if ($_GET["search"] != "") {
        $search = preg_replace('/\s+/', '%20', $_GET["search"]); 
        if ($page > 1) {
            $html = getPage('https://www.olx.com.gt/nf/search/'.$search.'/-p-'.$page);
            preg_match_all("/class=\"item (.*?)<\/li>/sim", $html, $matches);
        } else {
            $html = getPage('https://www.olx.com.gt/nf/search/'.$search);
            preg_match_all("/class=\"item ^(featuredad)(.*?)<\/li>/sim", $html, $matches);
        }
        return array_values($matches[0]);
      }
      return $matches;    
	}
// set up information for each of the properties 
  function getNextEntry($matches, $counter, $conversionRate) {
      $dom = new DOMDocument;
      @$dom->loadHTML($matches[$counter]);
      $links = $dom->getElementsByTagName('a');
      $link = "https:".($links[0]->getAttribute('href'));
      $html = getPage($link);
      // retrieve price from page
      $price = preg_replace('/\s+/', '', getClass($html, "price")[0]->textContent);
      preg_match("/[\$Q](\d*)/", $price, $price);
      $prices = formatPrice($price[0], $conversionRate); 
      // retreive the description and the title 
      $size = getClass($html, "item_partials_description_view")[0]->textContent.getClass($html, "keywords-view")[0]->textContent.getClass($html, "item_partials_optionals_view")[0]->textContent;
    preg_match_all("/.?.?((de)? (ancho|frente).*\d[\d,.]*[metros2 .]*.*(de)? (fondo|largo).*\d[\d,.]*$|\d[\d,.]*[metros2 .]*\s*(de)? (ancho|frente).*\d[\d,.]*[metros2 .]*\s*(de)? (fondo|largo)|\d[\d,.]+\d[\d,.]*\s*v[aras2]{1,}|(\d[\d,.]*\s*+[metros.]*\s*([x\*]|by|por)+\s*\d[\d.]*\s*)|\d+[\d,.]*\d+\s*m[etros2.]+|m[etros2.]+\s*[:-=]\s*[\d.,]+|v[aras2]{1,}\s*\d[\d,.]+\s*|Metros Cuadrados:\s*\d[\d,.]{2,}|[\$Q]\s*\d[\d,.]* por\s*v[aras2]*|por\s*v[aras2]*.*[\$Q]\s*\d[\d,.]*)/i", $size, $size);
      $sizes = formatSize($size[0], $prices["USD"], $conversionRate);
      // retreive image from page
      preg_match_all( '|<img.*?src=[\'"](.*?)[\'"].*?>|i', $html, $imgs);
      $image = $imgs[0][0]; 
      // retreive post date from page 
      $date = getClass($html, "date")[0]->textContent;
      $date = formatDate($date);
    // get id from link
      $id = preg_replace('/.*iid-/', '', $link);
      $pricePer = "Not Enough Information Provided"; 
      if ($prices["valid"] AND $sizes["valid"]) {
        $price = (float) (preg_replace("/[^\d.]/", "",  $prices["USD"]));
        $size = (float) (preg_replace("/[^\d.]/", "",  $sizes["meters"]));
        $pricePer = "$" . number_format($price/$size, 2,".",",");
      }
      if ($prices["valid"]) {
         $prices["USD"] = "$" . $prices["USD"];
      } else {
         $prices["USD"] = "Not Enough Information Provided";
      }
      if (!($sizes["valid"])) {
         $sizes["meters"] = "Not Enough Information Provided";
      }
      return array("image" => $image, "link" => $link, "price" => $prices["USD"], "size" => $sizes["meters"], "date" => $date, "pricePer" => $pricePer, "id" => $id);             
}
?>
<!DOCTYPE html>
<html> 
  <head> 
      <title>Gatsby</title>
      <meta charset="utf-8"/>
      <meta  http-equiv="Content-type" content="text/html" charset="utf-8">
      <meta name="viewport" content="width=device-width" initial-scale="1">
      <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
      <title>Bootstrap Sortable Tables</title>
      <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
      <link rel="stylesheet" type="text/css" href="bootstrap-sortable.css">
      <link rel="stylesheet" type="text/css" href="site.css" />
      <link href="//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet">
      <style>
         img {
          width: 100px;
          height: 100px; 
          }
         .navbar {
           width: 100%;
         }
         .navbar-brand {
           margin-left: 50px;
           font-size: 150%; 
         }
         #topPagination {
           margin-top: 100px; 
         }
      </style>
  </head>
  <body>
    <!-- Set up header bar and search input box  -->
    <div class="navbar navbar-default navbar-static-top" role="navigation">
        <div class="navbar-header">
            <a class="navbar-brand" rel="home" href="/" title="Gatsby">Gatsby</a>
        </div>
        <div class="col-sm-3 col-md-3 pull-right">
          <form class="navbar-form" role="search">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search" name="search" id="search">
                <div class="input-group-btn">
                    <button class="btn btn-default" type="submit"><i class="glyphicon glyphicon-search"></i></button>
                </div>
            </div>
          </form>
        </div>	
    </div>
    <!-- Set up page navigator bar on the top -->
    <div class="container text-center" id="topPagination">
        <ul class="pagination pagination-default">
           <?php
            // number of olx pages loaded per page 
          	$olxPagesPer = 3.0;
          	$olxPageCount = getPageCount();
          	$pages = ceil((float)$olxPageCount/$olxPagesPer); 
          	for ($i = 0; $i < $pages; $i++) {
              // generate the href link for each of the pages 
              if (preg_match("/page=[\d]*/i", $_SERVER['REQUEST_URI'])) {
                $url = 'http://' . $_SERVER['HTTP_HOST'] . preg_replace("/page=[\d]+/i", "page=".($i+1), $_SERVER['REQUEST_URI']);
              } else {
                $url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "&page=" . ($i+1);
              }
              //set the active page 
              if ((int) $_GET["page"] === 0 AND $i === 0) {
                echo  "<li class=\"active\"> <a href=\"" . $url . "\" >" .($i + 1). "</a></li >";
              }
              else if (($i + 1) === (int) $_GET["page"]) {
              	echo  "<li class=\"active\"> <a href=\"" . $url . "\" >" .($i + 1). "</a></li >";
              } else {
                echo  "<li> <a href=\"" . $url . "\" >" .($i + 1). "</a></li >";
              }
            }
          ?>
        </ul>  
      </div>
       <!-- Set up sortable table -->
       <div class='container'>
        <table class='table table-bordered table-striped sortable'>
            <thead>
                <tr>
                    <th style="width: 20%" data-mainsort="1"  data-firstsort="desc">Land</th>
                    <th style="width: 20%">Price</th>
                    <th style="width: 20%">Meters Squared</th>
                    <th style="width: 20%">Price per Square Meter</th>
                    <th style="width: 20%">Date Posted</th>
                </tr>
             </thead>
             <tbody>
              <?php 
               	  if ($_GET["page"] == "") {
                    $page = 1; 
                  } else {
                  	$page = (int) $_GET["page"];
                  }
                  $ids = [];
                  for ($j = 1; $j <= $olxPagesPer; $j++) {
                    $matches = getMatchesOnPage($j + ($page - 1)*$olxPagesPer); 
                    for ($i = 0; $i < count($matches); $i++) {
                       $match = getNextEntry($matches, $i, $conversionRate);
                       if (in_array($match["id"], $ids) == false) {
                         array_push($ids, $match["id"]);
                         $dateScale = 1; 
                         if (preg_match("/day/i", $match["date"])) {
                            $dateScale = 24; 
                         } else if (preg_match("/month/i", $match["date"])) {
                            $dateScale = 30*24;         
                         }
                         echo "<tr><td class=\"sorted\"> <a href=\""  . $match["link"] . "\" target=\"_blank\" >" . $match["image"] . "</a> </td><td data-value=\"" . preg_replace("/[^0-9.]/","", $match["price"])  . "\"> "  .   $match["price"] . "</td><td data-value=\"" . preg_replace("/[^0-9.]/","", $match["size"]) . "\"> " . $match["size"] . "</td><td data-value=\"" . preg_replace("/[^0-9.]/","", $match["pricePer"])  . "\"> "  . $match["pricePer"] . "</td><td data-value=\"" . preg_replace("/[^0-9]/","", $match["date"])*$dateScale . "\"> "  .  $match["date"] . "</td></tr>";
                        }
                    }
                 }
            ?> 
            </tbody>
        </table>
    </div>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.1/jquery.min.js"></script>
    <script src='moment.min.js'></script>
    <script src='bootstrap-sortable.js'></script>
    <script src='tablesort.js'>   
     
    </script>
    <!-- Set up page navigation bar on the bottom -->
    <div class="container text-center">
       <ul class="pagination">
          <?php
          // number of olx pages loaded per page 
          $olxPagesPer = 3.0;
          $olxPageCount = getPageCount();
          $pages = ceil((float)$olxPageCount/$olxPagesPer); 
          for ($i = 0; $i < $pages; $i++) {
            // generate the href link for each of the pages 
            if (preg_match("/page=[\d]*/i", $_SERVER['REQUEST_URI'])) {
              $url = 'http://' . $_SERVER['HTTP_HOST'] . preg_replace("/page=[\d]+/i", "page=".($i+1), $_SERVER['REQUEST_URI']);
            } else {
              $url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "&page=" . ($i+1);
            }
            //set the active page 
            if ((int) $_GET["page"] === 0 AND $i === 0) {
              echo  "<li class=\"active\"> <a href=\"" . $url . "\" >" .($i + 1). "</a></li >";
            }
            else if (($i + 1) === (int) $_GET["page"]) {
              echo  "<li class=\"active\"> <a href=\"" . $url . "\" >" .($i + 1). "</a></li >";
            } else {
              echo  "<li> <a href=\"" . $url . "\" >" .($i + 1). "</a></li >";
            }
          }
        ?>
      </ul> 
   </div>
  </body>
</html>

