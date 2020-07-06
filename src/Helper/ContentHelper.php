<?php

namespace App\Helper;


class ContentHelper
{

	
    /**
     * First step of parsing, just before parsing Markdown.
     * Transforms Redmine's h1. - h6. into Markdown syntax with one or multiple '#'
     */
    public static function parseFirst($text) 
    {
        // todo: check what happens if h1. is not at the beginning of a line
        $text = preg_replace_callback('/h([1-6])\.\s(.*)(\n)/',function($ma) {
            //var_dump($ma[0]);
            $repeat = intval($ma[1]);
            $head = str_repeat('#',intval($ma[1])) . ' ' . trim($ma[2]) . " {.title .is-" . intval($ma[1]) . "}\n";
          
            //var_dump($head);
            //var_dump($ma[1]);
            //var_dump($ma[2]);
            //var_dump($ma[3]);
            return $head;                        
        },$text);
        
        return $text;
    }
    
    /**
     * Get all wiki-style page links: [link]
     */
    public static function getPageLinks($text)
    {
      
      preg_match_all('/(\[\[(.*?)\]\])/',$text,$ma);

      return $ma[2];

    }


}