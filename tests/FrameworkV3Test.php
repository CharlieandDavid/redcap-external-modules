<?php
namespace ExternalModules;

class FrameworkV3Test extends FrameworkBaseTest
{
    function testGetEventId_urlParam(){
        $_GET['pid'] = (string) TEST_SETTING_PID;
        $_GET['event_id'] = rand();
        $this->assertSame($_GET['event_id'], $this->getEventId());
    }

    function testGetSafePath(){
        $test = function($path, $root=null){
            // Get the actual value before manipulating the root for testing.
            $actual = call_user_func_array([$this, 'getSafePath'], func_get_args());

            $moduleDirectory = $this->getInstance()->getModuleDirectoryName();
            if(!$root){
                $root = $moduleDirectory;
            }
            else if(!file_exists($root)){
                $root = "$moduleDirectory/$root";
            }

            $root = realpath($root);
            $expected = "$root/$path";
            if(file_exists($expected)){
                $expected = realpath($expected);
            }

            $this->assertEquals($expected, $actual);
        };

        $test(basename(__FILE__));
        $test('.');
        $test('non-existant-file.php');
        $test('test-subdirectory');
        $test('test-file.php', 'test-subdirectory');
        $test('test-file.php', __DIR__ . '/test-subdirectory');

        $expectedExceptions = [
            'outside of your allowed parent directory' => [
                '../index.php',
                '..',
                '../non-existant-file',
                '../../../passwd'
            ],
            'only works on directories that exist' => [
                'non-existant-directory/non-existant-file.php',
                'non-existant-directory/../../../passwd'
            ],
            'does not exist as either an absolute path or a relative path' => [
                ['foo', 'non-existent-root']
            ]
        ];

        foreach($expectedExceptions as $excerpt=>$calls){
            foreach($calls as $args){
                if(!is_array($args)){
                    $args = [$args];
                }    

                $this->assertThrowsException(function() use ($test, $args){
                    call_user_func_array($test, $args);
                }, $excerpt);
            }
        }
    }
}