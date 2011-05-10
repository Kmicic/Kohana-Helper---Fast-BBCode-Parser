<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Fast BBCode Parser
 * Helper for use in Kohana Framework
 * Parser is based on my original hypertext tags parsing algorithm.
 * The algorithm does not use recursion, and engines of the DOM.
 *
 * @package    Helpers
 * @author     Wojtek Jarzecki alias (beginend)
 * @copyright  (c) 2011 Wojar SoftWare
 * @license    BSD Free
 */

//   Warning:
//   Although this class should not have unexpected results, the use of this
//   class is at the risk of the user. The author can not be responsible
//   if any damage is caused by this class.

class Helper_BBparser {
protected  $html='';
protected  $parse_arr=array();
protected  $out_arr=array();
protected  $selfclosetag=array('*','h');

// allow for geshi highlighter
protected  $highlight_lang ='php|delphi|pascal|sql|pcre|vb|text|xml|html|javascript';

 /**
  * templates array for nested tags.  
  * example
  * ~~~
  * 'list'  =>     array( 
  *        // first key for begin and end tag if not attribute eg [list] 
  *     array(    'begin'    => '<ul>',    
  *         'end'    => '</ul>'), 
  *        // second key for begin and end tag if have attribute eg [list=lover-roman]     
  *     array(    'begin'    => '<ol style="list-style-type:%attr1%">',    // with attributes             
  *         'end'    => '</ol>')),
  * }
  * ~~~
  */
  
protected  $template_tag = array(
'list'  =>     array(
                array(    'begin'    => '<ul>', 
                        'end'    => '</ul>'),   // if not attributes
                array(    'begin'    => '<ol style="list-style-type:%attr1%">',                 
                        'end'    => '</ol>')),  // with attributes
'quote'  => array(
                array(    'begin'    => '<fieldset class="quote"><legend> Quote </legend>',
                        'end'    => '</fieldset>'),    // if not attributes
                array(    'begin'    => '<fieldset class="quote"><legend> Quote %attr1% </legend>',
                        'end'    => '</fieldset>')),   // with attributes
'code'  => array(
                array(    'begin'    => '<fieldset class="code"><legend> Code </legend>',
                        'end'    => '</fieldset>'),   // if not attributes
                array(    'begin'    => '<fieldset class="code"><legend>Code %attr1%</legend>',
                        'end'    => '</fieldset>')),  // with attributes
'chk'   => array(
                array(    'begin'    => '<fieldset class="checknum"><legend> Numbers </legend><pre style="max-width:580px;overflow: auto;">',
                        'end'    => '</pre></fieldset>'),   // if not attributes
                array(    'begin'    => '<fieldset class="checknum"><legend>Numbers check = %attr1%</legend><pre class="%attr1%" style="max-width:580px;overflow: auto;">',
                        'end'    => '</pre></fieldset>')),  // with attributes                     
);
                 
/**
 * simple search list of tags for simple preg_replace php instructions
 */
                 
protected  $simple_search = array(
    '/\[b\](.*?)\[\/b\]/is',                    // Bold
    '/\[i\](.*?)\[\/i\]/is',                    // Italic
    '/\[u\](.*?)\[\/u\]/is',                    // Underline
    '/\[s\](.*?)\[\/s\]/is',                    // Deleted
    '/\[size\=(.*?)\](.*?)\[\/size\]/is',       // Font size
    '/\[color\=(.*?)\](.*?)\[\/color\]/is',     // Font Color
    '/\[bg\=(.*?)\](.*?)\[\/bg\]/is',           // BackGround Color
    '/\[center\](.*?)\[\/center\]/is',          // Align Center 
    '/\[font\=(.*?)\](.*?)\[\/font\]/is',       // Font familly
    '/\[align\=(left|center|right)\](.*?)\[\/align\]/is',   // align attribute
    '/\[url\](.*?)\[\/url\]/is',                // URL
    '/\[url\=(.*?)\](.*?)\[\/url\]/is',         // URL ext
    '/\[mail\=(.*?)\](.*?)\[\/mail\]/is',       // email ext
    '/\[mail\](.*?)\[\/mail\]/is',              // email 
    '/\[img\](.*?)\[\/img\]/is',                // img
    '/\[img\=(\d*?)x(\d*?)\](.*?)\[\/img\]/is', //img ext 1
    '/\[img (.*?)\](.*?)\[\/img\]/ise',         //img ext 2
    '/\[small\](.*?)\[\/small\]/is',            //small size
    '/\[sub\](.*?)\[\/sub\]/is',                //sub text
    '/\[sup\](.*?)\[\/sup\]/is',                //sup text
    '/\[p\](.*?)\[\/p\]/is',                    //paragraf
    '/\[youtube\](.*?)\[\/youtube\]/is',        // YouTube Video
    '/\[gvideo\](.*?)\[\/gvideo\]/is',          // Google Video
    '/\[bull\s?\/\]/i',                         // bullet char
    '/\[copyright\s?\/\]/i',                    // copyright char
    '/\[registered\s?\/\]/i',                   // register char
    '/\[tm\s*\/\]/i',                           // trade mark char
  );

/**
 * simple replace list of tags for simple preg_replace php instructions
 */

protected $simple_replace = array(
    '<strong>$1</strong>',
    '<em>$1</em>',
    '<u>$1</u>',          
    '<del>$1</del>',      
    '<span style="font-size: $1;">$2</span>',
    '<span style="color: $1;">$2</span>',
    '<span style="background-color: $1;">$2</span>',
    '<div style="text-align: center;">$1</div>',
    '<span style="font-family: $1;">$2</span>',
    '<div style="text-align: $1;">$2</div>',
    '<a href="$1">$1</a>',
    '<a href="$1">$2</a>',  // ToDo ??? obfuscate email
    '<a href="mailto:$1">$2</a>',
    '<a href="mailto:$1">$1</a>',
    '<img style="max-width:625px;max-height:400px;" src="$1" alt="" />',
    '<img style="max-width:625px;max-height:400px;" height="$2" width="$1" alt="" src="$3" />',
    '"<img " . str_replace("&#039;", "\"",str_replace("&quot;", "\"", "$1")) . " src=\"$2\" style=\"max-width:625px;max-height:400px;\" />"',   // 
    '<small>$1</small>',
    '<sub>$1</sub>',
    '<sup>$1</sup>',
    '<p>$1</p>',
    '<object width="640" height="490"><embed src="http://www.youtube.com/v/$1" type="application/x-shockwave-flash" width="640" height="490"></embed></object>',
    '<embed src="http://video.google.com/googleplayer.swf?docId=$1" type="application/x-shockwave-flash" style="width: 425px; height: 350px;">',

    '&bull;',
    '&copy;',
    '&reg;',
    '&trade;',
  );

 /**
  * Class factory functions.
  *
  * example of use :
  * ~~~  
  *   $return  = Helper_BBparser::factory();
  * ~~~  
  * or :
  * ~~~  
  *   $return  = Helper_BBparser::factory($BBCode_string);
  * ~~~  
  * @param   string optionally    string for parse
  * @return  object instance if param1 is empty or parsed string if param1 have bbcode hypertekst
  */

public static function factory($html=NULL)    
{
    if ($html) 
    {
        $parser = new Helper_BBparser();
        return $parser->process_bbcode($html);
    }
    return new Helper_BBparser();
}

 /**
  * Run parse process.
  * example of use :
  * ~~~  
  *   $parser  = Helper_BBparser::factory();
  *   $return  = $parser->process_bbcode ($BBCode_string)
  * ~~~
  * @param   string string for parse
  * @return  string Parsed
  */

public function process_bbcode ($string) 
{
        $s = (string) $string;
        if (empty($s))  return '';
        $this->html = $this->preprocess($s);

        foreach($this->template_tag as $tag=>$values) 
        {
            $this->parse($tag);
            $this->doreplacetag($tag);
        }
return $this->html;        
}

private function parse($parse_tag) 
{
$TAG_OPEN  = "/\[(?P<tag>%%%tag%%%)\=?(?P<attr>[^\]]*?)?\]/usi";
$TAG_CLOSE = "/\[\/(?P<etag>%%%tag%%%)\s?]/usi";

        $this->parse_arr = array();
        $this->out_arr   = array();    
        // scan opened tags        
        
        $TAG_OPEN = str_replace('%%%tag%%%',$parse_tag,$TAG_OPEN);
        $TAG_CLOSE = str_replace('%%%tag%%%',$parse_tag,$TAG_CLOSE);
        
        if (preg_match_all($TAG_OPEN,$this->html,$capture,PREG_OFFSET_CAPTURE+PREG_SET_ORDER)) 
        {
            foreach($capture as $scan) 
            {
                $pos=$scan[0][1];
                $tag=strtolower($scan['tag'][0]);
                $len=strlen($scan[0][0]);
                $attr= isset($scan['attr'][0]) ? $scan['attr'][0] : FALSE ;
                $this->parse_arr[]=array("pos"=>$pos,"len"=>$len,"open"=>true,"level"=>0,"tag"=>$tag,"attr"=>$this->split_attributes($attr));                       
            }
        }
     

        if (preg_match_all($TAG_CLOSE,$this->html,$capture,PREG_OFFSET_CAPTURE+PREG_SET_ORDER)) 
        {        
            foreach($capture as $scan) 
            {
                $pos=$scan[0][1];
                $tag=strtolower($scan['etag'][0]);
                $len=strlen($scan[0][0]);
                $this->parse_arr[]=array("pos"=>$pos,"len"=>$len,"open"=>false,"level"=>0,"tag"=>$tag);                       
            }
        }

        usort($this->parse_arr, array('Helper_BBparser','ascsort'));

        $level=array();
        foreach($this->parse_arr as $key=>$arr) 
        {

            if ($arr["open"]) 
            {
                if (!isset($level[$arr["tag"]])) 
                    $level=array_merge($level,array($arr["tag"]=>0)); 
			    $level[$arr["tag"]]++;
                $this->parse_arr[$key]["level"]=$level[$arr["tag"]];        
            }        
            else 
            { 
                if (!isset($level[$arr["tag"]])) 
                    $level=array_merge($level,array($arr["tag"]=>'0'));
                $this->parse_arr[$key]["level"]=$level[$arr["tag"]];
                $level[$arr["tag"]]--;
            }
        }
        
    $this->set_parse_output_array();
}    

private function doreplacetag($tag) 
{
$out = array();
$child=array();
$last_root=0;

    $diff = 0;
    $oldlen=0;
    $newlen=0;
    $out=array_reverse($this->out_arr);
    $lastlevel=0;
    //print_r($out);
    foreach ($out as $key=>$item) {
    $idx = !empty($item['attr'][0]) ? 1 : 0 ;
    $cfgtag = $this->template_tag[$tag][$idx];
    $calcLen = $item['innerLen'] + $diff;

          
        $innerText = substr($this->html,$item['innerPos'],$calcLen);
        $left = preg_replace("/\%attr1\%/si",$item['attr'][0],$cfgtag['begin']);
        for ($i=1;$i<count($item['attr'])-1;$i++) 
        {
            $regex = "/\%attr$i\%/si";
            $left = preg_replace($regex,$item['attr'][$i],$left);
        }
        switch ($tag) 
        {
        case 'list' :     $inner = preg_replace("#<br>|<br />#si","",$innerText);
                        $inner = $this->process_list_items($inner); 
            break;    
        case 'quote' : $inner = $innerText; 
            break;
        case 'code' : $inner = preg_replace("#<br>|<br />#si","",$innerText);
                        // for geshi highlighter
                        if (preg_match('/'.$this->highlight_lang.'/usi',$item['attr'][0])) {
                          
                            $inner=Highlighter::parse($inner,$item['attr'][0]);
                            $inner = preg_replace("#\r?\n#si","",$inner);
                            }
            break;    
        case 'chk' :     $chk_arr = preg_split("/[^\d]+/si",trim($item['attr'][0]));
                        $chk_list=implode("|",$chk_arr);
                        //Kohana::$log->add(Log::INFO,$chk_list." ".trim($item['attr'][0]));
                        $inner = preg_replace("#<br>|<br />#si","",$innerText); 
                        $inner = preg_replace("/\b($chk_list)\b/si","<span style=\"background-color:orange;\">$1</span>",$inner);
            break;
        default: $inner = $innerText;
        }
        
        //$right = (!empty($item['attr'])) ? $cfgtag['altPos'] : $cfgtag['fPos'] ;
        $right = preg_replace("/\%attr1\%/si",$item['attr'][0],$cfgtag['end']);
        for ($i=1;$i<count($item['attr'])-1;$i++) 
        {
            $regex = "/\%attr$i\%/si";
            $right = preg_replace($regex,$item['attr'][$i],$right);
        }

        
        $newstr = $left.$inner.$right;
        $newlen = strlen($newstr);        
        $oldlen  = $calcLen+strlen($item['tagLeft'])+strlen($item['tagRight']);

    
                
        $this->html = substr_replace($this->html,$newstr,$item['pos'],$oldlen);
        $lastlevel=$item['level'];
        $diff_flag = ($lastlevel>1);
        $diff = $diff_flag ? $diff+($newlen - $oldlen): 0 ;         
    }
}
    
private function preprocess($html)    
{
    $s = (string) $html;
    if (empty($s)) 
    {
      return '';
    }    
    $s = nl2br($s);
    // Preappend http:// to url address if not present
    $s = preg_replace('/\[url\=([^(http)].+?)\](.*?)\[\/url\]/is', '[url=http://$1]$2[/url]', $s);
    $s = preg_replace('/\[url\]([^(http)].+?)\[\/url\]/is', '[url=http://$1]$1[/url]', $s);
    $s = preg_replace($this->simple_search, $this->simple_replace, $s);
return $s;
}

private function ascsort($a,$b) 
{
    return $a['pos']>$b['pos'] ? 1 : -1 ;
}

private static function  to2str($str) 
{
        while(strlen($str)<2) $str=' '.$str;
    return $str;
}    
    
private function split_attributes($attr) 
{
    return preg_split("/[;]+/si",$attr);
}
        
private static function process_list_items($list_items) 
{
    $result_list_items = array();
    preg_match_all("@\[li](.*?)\[/li]@uis", $list_items, $li_array);
    $li_array = $li_array[1];
    if (empty($li_array)) 
    {
        // we didn't find any [li] tags
        $list_items_array = explode("[*]", $list_items);
        foreach ($list_items_array as $li_text) {
            $li_text = trim($li_text);
            if (empty($li_text)) {
                continue;
            }
            if (!preg_match('/<li>/usi',$li_text)) 
            $li_text = nl2br($li_text);
            $result_list_items[] = '<li>'.$li_text.'</li>';
        }
    } 
    else 
    {
        // we found [li] tags!
        foreach ($li_array as $li_text) 
        {
            if (!preg_match('/<li>/usi',$li_text)) 
            $li_text = nl2br($li_text);
            $result_list_items[] = '<li>'.$li_text.'</li>';
        }
    }
    $list_items = implode("\n", $result_list_items);
    return $list_items;
}

private function find_close_tag($tag,$index_open) 
{
    for ($j=$index_open+1;$j<count($this->parse_arr);$j++) 
    {
        if ((!$this->parse_arr[$j]["open"]) and 
           ($this->parse_arr[$j]["tag"]==$this->parse_arr[$index_open]["tag"]) and
           ($this->parse_arr[$j]["level"]==$this->parse_arr[$index_open]["level"])) return $j;
    }        
return -1;    
}

private function set_parse_output_array() 
{
    $this->out_arr=array();
    foreach($this->parse_arr as $key=>$item) {
        if (!$item["open"]) continue; // for all open=true tag
            $find_key=$this->find_close_tag($item["tag"],$key); // find close tag
            if ($find_key=="-1") 
            {                              // if not found
                $ltag=substr($this->html,$item["pos"],$item["len"]);
                $rtag="";                                        // tag nieznany
                continue; // TODO : maybe self close prosess tag
            }    
            else 
            {     // znalazł parę             
            $ltag=substr($this->html,$item["pos"],$item["len"]);
            $rtagPos = $this->parse_arr[$find_key]["pos"];
            $rtagLen = $this->parse_arr[$find_key]["len"];
            $rtag=substr($this->html,$rtagPos,$rtagLen);
            $innerPos =$item['pos']+$item["len"];
            $innerLen =$rtagPos-$item['pos']-$item['len'];
            $allLen =$rtagPos-$item['pos']+$rtagLen;
            }
            
        // add pair to array
        $this->out_arr[]=array(
        "tag"=>$item["tag"],
        "level"=>$item["level"],
        "len"=>$allLen,
        "pos"=>$item["pos"],
        "innerPos" =>$innerPos,
        "innerLen" =>$innerLen,
        "tagLeft"=>$ltag,
        "tagRight"=>$rtag,
        "attr" =>$item["attr"],
        );        
    }
}

public function getResult() 
{
	return $this->html;
}        

}

?>