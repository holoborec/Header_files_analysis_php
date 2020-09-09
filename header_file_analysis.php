<?php

#CHA:xholec07

header('Content-type: text/plain; charset=utf-8'); //nastavenie kodovania na utf-8


//funkcia vytlaci napovedu
function print_help(){
echo "Napoveda pre skript na analyzu funkcii v hlavickovych suboroch jazyka C. \n";
echo "Skript je mozne spustit s nasledovnymi parametrami: \n";
echo "--help: zobrazi napovedu \n";
echo "--input=NIECO: kde nieco reprezentuje subor alebo adresar\n";
echo "--output=subor: kde subor je vystupny subor pre zapis. Implicitne stdout\n";
echo "--pretty-xml=k: odsadi vystup o k medzier, implicitne o 4\n";
echo "--no-inline: preskakuje funkcie so specifikatorom inline\n";
echo "--max-par=n: nevypise funkcie s n a viac parametrami\n";
echo "--no-duplicates: v pripade definicie aj deklaracie funkcie vypise funkciu len jedenkrat\n";
echo "--no-whitespaces: odstrani prebytocne biele znaky vo funkciach";  
exit (0);                                                              
}

//Pole, kde si ukladam, ci bol dany parameter zadany 
$prepinace = array(
  "help" => false,
  "input" => false, 
  "output" => false, 
  "pretty" => false, 
  "no-inline" => false,
  "maxPar" => false, 
  "noDuplicates" => false,
  "removeWhitespaces" => false, );

//dodatocne premenne nesuce informacie od uzivatela  
$inputFile = "./";
$outputFile = "";
$k = 0;
$n = 0;
$files = array();

//zadane nekompatibilne parametre, alebo viacnasobne zadanie jedneho parametra
//vedie na chybu  
function chyba_prepinace(){
  fprintf(STDERR, "Chybne zadane prepinace. Pre napovedu pouzite prepinac --help.\n");
  exit(1);
}

/* Funkcia analyzuje parametre predane na prikazovom riadku. 
** Naplni pole prepinace a premenne suvisiace so vstupom. 
** Testuje viacnasobnost predania jedneho parametru.
*/
  
function get_args($argc, $argv, &$prepinace, &$inputFile, &$outputFile, &$k, &$n){

  foreach($argv as $arg){
  
    if(!strcmp($arg, "--help")) {
      if($argc == 2){
        print_help();
      }
      else{
        chyba_prepinace();
      }
    }
    
    else if(!strncmp($arg, "--input=", 8)){
      if ($prepinace["input"] == true){
        chyba_prepinace();
      }
      else{
        $prepinace["input"] = true;
        $inputFile = mb_substr($arg, 8, mb_strlen($arg));
        if($inputFile == "" || $inputFile == "."){
          $inputFile = "./"; //nezadany adresar => aktualny adresar
        }
      }
      
      
    }
    
    else if(!strncmp($arg, "--output=", 9)){
      if($prepinace["output"] == true || mb_strlen($arg) <= 9){
        chyba_prepinace();
      }
      $prepinace["output"] = true;
      $outputFile = mb_substr($arg, 9, mb_strlen($arg));    
    }
    
    else if(!strncmp($arg, "--pretty-xml", 12)) {
      if($prepinace["pretty"] == true){
        chyba_prepinace();
      }
      $prepinace["pretty"] = true;
      if(!strncmp($arg, "--pretty-xml=", 13)){
        $k = strval(mb_substr($arg, 13, mb_strlen($arg)));
      if(!is_numeric($k)){
        chyba_prepinace();
      }
      if($k == 0){
        $k = 4; //implicitna hodnota pri zadani prettyxml parametru
      }
      }
      
    //  fprintf(STDOUT, $k);    
    }
    
    else if(!strcmp($arg, "--no-inline")){
      if($prepinace["no-inline"] == true){
        chyba_prepinace();
      }
      $prepinace["no-inline"] = true;
    }
    
    else if(!strncmp($arg, "--max-par=", 10)){
      if($prepinace["maxPar"] == true || mb_strlen($arg) <=10){
        chyba_prepinace();
      }
      $prepinace["maxPar"] = true;
      $n = mb_substr($arg, 10, mb_strlen($arg));
      if(!is_numeric($n)){
        chyba_prepinace();
      } 
    }
    
    else if(!strcmp($arg, "--no-duplicates")){
      if($prepinace["noDuplicates"] == true){
        chyba_prepinace();      
      }
      $prepinace["noDuplicates"] = true;    
    }
    
    else if(!strcmp($arg, "--remove-whitespace")){
      if($prepinace["removeWhitespaces"] == true){
        chyba_prepinace();
      }
      $prepinace["removeWhitespaces"] = true;
    }
    else if(preg_match('/^--/', $arg)){
      chyba_prepinace();
    }
  } 
}

/* 
Funkcia dostava na vstup vstupny subor zadany uzivatelom, 
pripadne implicitne aktualny adresar. Vsetky spravne subory pridava do pola files. 
Prehladava pripadny zadany adresar, a to aj rekurzivne adresare umiestnene v nom. 
Funkcia vracia pole vsetkych hlavickovych suborov.
*/


function files_management($inputFile, $files){
  
  if($inputFile == "./"){
    $inputFile = ".";
  }        
  rtrim($inputFile, '\\/'); //odstranenie prebytocneho lomitka na konci 
  $tmp_files = array();
  
  if(is_file($inputFile)){ //je to citatelny subor s priponou h
    if(is_readable($inputFile)){
      if(pathinfo($inputFile, PATHINFO_EXTENSION) == 'h'){
        array_push($files, $inputFile); //pridame ho do pola
      }  
    }
  }
  elseif(is_dir($inputFile)){  //jedna sa o adresar
    $tmp_files = scandir($inputFile); //nacitame si vsetky subory v adresari
    foreach($tmp_files as $tmp){
      if($tmp != "." && $tmp != ".."){  //vynechame aktualny a nadradeny adresar
        if(is_dir($inputFile.'/'.$tmp.'/')){  //rekurzivne zanorenie v pripade, ze tu mame dalsi adresar
          if(is_readable($inputFile.'/'.$tmp)){
            files_management($inputFile.'/'.$tmp.'/', $files);
          }
          else{ //v adresari nemame pravo na citanie => chyba
            fprintf(STDERR, "Adresar nie je mozne prehladavat.");
            exit(2);  
          }             
        }
        elseif(is_file($inputFile.'/'.$tmp)){ //citatelny hlavickovy subor pridame do pola
          if(is_readable($inputFile.'/'.$tmp)){
            if(pathinfo($inputFile.'/'.$tmp, PATHINFO_EXTENSION) == 'h'){
              array_push($files, $inputFile.'/'.$tmp);
            }
          }
        }
        else{
          fprintf(STDERR, "Zadana cesta nie je ani subor, ani adresar.");
          exit(2);
        }
      }
    }  
    
  
  }
  else{
    fprintf(STDERR, "Zadana cesta nie je ani subor, ani adresar.");
    exit(2);
  }             
    
    return $files;
}

/*
  Funkcia otvori subor predany parametrom, 
  nacita ho do stringu content a vola filtrovaciu funkciu.
  Vystupom je string content obsahujuci len uzitocne informacie - 
  funkcie v hlavickoych suboroch, s odstranenymi telami, makrami, typedefmi...
*/


function file_content($f){

    if(file_exists($f)){
      if(is_readable($f)){
        if($file = @fopen($f, "r") == FALSE){
          fprintf(STDERR, "Nie je mozne otvorit subor.\n");
          exit(2);    
        } 
    
        $content = file_get_contents($f);
        $functions = content_filter($content);
        return $functions;
      }
       
    }
    
     
}
  

/*
  Funkcia odstranuje z nacitaneho suboru prebytocne riadky, nechava len tie, 
  kde je deklaracia/definicia funkcie. Odstranuje makra, bloky kodu, komentare.
  Vstupom je obsah hlavickoveho suboru.
  Vystupom je pole poli, kde v poli na pozicii 0 su definicie funkcii, 
  na pozicii 1 navratove typy funkcii, na pozicii 2 pole nazvov funkcii, 
  na pozicii 3 zoznam parametrov funkcii. 
*/

function content_filter($content){
	$filtered = "";
	$content_size = strlen($content);
	$line_comment = false;
  $block_comment = false;
	$macro = false;
	$inside_function = 0;

	for ($i=0; $i < $content_size; $i++){ 
		if($content[$i] == "/" && ($i+1) <= $content_size && $content[$i+1] == "/"
			 && !$block_comment
			 && !$macro){
			$line_comment = true;
		}
		else if($content[$i] == "\n" && $line_comment){
				$line_comment = false;
		}
		else if($content[$i] == "\n" && $macro){
				$macro = false;
		}
		else if($content[$i] == "/" && ($i+1) <= $content_size && $content[$i+1] == "*"
			      && !$line_comment){
			$block_comment = true;
		}
		else if($content[$i] == "*" && ($i+1) <= $content_size && $content[$i+1] == "/"
			      && !$line_comment){
			$block_comment = false;
			$i++;
			if(!$inside_function){
				continue;
			}
		}
		else if($content[$i] == "{"
			     && !$block_comment
			     && !$line_comment
			     && !$macro){
			$inside_function++;
		}
		else if($content[$i] == "}"
			      && !$block_comment
			      && !$line_comment
			      && !$macro){
			$inside_function--;
			if(!$inside_function){
				continue;
			}
		}
		
		else if($macro && $content[$i] == "/" && ($i+1) <= $content_size
			      && $content[$i+1] == "\n"){
			$i++;
			continue;
		}
		
		else if(($content[$i] == "#")
			       && !$line_comment
			       && !$block_comment
			       && !$inside_function){
			$macro = true;
		}

		if(!$line_comment && !$block_comment && !$macro && !$inside_function)
		{
			$filtered .= $content[$i];
		}
	}  
          
    
    $filtered = preg_replace('/(typedef)?\s+(struct)\s+(\w+)?/u', '', $filtered); //odstranenie typedef struct
    $filtered = preg_replace('/(enum)\s+(\w+)?(;)?/u', '', $filtered);  //odstranenie enum
    $filtered = preg_replace('/(typedef)(\s)*/', '', $filtered); //typedef enum - z enumu uz nic neostane 
                                                                //po predchadzajucom riadku     
    
      
    /*
    Regularny vyraz zabezpecujuci rozdelenie casti funckii do jednotlivych poli. 
    Blizsia definicia vid. popis funkcie
    */
    preg_match_all("/((?:[\w\*]+\s+)+)(\w+)\s*\(((?:.|\n)*?)\)/", $filtered, $functions);
    
    /*     POMOCNA TLAC
    for($i = 0; $i < count($functions[0]); $i++){
    fprintf(STDOUT, "iteracia = ".$i."\n");
    fprintf(STDOUT, "pozicia[0]".$functions[0][$i]."\n");
    fprintf(STDOUT, "pozicia[1]".$functions[1][$i]."\n");
    fprintf(STDOUT, "pozicia[2]".$functions[2][$i]."\n");
    fprintf(STDOUT, "pozicia[3]".$functions[3][$i]."\n");
    
    } */
                                                              
    return $functions;      
    
}

//xml hlavicka tvorba
function xml_head($prepinace, &$xml){
  $newline = "";
  if($prepinace["pretty"]){
    $newline = "\n"; 
  }
  $xml .= '<?xml version="1.0" encoding="utf-8"?>'.$newline;
}

/*
  Funkcia zabezpecujuca tlace uvodneho tagu <functions>
*/

function xml_start_functions($file, $k, &$xml, $prepinace, $inputFile){
  
  $newline = "";
  if($prepinace["pretty"]){
    $newline = "\n"; 
  }
  if(is_file($file)){
    $file = "";
  }
  elseif(is_dir($file) && $prepinace["input"]){
    $file = $inputFile;
    if (!(preg_match('/(\/)$/', $file))){ //pokial na konci nie je / v nazve adresara - doplnime
      $file  .= "/";
    }
  }
 // else{
 //   $file = "./";
 // }      
  
  $xml .= "<functions dir=\"$file\">".$newline;
  
}


//tato funkcia zabezpecuje tlac koncoveho tagu <functions>
function xml_end_functions($dir, $k, &$xml, $prepinace){
  
  $newline = "";
  if($prepinace["pretty"]){
    $newline = "\n"; 
  }
  
  $xml .= "</functions>".$newline;
}

/*
  Tato funkcia zabezpecuje tlac tela XML prikazov - tagy <function> a <param>.
  Respektuje volbu uzivatela s prepinacmi prettyxml pripadne noWhitespaces.
  Rozdeluje jednotlive parametre do pola. Neuvadza funkcie ktore porusuju podmienku 
  maximalneho poctu parametrov. Prechadza parametre funkcie, odstranuje z nich 
  ich meno a tlaci ich navratovy typ. Uchovava sa a tlaci sa informacia 
  taktiez o navratovom type funkcie rettype a mene funckie, pripadne informacia 
  o premennom pocte parametrov var_args. Tieto informacie su propagovane z funkcie xml_management, 
  ktora tuto funkciu vola.

*/

function xml_inside_function($file, $rettype, $function_name, $params, $var_args, &$xml, $prepinace, $k, $n){
  //fprintf(STDOUT, "inside function");
  $number = 1;
  $newline = "";
  $indent = "";
  if($prepinace["pretty"]){
    $newline = "\n"; 
  }
  
  while($k != 0){
    $indent .= " ";
    $k--;
  }
  //z jedneho stringu parametrov oddelenych ciarkami dostaneme
  //pole parametrov o samote
  $params = explode(",", $params);
  
  for($i = 0; $i < count($params); $i++){
    $params[$i] = trim($params[$i]); //odstranime prebytocne biele znaky na zaciatku a konci
    if ($params[$i] == "void" || $params[$i] == " "  || empty($params[$i])){  //prazdny parameter nevypisujeme
      unset($params[$i]);      
      continue;
    }
  }
  
  $params = array_filter($params); //pripadnu neexistenciu parametru riesime jeho odstranenim z pola
  
  if($prepinace["maxPar"]){ //ak ma tato funkcia viac parametrov je vyzadovane, koncime
    if(count($params) > $n){
      return;
    }        
  }
  
   
  $xml .= $indent."<function file=\"$file\" name=\"$function_name\" varargs=\"$var_args\" rettype=\"$rettype\">".$newline;
  
  
  foreach($params as &$param){ 
    $param = preg_replace('/[\w]+$/','',$param); //odstranenie nazvu parametru
    $param = trim($param); //odstranenie prebytocnych bielych znakov na zaciatku a konci
    if($prepinace["removeWhitespaces"]){ //odstranenie bielych znakov uprostred
      $patterns = array("/\s+/", "/\s+\*/", "/\*\s+/");
      $replacements = array(" ", "*", "*");
      $param = preg_replace($patterns, $replacements, $param);
    }
  }
  
  for ($i = 0; $i < count($params); $i++){    
    $xml .= $indent.$indent."<param number=\"".$number."\" type=\"$params[$i]\" />".$newline; //vypis parametru
    $number += 1;  //cislovanie parametrov
  }
  $xml .= $indent."</function>".$newline; //ukoncenie funkcie s pripadnym odriadkovanim (ak xml)


}

/*
   Klucova funkcia, ktora prechadza subor po subore z pola files a analyzuje 
   vsetky funkcie v nom, nakoniec posiela obsah dalej funkcii xml_inside_function, 
   ktora do premennej xml prida potrebna tagy a strukturu textu.
*/

function xml_management($files, $prepinace, &$xml, $k, $n){
   foreach ($files as $file){
     $functions = file_content($file); // z kazdeho suboru odstranime nepotrebne informacie a rozparsujeme pre nasu potrebu
     if ($functions != NULL){   //ak v tom subore nejaka definicia/deklaracia funkcie bola: 
        $array_nodupl = array();
        for($i = 0; $i < count($functions[0]); $i++){ //pre vsetky funkcie
          $functions[3][$i] = preg_replace('/[\.\.\.]/','',$functions[3][$i],-1,$count);  //zistovanie premenneho poctu argumentov
            if ($count){
              $var_args = "yes";
            }    	    	
            else{
              $var_args = "no";
           }
            
            if($prepinace["no-inline"]){  //nezahrnutie funkcii s modifikatorom inline
              if(preg_match('/inline/u',$functions[1][$i]))
	              continue;            
            }  
            
            
            if ($prepinace["removeWhitespaces"]) //odstranenie prebytocnych bielych znakov uprostred navratoveho typu
            {
                $patterns = array("/\s+/", "/\s+\*/", "/\*\s+/");
                $replacements = array(" ", "*", "*");
                $functions[1][$i] = preg_replace($patterns, $replacements, $functions[1][$i]);
            }
            
            /*
            V pripade udania parametru noDuplicates si vytvorime pomocne pole array_nodupl, 
            do ktoreho postupne pridavame len funkcie, ktore tam este nie su obsiahnute.
            */
            if($prepinace["noDuplicates"]){ 
              if(!in_array($functions[2][$i], $array_nodupl)){
                array_push($array_dupl, $functions[2][$i]);
              }
              else{
                continue;
              }
            }
            
            $functions[1][$i] = trim($functions[1][$i]); //odstranenie prebytocnych bielych znakov
            
            /*volanie funkcie, ktora do premennej xml prida potrebny text na tlac xml suboru*/
	          xml_inside_function($file, $functions[1][$i], $functions[2][$i],$functions[3][$i], $var_args, $xml, $prepinace, $k, $n);      
          
        } 
     }
   
   }

}


/* TELO PROGRAMU A JEHO RIADENIE */


$xml = "";
get_args($argc, $argv, $prepinace, $inputFile, $outputFile, $k, $n);
$files = files_management($inputFile, $files);
xml_head($prepinace, $xml);
xml_start_functions($inputFile, $k, &$xml, $prepinace, $inputFile);
xml_management($files, $prepinace, $xml, $k, $n);
xml_end_functions($inputFile, $k, &$xml, $prepinace);


if($outputFile != ""){
  $handle = fopen($outputFile, 'w+');
	if($handle === false){
		fwrite(STDERR, "Nie je mozne otvorit outputfile.\n");
		exit(3);
	}
  else{
    fwrite($handle, $xml, strlen($xml));
    if(fclose($handle) == FALSE){
      fwrite(STDERR, "Nie je mozne zatvorit outputfile.\n");
		  exit(3);
    }
  }
}
else{
  fwrite(STDOUT, $xml, strlen($xml));
}

exit(0);





?>