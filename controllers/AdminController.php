<?php

class AdminController extends BaseAdminController
{
	public function accessRules()
	{
		return array(
			array('allow',
				'users' => array('@'),
			),
			array('deny',
				'users' => array('*')
			),
		);
	}
}
