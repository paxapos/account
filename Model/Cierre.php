<?php

App::uses('AccountAppModel', 'Account.Model');

/**
 * @property Gasto $Gasto
 */
class Cierre extends AccountAppModel {

    
    public $order = array('Cierre.created' => 'DESC');
        
	public $validate = array(
		'name' => array('notBlank')
	);
 
    public $hasMany = array('Account.Gasto');
        
}