<?php

namespace R2\Templating\Tests;

use R2\Templating\PhpEngine;

class PhpEngineTest extends \PHPUnit_Framework_TestCase
{
    /** @var PhpEngine */
    protected $engine;
    /** @var string */
    protected $views;
    /** @var string */
    protected $ext;
    /** @var string[] */
    protected $templates;

    protected function setUp()
    {
        $this->views  = sys_get_temp_dir();
        $this->ext    = '.php';
        $this->engine = new PhpEngine(
            [
                'views' => $this->views,
                'ext'   => $this->ext,
            ]
        );
        $this->templates = [];
    }

    protected function tearDown()
    {
        foreach ($this->templates as $name) {
            unlink($this->views.'/'.$name.$this->ext);
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
     * @covers R2\Templating\PhpEngine::render
     */
    public function testRender()
    {
        $name = $this->template('Well done, <?= $grade ?>!');
        $this->engine->render($name, ['grade' => 'captain']);
        $this->expectOutputString('Well done, captain!');
    }

    /**
     * @covers R2\Templating\PhpEngine::fetch
     */
    public function testFetch()
    {
        $parentName = $this->template('The text is (<?= $this->block(\'content\') ?>).');
        $name = $this->template('<?php $this->extend(\''.$parentName.'\'); ?>xxx');
        $result = $this->engine->fetch($name, []);
        $this->assertEquals('The text is (xxx).', $result);
    }

    /**
     * @covers R2\Templating\PhpEngine::exists
     */
    public function testExists()
    {
        $name = $this->template('dummy');
        $this->assertTrue($this->engine->exists($name));
        $this->assertFalse($this->engine->exists('some-weird-name'));
    }
}
