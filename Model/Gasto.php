<?php

App::uses('AccountAppModel', 'Account.Model');

class Gasto extends AccountAppModel {

    public $findMethods = array('pagado' =>  true);

	//public $name = 'Gasto';
    public $order = array(
            'Gasto.fecha' => 'DESC', 
            'Gasto.modified' => 'DESC'
            );
        
    public $displayField = 'importe_total';

    public $actsAs = array(
        'Search.Searchable',
        'Containable',
        'Risto.MediaUploadable' ,
        'Risto.DiaBuscable' => array(
                'fechaField' => 'fecha',
                'fieldsParaSumatoria' => array(
                        "importe_neto",
                        "importe_total",
                ),
            ),
    );
    
    
    public $validate = array(
		'factura_nro' => array(
			'no_repetido' => array(
                'on' => 'create',
				'rule' => 'factura_no_repetida',
				'required' => false,
				'message' => 'Este número de factura ya esta cargada para este mismo proveedor'
			)
		),
        'proveedor_list' => array(
            'no_repetido proveedor' => array(
                'on' => 'create',
                'rule' => 'proveedor_no_repetido',
                'required' => false,
                'message' => 'El proveedor ya posee una factura de este numero'
            )
        ),
        'fecha' => array(
		  'date' => array(
				'rule' => 'date',
                                'message' => 'Ingrese una fecha válida',
                                'allowEmpty' => false,
				'required' => true,
			)
		),
        'tipo_factura_id' => array(
			'numeric' => array(
				'rule' => 'numeric',
				'required' => false,
                'allowEmpty' => true,
				'message' => 'Debe especificar un tipo de factura'
			)
		),
        'importe_neto' => array(
            'numeric' => array(
                'rule' => 'numeric',
                'required' => true,
                'allowEmpty' => false,
                'message' => 'Debe especificar un importe numérico'
            )
        ),
        'importe_total' => array(
			'numeric' => array(
				'rule' => 'numeric',
                'required' => true,
				'allowEmpty' => false,
				'message' => 'Debe especificar un importe total numérico'
			)
		),
	);

	//The Associations below have been created with all possible keys, those that are not needed can be removed
	public $belongsTo = array(
		'Account.Proveedor',
        'Account.Clasificacion',
		'Risto.TipoFactura',
        'Account.Cierre',
        'Risto.Media', 
	);
        
    public $hasMany = array(
		'Account.Impuesto',
            );

    public $hasOne = array(
        'Compras.Pedido',
        );
        
        //The Associations below have been created with all possible keys, those that are not needed can be removed
	public $hasAndBelongsToMany = array(                
        'Egreso' => array(
            'className' => 'Account.Egreso',
			'joinTable' => 'account_egresos_gastos',
			'foreignKey' => 'gasto_id',
			'associationForeignKey' => 'egreso_id',
			'unique' => true, 
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'limit' => '',
			'offset' => '',
			'finderQuery' => '',
			'deleteQuery' => '',
			'insertQuery' => ''
                    
                    ),
        'TipoImpuesto' => array(
			'className' => 'Account.TipoImpuesto',
			'joinTable' => 'account_impuestos',
			'foreignKey' => 'gasto_id',
			'associationForeignKey' => 'tipo_impuesto_id',
			'unique' => true,
			'conditions' => '',
			'fields' => '',
			'order' => 'TipoImpuesto.name ASC',
			'limit' => '',
			'offset' => '',
			'finderQuery' => '',
			'deleteQuery' => '',
			'insertQuery' => ''
		),
	);
    public $filterArgs = array(
        'cierre_id' => array(
            'type' => 'value',
            ),
        'tipo_cierre' => array(
            'type' => 'query',
            'method' => 'filterTipoCierre'
            ),
        'fecha_desde' => array(
            'type' => 'value',
            'field' => 'Gasto.fecha >='
            ),
         'fecha_hasta' => array(
            'type' => 'value',
            'field' => 'Gasto.fecha <='
            ),
         'importe_neto' => array(
            'type' => 'value',
            ),

         'proveedor_id' => array(
            'type' => 'value',
            ),

         'clasificacion_id' => array(
            'type' => 'value',
            ),

         'tipo_factura_id' => array(
            'type' => 'value',
            ),
        );

           
        public function filterTipoCierre ($data = array()) {
            $tipo_cierre = $data['tipo_cierre'];
            if ( $tipo_cierre == 'c' ) {
                $condition = 'Gasto.cierre_id IS NOT NULL';
            } else {
                $condition = 'Gasto.cierre_id IS NULL';
            }
            
            return $condition;
        }
        
        public function beforeDelete($cascade = true)
        {            
            parent::beforeDelete($cascade);
            $this->contain('Egreso');
            $gasto = $this->read();            
            foreach ( $gasto['Egreso'] as $g ) {
                if ( !$this->Egreso->delete($g['id']) ) {
                    return false;
                }
            }
            return true;
        }
       
        public function beforeSave($options = array())
        {
            parent::beforeSave($options);
            
            $this->_calcularImporteNeto();
            
            $this->__proveedorAddOnBeforeSave();
            
            return true;
        }



        private function getProveedorFromFieldData () {
            if ( !empty($this->data['Gasto']['proveedor_list'])) {                
                $cuit = null;
                $name = trim($this->data['Gasto']['proveedor_list']);
                if ( preg_match_all('/(?:\s|^)(\d{11}|\d{2}-\d{8}-\d{1})(?:\s|$)/', 
                     $this->data['Gasto']['proveedor_list'], $proveedor_cuit) 
                     && 
                     preg_match_all('/[A-Za-z ]+/', 
                     $this->data['Gasto']['proveedor_list'], $proveedor_name)) 
                     { // sacar el cuit y name al string

                    // sacar guion del cuit
                    $cuit = trim(str_replace("-", "", $proveedor_cuit[1][0] ));
                    debug($proveedor_cuit[1][0]."  asdasd  ".$cuit."   THE NAME IS: ".$proveedor_name[0][0]);
                    $name = trim( str_replace($name, '', $proveedor_name[0][0]) );
                    debug($name."   ".$cuit);
                    
                    if ( !empty($cuit) && !validate_cuit_cuil($cuit) ) {
                        $cuit = null;                    
                        $name.= ' [CUIT ERROR: '.$cuit.'?, por favor resolver editando a mano]';
                    }elseif ( empty($cuit)) {
                        $cuit = null;
                    }
                }

                $data = array(
                    'Proveedor' => array(
                        'cuit' => $cuit,
                        'name' => $name,
                    )
                );



                return $data;
            }
            return false;
        }

        private function __proveedorAddOnBeforeSave () {

            $data = $this->getProveedorFromFieldData();
            if ( $data && empty($this->data['Gasto']['proveedor_id'])  ) {
                if ( empty($data['Proveedor']['cuit']) ) {
                    $provExist = false;
                } else {
                    $provExist = $this->Proveedor->findByCuit( $data['Proveedor']['cuit'] );
                }
                
                if ( empty($provExist) ) {                   
                    $this->Proveedor->create();
                    if ( $this->Proveedor->save($data) ) {
                        $this->data['Gasto']['proveedor_id'] = $this->Proveedor->id;
                    } else {
                        throw new Exception('No se pudo guardar el proveedor');
                    }
                } else {
                    $this->data['Gasto']['proveedor_id'] = $provExist['Proveedor']['id'];
                }
            }
        }
       
        
        public function afterSave($created,  $options = array())
        {
            parent::afterSave($created);
            
            return $this->_refreshImpuestos($created);
        }
        
        
        /**
         * Calcula el neto sumando los impuestos y lo setea en el data
         */
        private function _calcularImporteNeto(){
            if (!empty($this->data['Gasto']['Impuesto']) && empty($this->data['Gasto']['importe_neto'])) {
                if (!empty($this->data['Gasto']['Impuesto'])) {
                    foreach ($this->data['Gasto']['Impuesto'] as $imp){
                        $this->data['Gasto']['importe_neto'] += $imp['neto'];
                    }
                }
            }
        }
        
        /**
         *  Ante un cambio en e gasto, resetea los valores anteriores
         * @param boolean $created
         * @return boolean
         */
        private function _refreshImpuestos($created){
            if (!empty($this->data['Gasto']['Impuesto'])) {
                if (!$created){
                        $this->Impuesto->deleteAll(array('Impuesto.gasto_id'=>$this->id ));
                }
                
                foreach ($this->data['Gasto']['Impuesto'] as $impId=>$imp){
                    if (!empty($imp)) {                       
                        if (!empty($imp['checked']) && (!empty($imp['importe']) || !empty($imp['neto'])) ) {
                            $importe = empty($imp['importe'])?0:$imp['importe'];
                            $neto = empty($imp['neto'])?0:$imp['neto'];
                            $nuevoImp = array(
                                'gasto_id' => $this->id,
                                'tipo_impuesto_id' => $impId,
                                'importe' => $importe,
                                'neto' => $neto,
                            );
                            $this->Impuesto->create($nuevoImp);
                            if (!$this->Impuesto->save()){
                                return false;
                            }
                        }
                    }
                }
            }
            return true;
        }
        
        
        /**
         * Devuelve todos los gastos que adeudan pagos
         * o sea, cuyo importe_total no llega a ser cubierto con los pagos realizados
         * @return array de Gastos
         */
        public  function enDeuda($conditions = array()){

             $dbo = $this->getDataSource();  
             
             $subQuery = $dbo->buildStatement(
                array(
                    'fields' => array('SUM(  `Aeg`.`importe` )'),
                    'table' => 'account_egresos_gastos',
                    'alias' => 'Aeg',
                    'limit' => null,
                    'offset' => null,
                    'joins' => array(),
                    'conditions' => array(
                        'Aeg.gasto_id = `Gasto`.`id`',
                        ),
                    'order' => null,
                    'group' => array('Aeg.gasto_id')
                ), $this
            );      
             
            $conditions[] = "IFNULL(($subQuery), 0) <> `Gasto`.`importe_total`";
            $fieldContain['recursive'] = -1;
            $fieldContain['fields'] = array('Gasto.id','Gasto.id');
            $fieldContain['conditions'] = $conditions;

            $ret = parent::find('list', $fieldContain);

            $gastos = $this->find('all', array('conditions'=>array('Gasto.id'=>$ret)));
            return $gastos;
        }

        

        /**
         * Indica la sumatoria de todos los pagos realizados para ese gasto
         * @param integer $id gasto_id
         * @return $ importe pagado
         */
        public function importePagado($id = null){
            $importePagado = 0;
            
            if (empty($id)) {
                $id = $this->id;
            }
            
            $fieldContain['recursive'] = -1;
            $fieldContain['contain'] = 'Egreso';
            $fieldContain['conditions'] = array('Gasto.id'=>$id);
            $coso = parent::find('first', $fieldContain);            
            if (!empty($coso['Egreso'])) {
                foreach ($coso['Egreso'] as $eg){
                    if ($eg['AccountEgresosGasto']['gasto_id'] == $id ) {
                        $importePagado += $eg['AccountEgresosGasto']['importe'];
                    }
                }
            }
            
            return $importePagado;
        }
        

        /**
        *
        *   @param array $ret Array del find de Gasto
        *
        *   me devuelve el mismo array pero con un nuevo campo "importe_pagado"
        **/
        public function completarConImportePagado ( $ret ) {
            if ( array_key_exists($this->name, $ret)) {
                // es porque vino de un find First
                $ret[$this->name]['importe_pagado'] = $this->importePagado( $ret[$this->name]['id'] );

            } else {
                // el $ret viene del find All
                foreach ($ret as $k => $g){
                    $ret[$k] = $this->completarConImportePagado( $g );
                }
            }

            return $ret;
        }
      


          function proveedor_no_repetido(){
              if (!empty($this->data['Gasto']['factura_nro'])){
                  $data = $this->getProveedorFromFieldData();
                  $provExist = false;
                  if ( $data ) {
                      $this->Proveedor->recursive = -1;
                      $provExist = $this->Proveedor->findByCuit( $data['Proveedor']['cuit'] );
                  }
                  $ops = array(
                      'conditions' => array(
                          'Gasto.factura_nro' => $this->data['Gasto']['factura_nro']
                      ),
                  );

                  $cant = true;
                  if ( $provExist ) {
                      $ops['conditions']['Gasto.proveedor_id'] = $provExist['Proveedor']['id'];
                  } elseif ( empty($this->data['Gasto']['proveedor_list']) ) {
                      // es sin proveedor
                      $ops['conditions']['Gasto.proveedor_id'] = 'NULL';
                  } else {
                      $cant = 0;
                  }

                  if ( $this->data['Gasto']) {
                      $cant = $this->find('count', $ops);
                  }
                  return !($cant > 0);
              }
              return true;

          }
         function factura_no_repetida(){
            if (!empty($this->data['Gasto']['factura_nro'])){
                $data = $this->getProveedorFromFieldData();
                $provExist = false;
                if ( $data ) {
                    $this->Proveedor->recursive = -1;
                    $provExist = $this->Proveedor->findByCuit( $data['Proveedor']['cuit'] );
                }
                $ops = array(
                    'conditions' => array(
                        'Gasto.factura_nro' => $this->data['Gasto']['factura_nro']
                    ),
                );

                $cant = true;
                if ( $provExist ) {
                    $ops['conditions']['Gasto.proveedor_id'] = $provExist['Proveedor']['id'];
                } elseif ( empty($this->data['Gasto']['proveedor_list']) ) {
                    // es sin proveedor
                    $ops['conditions']['Gasto.proveedor_id'] = 'NULL';
                } else {
                    $cant = 0;
                }

                if ( $this->data['Gasto']) {
                    $cant = $this->find('count', $ops);
                }
                return !($cant > 0);
            }
            return true;
        }


}
