<?php
namespace ExternalModules;

class FrameworkV3Test extends FrameworkBaseTest
{
    function testGetLinkIconHtml(){
        $iconName = 'fas fa-whatever';
        $link = ['icon' => $iconName];
        $html = ExternalModules::getLinkIconHtml($this->getInstance(), $link);
        $this->assertTrue(strpos($html, "<i class='$iconName'") > 0);
    }
}