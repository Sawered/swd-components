<?php

namespace Swd\Component\Utils\Html;

class SimpleTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider getHtmls
     *
     * @return void
     * @author skoryukin
     **/
    public function test($html,$text)
    {
        $obj = new Html2PlainText([
            "merge_whitespace"=>true,
            "ignore_newline"=>true,
        ]);

        $result = trim($obj->process($html));
        $text = preg_replace("/[[:blank:]]/mu",' ',$text);
        $result = preg_replace("/[[:blank:]]/mu",' ',$result);
        //var_export($result);
        //echo "\n";
        //var_export($text);
        $this->assertEquals($text,$result);
    }

    public function getHtmls()
    {
        $names = array(
            'simple',
            'fulltext',
            'whitespace_inline',
            'block_newline',
            'whitespace_br',
            'tag_a',
        );

        $result = array();
        $cwd = __DIR__;
        foreach($names as $name){
            $name = $cwd.'/data/'.$name;
            //var_dump($name);
            $result[] = array(
                trim(file_get_contents($name.'.html')),
                trim(file_get_contents($name.'.txt')),
            );
        }

        return $result;
    }
}
