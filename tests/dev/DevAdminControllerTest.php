<?php 

/**
 * Note: the running of this test is handled by the thing it's testing (DevelopmentAdmin controller).
 * 
 * @package framework
 * @package tests
 */
class DevAdminControllerTest extends SapphireTest {
	
	public function testGetRegisteredController(){
		Config::inst()->update('DevelopmentAdmin', 'registered_controllers', array(
			'test1' => array(
				'controller' => 'Controller1',
				'links' => array(
					'test1' => 'test1 description'
				)
			)
		));
		
		$this->assertEquals('Controller1', DevelopmentAdmin::getRegisteredController('test1'));
		$this->assertEquals(null, DevelopmentAdmin::getRegisteredController('test2'));
	}
	
}

