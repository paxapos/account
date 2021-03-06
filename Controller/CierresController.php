<?php

App::uses('AccountAppController', 'Account.Controller');

/**
 * @property Cierre $Cierre
 */
class CierresController extends AccountAppController
{

            
    function index()
    {
        $this->Cierre->recursive = -1;
        $this->set('cierres', $this->paginate());
    }

    
   
    function add()
    {        
        if (!empty($this->request->data)) {
            $this->Cierre->create();
            if ($this->Cierre->save($this->request->data)) {
                foreach ($this->request->data['Gasto'] as $gasto){
                    $this->Cierre->Gasto->id = $gasto['id'];
                    $this->Cierre->Gasto->saveField('cierre_id', $this->Cierre->id);
                }
                $this->Session->setFlash('Se Guardó correctamente');
                $this->redirect($this->referer() );
            } else {
                $this->Session->setFlash('Fallo al guardar');
            }
        }
    }


    function edit( $id)
    {        
        if ( $this->request->is(array('post', 'put')) && !empty($this->request->data)) {
            if ($this->Cierre->save($this->request->data)) {
                $this->Session->setFlash('Se Guardó correctamente');
            } else {
                $this->Session->setFlash('Fallo al guardar', 'Risto.flash_error');
            }
            $this->redirect($this->referer());
        }
        $this->request->data = $this->Cierre->read(null, $id);

    }
    
    function view( $id ) {
        if ( empty($id) ) {
            throw new Exception("Se debe pasar un ID como parámetro");            
        }
        
        $ops = array(            
            'conditions' => array(
                'Gasto.cierre_id' => $id,
            ),
            'contain' => array(
                'TipoImpuesto',
                'Impuesto',
                'Cierre',
                'Media',
                'Egreso'=> array('Media'),
                'Proveedor',
                'Clasificacion',
                'TipoFactura',
                ),           
        );

        $gastos = $this->Cierre->Gasto->find('all', $ops);     
        $gastos = $this->Cierre->Gasto->completarConImportePagado($gastos);   

        $this->Cierre->recursive = -1;
        $cierre = $this->Cierre->read( null, $id );
        $tipo_impuestos = $this->Cierre->Gasto->TipoImpuesto->find('list');      
        $this->set(compact('gastos', 'cierre', 'tipo_impuestos'));       
            
    }


}

