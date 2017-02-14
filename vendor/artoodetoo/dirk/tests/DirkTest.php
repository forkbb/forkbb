<?php

namespace R2\Templating\Tests;

use R2\Templating\Dirk;

class DirkTest extends \PHPUnit_Framework_TestCase
{
    /** @var PhpEngine */
    protected $engine;
    /** @var string */
    protected $views;
    /** @var string */
    protected $cache;
    /** @var string */
    protected $ext;
    /** @var string[] */
    protected $templates;

    protected function setUp()
    {
        $this->views  = sys_get_temp_dir();
        $this->cache  = $this->views;
        $this->ext    = '.dirk.html';
        $this->engine = new Dirk(
            [
                'views' => $this->views,
                'cache' => $this->cache,
                'ext'   => $this->ext,
            ]
        );
        $this->templates = [];
    }

    protected function tearDown()
    {
        foreach ($this->templates as $name) {
            $src = $name.$this->ext;
            $dst = md5($name).'.php';
            unlink($this->views.'/'.$src);
            unlink($this->views.'/'.$dst);
        }
        unset($this->engine, $this->templates);
    }

    protected function template($text)
    {
        $name = \md5(\uniqid());
        file_put_contents($this->views.'/'.$name.$this->ext, $text);
        $this->templates[] = $name;
        return $name;
    }

    /**
     * @covers R2\Templating\Dirk::render
     */
    public function testRender1()
    {
        $name1 = $this->template('Well done, {{ $grade }}!');
        $this->engine->render($name1, ['grade' => '<b>captain</b>']);
        $this->expectOutputString('Well done, &lt;b&gt;captain&lt;/b&gt;!');
    }

    /**
     * @covers R2\Templating\Dirk::render
     */
    public function testRender2()
    {
        $name2 = $this->template('Well done, {!! $grade !!}!');
        $this->engine->render($name2, ['grade' => '<b>captain</b>']);
        $this->expectOutputString('Well done, <b>captain</b>!');
    }

    /**
     * @covers R2\Templating\Dirk::fetch
     */
    public function testFetch()
    {
        $parentName = $this->template(
            'The text is `@yield(\'content\')`. '.
            '@foreach([1,2,3] as $i)'.
            '{!! $i !!}'.
            '@endforeach'
        );
        $name = $this->template("@extends('{$parentName}')-xxx-");
        $result = $this->engine->fetch($name, []);
        $this->assertEquals("The text is `-xxx-`. 123", $result);
    }
}
